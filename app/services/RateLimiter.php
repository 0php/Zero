<?php
namespace App\Services;

use Zero\Lib\Http\Request;

class RateLimiter
{
    private string $dir;

    public function __construct()
    {
        $subdir = (string) (config('rate_limit.storage.directory') ?? 'rate_limit');
        $this->dir = rtrim(storage_path('cache/' . $subdir), '/\\');
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0777, true);
        }
    }

    public function keyFor(Request $request, string $strategy = 'ip', ?string $suffix = null): string
    {
        $parts = [];
        $ip = (string) ($request->ip() ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $route = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') . ' ' . (string) ($_SERVER['REQUEST_URI'] ?? '/');

        switch ($strategy) {
            case 'route':
                $parts[] = $route;
                break;
            case 'ip_route':
                $parts[] = $ip;
                $parts[] = $route;
                break;
            case 'ip':
            default:
                $parts[] = $ip;
                break;
        }

        if ($suffix) {
            $parts[] = $suffix;
        }

        return sha1(implode('|', $parts));
    }

    /**
     * Record a hit and determine if the request is allowed.
     * Returns [allowed, remaining, retryAfter, resetAt].
     */
    public function hit(string $key, int $max, int $decaySeconds): array
    {
        $now = time();
        $file = $this->dir . DIRECTORY_SEPARATOR . $key . '.json';

        $data = [
            'reset' => $now + $decaySeconds,
            'count' => 0,
        ];

        $fh = fopen($file, 'c+');
        if ($fh === false) {
            // If storage fails, default to allow
            return [true, $max, 0, $now + $decaySeconds];
        }

        try {
            flock($fh, LOCK_EX);
            $contents = stream_get_contents($fh);
            if ($contents !== false && $contents !== '') {
                $existing = json_decode($contents, true);
                if (is_array($existing)) {
                    $data = $existing;
                }
            }

            if (!isset($data['reset']) || $data['reset'] <= $now) {
                $data['reset'] = $now + $decaySeconds;
                $data['count'] = 0;
            }

            $data['count'] = (int) ($data['count'] ?? 0) + 1;

            $allowed = $data['count'] <= $max;
            $remaining = max(0, $max - $data['count']);
            $retryAfter = $allowed ? 0 : max(1, $data['reset'] - $now);

            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($data));
            fflush($fh);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }

        return [$allowed, $remaining, $retryAfter, (int) $data['reset']];
    }
}
