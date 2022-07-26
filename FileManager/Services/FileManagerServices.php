<?php

namespace FileManager\Services;

use App\Helper;
use App\Models\File;
use App\Utils\Storage;
use App\Utils\Str;
use FileManager\Entities\FileEntity;
use FileManager\Http\Request\ServerRequestFactory;
use FileManager\Http\Request\UploadedFile;
use FileManager\Http\Response\BinaryFileResponse;
use FileManager\Http\Response\Mime\FileInfoMimeTypeGuesser;
use FileManager\Http\Response\Response;
use FileManager\Http\Response\ResponseHeaderBag;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\NoReturn;
use JsonException;

class FileManagerServices
{
    /**
     * @throws JsonException
     */
    #[NoReturn]
    public static function upload(): void
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $uploadedFile = self::getUploadedFileOrNull();

        if (!$uploadedFile) {
            $response->setContent(
                json_encode(
                    ['status' => 'error', 'message' => 'Файл не передан'],
                    JSON_THROW_ON_ERROR
                )
            );
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->send();
        }

        $uploadedFileInfo = self::getUploadedFileInfo($uploadedFile);

        if (self::existsFileInDb($uploadedFileInfo['hash'])) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->setContent(json_encode(['status' => 'error', 'message' => 'Файл ужe загружен'],
                JSON_THROW_ON_ERROR));

            $response->send();
        }

        self::move($uploadedFile, $uploadedFileInfo['hashName']);

        $savedFile = self::saveInDb($uploadedFileInfo);

        if (!$savedFile) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->send();
        }

        $response->setStatusCode(Response::HTTP_CREATED);
        $response->setContent(json_encode([
            'status' => 'success',
            'message' => 'Файл передан',
            'data' => [
                'id' => $savedFile->id,
                'name' => $savedFile->name,
                'url' => $savedFile->url,
                'hash' => $savedFile->hash,
            ],
        ], JSON_THROW_ON_ERROR));

        $response->send();
    }


    /**
     * @throws JsonException
     */
    #[NoReturn]
    public static function delete(): void
    {
        $id = 1;
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

    /**
     * @throws JsonException
     */
    #[NoReturn]
    public static function download(): void
    {
        $id = 1;
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


    #[ArrayShape(['hashName' => "string", 'hash' => "string", 'url' => "string"])]
    private static function getUploadedFileInfo(UploadedFile $file): array
    {
        $hashName = Storage::hashName($file->getClientFilename());
        $hash = Storage::hash($file->getFile());
        $url = Storage::url($hashName);

        return [
            'hashName' => $hashName,
            'hash' => $hash,
            'url' => $url,
        ];
    }

    private static function existsFileInDb(string $hash): bool
    {
        return (bool) File::getByHash($hash);
    }

    /**
     * @throws JsonException
     */
    private static function getUploadedFileOrNull(): ?UploadedFile
    {
        $serverRequest = ServerRequestFactory::fromGlobal();

        return $serverRequest->getUploadedFiles()['file'] ?? null;
    }

    private static function move(UploadedFile $uploadedFile, string $name): void
    {
        Storage::store(Storage::getFullPath($name), $uploadedFile->getFile());
    }

    private static function saveInDb(array $uploadedFileInfo): ?FileEntity
    {
        do {
            $fileId = Str::random();
        } while (File::show($fileId));

        $fileEntity = new FileEntity(
            $fileId,
            $uploadedFileInfo['hashName'],
            $uploadedFileInfo['url'],
            $uploadedFileInfo['hash'],
        );

        if (!File::store($fileEntity)) {
            return null;
        }

        return $fileEntity;
    }
}
