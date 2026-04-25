# Views

Native PHP templates with a handful of Blade-inspired directives. Files live under `resources/views/` and can opt into layouts, sections, includes, and view caching.

```php
use Zero\Lib\View;
```

Implementation: [`View.php`](../core/libraries/View/View.php), compiler at [`ViewCompiler.php`](../core/libraries/View/ViewCompiler.php).

---

## API reference

### `View::render(string $view, array $data = []): string`
Render a template. The view name is the path under `resources/views/`, with `/` or `.` as the separator. Returns the compiled HTML.
```php
$html = View::render('pages.home', ['user' => $user]);
$html = View::render('pages/home', ['user' => $user]); // equivalent
```

Inside the template, every key in `$data` is available as a local variable:
```php
<!-- resources/views/pages/home.php -->
<h1>Hello, <?= htmlspecialchars($user->name) ?></h1>
```

In controllers prefer the global `view()` helper, which wraps `render()` in a `Response`:
```php
return view('pages.home', ['user' => $user]);
```

### `View::renderString(string $template, array $data = []): string`
Render a Blade-style string template (no file lookup). Useful for emails compiled at runtime.
```php
$html = View::renderString('<h1>{{ $title }}</h1>', ['title' => 'Hi']);
```

### `View::include(string $view, array $data = []): void`
Render a partial inline (echoes directly). Inside templates use the `@include('partials.header')` directive — `View::include()` is the runtime call it compiles to.
```php
View::include('partials.header', ['user' => $user]);
```

### `View::layout(string $layout, array $data = []): void`
Set the parent layout for the current render. Usually invoked from inside a template via the `@layout('layouts.app')` directive.
```php
// inside a template
@layout('layouts.app')
```

### `View::startSection(string $section): void` / `View::endSection(): void`
Start / finish capturing a named section. Wired up via `@section(...)` / `@endsection`.
```html
@section('title')
    Dashboard
@endsection
```

### `View::yieldSection(string $section): string`
Output the captured section content. Used inside layouts via `@yield(...)`.
```html
<!-- resources/views/layouts/app.php -->
<title>@yield('title')</title>
```

### `View::configure(array $config = []): void`
Override the view config at runtime. Keys: `cache_enabled`, `cache_path`, `cache_lifetime`, `debug`. Defaults come from [`config/view.php`](../config/view.php).
```php
View::configure([
    'cache_enabled' => true,
    'cache_lifetime' => 3600,
]);
```

### `View::clearCache(): void`
Drop every compiled view in the configured cache path.
```php
View::clearCache();
```

### `View::clearViewCache(string $view): void`
Drop the cache file for one view.
```php
View::clearViewCache('pages.home');
```

---

## Directives

Compiled by [`ViewCompiler`](../core/libraries/View/ViewCompiler.php). All directives transparently compile to native PHP.

### Echoing values

```html
{{ $name }}             {{-- escaped --}}
{!! $trustedHtml !!}    {{-- raw --}}
{{{ $alsoEscaped }}}    {{-- legacy: escaped --}}
@{{ literal }}          {{-- escape the curly braces themselves --}}
```

### Control flow

```html
@if ($user)
    Welcome, {{ $user->name }}
@elseif ($guest)
    Hi guest
@else
    Sign in
@endif

@for ($i = 0; $i < 5; $i++)
    {{ $i }}
@endfor

@foreach ($items as $item)
    {{ $item }}
@empty
    Nothing here yet.
@endforeach
```

`@empty` only fires when the iterable yields zero items.

### Layouts and sections

```html
{{-- resources/views/layouts/app.php --}}
<!doctype html>
<html>
<head><title>@yield('title')</title></head>
<body>
    @yield('body')
</body>
</html>
```

```html
{{-- resources/views/pages/home.php --}}
@layout('layouts.app')

@section('title') Dashboard @endsection

@section('body')
    <h1>Hello, {{ $user->name }}</h1>
    @include('partials.footer')
@endsection
```

### Includes

```html
@include('partials.header')
@include('partials.alert', ['type' => 'error', 'message' => $msg])
```

### i18n directives

See [i18n.md](i18n.md) for the full picture.
```html
@t('common.welcome')                      {{-- translates --}}
@i18n('mail/welcome')                     {{-- switch translation file --}}

@i18n(['title' => 'Hello'])
    {{ $title }}
@endi18n
```

### Inline PHP

```html
@php
    $count = count($items);
@endphp

@php($count = count($items))   {{-- single-expression form --}}

{{ $count }}

@dd($payload)   {{-- dump and exit --}}
```

---

## Caching

Compiled templates live under `storage/cache/views`. Toggle caching with `View::configure(['cache_enabled' => true])` or `config/view.php`. Each compiled file is keyed by an MD5 of the view identifier and re-validated against the source mtime on every render.

To bust the cache for one view (e.g. after deploying a hot fix without a deploy script):
```php
View::clearViewCache('pages.home');
```

To wipe everything:
```php
View::clearCache();
```

---

## Working with views from controllers

```php
namespace App\Controllers;

use Zero\Lib\Http\Request;

class UserController
{
    public function show(int $id, Request $request)
    {
        $user = \App\Models\User::find($id);

        if ($request->wantsJson()) {
            return $user;             // → JSON
        }

        return view('users.show', compact('user'));
    }
}
```

Controllers can return:

- a `Response` (`view()` returns one)
- a string (HTML)
- a model / array (auto-JSON)
- `null` (204 No Content)

The router runs everything through `Response::resolve()`.
