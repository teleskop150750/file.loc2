<?php

namespace FileManager;

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
    private const FILE_FIELD_NAME = 'file';
    private const DOWNLOAD_GET_PARAMETER = 'get_file';
    private const DELETE_GET_PARAMETER = 'delete_file';

    /**
     * @throws JsonException
     */
    #[NoReturn]
    public static function execute(): void
    {
        $queryParams = ServerRequestFactory::fromGlobal()->getQueryParams();

        if (isset($queryParams[self::DOWNLOAD_GET_PARAMETER])) {
            self::download($queryParams[self::DOWNLOAD_GET_PARAMETER]);
        } elseif (isset($queryParams[self::DELETE_GET_PARAMETER])) {
            self::delete($queryParams[self::DELETE_GET_PARAMETER]);
        } else {
            self::upload();
        }
    }

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
            $response->setContent(json_encode(['status' => 'error', 'message' => 'Файл не передан'],
                JSON_THROW_ON_ERROR));
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->send();
            exit();
        }

        if ($uploadedFile->getError() > 0) {
            $response->setContent(json_encode(['status' => 'error', 'message' => $uploadedFile->getErrorMessage()],
                JSON_THROW_ON_ERROR));
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->send();
            exit();
        }

        $uploadedFileInfo = self::getUploadedFileInfo($uploadedFile);

        if (self::existsFileInDb($uploadedFileInfo['hash'])) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->setContent(json_encode(['status' => 'error', 'message' => 'Файл ужe загружен'],
                JSON_THROW_ON_ERROR));

            $response->send();
            exit();
        }

        self::move($uploadedFile, $uploadedFileInfo['hashName']);

        $savedFile = self::saveInDb($uploadedFileInfo);

        if (!$savedFile) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->send();
            exit();
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
        exit();
    }

    /**
     * @throws JsonException
     */
    #[NoReturn]
    public static function delete(string $id): void
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $output = [];

        $file = File::show($id);

        if ($file) {
            $response->setStatusCode(Response::HTTP_OK);
            $output['status'] = 'SUCCESS';
            $output['message'] = 'Этот процесс был успешно завершен!';
            File::delete($file['id']);
            Storage::delete(Storage::getFullPath($file['name']));
        } else {
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
            $output['status'] = 'ERROR';
            $output['message'] = 'Здесь какая-то ошибка! Пожалуйста, попробуйте снова.';
        }

        $response->setContent(json_encode($output, JSON_THROW_ON_ERROR));
        $response->send();
        exit();
    }

    /**
     * @throws JsonException
     */
    #[NoReturn]
    public static function download(string $id): void
    {
        $file = File::show($id);

        if (!$file) {
            $output['status'] = 'ERROR';
            $output['message'] = 'Файл не найден.';
            $response = new Response();
            $response->setContent(json_encode($output, JSON_THROW_ON_ERROR));
            $response->headers->set('Content-Type', 'application/json');

            $response->send();
            exit();
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
        exit();
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


    private static function getUploadedFileOrNull(): ?UploadedFile
    {
        $uploadedFiles = ServerRequestFactory::fromGlobal()->getUploadedFiles();

        return $uploadedFiles[self::FILE_FIELD_NAME] ?? null;
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
