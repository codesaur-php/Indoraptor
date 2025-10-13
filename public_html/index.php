<?php

use codesaur\Http\Message\ServerRequest;

\error_reporting(\E_ALL);

$root_dir = \dirname(__FILE__) . '/..';
$autoload = "$root_dir/vendor/autoload.php";
if (!\file_exists($autoload)) {
    die("codesaur exit: <strong>$autoload is missing!</strong>");
}
$composer = require($autoload);

\ini_set('log_errors', 'On');
\ini_set('error_log', "$root_dir/logs/code.log");

try {
    $dotenv = \Dotenv\Dotenv::createImmutable($root_dir);
    $dotenv->load();
    foreach ($_ENV as &$env) {
        if ($env == 'true') {
            $env = true;
        } elseif ($env == 'false') {
            $env = false;
        }
    }
} catch (\Throwable $e) {
    die("codesaur exit: <strong>{$e->getMessage()}</strong>");
}

\define('CODESAUR_DEVELOPMENT', isset($_ENV['CODESAUR_APP_ENV']) ? $_ENV['CODESAUR_APP_ENV'] != 'production' : false);
\ini_set('display_errors', CODESAUR_DEVELOPMENT ? 'On' : 'Off');
\set_error_handler(function($errno, $errstr, $errfile, $errline)
{
    switch ($errno) {
        case \E_USER_ERROR: $error = 'Fatal error'; break;
        case \E_USER_WARNING: $error = 'Warning'; break;
        case \E_USER_NOTICE: $error = 'Notice'; break;
        default: $error = 'Unknown error'; break;
    }
    \error_log("$error #$errno: $errstr in $errfile on line $errline");
    return true;
});

if (!empty($_ENV['CODESAUR_APP_TIME_ZONE'])) {
    \date_default_timezone_set($_ENV['CODESAUR_APP_TIME_ZONE']);
}

$request = (new ServerRequest())->initFromGlobal();
$path = \rawurldecode($request->getUri()->getPath());
if (($lngth = \strlen(\dirname($request->getServerParams()['SCRIPT_NAME']))) > 1) {
    $path = \substr($path, $lngth);
    $path = '/' . \ltrim($path, '/');
}
if ((\explode('/', $path)[1] ?? '') == 'dashboard') {
    $application = new \Dashboard\Application();
} else {
    $application = new \Web\Application();
}
$application->handle($request);
