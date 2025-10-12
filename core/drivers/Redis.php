<?php
declare(strict_types=1);

class RedisException extends \RuntimeException {}

class RedisDriver
{
    private ?string $host;
    private int $port;
    private ?string $unixSocket;
    private ?string $username;
    private ?string $password;
    private int $database;
    private float $connectTimeout;
    private ?float $rwTimeout;
    private bool $persistent;
    private bool $useTLS;
    private array $tlsContext; // ssl context options
    private $stream = null;

    private int $respVersion = 2; // 2 or 3
    private bool $inPubSub = false;

    public function __construct(array $opts = [])
    {
        $this->host            = $opts['host']            ?? '127.0.0.1';
        $this->port            = (int)($opts['port']      ?? 6379);
        $this->unixSocket      = $opts['unix_socket']     ?? null;
        $this->username        = $opts['username']        ?? null;
        $this->password        = $opts['password']        ?? null;
        $this->database        = (int)($opts['database']  ?? 0);
        $this->connectTimeout  = (float)($opts['connect_timeout'] ?? 1.0);
        $this->rwTimeout       = array_key_exists('rw_timeout', $opts) ? (float)$opts['rw_timeout'] : 1.0;
        $this->persistent      = (bool)($opts['persistent'] ?? false);
        $this->useTLS          = (bool)($opts['tls'] ?? false);
        $this->tlsContext      = (array)($opts['tls_context'] ?? []);
    }

    public function __destruct() { $this->close(); }

    public function connect(): void
    {
        if (is_resource($this->stream)) return;

        if ($this->unixSocket) {
            $remote = "unix://{$this->unixSocket}";
        } else {
            $scheme = $this->useTLS ? 'tls' : 'tcp';
            $remote = "{$scheme}://{$this->host}:{$this->port}";
        }

        $flags = STREAM_CLIENT_CONNECT | ($this->persistent ? STREAM_CLIENT_PERSISTENT : 0);
        $ctxArr = [
            'socket' => ['so_keepalive' => true, 'tcp_nodelay' => true],
        ];
        if ($this->useTLS) {
            $ctxArr['ssl'] = $this->tlsContext + [
                'verify_peer' => true,
                'verify_peer_name' => true,
                // 'cafile' => '/path/to/ca.pem', // set as needed
                // 'peer_name' => $this->host,   // override SNI
                'SNI_enabled' => true,
            ];
        }
        $ctx = stream_context_create($ctxArr);

        $errno = 0; $errstr = '';
        $this->stream = @stream_socket_client($remote, $errno, $errstr, $this->connectTimeout, $flags, $ctx);
        if (!is_resource($this->stream)) throw new RedisException("Connect failed: {$errstr} ({$errno})");

        if ($this->rwTimeout !== null) {
            stream_set_timeout($this->stream, (int)$this->rwTimeout, (int)(($this->rwTimeout - (int)$this->rwTimeout) * 1_000_000));
        }
        stream_set_blocking($this->stream, true);

        // Upgrade to RESP3 when possible (Redis 6+)
        try {
            $hello = $this->sendCommandRawExpect(['HELLO','3'] + ($this->username || $this->password ? ['AUTH',$this->username ?? 'default',$this->password ?? ''] : []) + []);
            if (is_array($hello)) $this->respVersion = 3;
        } catch (\Throwable $_) {
            $this->respVersion = 2; // older servers still fine
        }

        // If HELLO didn't auth (older versions), do classic AUTH
        if ($this->respVersion === 2 && $this->password !== null) {
            if ($this->username !== null) $this->sendCommand('AUTH', $this->username, $this->password);
            else                          $this->sendCommand('AUTH', $this->password);
        }
        if ($this->database > 0) $this->sendCommand('SELECT', (string)$this->database);
    }

    public function close(): void
    {
        if (is_resource($this->stream)) fclose($this->stream);
        $this->stream = null; $this->inPubSub = false;
    }

    public function quit(): void
    {
        try { $this->sendCommand('QUIT'); } catch (\Throwable $e) {}
        $this->close();
    }

    // ---------- Public ops ----------
    public function sendCommand(string $cmd, string ...$args)
    {
        $this->connect();
        $this->sendRaw([$cmd, ...$args]);
        return $this->readResp();
    }

    /** like sendCommand but used during connect where we can't blow away state on failure */
    private function sendCommandRawExpect(array $parts)
    {
        $this->connect();
        $this->sendRaw($parts);
        return $this->readResp();
    }

