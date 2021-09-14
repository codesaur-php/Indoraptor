<?php

namespace Indoraptor;

use PDO;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PDOConnectMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $driver = getenv('INDO_DB_DRIVER', true) ?: 'mysql';
        $host = getenv('INDO_DB_HOST', true) ?: 'localhost';
        $username =  getenv('INDO_DB_USERNAME', true) ?: 'root';
        $passwd = getenv('INDO_DB_PASSWORD', true) ?: '';
        $charset = getenv('INDO_DB_CHARSET', true) ?: 'utf8';
        $options = array(
            PDO::ATTR_PERSISTENT => getenv('INDO_DB_PERSISTENT', true) == 'true',
            PDO::ATTR_ERRMODE => defined('CODESAUR_DEVELOPMENT') && CODESAUR_DEVELOPMENT ? PDO::ERRMODE_WARNING : PDO::ERRMODE_EXCEPTION
        );
        
        $dsn = "$driver:host=$host;charset=$charset";
        $pdo = new PDO($dsn, $username, $passwd, $options);

        $database = getenv('INDO_DB_NAME', true) ?: 'indoraptor';
        if ($request->getServerParams()['HTTP_HOST'] === 'localhost'
                && in_array($request->getServerParams()['REMOTE_ADDR'], array('127.0.0.1', '::1'))
        ) {
            $collation = getenv('INDO_DB_COLLATION', true) ?: 'utf8_unicode_ci';
            $pdo->exec("CREATE DATABASE IF NOT EXISTS $database COLLATE " . $pdo->quote($collation));
        }
        $pdo->exec("USE $database");
        
        if (getenv('INDO_TIME_ZONE_UTC', true)) {
            $pdo->exec('SET time_zone = ' . $pdo->quote(getenv('INDO_TIME_ZONE_UTC', true)));
        }
        
        return $handler->handle($request->withAttribute('pdo', $pdo));
    }
}
