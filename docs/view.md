# View Layer

The view system renders PHP templates with Blade-inspired conveniences while staying fully native.

## Rendering Templates

`View::render($name, $data)` returns the rendered markup as a string. Controllers can feed the result into `Response::html()` to participate in the response pipeline.

```php
$html = View::render('pages/home', ['examples' => $examples]);
return Response::html($html);
```

## Layouts and Sections

Use `View::layout()` inside a template to specify a parent layout and `View::startSection()` / `View::endSection()` to define regions that layouts can yield.

```php
<?php View::layout('layouts/app') ?>

<?php View::startSection('content') ?>
    <h1>Hello World</h1>
<?php View::endSection() ?>
```

In `resources/views/layouts/app.php`:

```php
<!DOCTYPE html>
<html>
    <body>
        <?= View::yieldSection('content') ?>
    </body>
</html>
```

## Blade-Like Directives

- Control structures: `@if`, `@elseif`, `@else`, `@endif`, `@foreach`, `@endforeach`
- Echo helpers: `{{ $variable }}` (escaped), `{{{ $raw }}}` (unescaped)
- Includes: `@include('partials/header.php')`
- Sections: `@section`, `@endsection`, `@yield`
- Debugging: `@dd($value)`

## Caching

Enable caching via `View::configure(['cache_enabled' => true])` or by editing `config/view.php`. The renderer writes compiled templates to `storage/cache/views`. Use `View::clearCache()` or `View::clearViewCache($name)` when deploying new views.

## State Reset

To avoid cross-request contamination, the renderer resets section and layout state each time you call `View::render()`. This makes nested render calls safe (e.g., when rendering partials or emails).