    /** @param list<array{0:string,1?:string,...}> $commands */
    public function pipeline(array $commands): array
    {
        $this->connect();
        foreach ($commands as $c) $this->sendRaw($c, false);
        $out = [];
        foreach ($commands as $_) $out[] = $this->readResp();
        return $out;
    }

    /** @param list<array{0:string,1?:string,...}> $queued */
    public function multiExec(array $queued): array
    {
        $this->connect();
        $this->sendRaw(['MULTI'], false);
        foreach ($queued as $c) $this->sendRaw($c, false);
        $this->sendRaw(['EXEC'], false);
        $res = $this->readResp();
        if (!is_array($res)) throw new RedisException('EXEC returned non-array');
        return $res;
    }

    /**
     * Pub/Sub – supports RESP2 array messages and RESP3 push frames.
     * Handler signature: function(string $channel, string $message): bool|void
     * Return false to UNSUBSCRIBE and exit.
     */
    public function subscribe(array $channels, callable $onMessage): void
    {
        if ($this->inPubSub) throw new RedisException('Already in Pub/Sub mode');
        $this->sendRaw(['SUBSCRIBE', ...$channels]);
        $this->inPubSub = true;
        try {
            while (true) {
                $msg = $this->readResp(); // can be array (RESP2) or push (already normalized below)
                [$type, $channel, $payload] = $this->normalizePubSub($msg);
                if ($type === 'message') {
                    $keep = $onMessage($channel, $payload);
                    if ($keep === false) {
                        $this->sendRaw(['UNSUBSCRIBE']);
                        $this->drainUntilUnsub();
                        break;
                    }
                }
            }
        } finally { $this->inPubSub = false; }
    }

    public function publish(string $channel, string $payload): int
    {
        return (int)$this->sendCommand('PUBLISH', $channel, $payload);
    }

    // ---------- RESP encoder / decoder ----------
    private function sendRaw(array $parts, bool $flush = true): void
    {
        $buf = '*' . count($parts) . "\r\n";
        foreach ($parts as $p) {
            $s = (string)$p;
            $buf .= '$' . strlen($s) . "\r\n{$s}\r\n";
        }
        $this->writeAll($buf, $flush);
    }

    private function writeAll(string $buf, bool $flush): void
    {
        $n = strlen($buf); $wrote = 0;
        while ($wrote < $n) {
            $w = @fwrite($this->stream, substr($buf, $wrote));
            if ($w === false || $w === 0) {
                $meta = is_resource($this->stream) ? stream_get_meta_data($this->stream) : [];
                $this->close();
                throw new RedisException('Write failed' . (!empty($meta['timed_out']) ? ' (timeout)' : ''));
            }
            $wrote += $w;
        }
        if ($flush) fflush($this->stream);
    }

    private function readLine(): string
    {
        $line = @fgets($this->stream);
        if ($line === false) {
            $meta = stream_get_meta_data($this->stream);
            $this->close();
            throw new RedisException('Read failed' . (!empty($meta['timed_out']) ? ' (timeout)' : ''));
        }
        return str_ends_with($line, "\r\n") ? substr($line, 0, -2) : rtrim($line, "\r\n");
    }

