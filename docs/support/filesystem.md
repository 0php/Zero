# Filesystem helpers

Namespace: `Zero\Lib\Filesystem\File`. Wraps file IO (reading, writing, hashing, MIME helpers) and supports in-memory factories.

## Factories

### `File::fromPath(string $path): File`
```php
$file = File::fromPath('/tmp/avatar.png');
```

### `File::fromContents(string $contents, string $extension = 'txt'): File`
```php
$file = File::fromContents('hello', extension: 'txt');
```

### `File::fromBase64(string $payload, string $extension = 'png'): File`
```php
$file = File::fromBase64($payload, extension: 'png');
```

### `File::fromUrl(string $url): File`
```php
$file = File::fromUrl('https://example.com/logo.png');
```

### `File::from(mixed $value): File`
Auto-detect path / URL / base64 / raw contents.
```php
$file = File::from($input); // detects what kind of value it is
```

## Common methods

```php
$file->getSignedUrl();
$file->setMimeType('image/png');
$file->setExtension('png');
$file->isImage();
$file->isVideo();
$file->is('pdf');
```

## Storage integration

Pass any `File` (or `UploadedFile`, which extends `File`) to `Storage::put()`:

```php
use Zero\Lib\Storage\Storage;

Storage::put('users/' . $id . '/avatar.png', $file);
```
