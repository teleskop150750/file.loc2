<?php

declare(strict_types=1);

namespace App\Http\Psr17;

use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use App\Http\Psr17\StreamFactory;
use App\Http\Psr17\UriFactory;
use App\Http\Psr17\Utils\SuperGlobal;
use App\Http\Psr7\ServerRequest;

use function str_replace;
use function extract;

class ServerRequestFactory implements ServerRequestFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        extract(SuperGlobal::extract());

        if ($serverParams !== []) {
            $server = $serverParams;
        }

        $protocol = $server['SERVER_PROTOCOL'] ?? '1.1';
        $protocol = str_replace('HTTP/', '', $protocol);

        if (!($uri instanceof UriInterface)) {
            $uriFactory = new UriFactory();
            $uri = $uriFactory->createUri($uri);
        }

        $streamFactory = new StreamFactory();
        $body = $streamFactory->createStream();

        return new ServerRequest(
            $method,
            $uri,
            $body,
            $header, // from extract.
            $protocol,
            $server, // from extract.
            $cookie, // from extract.
            $post,   // from extract.
            $get,    // from extract.
            $files   // from extract.
        );
    }

    public static function fromGlobal(): ServerRequestInterface
    {
        extract(SuperGlobal::extract());

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
            $header, // from extract.
            $protocol,
            $server, // from extract.
            $cookie, // from extract.
            $post,   // from extract.
            $get,    // from extract.
            $files   // from extract.
        );
    }
}
