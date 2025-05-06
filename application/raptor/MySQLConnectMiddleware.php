<?php

namespace Raptor;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MySQLConnectMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $host = $_ENV['INDO_DB_HOST'] ?? 'localhost';
        $username = $_ENV['INDO_DB_USERNAME'] ?? 'root';
        $password = $_ENV['INDO_DB_PASSWORD'] ?? '';
        $charset = $_ENV['INDO_DB_CHARSET'] ?? 'utf8';
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_PERSISTENT => $_ENV['INDO_DB_PERSISTENT'] ?? false
        ];
        
        $dsn = "mysql:host=$host;charset=$charset";
        $pdo = new \PDO($dsn, $username, $password, $options);
        $database = $_ENV['INDO_DB_NAME'] ?? 'indoraptor';
        if (\in_array($request->getServerParams()['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
            $collation = $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci';
            $pdo->exec("CREATE DATABASE IF NOT EXISTS $database COLLATE " . $pdo->quote($collation));
        }
        $pdo->exec("USE $database");
        
        if (!empty($_ENV['INDO_TIME_ZONE_UTC'])) {
            $pdo->exec('SET time_zone = ' . $pdo->quote($_ENV['INDO_TIME_ZONE_UTC']));
        }
        
        return $handler->handle($request->withAttribute('pdo', $pdo));
    }
}
