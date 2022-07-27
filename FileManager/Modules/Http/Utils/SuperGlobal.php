<?php

namespace FileManager\Modules\Http\Utils;

use JetBrains\PhpStorm\ArrayShape;

class SuperGlobal
{
    /**
     * Извлекает данные из глобальных переменных.
     *
     * @return array
     */
    #[ArrayShape([
        'header' => "array",
        'server' => "array",
        'cookie' => "array",
        'files' => "array",
        'post' => "array",
        'get' => "array",
    ])]
    public static function extract(): array
    {
        // Здесь мы сами добавляем префикс HTTP...
        $headerParamsWithoutHttpPrefix = [
            'CONTENT_TYPE',
            'CONTENT_LENGTH',
        ];

        foreach ($headerParamsWithoutHttpPrefix as $value) {
            if (isset($_SERVER[$value])) {
                $_SERVER['HTTP_'.$value] = $_SERVER[$value];
            }
        }

        $headerParams = [];
        $serverParams = $_SERVER ?? [];
        $cookieParams = $_COOKIE ?? [];
        $filesParams = $_FILES ?? [];
        $postParams = $_POST ?? [];
        $getParams = $_GET ?? [];

        foreach ($serverParams as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $key = strtolower(str_replace('_', '-', substr($name, 5)));
                $headerParams[$key] = $value;
            }
        }

        return [
            'header' => $headerParams,
            'server' => $serverParams,
            'cookie' => $cookieParams,
            'files' => $filesParams,
            'post' => $postParams,
            'get' => $getParams,
        ];
    }
}
