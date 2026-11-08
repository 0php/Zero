<?php

declare(strict_types=1);

namespace Zero\Lib\Filesystem;

/**
 * Maps content-detected MIME types to file extensions and flags extensions that must never
 * be written under a client supplied name (server-side scripts and active web content).
 */
final class MimeTypeMap
{
    /**
     * Extensions a web server may execute. Never stored as-is from an upload.
     */
    private const EXECUTABLE = [
        'php', 'php2', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar', 'pgif',
        'inc', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'asp', 'aspx', 'ascx', 'ashx', 'asmx', 'jsp', 'jspx',
        'shtml', 'shtm', 'stm', 'htaccess', 'htpasswd', 'user.ini', 'ini', 'exe', 'dll', 'bat', 'cmd', 'msi',
    ];

    /**
     * Extensions a browser renders as active content on the application origin (stored XSS).
     */
    private const ACTIVE_CONTENT = [
        'html', 'htm', 'xhtml', 'xht', 'shtml', 'svg', 'svgz', 'xml', 'xsl', 'xslt', 'js', 'mjs', 'swf', 'hta', 'mht', 'mhtml',
    ];

    /**
     * @var array<string, string[]> detected MIME type => accepted extensions (first one is canonical)
     */
    private const MAP = [
        'image/jpeg' => ['jpg', 'jpeg', 'jpe', 'jfif'],
        'image/pjpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'image/bmp' => ['bmp'],
        'image/x-ms-bmp' => ['bmp'],
        'image/tiff' => ['tif', 'tiff'],
        'image/heic' => ['heic'],
        'image/heif' => ['heif', 'heic'],
        'image/avif' => ['avif'],
        'image/x-icon' => ['ico'],
        'image/vnd.microsoft.icon' => ['ico'],
        'image/svg+xml' => ['svg'],
        'application/pdf' => ['pdf'],
        'application/zip' => ['zip', 'docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp', 'apk', 'jar', 'epub'],
        'application/x-zip-compressed' => ['zip'],
        'application/gzip' => ['gz', 'tgz'],
        'application/x-gzip' => ['gz', 'tgz'],
        'application/x-tar' => ['tar'],
        'application/x-7z-compressed' => ['7z'],
        'application/x-rar' => ['rar'],
        'application/vnd.rar' => ['rar'],
        'application/msword' => ['doc'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.ms-powerpoint' => ['ppt'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
        'application/vnd.oasis.opendocument.text' => ['odt'],
        'application/vnd.oasis.opendocument.spreadsheet' => ['ods'],
        'application/vnd.oasis.opendocument.presentation' => ['odp'],
        'application/vnd.android.package-archive' => ['apk'],
        'application/json' => ['json'],
        'application/rtf' => ['rtf'],
        'text/rtf' => ['rtf'],
        'text/plain' => ['txt', 'csv', 'log', 'md', 'json', 'tsv'],
        'text/csv' => ['csv', 'txt'],
        'application/csv' => ['csv'],
        'audio/mpeg' => ['mp3'],
        'audio/ogg' => ['ogg', 'oga'],
        'audio/wav' => ['wav'],
        'audio/x-wav' => ['wav'],
        'audio/mp4' => ['m4a', 'mp4'],
        'audio/x-m4a' => ['m4a'],
        'audio/aac' => ['aac'],
        'audio/flac' => ['flac'],
        'audio/webm' => ['weba', 'webm'],
        'video/mp4' => ['mp4', 'm4v'],
        'video/quicktime' => ['mov'],
        'video/webm' => ['webm'],
        'video/x-msvideo' => ['avi'],
        'video/x-matroska' => ['mkv'],
        'video/3gpp' => ['3gp'],
        'video/mpeg' => ['mpeg', 'mpg'],
        'font/woff' => ['woff'],
        'font/woff2' => ['woff2'],
        'font/ttf' => ['ttf'],
        'font/otf' => ['otf'],
        'text/html' => ['html', 'htm'],
        'text/xml' => ['xml'],
        'application/xml' => ['xml'],
    ];

    /**
     * Types libmagic reports for many unrelated formats (ASCII STL/OBJ/GCODE, binary blobs).
     */
    private const GENERIC = ['text/plain', 'application/octet-stream', 'inode/x-empty', 'application/x-empty'];

    /**
     * True for an extension that is short, alphanumeric and neither executable nor active content.
     */
    public static function isHarmless(string $extension): bool
    {
        $extension = strtolower($extension);

        return preg_match('/^[a-z0-9]{1,10}$/', $extension) === 1
            && !self::isExecutable($extension)
            && !self::isActiveContent($extension);
    }

    public static function isGeneric(?string $mime): bool
    {
        return $mime === null || in_array($mime, self::GENERIC, true);
    }

    /**
     * Detect the MIME type from the file contents (never from client headers).
     */
    public static function detect(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $mime = false;

        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($path);
        } elseif (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);
        }

        return is_string($mime) && $mime !== '' ? strtolower($mime) : null;
    }

    /**
     * @return string[] extensions accepted for the given MIME type
     */
    public static function extensionsFor(?string $mime): array
    {
        return $mime !== null ? (self::MAP[strtolower($mime)] ?? []) : [];
    }

    public static function isExecutable(string $extension): bool
    {
        return in_array(strtolower($extension), self::EXECUTABLE, true);
    }

    public static function isActiveContent(string $extension): bool
    {
        return in_array(strtolower($extension), self::ACTIVE_CONTENT, true);
    }

    /**
     * Pick an extension that is consistent with the file's real content.
     *
     * - client extension kept when it matches the detected type (photo.jpeg stays .jpeg)
     * - otherwise the canonical extension of the detected type is used
     * - unknown content keeps a harmless client extension, else falls back to "bin"
     * - executable / active-content extensions are never returned for an unrelated type, HTML becomes .txt
     */
    public static function safeExtension(string $path, string $clientExtension): string
    {
        $client = strtolower(trim($clientExtension));
        $mime = self::detect($path);
        $candidates = self::extensionsFor($mime);

        if ($client !== '' && in_array($client, $candidates, true) && self::isHarmless($client)) {
            return $client;
        }

        // Generic content (plain text, unknown binary): keep a harmless client extension (.stl, .gcode, .csv).
        if (self::isGeneric($mime) && $client !== '' && self::isHarmless($client)) {
            return $client;
        }

        if ($candidates !== []) {
            // Uploaded HTML documents are stored as plain text so they can never render on the app origin.
            return in_array($candidates[0], ['html', 'htm', 'xhtml'], true) ? 'txt' : $candidates[0];
        }

        if ($client !== '' && self::isHarmless($client) && !str_contains((string) $mime, 'php')
            && !str_contains((string) $mime, 'script') && !str_contains((string) $mime, 'html')) {
            return $client;
        }

        return str_starts_with((string) $mime, 'text/') ? 'txt' : 'bin';
    }
}
