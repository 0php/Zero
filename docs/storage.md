# Storage

Zero Framework ships with a light filesystem abstraction that keeps uploads, generated assets, and cached artefacts in one place. The `Zero\\Lib\\Storage\\Storage` facade resolves a configured disk (default `public`) and proxies basic file operations to the underlying driver.

## Configuration

The `config/storage.php` file describes the available disks. At the top of the file we cache `APP_URL` once and then return a clean array. You don’t need to set

```php
$appUrl = rtrim((string) env('APP_URL', 'http://127.0.0.1:8000'), '/');

return [
    'default' => env('STORAGE_DISK', 'public'),
    'disks' => [
        'public' => [
            'driver' => 'local',
            'root' => env('STORAGE_PUBLIC_ROOT', storage_path('app/public')),
            'url' => $appUrl . '/storage',
            'visibility' => 'public',
        ],
        'private' => [
            'driver' => 'local',
            'root' => env('STORAGE_PRIVATE_ROOT', storage_path('app/private')),
            'url' => $appUrl . '/files/private',
            'visibility' => 'private',
        ],
        's3' => [
            'driver' => 's3',
            'key' => env('S3_ACCESS_KEY'),
            'secret' => env('S3_SECRET_KEY'),
            'region' => env('S3_REGION', 'us-east-1'),
            'signing_region' => env('S3_SIGNING_REGION'),
            'bucket' => env('S3_BUCKET'),
            'endpoint' => env('S3_ENDPOINT'),
            'path_style' => filter_var(env('S3_PATH_STYLE', true), FILTER_VALIDATE_BOOLEAN),
            'acl' => env('S3_DEFAULT_ACL', 'private'),
            'root' => env('S3_ROOT_PATH', ''),
            'timeout' => (int) env('S3_TIMEOUT', 60),
            'signature_version' => strtolower((string) env('S3_SIGNATURE_VERSION', 'auto')),
            'visibility' => env('S3_VISIBILITY', 'private'),
        ],
    ],
    'links' => [
        public_path('storage') => 'public',
    ],
];
```

Requesting an undefined disk throws an `InvalidArgumentException`, so keep the configuration in sync with your usage. The optional `visibility` key is consumed by helper methods such as `File::getUrl()` to decide whether a direct link or a signed URL should be returned.

Temporary URLs are signed using your application's `APP_KEY`, so keep it secret and unique per environment. The framework automatically exposes signed private files under `/files/private/{path}` guarded by the `ValidateStorageSignature` middleware.

## Writing Files

```php
use Zero\\Lib\\Storage\\Storage;

$path = Storage::put('reports/latest.txt', "Report generated at " . date('c'));
// $path === 'reports/latest.txt'

$file = Zero\Lib\Filesystem\File::fromUrl('https://example.com/logo.png');
Storage::put('assets/logo.png', $file, 'public');

Storage::put('media/banner.jpg', $file, 's3');
```

All paths are relative to the disk root; use helper functions such as `storage_path()` when you need the absolute location.

## Reading Files

Fetch file contents directly through the facade:

```php
$contents = Storage::get('reports/latest.txt');
```

Call `Storage::disk('private')->get(...)` (or `'public'`, `'s3'`) to read from a non-default disk. The method throws a `RuntimeException` if the file is missing or unreadable, which makes error handling explicit in CLI scripts and controllers.

Need to stream the file back to the browser without loading it fully into memory? Use `Storage::response($path, $disk)` to return a `Response` that streams the file contents and sets sane headers:

```php
return Storage::response('reports/latest.txt', 'private');
```

## Checking Files

```php
if (Storage::exists('reports/latest.txt')) {
    // ...
}
```

Pass a second argument (or use `Storage::disk('public')`) to target a different disk. The method returns `false` for directories; it is designed for files.

## Listing Files

List the files immediately within a directory. Each entry is returned as a `Zero\Lib\Filesystem\File` instance. Remote S3 objects are represented by lightweight in-memory virtual files so no local cache or sidecar JSON is written:

```php
$files = Storage::files('reports');

foreach ($files as $file) {
    $basename = $file->getBasename();
    $signedUrl = $file->getSignedUrl('+10 minutes', 'private');
}

$private = Storage::files('reports', disk: 'private');
```

