<?php

namespace Indoraptor;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

use codesaur\Http\Message\Uri;

class InternalRequest implements ServerRequestInterface
{
    protected array $headers = [];
    
    protected StreamInterface $body;
    
    protected string $protocolVersion = '1.1';

    protected UriInterface $uri;
    
    protected string $method;
    
    protected string $requestTarget;

    protected array $serverParams = [];
    
    protected array $cookies = [];
    
    protected array $attributes = [];

    protected array $parsedBody;
    
    protected array $uploadedFiles = [];
    
    protected ?array $queryParams = null;
    
    public function __construct(string $method, string $pattern, array $payload = [], ?string $token = null)
    {
        $this->serverParams['HTTP_HOST'] = $_SERVER['HTTP_HOST'];
        $this->serverParams['REQUEST_URI'] = $pattern;
        $this->serverParams['SCRIPT_NAME'] = '/index.php';
        $this->serverParams['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
        $this->serverParams['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $this->serverParams['PHP_SELF'] = $_SERVER['PHP_SELF'];
        
        $this->method = $method;
        
        $this->uri = new Uri();
        $this->requestTarget = $pattern;
        if (($pos = \strpos($pattern, '?')) !== false) {
            $query = \substr($pattern, $pos + 1);
            $this->serverParams['QUERY_STRING'] = $query;
            $this->uri->setPath(\substr($pattern, 0, $pos));
            $this->uri->setQuery($query);
            \parse_str($query, $this->queryParams);
        } else {
            $this->uri->setPath($pattern);
        }
        
        $this->parsedBody = $payload;
        
        if (isset($token)) {
            $this->serverParams['HTTP_AUTHORIZATION'] = "Bearer $token";
        }
    }

    public function getAttribute(string $name, $default = null)
    {
        if (!isset($this->attributes[$name])) {
            return $default;
        }
        
        return $this->attributes[$name];
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function getCookieParams(): array
    {
        return $this->cookies;
    }

    public function getHeader(string $name): array
    {
        return $this->headers[\strtoupper($name)] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        $values = $this->getHeader($name);
        return \implode(',', $values);
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[\strtoupper($name)]);
    }
    
    public function setHeader(string $name, $value)
    {
        if (\is_array($value)) {
            $this->headers[\strtoupper($name)] = $value;
        } else {
            $this->headers[\strtoupper($name)] = [$value];
        }
    }

    public function getMethod(): string
    {
        return $this->method;
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
        if (\is_array($this->queryParams)) {
            return $this->queryParams;
        }

        if (!$this->getUri() instanceof UriInterface) {
            return [];
        }
        
        $query = \rawurldecode($this->getUri()->getQuery());
        \parse_str($query, $this->queryParams);
        return $this->queryParams;
    }

    public function getRequestTarget(): string
    {
        if (!empty($this->requestTarget)) {
            return $this->requestTarget;
        } elseif (!$this->getUri() instanceof UriInterface) {
            return '/';
        }

        $path = \rawurldecode($this->getUri()->getPath());
        $requestTarget = '/' . \ltrim($path, '/');
        
        $query = $this->getUri()->getQuery();
        if ($query != '') {
            $requestTarget .= '?' . \rawurldecode($query);
        }
        
        $fragment = $this->getUri()->getFragment();
        if ($fragment != '') {
            $requestTarget .= '#' . \rawurldecode($fragment);
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

    public function withAddedHeader(string $name, $value): MessageInterface
    {
        $clone = clone $this;
        if ($this->hasHeader($name)) {
            if (\is_array($value)) {
                $this->headers[\strtoupper($name)] += $value;
            } else {
                $this->headers[\strtoupper($name)][] = $value;
            }
        } else {
            $this->setHeader($name, $value);
        }
        
        return $clone;
    }

    public function withAttribute(string $name, $value): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    public function withBody(StreamInterface $body): MessageInterface
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->cookies = $cookies;
        return $clone;
    }

    public function withHeader(string $name, $value): MessageInterface
    {
        $clone = clone $this;
        $clone->setHeader($name, $value);
        return $clone;
    }

    public function withMethod(string $method): RequestInterface
    {
        $clone = clone $this;
        $clone->method = \strtoupper($method);
        return $clone;
    }

    public function withParsedBody($data): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->parsedBody = $data;
        return $clone;
    }

    public function withProtocolVersion(string $version): MessageInterface
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;
        return $clone;
    }

    public function withQueryParams(array $query): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->queryParams = $query;
        return $clone;
    }

    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        $clone = clone $this;
        $clone->requestTarget = $requestTarget;
        return $clone;
    }

    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->uploadedFiles = $uploadedFiles;
        return $clone;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        $clone = clone $this;
        $clone->uri = $uri;
        
        if (!$preserveHost) {
            if ($uri->getHost() != '') {
                $clone->setHeader('Host', $uri->getHost());
            }
            
            return $clone;
        }

        if ($this->getHeaderLine('Host') == ''
            && $uri->getHost() != ''
        ) {
            $clone->setHeader('Host', $uri->getHost());
        }

        return $clone;
    }

    public function withoutAttribute(string $name): ServerRequestInterface
    {
        $clone = clone $this;
        if (isset($clone->attributes[$name])) {
            unset($clone->attributes[$name]);
        }
        return $clone;
    }

    public function withoutHeader(string $name): MessageInterface
    {
        $clone = clone $this;
        if ($this->hasHeader($name)) {
            unset($this->headers[\strtoupper($name)]);
        }
        return $clone;
    }
}
