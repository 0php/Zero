# View Layer

The Zero Framework view layer lets you render native PHP templates with a handful of Blade-inspired directives. Templates stay simple PHP files while gaining conveniences such as sections, layouts, and automatic HTML escaping.

## Rendering Views

Use the `view()` helper or `View::render()` to produce HTML from a template stored in `resources/views`.

```php
// In a controller or route callback
return view('pages/home', [
    'title' => 'Welcome home',
    'posts' => Post::latest()->take(5)->get(),
]);
```

Both helpers accept an array of data that is extracted into the template's scope. Keys are converted to variables (`$title`, `$posts`, …).

Whenever you print data, prefer the escaped output directive `{{ $value }}`—it runs the expression through `htmlspecialchars`, keeping user-supplied content safe by default.

### Layouts & Sections

Add `@layout` at the top of a template to wrap it in a parent layout. Sections declared with `@section` / `@endsection` are later inserted with `@yield` inside the layout.

```php
// resources/views/layouts/app.php
@include('components.head', ['title' => $title ?? 'Zero Framework'])

    <main class="container py-5">
        @yield('content')
    </main>

@include('components.footer')
```

```php
// resources/views/pages/home.php
@layout('layouts.app', ['title' => $title ?? 'Dashboard'])

@section('content')
    <h1 class="mb-4">{{ $title }}</h1>

    @foreach ($posts as $post)
        <article class="mb-3">
            <h2 class="h5 mb-1">{{ $post->title }}</h2>
            <p class="mb-0">{!! $post->excerpt !!}</p>
        </article>
    @empty
        <p class="text-muted">No posts yet. Check back soon!</p>
    @endforeach
@endsection
```

Layout data passed to `@layout` is extracted after the view data. This lets layout-specific variables (such as meta tags) override values defined within the view when necessary.

## Including Partials

Use `@include('path.to.partial', ['data' => $value])` to pull in reusable fragments. The path mirrors the filesystem under `resources/views`, using dots or slashes (`@include('components.alert')` maps to `resources/views/components/alert.php`).

## Output & Escaping

| Directive        | Description                                          |
| ---------------- | ---------------------------------------------------- |
| `{{ $value }}`   | Escapes the expression with `htmlspecialchars`.      |
| `{!! $value !!}` | Outputs raw HTML without escaping — use sparingly.   |
| `{{{ $value }}}` | Alias for raw output, kept for legacy compatibility. |

Prefer the escaped `{{ }}` syntax to avoid XSS. Reserve raw output forms for trusted, pre-sanitised HTML snippets.

## Control Structures

The compiler understands a subset of Blade-style directives that compile directly to PHP:

- Conditionals: `@if`, `@elseif`, `@else`, `@endif`
- Loops: `@for`, `@endfor`, `@foreach`, `@endforeach`
- Empty states: pair `@empty` inside an `@foreach` to render fallback content when the iterable has no items

Example:

```php
@foreach ($comments as $comment)
    <li>{{ $comment->body }}</li>
@empty
    <li class="text-muted">Be the first to comment.</li>
@endforeach
```

Nested loops are supported and each `@empty` is scoped to its nearest `@foreach` block.

## Running Arbitrary PHP

For quick inline statements use `@php($counter++)`. For multi-line blocks, wrap the code with `@php ... @endphp`.

```php
@php($hasSidebar = count($widgets) > 0)

@php
    $timestamp = date('c');
@endphp
```

## Legacy Static API

Directives are syntactic sugar for the underlying `Zero\Lib\View` static helpers. You can mix and match both styles or fall back entirely to PHP if you prefer:

```php
<?php View::layout('layouts.app'); ?>

<?php View::startSection('content'); ?>
    <h1>Hello from the legacy API</h1>
<?php View::endSection(); ?>
```

All helper methods remain available:

- `View::layout($name, $data = [])`
- `View::startSection($name)` / `View::endSection()`
- `View::yieldSection($name)`
- `View::include($name, $data = [])`
- `View::render($name, $data = [])`

## Caching & Configuration

Compiled templates can be cached to disk. Enable caching in `config/view.php` or at runtime:

```php
View::configure([
    'cache_enabled' => true,
    'cache_path' => base('storage/cache'),
    'cache_lifetime' => 3600,
]);
```

Use `View::clearCache()` to purge the entire cache or `View::clearViewCache('pages/home')` to refresh a single template. When cache is disabled the framework recompiles templates on every render.

## Debugging Tips

- Drop `@dd($variable)` in a template to dump a value and stop execution.
- To inspect the compiled PHP, enable caching with `'debug' => true` in the view configuration. Compiled files are written beneath `<cache_path>/views/cache`.
- If a directive throws a syntax error, re-run with `'cache_enabled' => false` to ensure you are testing fresh output.

## Quick Reference

| Feature                | Directive                             | Underlying API                            |
| ---------------------- | ------------------------------------- | ----------------------------------------- |
| Layout binding         | `@layout('layouts.app')`              | `View::layout('layouts.app')`             |
| Sections               | `@section('name') ... @endsection`    | `View::startSection` / `View::endSection` |
| Yield                  | `@yield('name')`                      | `View::yieldSection`                      |
| Partials               | `@include('partials.alert')`          | `View::include`                           |
| Loops with empty state | `@foreach ... @empty ... @endforeach` | generated PHP guard                       |
| Raw PHP                | `@php($expr)` / `@php ... @endphp`    | inline PHP blocks                         |
| Raw HTML               | `{!! $html !!}` or `{{{ $html }}}`    | direct `echo`                             |
| Safety render text     | `{{ $text }}`                         | `htmlspecialchars`                        |

Keep templates small and focused. For complex presentation logic, move calculations into controllers or view models before handing data to the renderer.
