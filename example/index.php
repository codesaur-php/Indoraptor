<?php

namespace Indoraptor\Example;

/* DEV: v1.2021.03.15
 * 
 * This is an example script!
 */

use Error;

use Firebase\JWT\JWT;

use codesaur\Http\Message\ServerRequest;

use Indoraptor\IndoApplication;
use Indoraptor\IndoExceptionHandler;

$autoload = require_once '../vendor/autoload.php';

define('CODESAUR_DEVELOPMENT', true);

ini_set('display_errors', 'On');
error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);
set_exception_handler(array(new IndoExceptionHandler(), 'exception'));

$request = new ServerRequest();
$request->initFromGlobal();

function isValidIP(string $ip): bool
{
    $real = ip2long($ip);
    if (empty($ip) || $real === -1 || $real === false) {
        return false;
    }

    $private_ips = array(
        ['0.0.0.0', '2.255.255.255'],
        ['10.0.0.0', '10.255.255.255'],
        ['127.0.0.0', '127.255.255.255'],
        ['169.254.0.0', '169.254.255.255'],
        ['172.16.0.0', '172.31.255.255'],
        ['192.0.2.0', '192.0.2.255'],
        ['192.168.0.0', '192.168.255.255'],
        ['255.255.255.0', '255.255.255.255']);
    foreach ($private_ips as $r) {
        $min = ip2long($r[0]); $max = ip2long($r[1]);
        if ($real >= $min && $real <= $max) {
            return false;
        }
    }

    return true;
}

function getRemoteAddr(array $serverParams): string
{
    if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
        if (!empty($serverParams['HTTP_CLIENT_IP'])
                && $this->isValidIP($serverParams['HTTP_CLIENT_IP'])) {
            return $serverParams['HTTP_CLIENT_IP'];
        }            
        foreach (explode(',', $serverParams['HTTP_X_FORWARDED_FOR']) as $ip) {
            if ($this->isValidIP(trim($ip))) {
                return $ip;
            }
        }
    }

    if (!empty($serverParams['HTTP_X_FORWARDED'])
            && $this->isValidIP($serverParams['HTTP_X_FORWARDED'])) {
        return $serverParams['HTTP_X_FORWARDED'];
    } elseif (!empty($serverParams['HTTP_X_CLUSTER_CLIENT_IP'])
            && $this->isValidIP($serverParams['HTTP_X_CLUSTER_CLIENT_IP'])) {
        return $serverParams['HTTP_X_CLUSTER_CLIENT_IP'];
    } elseif (!empty($serverParams['HTTP_FORWARDED_FOR'])
            && $this->isValidIP($serverParams['HTTP_FORWARDED_FOR'])) {
        return $serverParams['HTTP_FORWARDED_FOR'];
    } elseif (!empty($serverParams['HTTP_FORWARDED'])
            && $this->isValidIP($serverParams['HTTP_FORWARDED'])) {
        return $serverParams['HTTP_FORWARDED'];
    }
    
    return $serverParams['REMOTE_ADDR'] ?? '';
}

if ($request->getServerParams()['HTTP_HOST'] != 'localhost'
        || !in_array(getRemoteAddr($request->getServerParams()), array('127.0.0.1', '::1'))
) {
    throw new Error('This experimental example only works on local development enviroment');
}

if (empty($request->getServerParams()['HTTP_JWT'])) {
    // For testing purpose we authorizing into Indoraptor
    $issuedAt = time();
    $expirationTime = $issuedAt + 300;
    $payload = array(
        'iat' => $issuedAt,
        'exp' => $expirationTime,
        'account_id' => 1,
        'organization_id' => 1
    );
    $key = 'codesaur-indoraptor-not-so-secret';    
    $jwt = JWT::encode($payload, $key);
    $request = $request->withHeader('INDO_JWT', $jwt);
}

(new IndoApplication())->handle($request);
