<?php

use Zero\Lib\Http\Response;
use Zero\Lib\View;

if (!function_exists('response')) {
    /**
     * Create a response instance from arbitrary data.
     */
    function response(mixed $value = null, int $status = 200, array $headers = []): Response
    {
        if ($value instanceof Response) {
            if (! empty($headers)) {
                $value->withHeaders($headers);
            }

            if ($status !== $value->getStatus()) {
                $value->status($status);
            }

            return $value;
        }

        if ($value === null) {
            return Response::noContent($status === 200 ? 204 : $status, $headers);
        }

        if (is_array($value) || $value instanceof \JsonSerializable || $value instanceof \Traversable || is_object($value)) {
            return Response::json($value, $status, $headers);
        }

        if (is_bool($value)) {
            return Response::json($value, $status, $headers);
        }

        return Response::text((string) $value, $status, $headers);
    }
}

if (!function_exists('view')) {
    /**
     * Render a view into an HTML response.
     */
    function view(string $template, array $data = [], int $status = 200, array $headers = []): Response
    {
        $content = View::render($template, $data);

        return Response::html($content, $status, $headers);
    }
}
