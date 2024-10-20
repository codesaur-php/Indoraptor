<?php

namespace Web;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SessionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        \session_name('indoraptor');
        
        if (\session_status() != \PHP_SESSION_ACTIVE) {
            $lifetime = \time() + 30 * 24 * 60 * 60;
            \session_set_cookie_params($lifetime);
            \session_start();
        }
        
        if (\session_status() == \PHP_SESSION_ACTIVE) {
            $path = \rawurldecode($request->getUri()->getPath());
            if (($lngth = \strlen(\dirname($request->getServerParams()['SCRIPT_NAME']))) > 1) {
                $path = \substr($path, $lngth);
                $path = '/' . \ltrim($path, '/');
            }
            if (!\str_starts_with($path, '/language/')) {
                // Only language route needs write access on $_SESSION,
                // otherwise we better write_close the session as soon as possible
                \session_write_close();
            }
        }
        
        return $handler->handle($request);
    }
}
