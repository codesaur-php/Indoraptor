<?php

namespace Indoraptor;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Fig\Http\Message\StatusCodeInterface;

class JsonResponseMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        $response = $handler->handle($request);
        
        $status = $response->getStatusCode();
        if ($status != StatusCodeInterface::STATUS_OK) {
            http_response_code($status);
        }
        
        return $response;
    }
}
