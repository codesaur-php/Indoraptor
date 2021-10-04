<?php

namespace Indoraptor\Example;

/* DEV: v2.2021.09.21
 * 
 * This is an example script!
 */

define('CODESAUR_DEVELOPMENT', true);

ini_set('display_errors', 'Off');
error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);

use Indoraptor\IndoApplication;
use Indoraptor\IndoExceptionHandler;
use Indoraptor\PDOConnectMiddleware;
use Indoraptor\JsonResponseMiddleware;

$autoload = require_once '../vendor/autoload.php';
$autoload->addPsr4(__NAMESPACE__ . '\\', dirname(__FILE__));

$application = new IndoApplication();
$application->use(new IndoExceptionHandler());
$application->use(new PDOConnectMiddleware());
$application->use(new JsonResponseMiddleware());
$application->handle(new ExampleRequest());
