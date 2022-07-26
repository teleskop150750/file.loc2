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


    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): Uri
    {
        return $this->uri;
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
