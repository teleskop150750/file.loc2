<?php

namespace App\Controllers;

use App\View;
use FileManager\FileManagerServices;
use FileManager\Models\File;
use JetBrains\PhpStorm\NoReturn;
use JsonException;

class FileController
{
    /**
     * READ all
     *
     * @return string
     */
    public function index(): string
    {
        $files = File::index();

        return View::make(
            'File/index',
            [
                'page_title' => 'Файлы',
                'page_subtitle' => '',
                'files' => $files,
            ],
            'dashboard',
        );
    }


    /**
     * CREATE
     *
     * @return string
     */
    public function create(): string
    {
        return View::make(
            'File/create',
            [
                'page_title' => 'Отправить файл',
                'page_subtitle' => '',
            ],
            'dashboard',
        );
    }

    #[NoReturn]
    public function fileManager(): void
    {
        (new FileManagerServices())->execute();
    }
}
