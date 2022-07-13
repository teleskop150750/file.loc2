<?php

namespace App\Controllers;

use App\Helper;
use App\Http\Main\Response;
use App\Http\Psr7\Utils\UploadedFileHelper;
use App\View;
use App\Http\Psr7\Stream;
use App\Http\Psr7\UploadedFile;

class HomeController
{
    public function index(): string
    {
        return View::make(
            'Home/home',
            [
                'page_title' => 'Тестовое задание по PHP',
                'page_subtitle' => '',
            ]
        );
    }

    public function test()
    {
        $files = [
            'foo' => [
                'name' => [
                    0 => 'image.jpg',
                    1 => 'arhive.zip',
                ],
                'type' => [
                    0 => 'image/jpeg',
                    1 => 'application/zip',
                ],
                'tmp_name' => [
                    0 => '/home/user/temp/phpK3h32F',
                    1 => '/home/user/temp/phpBrGxus',
                ],
                'error' => [
                    0 => 0,
                    1 => 0,
                ],
                'size' => [
                    0 => 119303,
                    1 => 6792,
                ],
            ],
            'bar' => [
                'name' => 'image.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/home/user/temp/phpK3h32F',
                'error' => 0,
                'size' => 34234,
            ],
        ];
//    }

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode(['status' => 'error', 'message' => 'Файл ужe загружен'],
            JSON_THROW_ON_ERROR));

        return $response->getContent();
//        exit();
    }
}
