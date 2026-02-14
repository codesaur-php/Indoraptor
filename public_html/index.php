<?php
/**
 * ============================================================================
 *  Indoraptor Framework - Entry Point / Bootstrap
 * ============================================================================
 *
 *  Энэ файл нь Indoraptor төслийн бүх HTTP хүсэлтийг хүлээн авч,
 *  тохирох Application (Web эсвэл Dashboard)-д дамжуулан боловсруулах
 *  гол эхлэл цэг (bootstrap) юм.
 *
 *  Indoraptor нь codesaur ecosystem-ийн core package бөгөөд бусад codesaur
 *  packages-тэй хамтран ажилладаг framework юм.
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
 *  8. PSR-15 стандартын дагуу request-г handle хийх
 *
 *  @package    codesaur/Indoraptor
 *  @author     Narankhuu <codesaur@gmail.com>
 *  @copyright  Copyright (c) 2012-present codesaur (Narankhuu)
 *  @license    MIT
 *  @since      1.0.0
 *
 *  ⚠️  СЕРВЕРИЙН ТОХИРГООНЫ ТАЙЛБАР:
 *  -----------------------------------
 *  Энэ index.php файл нь Apache серверийн .htaccess тохиргоотой
 *  зөв ажиллаж байна. Гэхдээ Apache сервер биш, nginx сервертэй 
 *  тохиолдолд .nginx.conf.example жишээ тохиргооноос харж өөрийн 
 *  nginx тохиргоог зөв хийж Indoraptor-г ажиллуулах хэрэгтэй!
 *
 *  @see docs/conf.example/.nginx.conf.example nginx серверийн жишээ тохиргоо
 *  @see docs/conf.example/.htaccess.example Apache серверийн жишээ тохиргоо
 */

use codesaur\Http\Message\ServerRequest;

/**
 * Бүх алдааны төрлийг идэвхтэй болгох
 * Development болон Production аль ч горимд алдааг лог хийх зорилгоор
 */
\error_reporting(\E_ALL);

// ---------------------------------------------------------------------------
// 1. Root болон autoload файлуудыг шалгаж ачаалах
// ---------------------------------------------------------------------------
/** @var string $root_dir Төслийн root директорийн зам */
$root_dir = \dirname(__DIR__);

/** @var string $autoload Composer autoload файлын бүтэн зам */
$autoload = "$root_dir/vendor/autoload.php";

if (!\file_exists($autoload)) {
    die("codesaur exit: <strong>$autoload is missing!</strong>");
}

/** @var \Composer\Autoload\ClassLoader $composer Composer autoload instance */
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
    /** @var \Dotenv\Dotenv $dotenv Dotenv instance для .env файл унших */
    $dotenv = \Dotenv\Dotenv::createImmutable($root_dir);
    $dotenv->load();

    /**
     * .env файлаас уншсан boolean утгыг string-ээс бодит boolean болгох
     * (Dotenv нь бүх утгыг string хэлбэрээр авдаг тул)
     */
    foreach ($_ENV as &$env) {
        if ($env == 'true') {
            $env = true;
        } elseif ($env == 'false') {
            $env = false;
        }
    }
    unset($env); // Reference-г цэвэрлэх
} catch (\Throwable $e) {
    die("codesaur exit: <strong>{$e->getMessage()}</strong>");
}

// ---------------------------------------------------------------------------
// 4. Хөгжүүлэлтийн горим тодорхойлох
// ---------------------------------------------------------------------------
/**
 * CODESAUR_DEVELOPMENT тогтмол
 * 
 * @var bool CODESAUR_DEVELOPMENT Хөгжүүлэлтийн горим эсэхийг тодорхойлдог.
 *                                  true бол development, false бол production.
 *                                  CODESAUR_APP_ENV != 'production' үед true байна.
 */
\define(
    'CODESAUR_DEVELOPMENT',
    isset($_ENV['CODESAUR_APP_ENV'])
        ? $_ENV['CODESAUR_APP_ENV'] != 'production'
        : false
);

