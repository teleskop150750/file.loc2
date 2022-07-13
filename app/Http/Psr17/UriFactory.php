<?php
declare(strict_types=1);

namespace App\Http\Psr17;

use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use App\Http\Psr7\Uri;

class UriFactory implements UriFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }

    public static function fromGlobal(): UriInterface
    {
        $server = $_SERVER ?? [];

        $uri = '';
        $user = '';
        $host = '';
        $pass = '';
        $path = '';
        $port = '';
        $query = '';
        $scheme = '';

        $uriComponents = [
            'user' => 'PHP_AUTH_USER',
            'host' => 'HTTP_HOST',
            'pass' => 'PHP_AUTH_PW',
            'path' => 'REQUEST_URI',
            'port' => 'SERVER_PORT',
            'query' => 'QUERY_STRING',
            'scheme' => 'REQUEST_SCHEME',
        ];

        foreach ($uriComponents as $key => $value) {
            ${$key} = $server[$value] ?? '';
        }

        $userInfo = $user;

        if ($pass) {
            $userInfo .= ':' . $pass;
        }

        $authority = '';

        if ($userInfo) {
            $authority .= $userInfo . '@';
        }

        $authority .= $host;

        if ($port) {
            $authority .= ':' . $port;
        }

        if ($scheme) {
            $uri .= $scheme . ':';
        }

        if ($authority) {
            $uri .= '//' . $authority;
        }

        $uri .= '/' . ltrim($path, '/');

        if ($query) {
            $uri .= '?' . $query;
        }

        return new Uri($uri);
    }

    public static function fromNew(): UriInterface
    {
        return new Uri();
    }
}
