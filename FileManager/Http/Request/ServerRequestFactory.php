<?php

declare(strict_types=1);

namespace FileManager\Http\Request;

use FileManager\Http\Utils\SuperGlobal;
use JsonException;

class ServerRequestFactory
{

    public static function fromGlobal(): ServerRequest
    {
        $globals = SuperGlobal::extract();

        // HTTP method.
        $method = $server['REQUEST_METHOD'] ?? 'GET';

        // HTTP protocol version.
        $protocol = $server['SERVER_PROTOCOL'] ?? '1.1';
        $protocol = str_replace('HTTP/', '', $protocol);

        $uri = UriFactory::fromGlobal();

        $streamFactory = new StreamFactory();
        $body = $streamFactory->createStream();

        return new ServerRequest(
            $method,
            $uri,
            $body,
            $globals['header'],
            $protocol,
            $globals['server'],
            $globals['cookie'],
            $globals['post'],
            $globals['get'],
            $globals['files']
        );
    }
}
