<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use InvalidArgumentException;
use RuntimeException;
use Zero\Lib\Console\Command\CommandInterface;

final class UpdateLatestCommand implements CommandInterface
{
    private const DEFAULT_GITHUB_API = 'https://api.github.com/repos/%s/releases/latest';
    private const DEFAULT_GITHUB_COMMIT_API = 'https://api.github.com/repos/%s/commits/%s';
    private const DEFAULT_GITHUB_BRANCH_ZIP = 'https://codeload.github.com/%s/zip/refs/heads/%s';
    private const USER_AGENT = 'ZeroFramework-Updater/1.0';

    /** @var string[] */
    private array $preservePaths = ['.env', 'storage', 'sqlite', 'app', 'resources/views', 'routes', 'database/seeders', 'database/migrations'];

    public function getName(): string
    {
        return 'update:latest';
    }

    public function getDescription(): string
    {
        return 'Fetch the latest framework release and apply file updates.';
    }

    public function getUsage(): string
    {
        return 'php zero update:latest [--yes]';
    }

    public function execute(array $argv): int
    {
        $timeout = (int) (config('update.timeout') ?? 15);
        $manifestUrl = (string) (config('update.manifest_url') ?? '');

        if ($manifestUrl !== '') {
            return $this->updateFromManifest($manifestUrl, $timeout, $argv);
        }

        return $this->updateFromGithub($timeout, $argv);
    }

    /**
     * Manifest-driven updates for self-hosted release feeds.
     */
    private function updateFromManifest(string $manifestUrl, int $timeout, array $argv): int
    {
        $manifest = $this->fetchManifest($manifestUrl, $timeout);
        if ($manifest === null) {
            return 1;
        }

        $newVersion = (string) ($manifest['version'] ?? '');
        $files = $manifest['files'] ?? [];
        $notes = $manifest['notes'] ?? null;

        if ($newVersion === '' || !is_array($files)) {
            fwrite(STDERR, "Manifest is missing required keys.\n");

            return 1;
        }

        $currentVersion = $this->currentVersion();
        fwrite(STDOUT, "Current version: {$currentVersion}\n");
        fwrite(STDOUT, "Available version: {$newVersion}\n");
        fwrite(STDOUT, "Files to update: " . count($files) . "\n\n");

        if ($currentVersion === $newVersion) {
            fwrite(STDOUT, "Already up to date.\n");
            return 0;
        }

        $force = in_array('--yes', $argv, true) || in_array('-y', $argv, true);
        if (!$force && !$this->confirm('Apply updates now? [y/N] ')) {
            fwrite(STDOUT, "Update cancelled.\n");
            return 0;
        }

        foreach ($files as $entry) {
            if (!is_array($entry) || empty($entry['path']) || empty($entry['url'])) {
                fwrite(STDERR, "Invalid file entry in manifest; skipping.\n");
                continue;
            }

            $path = $this->normalizePath((string) $entry['path']);
            if ($path === null) {
                fwrite(STDERR, "Skipping unsafe path: {$entry['path']}\n");
                continue;
            }

            $contents = $this->download((string) $entry['url'], $timeout);
            if ($contents === null) {
                fwrite(STDERR, "Failed to download {$entry['url']}\n");
                continue;
            }

            if (!empty($entry['checksum']) && !$this->verifyChecksum($contents, (string) $entry['checksum'])) {
                fwrite(STDERR, "Checksum mismatch for {$entry['path']}; skipping.\n");
                continue;
            }

            $this->writeFile($path, $contents);
        }

        $this->writeVersion($newVersion);
        fwrite(STDOUT, "\nUpdate complete.\n");

        if (is_string($notes) && $notes !== '') {
            fwrite(STDOUT, "Notes: {$notes}\n");
        }

        fwrite(STDOUT, "Review changes, clear caches, and run migrations if necessary.\n");

        return 0;
    }