Passing `true` as the second argument returns every descendant file (still as `File` instances):

```php
$tree = Storage::files('reports', true);

$nested = Storage::files('reports', true, 'private');
// or Storage::files('reports', recursive: true, disk: 'private');
```

`Storage::list(...)` is an alias for `Storage::files(...)`. A missing directory results in an empty array.

## Generating URLs

Use `Storage::url()` to build a publicly accessible URL for a file when the disk advertises a base URL (configure `disks.*.url` in `config/storage.php` or adjust `APP_URL`). It falls back to the absolute filesystem path when no URL is available.

```php
$link = Storage::url('reports/latest.txt');
```

### Temporary URLs

To grant time-limited access, request a signed URL:

```php
$signed = Storage::temporaryUrl('reports/latest.txt', Zero\Lib\Support\DateTime::parse('+10 minutes'), 'private');
```

The helper appends `path`, `expires`, and `signature` query parameters using your `APP_KEY`. Validate these parameters inside your download endpoint or middleware before streaming the file (see `App\Middlewares\ValidateStorageSignature`).

### Middleware

`App\Middlewares\ValidateStorageSignature` encapsulates the verification logic so you can reuse it anywhere you expose signed downloads. The private file route enables it automatically, but you can also attach it to custom endpoints or route groups.

The middleware accepts the `path` query string produced by `Storage::temporaryUrl()` or a custom request attribute (`Request::set('storage.signed.path', $resource)`), giving you flexibility over how the resource path is resolved. Internally it validates the signature using `APP_KEY`, checks that the link has not expired, and returns a 403 response if the checks fail. Pair it with the `private` disk to keep sensitive files off the public symlink.

When you use the `s3` disk, nothing is written to `storage/meta` or `storage/app/cache`; file context (e.g. size, etag) is kept in-memory with the listing.

## Working With Uploads

Uploaded files are represented by `Zero\\Lib\\Http\\UploadedFile` and expose convenience helpers that integrate with the storage facade.

```php
$avatar = Request::file('avatar');

if ($avatar && $avatar->isValid()) {
    $stored = $avatar->store('avatars');
    // avatars/slug-64d2c6b1e5c3.jpg
}
```

Validate incoming uploads with the built-in rules before persisting them:

```php
$data = Request::validate([
    'avatar' => ['required', 'file', 'image', 'mimes:jpg,png', 'max:2048'],
]);
```

`file` ensures the payload is a valid `UploadedFile`, `image` restricts the MIME type to `image/*`, `mimes` checks the extension, and `min`/`max` interpret their limits in kilobytes when applied to files.

Need to control the filename or disk?

```php
$avatar->storeAs('avatars', $avatar->hashedName(), disk: 'private');
```

Both `store()` and `storeAs()` return the relative path written to the disk. Pass the optional disk name (`Storage::disk('private')` or `$file->store('docs', 'private')`) to separate public assets from private documents.

## Additional Disks

Add new entries under the `disks` array to extend storage locations (for example, a dedicated public disk for CDN assets). Retrieval looks like:

```php
Storage::disk('public')->put('assets/app.js', $compiled);
```

Implement custom drivers by updating `Zero\\Lib\\Storage\\StorageManager::createDriver()` to resolve new driver strings.

## Symlinking Disks

```bash
php zero storage:link
```

The command reads `config/storage.php['links']` and creates symbolic links so web servers can read from non-public roots (defaults to mapping `public/storage` to the `public` disk). Ensure the process has permission to create the link path.

## Roadmap

- Support streaming reads/writes for large files.
- Add convenience helpers (delete, directories) across drivers.
- Document custom driver development once additional backends are available.

## Related APIs

- `Zero\\Lib\\Filesystem\\File` – general-purpose filesystem helper used by storage drivers and application code.
- `Zero\\Lib\\Storage\\Drivers\\LocalStorage` – default driver implementation.
- `Zero\\Lib\\Http\\UploadedFile` – upload wrapper that delegates persistence to storage disks.
- `Zero\\Lib\\Console\\Commands\\StorageLinkCommand` – CLI helper for creating symlinks.
