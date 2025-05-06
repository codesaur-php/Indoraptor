<?php

namespace Raptor;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PostgresConnectMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $host = $_ENV['INDO_DB_HOST'] ?? 'localhost';
        $username = $_ENV['INDO_DB_USERNAME'] ?? 'postgres';
        $database = $_ENV['INDO_DB_NAME'] ?? 'indoraptor';
        $password = $_ENV['INDO_DB_PASSWORD']
            ?? throw new \Exception('INDO_DB_PASSWORD is not set. See the .env!');
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_PERSISTENT =>  $_ENV['INDO_DB_PERSISTENT'] ?? false
        ];
        $pdo = new \PDO("pgsql:host=$host;dbname=$database", $username, $password, $options);
        if (!empty($_ENV['INDO_TIME_ZONE_UTC'])) {
            $pdo->exec('SET TIME ZONE ' . $pdo->quote($_ENV['INDO_TIME_ZONE_UTC']));
        }
        
        return $handler->handle($request->withAttribute('pdo', $pdo));
    }
}
