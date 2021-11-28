<?php

namespace Indoraptor\Example;

/* DEV: v2.2021.09.21
 * 
 * This is an example script!
 */

define('CODESAUR_DEVELOPMENT', true);

ini_set('display_errors', 'On');
error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);

use Indoraptor\IndoApplication;

$autoload = require_once '../vendor/autoload.php';
$autoload->addPsr4(__NAMESPACE__ . '\\', dirname(__FILE__));

$application = new IndoApplication(true);
$application->handle(new ExampleRequest());
