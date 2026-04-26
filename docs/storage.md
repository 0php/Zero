# Storage

A filesystem abstraction for uploads, generated assets, and cached artefacts. The `Zero\Lib\Storage\Storage` facade resolves a configured disk and proxies the operation to the underlying driver. Two drivers ship in v1: **local** (POSIX filesystem) and **s3** (any S3-compatible bucket — AWS, MinIO, R2, DigitalOcean Spaces, Wasabi, etc.). Every method on the facade is also available directly on a disk instance via `Storage::disk('name')`.

---

## Contents

- [Quick start](#quick-start)
- [Configuration](#configuration)
- [Reading & writing](#reading--writing)
- [File metadata](#file-metadata)
- [Listing files & directories](#listing-files--directories)
- [Deleting, copying, moving](#deleting-copying-moving)
- [Streams](#streams)
- [Visibility](#visibility)
- [URLs & responses](#urls--responses)
- [Working with `File` and `UploadedFile`](#working-with-file-and-uploadedfile)
- [Driver behaviour notes](#driver-behaviour-notes)
- [Authoring a custom driver](#authoring-a-custom-driver)

---

## Quick start

```php
use Zero\Lib\Storage\Storage;

// Write
Storage::put('reports/2026.csv', $csvString);

// Read
$body = Storage::get('reports/2026.csv');

// Stream as the HTTP response
return Storage::response('reports/2026.csv', null, [
    'name' => 'sales-2026.csv',
    'disposition' => 'attachment',
]);
```

The disk used is the default (`config('storage.default')`). Pass an explicit disk name as the trailing argument to switch:

```php
Storage::put('reports/2026.csv', $csvString, 's3');
```

---

## Configuration

`config/storage.php`:

```php
return [
    'default' => env('STORAGE_DISK', 'public'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'url' => null,
            'visibility' => 'private',
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),       // optional, e.g. MinIO/R2
            'path_style' => env('AWS_PATH_STYLE', true),
            'acl' => env('AWS_ACL'),                 // optional default ACL
            'root' => env('AWS_PREFIX', ''),         // optional bucket-prefix
        ],
    ],
];
```

### Disk roles

The convention used by the bundled scaffolding:

| Disk | Driver | Purpose |
| --- | --- | --- |
| `local` | local | Private artefacts (uploads pending moderation, cache, generated PDFs, log archives) |
| `public` | local | Files that need a stable URL — browser access via `public/storage` symlink (`php zero storage:link`) |
| `s3` | s3 | Production storage backed by any S3-compatible bucket |

You can define as many disks as you like; the facade resolves them by name.

### S3 endpoints for non-AWS providers

```php
// MinIO
'endpoint' => 'http://minio.local:9000',
'path_style' => true,

// Cloudflare R2
'endpoint' => 'https://{account_id}.r2.cloudflarestorage.com',
'region' => 'auto',
'path_style' => false,

// DigitalOcean Spaces
'endpoint' => 'https://nyc3.digitaloceanspaces.com',
'region' => 'nyc3',
'path_style' => false,

// Wasabi
'endpoint' => 'https://s3.wasabisys.com',
'region' => 'us-east-1',
```

---

## Reading & writing

### `Storage::put(string $path, string|File $contents, ?string $disk = null): string`
Write raw contents (or a `File` instance) and return the stored relative path.
```php
Storage::put('logs/today.txt', 'Hello world');
Storage::put('avatars/user-42.jpg', $uploadedFile);
Storage::put('archive/snapshot.json', $jsonBlob, 's3');
```

### `Storage::get(string|File $path, ?string $disk = null): string`
Return the contents as a string. Throws `RuntimeException` if the path is missing or unreadable.
```php
$csv = Storage::get('reports/2026.csv');
```

### `Storage::exists(string $path, ?string $disk = null): bool`
```php
if (Storage::exists('exports/finished.flag')) {
    // ...
}
```

### `Storage::putFile(string $directory, File $file, ?string $disk = null): string`
Store a `File`/`UploadedFile` inside `$directory`, generating a unique filename derived from the original.
```php
$path = Storage::putFile('uploads', $request->file('photo'));
// uploads/photo-65f2a9e1b8d2.jpg
```

### `Storage::putFileAs(string $directory, File $file, string $name, ?string $disk = null): string`
Same as above but pins the filename.
```php
Storage::putFileAs('avatars', $request->file('photo'), 'user-42.jpg');
```

### `Storage::prepend(string $path, string $data, ?string $disk = null): string`
Prepend `$data` to the file. Creates the file if it doesn't exist.
```php
Storage::prepend('logs/today.txt', "[start] {$now}\n");
```

### `Storage::append(string $path, string $data, ?string $disk = null): string`
Append `$data` to the file. Creates the file if it doesn't exist.
```php
Storage::append('logs/today.txt', $logLine . "\n");
```

> ⚠️ On the S3 driver, `prepend()` and `append()` are read-modify-write — the entire object is downloaded, modified in memory, and re-uploaded. Cheap for small files, expensive for large ones, **not atomic** under concurrent writers.

---

## File metadata

### `Storage::size(string $path, ?string $disk = null): int`
File size in bytes. Throws if the path is missing.
```php
$bytes = Storage::size('reports/2026.csv');
```

### `Storage::lastModified(string $path, ?string $disk = null): int`
Last-modified time as a Unix timestamp.
```php
$age = time() - Storage::lastModified('exports/finished.flag');
```

### `Storage::mimeType(string $path, ?string $disk = null): string`
Best-effort MIME type. Falls back to `application/octet-stream` when detection fails.
```php
Storage::mimeType('avatars/user-42.jpg');  // image/jpeg
```

> On the S3 driver, `size()`, `lastModified()`, and `mimeType()` are pulled from `HEAD` response headers — no body download.

---

## Listing files & directories

### `Storage::files(string $directory = '', bool $recursive = false, ?string $disk = null): array`
Return `File`/`RemoteFile` instances for every file under `$directory`.
```php
foreach (Storage::files('uploads') as $file) {
    echo $file->getFilename();
}

// Recursive
$all = Storage::files('uploads', true);
```

### `Storage::directories(string $directory = '', bool $recursive = false, ?string $disk = null): array`
Return relative directory paths (strings, not `File` objects). On S3 this is derived from common prefixes — there are no real directories.
```php
Storage::directories('docs');         // ['docs/2024', 'docs/2025']
Storage::directories('', true);       // every prefix in the disk
```

### `Storage::makeDirectory(string $path, ?string $disk = null): bool`
Create a directory. On S3 this writes a 0-byte placeholder ending in `/` so the prefix shows up in console UIs.
```php
Storage::makeDirectory('exports/2026/Q1');
```

---

## Deleting, copying, moving

### `Storage::delete(string|array $paths, ?string $disk = null): bool`
Delete one or many files. Missing files count as success — the desired state is "gone".
```php
Storage::delete('logs/today.txt');
Storage::delete(['a.txt', 'b.txt', 'c.txt']);
```

### `Storage::deleteDirectory(string $directory, ?string $disk = null): bool`
Recursively wipe a directory. On S3 this iterates every object under the prefix and issues a delete — there's no native single-call equivalent.
```php
Storage::deleteDirectory('exports/2025');
```

> The S3 driver refuses to delete the empty directory (`''`) — that would wipe the entire bucket. Pass an explicit prefix.

### `Storage::copy(string $from, string $to, ?string $disk = null): bool`
Copy a file inside the same disk. Returns `false` when the source is missing.
```php
Storage::copy('uploads/draft.pdf', 'archive/draft-' . time() . '.pdf');
```

### `Storage::move(string $from, string $to, ?string $disk = null): bool`
Move (rename) a file inside the same disk.
```php
Storage::move('uploads/temp/photo.jpg', 'avatars/user-42.jpg');
```

> Both `copy()` and `move()` operate on a single disk. To move a file *between* disks, read from one and write to the other:
> ```php
> Storage::put('archive/file.pdf', Storage::get('uploads/file.pdf', 'public'), 's3');
> Storage::delete('uploads/file.pdf', 'public');
> ```

---

## Streams

Use streams when files are large enough that loading them entirely into memory matters (videos, backups, analytics dumps).

### `Storage::readStream(string $path, ?string $disk = null)`
Open the file/object for reading. Returns a PHP stream resource — the **caller** is responsible for `fclose()`.
```php
$stream = Storage::readStream('videos/talk.mp4', 's3');
while (! feof($stream)) {
    echo fread($stream, 65536);
}
fclose($stream);
```

### `Storage::writeStream(string $path, $stream, ?string $disk = null): string`
Pipe a readable stream into the disk. Does **not** close the source stream.
```php
$src = fopen('php://input', 'rb');
Storage::writeStream('uploads/raw-body.bin', $src);
fclose($src);
```

> The S3 driver's `writeStream()` buffers the entire stream in memory before uploading (the underlying adapter doesn't yet support multipart uploads). For genuinely large files, save to a `File` first and use `Storage::putFile()` / `Storage::put()`.

---

## Visibility

Visibility is a portable abstraction over per-driver permission models:

| Driver | `public` | `private` |
| --- | --- | --- |
| local | `0664` (file) / `0775` (dir) | `0600` (file) / `0700` (dir) |
| s3 | `public-read` ACL | `private` ACL |

### `Storage::setVisibility(string $path, string $visibility, ?string $disk = null): bool`
```php
Storage::setVisibility('avatars/user-42.jpg', 'public', 's3');
Storage::setVisibility('reports/draft.pdf', 'private');
```

### `Storage::getVisibility(string $path, ?string $disk = null): string`
Returns `'public'` or `'private'`.
```php
$mode = Storage::getVisibility('uploads/photo.jpg');
```

> S3's `getVisibility()` is best-effort — many providers don't surface ACL via `HEAD`, and the driver returns `'private'` as the safe default. Don't use it for security checks; rely on bucket policies and signed URLs instead.

---

## URLs & responses

### `Storage::url(string $path, ?string $disk = null): string`
A direct URL to the file. On the local driver this returns the configured `url` prefix joined to the path; on S3 it returns a 5-minute presigned GET URL.
```php
Storage::url('avatars/user-42.jpg');
// local: https://example.com/storage/avatars/user-42.jpg
// s3:    https://bucket.s3.amazonaws.com/avatars/user-42.jpg?X-Amz-Algorithm=...
```

### `Storage::temporaryUrl(string $path, DateTimeInterface|int $expiration, ?string $disk = null): string`
A signed URL that expires. Pass either an absolute timestamp or seconds-from-now (when given a positive int). On the local driver this produces an HMAC-signed URL the application's storage controller verifies before serving the file.
```php
Storage::temporaryUrl('reports/2026.csv', now()->addMinutes(15));
Storage::temporaryUrl('reports/2026.csv', 300);              // 5 min
```

### `Storage::response(string $path, ?string $disk = null, array $options = []): Response`
Build an HTTP `Response` that streams the file inline (browser-displayed) or as an attachment (download).

```php
return Storage::response('reports/2026.csv', null, [
    'name' => 'sales-2026.csv',
    'disposition' => 'attachment',     // or 'inline'
    'headers' => ['Cache-Control' => 'public, max-age=3600'],
]);
```

The S3 driver streams chunks directly — no intermediate buffering — so this is suitable for large files.

---

## Working with `File` and `UploadedFile`

Storage operations accept both raw paths and Zero's filesystem objects:

```php
use Zero\Lib\Filesystem\File;

// File created from a local path
$file = File::fromPath('/tmp/inbound.csv');
Storage::put('imports/inbound.csv', $file);

// UploadedFile straight off the request
$upload = $request->file('avatar');
Storage::putFile('avatars', $upload);

// File instances from a listing
foreach (Storage::files('uploads') as $file) {
    if ($file->getMimeType() === 'image/png') {
        Storage::move($file->getStoragePath(), "approved/{$file->getBasename()}");
    }
}
```

Listing operations return `File` (local) or `RemoteFile` (S3) — both expose the same metadata accessors (`getFilename`, `getMimeType`, `getSize`, etc.) plus a `getStoragePath()` you can pass straight back to `Storage::*`.

---

## Driver behaviour notes

A few places where the two drivers behave differently:

| Operation | Local | S3 |
| --- | --- | --- |
| `prepend` / `append` | True append at the OS level | Read-modify-write the entire object (not atomic) |
| `directories()` | Real directory entries | Derived from object key prefixes |
| `makeDirectory()` | Real `mkdir` | 0-byte placeholder key ending in `/` |
| `deleteDirectory('')` | Wipes the disk root | **Refused** — returns false (avoid bucket-wipe) |
| `getVisibility()` | Reads POSIX permissions | Returns `'private'` as a safe default |
| `writeStream()` | Streams chunk-by-chunk | Buffers fully in memory before upload |
| `url()` | Configured base URL or path | 5-minute presigned URL |

When in doubt, the local driver is the source of truth — its semantics match what most code expects.

---

## Authoring a custom driver

Custom drivers should expose the same surface as `LocalStorage` / `S3Storage` so the facade can call through. The contract is implicit (no formal interface in v1) but the methods are:

```
put(string $path, string|File $contents): string
putFile(string $directory, File $file): string
putFileAs(string $directory, File $file, string $name): string
get(string|File $path): string
exists(string $path): bool
files(string $directory = '', bool $recursive = false): array
directories(string $directory = '', bool $recursive = false): array
makeDirectory(string $path): bool
delete(string|array $paths): bool
deleteDirectory(string $directory): bool
copy(string $from, string $to): bool
move(string $from, string $to): bool
prepend(string $path, string $data): string
append(string $path, string $data): string
size(string $path): int
lastModified(string $path): int
mimeType(string $path): string
readStream(string $path)
writeStream(string $path, $stream): string
setVisibility(string $path, string $visibility): bool
getVisibility(string $path): string
url(string $path): string
temporaryUrl(string $path, DateTimeInterface|int $expiration): string
response(string $path, array $options = []): Response
```

Register the driver by extending `StorageManager` (or instantiating it manually and wiring it into your bootstrap). A future revision will introduce a formal `DiskInterface` so the contract is enforceable; treat the list above as the working contract until then.
