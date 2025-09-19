<?php

/**
 * Debug and die function similar to Laravel's dd()
 * Dumps variables with syntax highlighting using highlight.js
 * 
 * @param mixed ...$args Variables to dump
 * @return void
 */

if(!function_exists('dd')) {
    function dd(...$args) {
        ob_clean();
        echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Debug Output</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css" rel="stylesheet">
        <style>
            body {
                background: #282c34;
                color: #abb2bf;
                font-family: monospace;
                padding: 20px;
                margin: 0;
                line-height: 1.5;
            }
            .debug-item {
                background: #21252b;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            .debug-item h3 {
                margin-top: 0;
                color: #e06c75;
                font-size: 1.2em;
            }
            .type-info {
                color: #61afef;
                margin-bottom: 10px;
                font-size: 0.9em;
            }
            pre {
                margin: 0;
                background: #282c34 !important;
                border-radius: 4px;
                padding: 15px;
            }
            code {
                font-family: "Fira Code", monospace;
                font-size: 14px;
            }
            .stack-trace {
                margin-top: 20px;
                padding: 15px;
                background: #21252b;
                border-radius: 8px;
                margin-bottom: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            .stack-trace h3 {
                color: #e06c75;
                margin-top: 0;
                font-size: 1.2em;
            }
            .file-info {
                color: #98c379;
            }
            .line-info {
                color: #c678dd;
            }
        </style>
    </head>
    <body>';

        $backtrace = debug_backtrace();
        $file = $backtrace[0]['file'];
        $line = $backtrace[0]['line'];

        echo "<div class='stack-trace'>
                <h3>Debug Called From:</h3>
                <div class='file-info'>File: {$file}</div>
                <div class='line-info'>Line: {$line}</div>
            </div>";

        foreach ($args as $index => $arg) {
            echo "<div class='debug-item'>";
            echo "<h3>Variable #" . ($index + 1) . "</h3>";
            echo "<div class='type-info'>Type: " . gettype($arg) . "</div>";
            
            // Format the output based on type
            if (is_array($arg) || is_object($arg)) {
                $output = var_export($arg, true);
            } elseif (is_string($arg)) {
                $output = "'" . addslashes($arg) . "'";
            } elseif (is_bool($arg)) {
                $output = $arg ? 'true' : 'false';
            } elseif (is_null($arg)) {
                $output = 'null';
            } else {
                $output = (string)$arg;
            }
            
            // Escape HTML special characters
            $output = htmlspecialchars($output);
            
            echo "<pre><code class='language-php'>";
            echo $output;
            echo "</code></pre>";
            echo "</div>";
        }

        echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/php.min.js"></script>
            <script>
                document.addEventListener("DOMContentLoaded", () => {
                    document.querySelectorAll("pre code").forEach(block => {
                        hljs.highlightElement(block);
                    });
                });
            </script>
            </body></html>';
        die();
    }
}