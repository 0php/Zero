# View Layer

The view system renders PHP templates with Blade-inspired conveniences while staying fully native.

## Rendering Templates

`View::render($name, $data)` returns the rendered markup as a string. Controllers can feed the result into `Response::html()` or simply return the `view()` helper if you prefer the shorthand.

```php
return view('pages/dashboard', ['user' => $user]);
```

## Layouts and Sections

Use the `@layout` directive inside a template to specify a parent layout and wrap markup with `@section` / `@endsection` blocks that the layout can `@yield`.

```php
@layout('layouts.app', ['title' => 'Welcome'])

@section('content')
    <h1>Hello World</h1>
@endsection
```

`resources/views/layouts/app.php` can yield the section or compose partials:

```php
@include('components.head', ['title' => $title ?? 'Zero Framework'])

    @yield('content')

@include('components.footer')
```

Both `@layout` and `@include` accept an optional second argument to pass an associative array of data (`@include('components.alert', ['type' => 'success'])`). Layout variables are extracted after the view data, so layout-supplied values take precedence when both define the same key.

## Blade-Like Directives

- Layouts & sections: `@layout`, `@section`, `@endsection`, `@yield`
- Control structures: `@if`, `@elseif`, `@else`, `@endif`, `@foreach`, `@endforeach`, `@for`, `@endfor`
- Echo helpers: `{{ $variable }}` (escaped), `{{{ $raw }}}` (unescaped)
- Free-form PHP: `@php ... @endphp` blocks or single-line `@php($answer = 42)`
- Partials: `@include('components.head')`, `@include('components.toast', ['message' => $message])`
- Debugging: `@dd($value)`

## Manual API (Legacy-Friendly)

You can still work with the low-level `View` API if you prefer the original, directive-free approach or need to mix plain PHP in templates.

```php
<?php View::layout('layouts.app', ['title' => 'Welcome']); ?>

<?php View::startSection('content'); ?>
    <h1>Hello World</h1>
<?php View::endSection(); ?>

<!-- In a layout file -->
<?php include base('resources/views/components/head.php'); ?>
<?php echo View::yieldSection('content'); ?>
<?php include base('resources/views/components/footer.php'); ?>
```

The static helpers (`View::layout`, `View::startSection`, `View::endSection`, `View::yieldSection`, `View::include`) remain available and behave the same way the directives expand under the hood, so both styles can coexist in the same project.

## Caching

Enable caching via `View::configure(['cache_enabled' => true])` or by editing `config/view.php`. The renderer writes compiled templates to `storage/cache/views`. Use `View::clearCache()` or `View::clearViewCache($name)` when deploying new views.

## State Reset

To avoid cross-request contamination, the renderer resets section and layout state each time you call `View::render()`. This makes nested render calls safe (e.g., when rendering partials or emails).
