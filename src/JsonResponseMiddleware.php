<?php

namespace Indoraptor;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Fig\Http\Message\StatusCodeInterface;

use codesaur\Http\Message\ReasonPrhase;

class JsonResponseMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!\headers_sent()) {
            \header('Content-Type: application/json');
        }

        $response = $handler->handle($request);

        $code = $response->getStatusCode();
        if ($code != StatusCodeInterface::STATUS_OK) {
            $status_code = "STATUS_$code";
            $reasonPhraseClass = ReasonPrhase::class;
            if (\defined("$reasonPhraseClass::$status_code")) {
                \http_response_code($code);
            }
        }
        
        return $response;
    }
}
