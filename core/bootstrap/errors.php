<?php

declare(strict_types=1);

use Zero\Lib\Log;
use Zero\Lib\Http\Response as HttpResponse;
use Zero\Lib\Validation\ValidationException;

if (!function_exists('zero_render_exception_page')) {
    function zero_render_exception_page(\Throwable $e): string
    {
        $traceHtml = '';

        foreach ($e->getTrace() as $index => $frame) {
            $file = htmlspecialchars((string) ($frame['file'] ?? '[internal]'), ENT_QUOTES, 'UTF-8');
            $line = htmlspecialchars((string) ($frame['line'] ?? '0'), ENT_QUOTES, 'UTF-8');
            $function = htmlspecialchars((string) ($frame['function'] ?? '(closure)'), ENT_QUOTES, 'UTF-8');
            $class = htmlspecialchars((string) ($frame['class'] ?? ''), ENT_QUOTES, 'UTF-8');
            $type = htmlspecialchars((string) ($frame['type'] ?? ''), ENT_QUOTES, 'UTF-8');

            $traceHtml .= "<div class=\"frame\"><div class=\"frame-index\">#{$index}</div><div><div class=\"frame-location\">{$class}{$type}{$function}</div><div class=\"frame-file\">{$file}<span class=\"frame-line\">:{$line}</span></div></div></div>";
        }

        $title = htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $file = htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8');
        $line = htmlspecialchars((string) $e->getLine(), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        :root {
            color-scheme: dark;
        }
        body {
            margin: 0;
            font-family: "Fira Code", Menlo, Consolas, monospace;
            background: #0d1117;
            color: #c9d1d9;
        }
        header {
            padding: 32px;
            background: #161b22;
            border-bottom: 1px solid #21262d;
        }
        h1 {
            margin: 0 0 12px;
            font-size: 28px;
            color: #f77669;
        }
        .meta {
            font-size: 14px;
            color: #8b949e;
        }
        main {
            padding: 32px;
        }
        .frame {
            display: flex;
            gap: 16px;
            padding: 16px;
            border-radius: 8px;
            background: #161b22;
            border: 1px solid #21262d;
            margin-bottom: 12px;
        }
        .frame-index {
            color: #8b949e;
            min-width: 32px;
        }
        .frame-location {
            color: #58a6ff;
            margin-bottom: 6px;
        }
        .frame-file {
            color: #8b949e;
        }
        .frame-line {
            color: #d2a8ff;
            margin-left: 6px;
        }
    </style>
</head>
<body>
<header>
    <h1>{$title}</h1>
    <div class="meta">{$message}</div>
    <div class="meta">{$file}<span class="frame-line">:{$line}</span></div>
</header>
<main>
    <section class="stack">
        <h2>Stack Trace</h2>
        {$traceHtml}
    </section>
</main>
</body>
</html>
HTML;
    }
}

if (!function_exists('zero_request_expects_json')) {
    function zero_request_expects_json(): bool
    {
        if (class_exists('Zero\\Lib\\Http\\Request')) {
            try {
                return \Zero\Lib\Http\Request::instance()->expectsJson();
            } catch (\Throwable) {
            }
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (str_contains($accept, 'application/json') || str_contains($accept, 'text/json') || str_contains($accept, 'application/vnd.api+json')) {
            return true;
        }

        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }
}

if (!function_exists('zero_build_error_response')) {
    function zero_build_error_response(int $status, array $context = []): HttpResponse
    {
        $expectsJson = zero_request_expects_json();
        $message = $context['message'] ?? match ($status) {
            403 => 'You do not have permission to access this resource.',
            404 => 'The requested resource could not be found.',
            422 => 'The submitted data was invalid.',
            default => 'Something went wrong.',
        };

        if ($expectsJson) {
            $payload = [
                'status' => $status,
                'message' => $message,
            ];

            if (array_key_exists('errors', $context)) {
                $payload['errors'] = $context['errors'];
            }

            return HttpResponse::json($payload, $status);
        }

        $viewData = array_merge($context, [
            'status' => $status,
            'message' => $message,
        ]);

        $html = null;

        if (class_exists('Zero\\Lib\\View')) {
            $candidates = ['errors/' . $status];

            if ($status >= 400 && $status < 500) {
                $candidates[] = 'errors/4xx';
            } elseif ($status >= 500) {
                $candidates[] = 'errors/5xx';
            }

            foreach ($candidates as $viewName) {
                try {
                    $html = \Zero\Lib\View::render($viewName, $viewData);
                    if ($html !== null) {
                        break;
                    }
                } catch (\Throwable) {
                    $html = null;
                }
            }
        }

        if ($html === null) {
            $errorList = '';

            if (!empty($context['errors']) && is_array($context['errors'])) {
                $items = '';

                foreach ($context['errors'] as $field => $messages) {
                    if (!is_array($messages)) {
                        $messages = [$messages];
                    }

                    foreach ($messages as $msg) {
                        $safeField = htmlspecialchars((string) $field, ENT_QUOTES, 'UTF-8');
                        $safeMsg = htmlspecialchars((string) $msg, ENT_QUOTES, 'UTF-8');
                        $items .= "<li><strong>{$safeField}:</strong> {$safeMsg}</li>";
                    }
                }

                if ($items !== '') {
                    $errorList = "<ul class=\"errors\">{$items}</ul>";
                }
            }

            $title = htmlspecialchars((string) ($context['title'] ?? 'Error ' . $status), ENT_QUOTES, 'UTF-8');
            $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

            $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$title}</title>
        <style>
            :root { color-scheme: dark; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #0d1117; color: #c9d1d9; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .wrapper { max-width: 520px; padding: 40px; border-radius: 12px; background: #161b22; text-align: center; box-shadow: 0 24px 60px rgba(0,0,0,0.35); }
            h1 { margin: 0 0 12px; font-size: 2.5rem; color: #58a6ff; }
            p { margin: 0; line-height: 1.6; color: #8b949e; }
            ul.errors { text-align: left; margin: 24px 0 0; padding: 0 0 0 20px; }
            ul.errors li { margin-bottom: 8px; color: #f77669; }
        </style>
    </head>
    <body>
        <div class="wrapper">
            <h1>{$title}</h1>
            <p>{$safeMessage}</p>
            {$errorList}
        </div>
    </body>
</html>
HTML;
        }

        return HttpResponse::html($html, $status);
    }
}

if (!function_exists('zero_http_error_response')) {
    function zero_http_error_response(int $status, array $context = []): void
    {
        zero_build_error_response($status, $context)->send();
    }
}

$debug = filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL);

set_error_handler(static function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (\Throwable $e) use ($debug): void {
    if ($e instanceof ValidationException) {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);

            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    fwrite(STDERR, sprintf(" - %s: %s\n", $field, $message));
                }
            }

            return;
        }

        zero_http_error_response(422, [
            'title' => 'Unprocessable Entity',
            'message' => 'The submitted data was invalid.',
            'errors' => $e->errors(),
        ]);

        return;
    }

    Log::error($e->getMessage(), [
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    if ($debug && PHP_SAPI !== 'cli') {
        http_response_code(500);
        echo zero_render_exception_page($e);
        return;
    }

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->__toString() . PHP_EOL);
        return;
    }

    zero_http_error_response(500, [
        'title' => 'Server Error',
        'message' => 'We ran into an unexpected issue while processing your request.',
    ]);
});
