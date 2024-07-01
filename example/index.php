<?php

namespace Indoraptor\Example;

/* DEV: v2.2021.09.21
 * 
 * This is an example script!
 */

\define('CODESAUR_DEVELOPMENT', true);

\ini_set('display_errors', 'On');
\error_reporting(\E_ALL);

use Firebase\JWT\JWT;

use codesaur\Http\Message\ServerRequest;

use Indoraptor\IndoApplication;
use Indoraptor\JsonExceptionHandler;
use Indoraptor\JsonResponseMiddleware;

$autoload = require_once '../vendor/autoload.php';
$autoload->addPsr4(__NAMESPACE__ . '\\', \dirname(__FILE__));

class MockRequest extends ServerRequest
{
    function __construct()
    {
        $this->initFromGlobal();
        
        if (!\in_array($this->serverParams['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
            throw new \Error('This experimental example only works on local development enviroment');
        }

        if (empty($this->serverParams['HTTP_AUTHORIZATION'])) {
            // For a testing purpose we authorizing into Indoraptor
            $issuedAt = \time();
            $lifeSeconds = 300;
            $expirationTime = $issuedAt + $lifeSeconds;
            $payload = [
                'iat' => $issuedAt,
                'exp' => $expirationTime,
                'seconds' => $lifeSeconds,
                'account_id' => 1
            ];
            $key = 'codesaur-indoraptor-not-so-secret';
            $jwt = JWT::encode($payload, $key, 'HS256');
            $this->serverParams['HTTP_AUTHORIZATION'] = "Bearer $jwt";
        }
    }
}

$application = new IndoApplication();
$application->use(new JsonExceptionHandler());
$application->use(new JsonResponseMiddleware());
$application->handle(new MockRequest());
