<?php

namespace App\Controllers;

use App\Helper;
use App\View;
use FileManager\FileManagerService;
use FileManager\Repositories\FileRepository;
use FileManager\Settings;
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
        Settings::setDbName('db_file');
        Settings::setDbUser('root');
        Settings::setDbPassword('root');
        $files = (new FileRepository())->all();

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

    public function fileManager(): void
    {
        Settings::setDbName('db_file');
        Settings::setDbUser('root');
        Settings::setDbPassword('root');
        Settings::setStorageDir('public/storage');
        $response = (new FileManagerService())->execute();
        $response->send();
        exit;
    }
}
