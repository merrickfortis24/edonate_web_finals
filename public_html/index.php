<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$applicationRoot = is_dir(__DIR__.'/edonate_web')
    ? __DIR__.'/edonate_web'
    : __DIR__.'/../edonate_web';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $applicationRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $applicationRoot.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $applicationRoot.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
