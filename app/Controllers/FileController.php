<?php

namespace App\Controllers;

use App\Helper;
use App\Http\Main\BinaryFileResponse;
use App\Http\Main\Mime\FileInfoMimeTypeGuesser;
use App\Http\Main\Response;
use App\Http\Main\ResponseHeaderBag;
use App\Middleware;
use App\Models\File;
use App\Utils\Storage;
use App\Utils\Str;
use App\View;
use JetBrains\PhpStorm\NoReturn;
use JsonException;
use App\Http\Psr17\ServerRequestFactory;
use App\Http\Psr7\UploadedFile;
use stdClass;

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
     * @return bool|string
     * @throws JsonException
     */
    #[NoReturn]
    public function show(string $id): bool|string
    {
        $file = File::show($id);

        if (!$file) {
            $output['status'] = 'ERROR';
            $output['message'] = 'Файл не найден.';
            $response = new Response();
            $response->setContent(json_encode($output, JSON_THROW_ON_ERROR));
            $response->headers->set('Content-Type', 'application/json');

            $response->send();
        }

        $fileName = $file['name'];
        $filePath = Storage::getFullPath($fileName);
        $response = new BinaryFileResponse($filePath);
        $mimeTypeGuesser = new FileInfoMimeTypeGuesser();

        if ($mimeTypeGuesser->isGuesserSupported()) {
            $response->headers->set('Content-Type', $mimeTypeGuesser->guessMimeType($filePath));
        } else {
            $response->headers->set('Content-Type', 'text/plain');
        }

        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName);

        $response->send();
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
    public function store()
    {
        header('Content-Type: application/json; charset=utf-8');
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');

        $serverRequest = ServerRequestFactory::fromGlobal();
        $file = $serverRequest->getUploadedFiles()['file'];

        $hashName = Storage::hashName($file->getClientFilename());
        $fileHash = Storage::hash($file->getFile());
        $fileUrl = Storage::url($hashName);

        if (File::getByHash($fileHash)) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->setContent(json_encode(['status' => 'error', 'message' => 'Файл ужe загружен'], JSON_THROW_ON_ERROR));

            $response->send();
        }

        Storage::store(Storage::getFullPath($hashName), $file->getFile());

        do {
            $fileId = Str::random();
        } while (File::show($fileId));


        $fileData = new stdClass();
        $fileData->id = $fileId;
        $fileData->name = $hashName;
        $fileData->url = $fileUrl;
        $fileData->hash = $fileHash;

        if (!File::store($fileData)) {
            $response->setStatusCode(Response::HTTP_NOT_FOUND);
            $response->send();
        }

        $response->setStatusCode(Response::HTTP_CREATED);
        $response->setContent(json_encode([
            'status' => 'success',
            'message' => 'Файл передан',
            'data' => [
                'id' => $fileData->id,
                'name' => $fileData->name,
                'url' => $fileData->url,
                'hash' => $fileData->hash,
            ],
        ], JSON_THROW_ON_ERROR));

        $response->send();
    }

    /**
     * DELETE
     *
     * @param  string  $id
     * @return bool|string
     * @throws JsonException
     */
    #[NoReturn]
    public function delete(string $id): bool|string
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $output = [];

        $file = File::show($id);
        if ($file) {
            Storage::delete(Storage::getFullPath($file['name']));
            File::delete($file['id']);
            $response->setStatusCode(Response::HTTP_OK);
            $output['status'] = 'SUCCESS';
            $output['message'] = 'Этот процесс был успешно завершен!';
        } else {
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
            $output['status'] = 'ERROR';
            $output['message'] = 'Здесь какая-то ошибка! Пожалуйста, попробуйте снова.';
        }

        $response->setContent(json_encode($output, JSON_THROW_ON_ERROR));

        $response->send();
    }
}
