<?php
declare(strict_types=1);

namespace FileManager\Modules\Http\Request;

class UriFactory
{
    public static function fromGlobal(): Uri
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
}
