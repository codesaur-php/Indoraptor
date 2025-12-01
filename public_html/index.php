<?php
/**
 * ============================================================================
 *  Indoraptor Framework – Entry Point
 * ============================================================================
 *
 *  Энэ файл нь Indoraptor төслийн бүх HTTP хүсэлтийг хүлээн авч,
 *  тохирох Application (Web эсвэл Dashboard)-д дамжуулан боловсруулах
 *  гол эхлэл цэг юм.
 *
 *  Үндсэн үүргүүд:
 *  ----------------
 *  1. Composer autoload-г ачаалах
 *  2. .env тохиргоог унших (Dotenv)
 *  3. Хөгжүүлэлтийн / Production горим тодорхойлох
 *  4. Алдааны лог тохируулах
 *  5. Цагийн бүс тохируулах (ENV дээр заасан бол)
 *  6. PSR-7 стандартын дагуу ServerRequest үүсгэх
 *  7. URL замаас хамааран Web эсвэл Dashboard Application-г ажиллуулах
 *
 *  @package   codesaur/Indoraptor
 *  @author    Narankhuu <codesaur@gmail.com>
 *  @license   MIT
 */

use codesaur\Http\Message\ServerRequest;

// Бүх алдааг идэвхтэй болгоно
\error_reporting(\E_ALL);

// ---------------------------------------------------------------------------
// 1. Root болон autoload файлуудыг шалгаж ачаалах
// ---------------------------------------------------------------------------
$root_dir = \dirname(__DIR__);
$autoload = "$root_dir/vendor/autoload.php";

if (!\file_exists($autoload)) {
    die("codesaur exit: <strong>$autoload is missing!</strong>");
}

$composer = require($autoload);

// ---------------------------------------------------------------------------
// 2. Error log байрлал тохируулах
// ---------------------------------------------------------------------------
\ini_set('log_errors', 'On');
\ini_set('error_log', "$root_dir/logs/code.log");

// ---------------------------------------------------------------------------
// 3. .env татаж орчны хувьсагчдыг ачаалах
// ---------------------------------------------------------------------------
try {
    $dotenv = \Dotenv\Dotenv::createImmutable($root_dir);
    $dotenv->load();

    // boolean утгыг string биш бодит boolean болгох
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

// ---------------------------------------------------------------------------
// 4. Хөгжүүлэлтийн горим тодорхойлох
// ---------------------------------------------------------------------------
\define(
    'CODESAUR_DEVELOPMENT',
    isset($_ENV['CODESAUR_APP_ENV'])
        ? $_ENV['CODESAUR_APP_ENV'] != 'production'
        : false
);

// Development үед error-г дэлгэцэн дээр харуулах
\ini_set('display_errors', CODESAUR_DEVELOPMENT ? 'On' : 'Off');

// ---------------------------------------------------------------------------
// 5. Error handler - бүх алдааг лог руу бичээд үргэлжлүүлэх
// ---------------------------------------------------------------------------
\set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    switch ($errno) {
        case \E_USER_ERROR:   $error = 'Fatal error'; break;
        case \E_USER_WARNING: $error = 'Warning';     break;
        case \E_USER_NOTICE:  $error = 'Notice';      break;
        default:              $error = 'Unknown error'; break;
    }
    \error_log("$error #$errno: $errstr in $errfile on line $errline");
    return true; // Default PHP handler руу дамжуулахгүй
});

// ---------------------------------------------------------------------------
// 6. Цагийн бүс тохируулах (ENV дээрээс)
// ---------------------------------------------------------------------------
if (!empty($_ENV['CODESAUR_APP_TIME_ZONE'])) {
    \date_default_timezone_set($_ENV['CODESAUR_APP_TIME_ZONE']);
}

// ---------------------------------------------------------------------------
// 7. PSR-7 дагуу ServerRequest-г глобал орчноос үүсгэх
// ---------------------------------------------------------------------------
$request = (new ServerRequest())->initFromGlobal();

// URL path-г цэвэрлэх (subdirectory дээр ажиллуулахад ашиглагдана)
$path = \rawurldecode($request->getUri()->getPath());
if (($lngth = \strlen(\dirname($request->getServerParams()['SCRIPT_NAME']))) > 1) {
    $path = \substr($path, $lngth);
    $path = '/' . \ltrim($path, '/');
}

// ---------------------------------------------------------------------------
// 8. Өгөгдсөн path-аас хамааран Application сонгох
// ---------------------------------------------------------------------------
//
//  /dashboard/... → Dashboard\Application
//  бусад бүх зам → Web\Application
//
if ((\explode('/', $path)[1] ?? '') == 'dashboard') {
    $application = new \Dashboard\Application();
} else {
    $application = new \Web\Application();
}

// ---------------------------------------------------------------------------
// 9. Сонгогдсон Application-д PSR-15 handler-ээр request-г дамжуулах
// ---------------------------------------------------------------------------
$application->handle($request);
