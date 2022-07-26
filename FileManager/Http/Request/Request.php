<?php
declare(strict_types=1);

namespace FileManager\Http\Request;

use FileManager\Http\Request\Uri;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;


class Request extends Message
{
    protected string $method;

    protected string $requestTarget;

    protected Uri $uri;

    protected array $validMethods = [
        'HEAD',
        'GET',
        'POST',
        'PUT',
        'DELETE',
        'PATCH',
        'CONNECT',
        'OPTIONS',
        'TRACE',
    ];

    public function __construct(
        string $method  = 'GET',
        Uri|string $uri     = '',
        StreamInterface|string $body    = '',
        array  $headers = [],
        string $version = '1.1'
    ) {
        $this->method = $method;

        $this->assertMethod($method);

        $this->assertProtocolVersion($version);
        $this->protocolVersion = $version;

        if ($uri instanceof Uri) {
            $this->uri = $uri;
        } elseif (is_string($uri)) {
            $this->uri = new Uri($uri);
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    'URL-адрес должен быть строкой или экземпляром интерфейса Uri, но при условии "%s".',
                    gettype($uri)
                )
            );
        }

        $this->setBody($body);
        $this->setHeaders($headers);
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget) {
            return $this->requestTarget;
        }

        $path = $this->uri->getPath();
        $query = $this->uri->getQuery();

        if (empty($path)) {
            $path = '/';
        }

        if (!empty($query)) {
            $path .= '?' . $query;
        }

        return $path;
    }

    public function withRequestTarget($requestTarget): Request
    {
        if (!is_string($requestTarget)) {
            throw new InvalidArgumentException('Целевой объект запроса должен быть строкой.');
        }

        if (preg_match('/\s/', $requestTarget)) {
            throw new InvalidArgumentException('Цель запроса не может содержать никаких пробелов.');
        }

        $clone = clone $this;
        $clone->requestTarget = $requestTarget;

        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod($method): Request
    {
        $this->assertMethod($method);

        $clone = clone $this;
        $clone->method = $method;

        return $clone;
    }

    public function getUri(): Uri
    {
        return $this->uri;
    }

    public function withUri(Uri $uri, $preserveHost = false): Request
    {
        $host = $uri->getHost();

        $clone = clone $this;
        $clone->uri = $uri;

        if (
            (!$preserveHost && $host !== '') ||
            ($preserveHost && !$this->hasHeader('Host') && $host !== '')
        ) {
            $headers = $this->getHeaders();
            $headers['host'] = $host;
            $clone->setHeaders($headers);
        }

        return $clone;
    }

    protected function assertMethod($method): void
    {
        if (!is_string($method)) {
            throw new InvalidArgumentException(
                sprintf(
                    'HTTP method must be a string. "%s"',
                    $method
                )
            );
        }

        if (!in_array(strtoupper($this->method), $this->validMethods, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Неподдерживаемый HTTP-метод. Он должен быть совместим с методом запроса RFC-7231, но при условии "%s".',
                    $method
                )
            );
        }
    }
}
