<?php
/**
 * @var array<string, class-string>
 */


$aliases = [
    'View' => Zero\Lib\View::class,
    'DB' => Zero\Lib\Database::class,
    'Model' => Zero\Lib\Model::class,
    'DBML' => Zero\Lib\DB\DBML::class,
    'Auth' => Zero\Lib\Auth\Auth::class,
    'Mail' => Zero\Lib\Mail\Mailer::class,
    'Log' => Zero\Lib\Log::class,
    'Str' => Zero\Lib\Support\Str::class,
    'Validator' => Zero\Lib\Validation\Validator::class,
];

$helpers =  [
    [
        'path' => lib_path('Console/Helper.php'),
        'cli' => true,
        'http' => true,
        'enabled' => [
            'console' => true,
            'http' => true,
        ],
    ], [
        'path' => lib_path('Config/Env.php'),
        'cli' => true,
        'http' => true,
        'enabled' => [
            'console' => true,
            'http' => true,
        ],
    ], [
        'path' => lib_path('Support/Helper.php'),
        'cli' => true,
        'http' => true,
        'enabled' => [
            'console' => true,
            'http' => true,
        ],
    ]
];

return [
    'aliases' => $aliases,
    'helpers' => $helpers,
];
