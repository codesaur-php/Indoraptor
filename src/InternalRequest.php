<?php

namespace Indoraptor;

use codesaur\Http\Message\ServerRequest;
use codesaur\Http\Message\Uri;

class InternalRequest extends ServerRequest
{
    function __construct(string $method, string $pattern, $payload = array(), $token = null)
    {
        $this->serverParams['HTTP_HOST'] = $_SERVER['HTTP_HOST'];
        $this->serverParams['REQUEST_URI'] = $pattern;
        $this->serverParams['SCRIPT_NAME'] = '/index.php';
        $this->serverParams['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
        $this->serverParams['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];
        $this->serverParams['PHP_SELF'] = $_SERVER['PHP_SELF'];
        
        $this->method = $method;
        
        $this->uri = new Uri();
        $this->requestTarget = $pattern;
        if (($pos = strpos($pattern, '?')) !== false) {
            $this->serverParams['QUERY_STRING'] = substr($pattern, $pos + 1);
            $this->uri->setPath(substr($pattern, 0, $pos));
            $this->uri->setQuery($this->serverParams['QUERY_STRING']);
        } else {
            $this->uri->setPath($pattern);
        }
        
        $this->parsedBody = $payload;
        
        if (isset($token)) {
            $this->serverParams['HTTP_AUTHORIZATION'] = "Bearer $token";
        }
    }
}
