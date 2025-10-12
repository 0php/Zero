<?php

declare(strict_types=1);

use Zero\Lib\Router;
use App\Middlewares\ValidateStorageSignature;
use Zero\Lib\Storage\Controllers\PrivateFileController;

Router::get('/files/private/{path:.+}', [PrivateFileController::class, '__invoke'])
    ->name('storage.private')
    ->middleware(ValidateStorageSignature::class);
