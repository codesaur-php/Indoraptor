<?php

namespace Indoraptor;

use codesaur\Http\Message\ServerRequest;
use codesaur\Http\Message\Uri;

class InternalRequest extends ServerRequest
{
    function __construct(string $method, string $pattern, $payload = array(), $token = null)
    {
        $this->serverParams['SCRIPT_NAME'] = '/index.php';
        $this->serverParams['HTTP_HOST'] = $_SERVER['HTTP_HOST'];
        $this->serverParams['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
        
        $this->method = $method;
        
        $this->uri = new Uri();        
        if (($pos = strpos($pattern, '?')) !== false) {
            $this->uri->setPath(substr($pattern, 0, $pos));
            $this->uri->setQuery(substr($pattern, $pos + 1));
        } else {
            $this->uri->setPath($pattern);
        }
        
        $this->parsedBody = $payload;
        
        if (isset($token)) {
            $this->serverParams['HTTP_JWT'] = $token;
        }
    }
}