    private function readExact(int $n): string
    {
        $buf = '';
        while (strlen($buf) < $n) {
            $chunk = @fread($this->stream, $n - strlen($buf));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->stream);
                $this->close();
                throw new RedisException('Read failed' . (!empty($meta['timed_out']) ? ' (timeout)' : ''));
            }
            $buf .= $chunk;
        }
        return $buf;
    }

    private function readResp()
    {
        $line = $this->readLine();
        if ($line === '') throw new RedisException('Empty response');

        $t = $line[0];
        $rest = substr($line, 1);

        // RESP2 and RESP3 shared primitives
        switch ($t) {
            case '+': return $rest;                   // Simple String
            case '-': throw new RedisException($rest);// Error
            case ':': return (int)$rest;              // Integer
            case '$':                                  // Bulk String
                $len = (int)$rest;
                if ($len === -1) return null;
                $data = $this->readExact($len);
                $this->readExact(2);
                return $data;
            case '*':                                  // Array
                $cnt = (int)$rest;
                if ($cnt === -1) return null;
                $arr = [];
                for ($i = 0; $i < $cnt; $i++) $arr[] = $this->readResp();
                return $arr;

            // RESP3 additions
            case '_': return null;                     // Null
            case '#': return $rest === 't';            // Boolean
            case ',': return (float)$rest;             // Double
            case '=':                                   // Verbatim string: format "type\ndata"
                $len = (int)$this->readLenFromVerbatim($rest);
                $data = $this->readExact($len);
                $this->readExact(2);
                return $data;
            case '!':                                   // Blob error
                $len = (int)$rest;
                $msg = $len === -1 ? '' : $this->readExact($len);
                if ($len !== -1) $this->readExact(2);
                throw new RedisException($msg);
            case '(':                                   // Big number
                // PHP int handles 64-bit; for bigger, keep as string
                return (strlen($rest) > 18) ? $rest : (int)$rest;
            case '%':                                   // Map -> associative array
                $pairs = (int)$rest;
                $map = [];
                for ($i = 0; $i < $pairs; $i++) {
                    $k = $this->readResp();
                    $v = $this->readResp();
                    $map[is_string($k) ? $k : json_encode($k)] = $v;
                }
                return $map;
            case '~':                                   // Set -> list
                $cnt = (int)$rest;
                $set = [];
                for ($i = 0; $i < $cnt; $i++) $set[] = $this->readResp();
                return $set;
            case '|':                                   // Attribute (ignored, but must parse)
                $pairs = (int)$rest;
                for ($i = 0; $i < $pairs; $i++) { $this->readResp(); $this->readResp(); }
                // After attributes, a real reply follows:
                return $this->readResp();
            case '>':                                   // Push (pub/sub, monitor, etc.)
                $cnt = (int)$rest;
                $arr = [];
                for ($i = 0; $i < $cnt; $i++) $arr[] = $this->readResp();
                return ['__push__' => $arr];
            default:
                throw new RedisException("Unknown RESP type: {$t}");
        }
    }

    private function readLenFromVerbatim(string $rest): int
    {
        // For '=' the actual format is: "=$len\r\n<type>:<data>\r\n" in RESP3 (server sends length first normally)
        // Some servers send directly the length in $rest; keep simple: $rest is length
        return (int)$rest;
    }

    /** Normalize pub/sub message across RESP2 and RESP3 push frames */
    private function normalizePubSub($msg): array
    {
        // RESP3 push example: ['__push__', ['message', 'channel', 'payload']]
        if (is_array($msg) && isset($msg['__push__'])) {
            $arr = $msg['__push__'];
            // server may wrap different push types; standard is: ['message', channel, payload]
            if (isset($arr[0]) && $arr[0] === 'message') {
                return ['message', (string)$arr[1], (string)$arr[2]];
            }
            // Fallback: try to coerce RESP2-like
            return [ (string)($arr[0] ?? 'message'), (string)($arr[1] ?? ''), (string)($arr[2] ?? '') ];
        }

        // RESP2 array: ["message", channel, payload]
        if (is_array($msg) && isset($msg[0]) && $msg[0] === 'message') {
            return ['message', (string)$msg[1], (string)$msg[2]];
        }

        // Unknown—return a no-op, handler won’t be called
        return ['unknown', '', ''];
    }

    private function drainUntilUnsub(): void
    {
        // Drain one or more unsubscribe confirmations
        for ($i = 0; $i < 2; $i++) {
            try { $this->readResp(); } catch (\Throwable $e) { break; }
        }
    }
}

/** ioredis-like wrapper */
final class RedisLike
{
    private RedisWireClient $client;
    private bool $lazy;
    private array $baseOpts;

    /**
     * @param string|array $urlOrOpts supports: redis://, rediss://, unix://
     * Extra options:
     *  - lazyConnect(bool), connectTimeout, readTimeout, persistent
     *  - tls(bool), tls_context(array ssl context options)
     */
    public function __construct(string|array $urlOrOpts = [], array $opts = [])
    {
        $parsed = is_string($urlOrOpts) ? self::parseRedisUrl($urlOrOpts) : $urlOrOpts;
        $final  = array_replace([
            'host' => '127.0.0.1',
            'port' => 6379,
            'unix_socket' => null,
            'username' => null,
            'password' => null,
            'database' => 0,
            'connect_timeout' => 1.0,
            'rw_timeout' => 1.0,
            'persistent' => false,
            'tls' => false,
            'tls_context' => [],
        ], self::normalizeOpts(array_replace($parsed, $opts)));

        $this->lazy = (bool)($final['lazyConnect'] ?? false);
        $this->baseOpts = $final;

        $this->client = new RedisWireClient([
            'host' => $final['host'],
            'port' => (int)$final['port'],
            'unix_socket' => $final['unix_socket'],
            'username' => $final['username'],
            'password' => $final['password'],
            'database' => (int)$final['database'],
            'connect_timeout' => (float)$final['connect_timeout'],
            'rw_timeout' => (float)$final['rw_timeout'],
            'persistent' => (bool)$final['persistent'],
            'tls' => (bool)$final['tls'],
            'tls_context' => (array)$final['tls_context'],
        ]);

        if (!$this->lazy) $this->connect();
    }

