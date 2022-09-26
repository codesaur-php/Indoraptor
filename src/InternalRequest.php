<?php

namespace Indoraptor;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

use codesaur\Http\Message\Uri;

class InternalRequest implements ServerRequestInterface
{
    protected $protocolVersion = '1.1';
    protected $headers = array();
    protected $body;

    protected $method;
    protected $uri;
    protected $requestTarget;

    protected $serverParams = array();
    protected $cookies = array();
    protected $attributes = array();

    protected $queryParams;
    protected $parsedBody;
    protected $uploadedFiles = array();
    
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
            $query = substr($pattern, $pos + 1);
            $this->serverParams['QUERY_STRING'] = $query;
            $this->uri->setPath(substr($pattern, 0, $pos));
            $this->uri->setQuery($query);
            parse_str($query, $this->queryParams);
        } else {
            $this->uri->setPath($pattern);
        }
        
        $this->parsedBody = $payload;
        
        if (isset($token)) {
            $this->serverParams['HTTP_AUTHORIZATION'] = "Bearer $token";
        }
    }

    public function getAttribute($name, $default = null)
    {
        if (!isset($this->attributes[$name])) {
            return $default;
        }
        
        return $this->attributes[$name];
    }

    public function getAttributes(): array
    {
        return $this->attributes ?? array();
    }

    public function getBody(): ?StreamInterface
    {
        return $this->body;
    }

    public function getCookieParams(): array
    {
        return $this->cookies;
    }

    public function getHeader($name): array
    {
        return $this->headers[strtoupper($name)] ?? array();
    }

    public function getHeaderLine($name): string
    {
        $values = $this->getHeader($name);        
        return implode(',', $values);
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getMethod(): string
    {
        return $this->method ?? '';
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function getQueryParams(): array
    {
        if (is_array($this->queryParams)) {
            return $this->queryParams;
        }

        if (!$this->getUri() instanceof UriInterface) {
            return array();
        }
        
        $query = rawurldecode($this->getUri()->getQuery());
        parse_str($query, $this->queryParams);
        return $this->queryParams;
    }

    public function getRequestTarget(): string
    {
        if (!empty($this->requestTarget)) {
            return $this->requestTarget;
        } elseif (!$this->getUri() instanceof UriInterface) {
            return '/';
        }

        $path = rawurldecode($this->getUri()->getPath());
        $requestTarget = '/' . ltrim($path, '/');
        
        $query = $this->getUri()->getQuery();
        if ($query !== '') {
            $requestTarget .= '?' . rawurldecode($query);
        }
        
        $fragment = $this->getUri()->getFragment();
        if ($fragment !== '') {
            $requestTarget .= '#' . rawurldecode($fragment);
        }

        return $requestTarget;
    }

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function hasHeader($name): bool
    {
        return isset($this->headers[strtoupper($name)]);
    }

    public function withAddedHeader($name, $value)
    {
        $clone = clone $this;
        if ($this->hasHeader($name)) {
            $this->headers[strtoupper($name)][] = $value;
        } else {
            $this->setHeader($name, $value);
        }
        
        return $clone;
    }

    public function withAttribute($name, $value)
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    public function withBody(StreamInterface $body)
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    public function withCookieParams(array $cookies)
    {
        $clone = clone $this;
        $clone->cookies = $cookies;
        return $clone;
    }

    public function withHeader($name, $value)
    {
        $clone = clone $this;
        $clone->setHeader($name, $value);
        return $clone;
    }

    public function withMethod($method)
    {
        $clone = clone $this;
        $clone->method = strtoupper($method);
        return $clone;
    }

    public function withParsedBody($data)
    {
        $clone = clone $this;
        $clone->parsedBody = $data;
        return $clone;
    }

    public function withProtocolVersion($version)
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;
        return $clone;
    }

    public function withQueryParams(array $query)
    {
        $clone = clone $this;
        $clone->queryParams = $query;
        return $clone;
    }

    public function withRequestTarget($requestTarget)
    {
        $clone = clone $this;
        $clone->requestTarget = $requestTarget;
        return $clone;
    }

    public function withUploadedFiles(array $uploadedFiles)
    {
        $clone = clone $this;
        $clone->uploadedFiles = $uploadedFiles;        
        return $clone;
    }

    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $clone = clone $this;
        $clone->uri = $uri;
        
        if (!$preserveHost) {
            if ($uri->getHost() !== '') {
                $clone->setHeader('Host', $uri->getHost());
            }
            
            return $clone;
        }

        if ($this->getHeaderLine('Host') === ''
            && $uri->getHost() !== ''
        ) {
            $clone->setHeader('Host', $uri->getHost());
        }

        return $clone;
    }

    public function withoutAttribute($name)
    {
        $clone = clone $this;
        if (isset($clone->attributes[$name])) {
            unset($clone->attributes[$name]);
        }
        return $clone;
    }

    public function withoutHeader($name)
    {
        $clone = clone $this;
        if ($this->hasHeader($name)) {
            unset($this->headers[strtoupper($name)]);
        }
        return $clone;
    }
}