    /**
     * GitHub release-based updates.
     */
    private function updateFromGithub(int $timeout, array $argv): int
    {
        if (!class_exists('\ZipArchive')) {
            fwrite(STDERR, "ZipArchive extension is required to perform GitHub updates.\n");

            return 1;
        }

        $repo = (string) (config('update.github_repo') ?? 'ZeroPHPFramework/Zero');
        $branch = (string) (config('update.github_branch') ?? 'main');

        $release = $this->fetchGithubRelease($repo, $timeout);
        if ($release && !empty($release['tag_name']) && !empty($release['zipball_url'])) {
            $tag = (string) $release['tag_name'];
            $zipUrl = (string) $release['zipball_url'];
            $notes = $release['body'] ?? null;

            return $this->applyGithubArchive($zipUrl, $tag, $notes, $timeout, $argv);
        }

        fwrite(STDOUT, "No GitHub releases found; falling back to latest commit on {$branch}.\n");

        $commit = $this->fetchGithubCommit($repo, $branch, $timeout);
        if ($commit === null) {
            return 1;
        }

        $sha = substr((string) ($commit['sha'] ?? ''), 0, 7);
        $zipUrl = sprintf(self::DEFAULT_GITHUB_BRANCH_ZIP, $repo, $branch);
        $notes = $commit['commit']['message'] ?? null;

        return $this->applyGithubArchive($zipUrl, $sha ?: $branch, $notes, $timeout, $argv);
    }

    private function applyGithubArchive(string $zipUrl, string $tag, ?string $notes, int $timeout, array $argv): int
    {
        $currentVersion = $this->currentVersion();
        fwrite(STDOUT, "Current version: {$currentVersion}\n");
        fwrite(STDOUT, "Available version: {$tag}\n\n");

        if ($currentVersion === $tag) {
            fwrite(STDOUT, "Already up to date.\n");
            return 0;
        }

        $force = in_array('--yes', $argv, true) || in_array('-y', $argv, true);
        if (!$force && !$this->confirm('Download and apply the latest GitHub archive? [y/N] ')) {
            fwrite(STDOUT, "Update cancelled.\n");
            return 0;
        }

        $zipData = $this->download($zipUrl, $timeout, [
            'User-Agent: ' . self::USER_AGENT,
            'Accept: application/octet-stream',
        ]);

        if ($zipData === null) {
            fwrite(STDERR, "Failed to download archive.\n");

            return 1;
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'zero-update-zip-');
        if ($tmpZip === false || file_put_contents($tmpZip, $zipData) === false) {
            fwrite(STDERR, "Unable to write temporary archive.\n");

            return 1;
        }

        $tmpExtract = sys_get_temp_dir() . '/zero-update-' . uniqid();
        if (!mkdir($tmpExtract) && !is_dir($tmpExtract)) {
            fwrite(STDERR, "Unable to create extraction directory.\n");
            @unlink($tmpZip);

            return 1;
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            fwrite(STDERR, "Unable to open release archive.\n");
            @unlink($tmpZip);
            @rmdir($tmpExtract);

            return 1;
        }

        $zip->extractTo($tmpExtract);
        $zip->close();
        @unlink($tmpZip);

        $root = $this->detectRootDirectory($tmpExtract);
        if ($root === null) {
            fwrite(STDERR, "Unable to detect archive root directory.\n");
            $this->cleanupDirectory($tmpExtract);

            return 1;
        }

        $this->copyDirectory($root, base(''));
        $this->cleanupDirectory($tmpExtract);

        $this->writeVersion($tag);
        fwrite(STDOUT, "Update complete.\n");

        if (is_string($notes) && $notes !== '') {
            fwrite(STDOUT, "Notes:\n{$notes}\n");
        }

        fwrite(STDOUT, "Review changes, install dependencies, and run migrations as needed.\n");

        return 0;
    }
    private function fetchManifest(string $url, int $timeout): ?array
    {
        $payload = $this->download($url, $timeout);
        if ($payload === null) {
            fwrite(STDERR, "Unable to fetch update manifest.\n");

            return null;
        }

        $manifest = json_decode($payload, true);
        if (!is_array($manifest)) {
            fwrite(STDERR, "Manifest is not valid JSON.\n");

            return null;
        }

        return $manifest;
    }