    public function connect(): void { $this->client->connect(); }
    public function disconnect(): void { $this->client->close(); }
    public function quit(): void { $this->client->quit(); }
    public function duplicate(): self { return new self($this->baseOpts + ['lazyConnect' => true]); }

    /** Dynamic commands: any Redis command works, e.g. $redis->zadd(...), $redis->xadd(...). */
    public function __call(string $name, array $args)
    {
        $cmd = strtoupper($name);
        $toStr = static fn($x) => is_bool($x) ? ($x ? '1' : '0') : (string)$x;
        $args = array_map($toStr, $args);
        return $this->client->sendCommand($cmd, ...$args);
    }

    public function pipeline(): PipelineLike { return new PipelineLike($this->client); }
    public function multi(): MultiLike { return new MultiLike($this->client); }

    /** ioredis-like subscribe (callback gets (channel, message)) */
    public function subscribe(array|string $channels, callable $handler): void
    {
        if (is_string($channels)) $channels = [$channels];
        $this->client->subscribe($channels, $handler);
    }

    public function publish(string $channel, string $message): int
    {
        return $this->client->publish($channel, $message);
    }

    private static function normalizeOpts(array $o): array
    {
        if (isset($o['connectTimeout'])) $o['connect_timeout'] = (float)$o['connectTimeout'];
        if (isset($o['readTimeout']))    $o['rw_timeout']      = (float)$o['readTimeout'];
        return $o;
    }

    private static function parseRedisUrl(string $url): array
    {
        if (str_starts_with($url, 'unix://')) {
            return ['unix_socket' => substr($url, 7)];
        }
        $p = parse_url($url);
        if ($p === false) throw new RedisException("Invalid redis URL: {$url}");

        $useTLS = in_array($p['scheme'] ?? 'redis', ['rediss','tls'], true);
        $out = [
            'host' => $p['host'] ?? '127.0.0.1',
            'port' => isset($p['port']) ? (int)$p['port'] : 6379,
            'tls'  => $useTLS,
        ];
        if (isset($p['user'])) $out['username'] = $p['user'];
        if (isset($p['pass'])) $out['password'] = $p['pass'];
        if (!empty($p['path']) && $p['path'] !== '/') {
            $db = ltrim($p['path'], '/');
            if ($db !== '' && ctype_digit($db)) $out['database'] = (int)$db;
        }
        return $out;
    }
}

/** Chainable pipeline collector */
final class PipelineLike
{
    private RedisWireClient $client;
    /** @var list<array{0:string,1?:string,...}> */
    private array $queued = [];

    public function __construct(RedisWireClient $client) { $this->client = $client; }

    public function __call(string $name, array $args): self
    {
        $cmd = strtoupper($name);
        $args = array_map(fn($x) => is_bool($x) ? ($x ? '1' : '0') : (string)$x, $args);
        $this->queued[] = array_merge([$cmd], $args);
        return $this;
    }

    /** @return list<mixed> */
    public function exec(): array
    {
        $out = $this->client->pipeline($this->queued);
        $this->queued = [];
        return $out;
    }
}

/** Chainable multi/exec collector */
final class MultiLike
{
    private RedisWireClient $client;
    /** @var list<array{0:string,1?:string,...}> */
    private array $queued = [];

    public function __construct(RedisWireClient $client) { $this->client = $client; }

    public function __call(string $name, array $args): self
    {
        $cmd = strtoupper($name);
        $args = array_map(fn($x) => is_bool($x) ? ($x ? '1' : '0') : (string)$x, $args);
        $this->queued[] = array_merge([$cmd], $args);
        return $this;
    }

    /** @return list<mixed> EXEC results */
    public function exec(): array
    {
        $out = $this->client->multiExec($this->queued);
        $this->queued = [];
        return $out;
    }
}
