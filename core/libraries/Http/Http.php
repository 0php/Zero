<?php
namespace Zero\Lib;

use Zero\Lib\Session;


class Http
{
    private static $defaultHeaders = [];

    private static function getBaseHeaders()
    {
        if (Session::has('token')) {
            self::$defaultHeaders = [
                'Authorization: Bearer ' . Session::get('token')
            ];
        }

        return self::$defaultHeaders;
    }

    public static function get($url, $query = [], $headers = [])
    {
        $url = $_ENV['CONFIG']['API_HOST'] . $url;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return self::request('GET', $url, null, $headers);
    }

    public static function patch($url, $data = [], $headers = [])
    {
        return self::request('PATCH', $_ENV['CONFIG']['API_HOST'] . $url, $data, $headers);
    }

    public static function post($url, $data = [], $headers = [])
    {
        return self::request('POST', $_ENV['CONFIG']['API_HOST'] . $url, $data, $headers);
    }

    public static function put($url, $data = [], $headers = [])
    {
        return self::request('PUT', $_ENV['CONFIG']['API_HOST'] . $url, $data, $headers);
    }

    public static function delete($url, $data = [], $headers = [])
    {
        return self::request('DELETE', $_ENV['CONFIG']['API_HOST'] . $url, $data, $headers);
    }

    private static function request($method, $url, $data = null, $additionalHeaders = [])
    {
        $ch = curl_init();
        
        // Prepare headers
        $headers = array_merge(self::getBaseHeaders(), array_map(function($key, $value) {
            return "$key: $value";
        }, array_keys($additionalHeaders), $additionalHeaders));
        
        // Add content type for requests with body
        if ($data !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        // Set cURL options
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        // Add request body if needed
        if ($data !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($ch, $options);

        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        curl_close($ch);

        // Handle errors
        if ($errno) {
            return self::formatErrorResponse($errno, $error);
        }

        return self::formatResponse($response, $httpCode);
    }

    private static function formatResponse($response, $statusCode)
    {
        return (object)[
            'ok' => $statusCode >= 200 && $statusCode < 300,
            'status' => $statusCode,
            'headers' => [], // Note: Headers parsing removed for simplicity
            'body' => json_decode($response, true),
        ];
    }

    private static function formatErrorResponse($errno, $error)
    {
        return (object)[
            'ok' => false,
            'status' => 500,
            'headers' => [],
            'body' => null,
            'error' => "cURL error ($errno): $error",
        ];
    }
}