    private function fetchGithubRelease(string $repo, int $timeout): ?array
    {
        $url = sprintf(self::DEFAULT_GITHUB_API, $repo);
        $payload = $this->download($url, $timeout, [
            'Accept: application/vnd.github+json',
        ]);

        if ($payload === null) {
            fwrite(STDERR, "Unable to reach GitHub release API.\n");

            return null;
        }

        $release = json_decode($payload, true);
        if (!is_array($release)) {
            fwrite(STDERR, "GitHub API did not return valid JSON.\n");

            return null;
        }

        return $release;
    }

    private function fetchGithubCommit(string $repo, string $branch, int $timeout): ?array
    {
        $url = sprintf(self::DEFAULT_GITHUB_COMMIT_API, $repo, $branch);
        $payload = $this->download($url, $timeout, [
            'Accept: application/vnd.github+json',
        ]);

        if ($payload === null) {
            fwrite(STDERR, "Unable to reach GitHub commit API.\n");

            return null;
        }

        $commit = json_decode($payload, true);
        if (!is_array($commit)) {
            fwrite(STDERR, "GitHub commit API did not return valid JSON.\n");

            return null;
        }

        return $commit;
    }

    private function download(string $url, int $timeout, array $headers = []): ?string
    {
        $headers = array_merge(['User-Agent: ' . self::USER_AGENT], $headers);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FAILONERROR => true,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $response = curl_exec($ch);
            if ($response === false) {
                fwrite(STDERR, 'HTTP request failed: ' . curl_error($ch) . "\n");
                curl_close($ch);

                return null;
            }
            curl_close($ch);

            return $response;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'header' => implode("\r\n", $headers),
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            fwrite(STDERR, "HTTP request failed for {$url}.\n");

            return null;
        }

        return $response;
    }

    private function verifyChecksum(string $contents, string $checksum): bool
    {
        if (str_starts_with($checksum, 'sha256:')) {
            $expected = substr($checksum, 7);
            $actual = hash('sha256', $contents);

            return hash_equals($expected, $actual);
        }

        return true;
    }

    private function currentVersion(): string
    {
        $path = base('core/version.php');
        if (!file_exists($path)) {
            return '0.0.0';
        }

        $version = require $path;

        return is_string($version) ? $version : '0.0.0';
    }

    private function writeVersion(string $version): void
    {
        $path = base('core/version.php');
        $contents = "<?php\n\nreturn '" . addslashes($version) . "';\n";
        file_put_contents($path, $contents);
    }

    private function normalizePath(string $path): ?string
    {
        $path = ltrim($path, '/\\');
        if (str_contains($path, '../')) {
            return null;
        }

        return $path;
    }

    private function confirm(string $prompt): bool
    {
        fwrite(STDOUT, $prompt);
        $answer = trim((string) fgets(STDIN));

        return in_array(strtolower($answer), ['y', 'yes'], true);
    }

    private function writeFile(string $relativePath, string $contents): void
    {
        $target = base($relativePath);
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create directory {$directory}");
        }

        if (file_put_contents($target, $contents) === false) {
            throw new RuntimeException("Failed to write {$relativePath}");
        }

        fwrite(STDOUT, "Updated {$relativePath}\n");
    }

    private function detectRootDirectory(string $extractedPath): ?string
    {
        $items = glob($extractedPath . '/*', GLOB_ONLYDIR);
        return $items[0] ?? null;
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);
            if ($this->shouldPreserve($relative)) {
                continue;
            }

            $target = $destination . DIRECTORY_SEPARATOR . $relative;

            if ($item->isDir()) {
                if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
                    throw new RuntimeException("Unable to create directory {$target}");
                }
                continue;
            }

            $dir = dirname($target);
            if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new RuntimeException("Unable to create directory {$dir}");
            }

            if (!copy($item->getPathname(), $target)) {
                throw new RuntimeException("Failed to copy {$relative}");
            }

            fwrite(STDOUT, "Updated {$relative}\n");
        }
    }

    private function shouldPreserve(string $relative): bool
    {
        foreach ($this->preservePaths as $preserve) {
            $preserve = trim($preserve, '/');
            if ($preserve === '') {
                continue;
            }

            if ($relative === $preserve || str_starts_with($relative, $preserve . '/')) {
                return true;
            }
        }

        return false;
    }

    private function cleanupDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($directory);
    }
}
