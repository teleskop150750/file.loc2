<?php

namespace App\Controllers;

use App\Helper;
use App\Models\File;
use App\View;
use FileManager\Services\FileManagerServices;
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
     * READ one
     *
     * @param  string  $id
     * @throws JsonException
     */
    #[NoReturn]
    public function show(string $id): void
    {
        FileManagerServices::download();
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

    /**
     * STORE
     *
     * @throws JsonException
     */
    #[NoReturn]
    public function store(): void
    {
        FileManagerServices::upload();
    }

    /**
     * DELETE
     *
     * @param  string  $id
     * @throws JsonException
     */
    #[NoReturn]
    public function delete(string $id): void
    {
        FileManagerServices::delete();
    }
}
