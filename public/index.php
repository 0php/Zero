<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$_ENV['BASE_PATH'] = dirname(__DIR__);

require_once __DIR__ . '/../core/libraries/Storage/Helper.php';
require_once __DIR__ . '/../core/libraries/Config/Env.php';
require_once __DIR__ . '/../core/libraries/Config/Helper.php';

loadEnvFiles();

require_once __DIR__ . '/../core/bootstrap/autoload.php';
require_once __DIR__ . '/../core/bootstrap/errors.php';

$kernel = $kernel ?? require __DIR__ . '/../core/kernel.php';
$helpers = $kernel['helpers'];

foreach ($helpers as $helper) {
    $enabled = $helper['enabled']['http'] ?? ($helper['http'] ?? true);

    if (! $enabled) {
        continue;
    }

    require_once $helper['path'];
}

require_once __DIR__ . '/../core/bootstrap/session.php';
require_once __DIR__ . '/../core/bootstrap/rate_limit.php';
require_once __DIR__ . '/../core/bootstrap.php';