/**
 * Development үед error-г дэлгэцэн дээр харуулах
 * Production үед зөвхөн лог файлд бичнэ
 */
\ini_set('display_errors', CODESAUR_DEVELOPMENT ? 'On' : 'Off');

// ---------------------------------------------------------------------------
// 5. Error handler - бүх алдааг лог руу бичээд үргэлжлүүлэх
// ---------------------------------------------------------------------------
/**
 * Custom error handler function
 * 
 * Бүх PHP алдааг барьж авч, лог файлд бичнэ.
 * Default PHP error handler-д дамжуулахгүй (return true).
 * 
 * @param int    $errno   Алдааны код (E_ERROR, E_WARNING, гэх мэт)
 * @param string $errstr  Алдааны мессеж
 * @param string $errfile Алдаа гарсан файлын зам
 * @param int    $errline Алдаа гарсан мөрийн дугаар
 * 
 * @return bool true - алдааг барьж авсан, default handler-д дамжуулахгүй
 */
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
/**
 * CODESAUR_APP_TIME_ZONE утга байвал PHP-ийн цагийн бүсийг тохируулах
 * 
 * Жишээ: 'Asia/Ulaanbaatar', 'UTC', 'America/New_York' гэх мэт
 * Байхгүй бол PHP-ийн default timezone ашиглана
 */
if (!empty($_ENV['CODESAUR_APP_TIME_ZONE'])) {
    \date_default_timezone_set($_ENV['CODESAUR_APP_TIME_ZONE']);
}

// ---------------------------------------------------------------------------
// 7. PSR-7 дагуу ServerRequest-г глобал орчноос үүсгэх
// ---------------------------------------------------------------------------
/**
 * PSR-7 стандартын дагуу ServerRequest объект үүсгэх
 * 
 * @var \codesaur\Http\Message\ServerRequest $request 
 *      Глобал PHP орчноос ($_SERVER, $_GET, $_POST, гэх мэт) үүссэн
 *      PSR-7 ServerRequest object
 */
$request = (new ServerRequest())->initFromGlobal();

/**
 * URL path-г цэвэрлэх (subdirectory дээр ажиллуулахад ашиглагдана)
 * 
 * Жишээ: Хэрэв скрипт /subdir/public_html/index.php байвал,
 *        /subdir/public_html хэсгийг path-аас хасна.
 * 
 * @var string $path Цэвэрлэгдсэн URL path (leading slash-тай)
 */
$path = \rawurldecode($request->getUri()->getPath());
if (($lngth = \strlen(\dirname($request->getServerParams()['SCRIPT_NAME']))) > 1) {
    $path = \substr($path, $lngth);
    $path = '/' . \ltrim($path, '/');
}

// ---------------------------------------------------------------------------
// 8. Өгөгдсөн path-аас хамааран Application сонгох
// ---------------------------------------------------------------------------
/**
 * URL path-аас хамааран Application instance үүсгэх
 * 
 * Routing логик:
 *   - /dashboard/... → Dashboard\Application (Admin panel)
 *   - Бусад бүх зам → Web\Application (Public website)
 * 
 * @var \Dashboard\Application|\Web\Application $application 
 *      Path-аас хамааран сонгогдсон Application instance
 */
if ((\explode('/', $path)[1] ?? '') == 'dashboard') {
    $application = new \Dashboard\Application();
} else {
    $application = new \Web\Application();
}

// ---------------------------------------------------------------------------
// 9. Сонгогдсон Application-д PSR-15 handler-ээр request-г дамжуулах
// ---------------------------------------------------------------------------
/**
 * PSR-15 стандартын дагуу request-г handle хийх
 * 
 * Application::handle() нь PSR-15 RequestHandlerInterface-ийн 
 * handle() method-ийг дуудна. Энэ нь:
 *   - Middleware chain-г дамжуулах
 *   - Route matching хийх
 *   - Controller-г ажиллуулах
 *   - Response буцаах
 * 
 * @see \Psr\Http\Server\RequestHandlerInterface::handle()
 */
$application->handle($request);
