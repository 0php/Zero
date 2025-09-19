<?php

declare(strict_types=1);

namespace Zero\Lib\Mail\Transport;

use Zero\Lib\Mail\MailException;
use Zero\Lib\Mail\Message;

final class SmtpTransport
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private array $config)
    {
    }

    public function send(Message $message): void
    {
        $from = $message->getFrom();

        if ($from === null) {
            throw new MailException('An email requires a "From" address.');
        }

        $recipients = $message->getEnvelopeRecipients();
        if (empty($recipients)) {
            throw new MailException('An email requires at least one recipient.');
        }

        $stream = $this->connect();

        try {
            $this->performHandshake($stream);
            $this->authenticate($stream);
            $this->mailFrom($stream, $from['address']);
            $this->rcptTo($stream, $recipients);
            $this->data($stream, $message->toMimeString());
            $this->sendCommand($stream, 'QUIT');
            $this->expect($stream, [221]);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return resource
     */
    private function connect()
    {
        $host = (string) ($this->config['host'] ?? '127.0.0.1');
        $port = (int) ($this->config['port'] ?? 25);
        $timeout = (int) ($this->config['timeout'] ?? 30);
        $encryption = $this->config['encryption'] ?? null;

        $contextOptions = [];

        if (! ($this->config['verify_peer'] ?? true)) {
            $contextOptions['ssl']['verify_peer'] = false;
            $contextOptions['ssl']['verify_peer_name'] = false;
        }

        if ($this->config['allow_self_signed'] ?? false) {
            $contextOptions['ssl']['allow_self_signed'] = true;
        }

        $context = stream_context_create($contextOptions);

        $endpoint = $host . ':' . $port;
        if ($encryption === 'ssl') {
            $endpoint = 'ssl://' . $endpoint;
        }

        $stream = @stream_socket_client(
            $endpoint,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! is_resource($stream)) {
            throw new MailException(sprintf('Unable to connect to SMTP host %s:%d (%s).', $host, $port, $errstr ?: 'unknown error'));
        }

        stream_set_timeout($stream, $timeout);

        $this->expect($stream, [220]);

        return $stream;
    }

    /**
     * @param resource $stream
     */
    private function performHandshake($stream): void
    {
        $domain = $this->config['hello'] ?? gethostname() ?: 'localhost';

        $this->sendCommand($stream, 'EHLO ' . $domain);
        $response = $this->expect($stream, [250], true);

        if (($this->config['encryption'] ?? null) === 'tls') {
            $this->initiateTls($stream, $response, $domain);
        }
    }

    /**
     * @param resource $stream
     * @param array<int, string> $ehloLines
     */
    private function initiateTls($stream, array $ehloLines, string $domain): void
    {
        $supportsStartTls = false;
        foreach ($ehloLines as $line) {
            if (stripos($line, 'STARTTLS') !== false) {
                $supportsStartTls = true;
                break;
            }
        }

        if (! $supportsStartTls) {
            throw new MailException('SMTP server does not advertise STARTTLS but TLS was requested.');
        }

        $this->sendCommand($stream, 'STARTTLS');
        $this->expect($stream, [220]);

        $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }

        if (! stream_socket_enable_crypto($stream, true, $cryptoMethod)) {
            throw new MailException('Unable to establish TLS connection with SMTP server.');
        }

        $this->sendCommand($stream, 'EHLO ' . $domain);
        $this->expect($stream, [250]);
    }

    /**
     * @param resource $stream
     */
    private function authenticate($stream): void
    {
        $username = $this->config['username'] ?? null;
        $password = $this->config['password'] ?? null;

        if (! $username || ! $password) {
            return;
        }

        $this->sendCommand($stream, 'AUTH LOGIN');
        $this->expect($stream, [334]);

        $this->sendCommand($stream, base64_encode((string) $username));
        $this->expect($stream, [334]);

        $this->sendCommand($stream, base64_encode((string) $password));
        $this->expect($stream, [235]);
    }

    /**
     * @param resource $stream
     */
    private function mailFrom($stream, string $address): void
    {
        $this->sendCommand($stream, 'MAIL FROM:<' . $address . '>');
        $this->expect($stream, [250, 251]);
    }

    /**
     * @param resource $stream
     * @param array<int, array{address: string, name: string|null}> $recipients
     */
    private function rcptTo($stream, array $recipients): void
    {
        foreach ($recipients as $recipient) {
            $this->sendCommand($stream, 'RCPT TO:<' . $recipient['address'] . '>');
            $this->expect($stream, [250, 251, 252]);
        }
    }

    /**
     * @param resource $stream
     */
    private function data($stream, string $payload): void
    {
        $this->sendCommand($stream, 'DATA');
        $this->expect($stream, [354]);

        $normalized = $this->normalizePayload($payload);
        fwrite($stream, $normalized . ".\r\n");
        $this->expect($stream, [250]);
    }

    private function normalizePayload(string $payload): string
    {
        $payload = str_replace(["\r\n", "\r"], "\n", $payload);
        $payload = str_replace("\n", "\r\n", $payload);

        $lines = explode("\r\n", $payload);
        foreach ($lines as &$line) {
            if ($line !== '' && $line[0] === '.') {
                $line = '.' . $line;
            }
        }
        unset($line);

        $normalized = implode("\r\n", $lines);

        if (! str_ends_with($normalized, "\r\n")) {
            $normalized .= "\r\n";
        }

        return $normalized;
    }

    /**
     * @param resource $stream
     */
    private function sendCommand($stream, string $command): void
    {
        fwrite($stream, $command . "\r\n");
    }

    /**
     * @param resource $stream
     * @param array<int, int> $expectedCodes
     * @return array<int, string>
     */
    private function expect($stream, array $expectedCodes, bool $collectLines = false): array
    {
        $lines = [];

        while (($line = fgets($stream, 515)) !== false) {
            $lines[] = rtrim($line, "\r\n");
            if (strlen($line) < 4) {
                break;
            }

            if ($line[3] === ' ') {
                break;
            }
        }

        if (empty($lines)) {
            throw new MailException('No response from SMTP server.');
        }

        $code = (int) substr($lines[0], 0, 3);

        if (! in_array($code, $expectedCodes, true)) {
            throw new MailException('Unexpected SMTP response: ' . implode("\n", $lines));
        }

        return $collectLines ? $lines : [];
    }
}
