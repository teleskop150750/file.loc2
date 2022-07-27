<?php

namespace FileManager;

use FileManager\Entity\FileEntity;
use FileManager\FileSystem\Storage;
use FileManager\Models\File;
use FileManager\Modules\Http\BinaryFileResponse;
use FileManager\Modules\Http\File\UploadedFile;
use FileManager\Modules\Http\Request;
use FileManager\Modules\Http\Response;
use FileManager\Modules\Http\ResponseHeaderBag;
use FileManager\Modules\Mime\FileInfoMimeTypeGuesser;
use FileManager\Utils\Str;
use JetBrains\PhpStorm\NoReturn;
use JsonException;
use RuntimeException;

class FileManagerServices
{
    private const FILE_FIELD_NAME = 'file';
    private const DOWNLOAD_GET_PARAMETER = 'get_file';
    private const DELETE_GET_PARAMETER = 'delete_file';
    private string $date;

    #[NoReturn]
    public function execute(): void
    {
        $queryParams = Request::createFromGlobals()->query->all();

        if (isset($queryParams[self::DOWNLOAD_GET_PARAMETER])) {
            $this->download($queryParams[self::DOWNLOAD_GET_PARAMETER]);
        } elseif (isset($queryParams[self::DELETE_GET_PARAMETER])) {
            $this->delete($queryParams[self::DELETE_GET_PARAMETER]);
        } else {
            $this->upload();
        }
    }

    #[NoReturn]
    public function upload(): void
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $this->prepareDate();

        try {
            $uploadedFile = $this->getUploadedFile();
        } catch (\Exception $exception) {
            $response->setContent(json_encode(['status' => 'error', 'message' => $exception->getMessage()]));
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->send();
            exit();
        }

        $uploadedFileEntity = $this->getUploadedFileEntity($uploadedFile);

        if ($this->existsFileInDb($uploadedFileEntity->hash)) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->setContent(json_encode(['status' => 'error', 'message' => 'Файл ужe загружен']));

            $response->send();
            exit();
        }

        $this->move($uploadedFile, $uploadedFileEntity->path);

        $savedFile = $this->saveInDb($uploadedFileEntity);

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
                'path' => $savedFile->path,
                'url' => $savedFile->url,
                'hash' => $savedFile->hash,
            ],
        ]));

        $response->send();
        exit();
    }

    #[NoReturn]
    public function delete(string $id): void
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

        $response->setContent(json_encode($output));
        $response->send();
        exit();
    }

    #[NoReturn]
    public function download(string $id): void
    {
        $file = File::show($id);

        if (!$file) {
            $output['status'] = 'ERROR';
            $output['message'] = 'Файл не найден.';
            $response = new Response();
            $response->setContent(json_encode($output));
            $response->headers->set('Content-Type', 'application/json');

            $response->send();
            exit();
        }

        $fileName = $file['name'];
        $filePath = Storage::getFullPath($file['path']);
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

    private function getUploadedFileEntity(UploadedFile $file): FileEntity
    {
        $fileEntity = new FileEntity();
        $fileEntity->name = Storage::hashName($file->getClientOriginalName());
        $fileEntity->path = $this->preparePath($fileEntity->name);
        $fileEntity->hash = Storage::hash($file->getRealPath());
        $fileEntity->url = Storage::url($fileEntity->path);

        return $fileEntity;
    }

    private function existsFileInDb(string $hash): bool
    {
        return (bool) File::getByHash($hash);
    }

    private function getUploadedFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = Request::createFromGlobals()->files->get(self::FILE_FIELD_NAME);

        if (!$file) {
            throw new RuntimeException('Файл не передан');
        }

        if ($file->getError() !== 0) {
            throw new RuntimeException($file->getErrorMessage());
        }

        return $file;
    }

    private function move(UploadedFile $uploadedFile, string $name): void
    {
        Storage::store($name, $uploadedFile->getRealPath());
    }

    private function saveInDb(FileEntity $fileEntity): ?FileEntity
    {
        do {
            $fileEntity->id = Str::random();
        } while (File::show($fileEntity->id));

        if (!File::store($fileEntity)) {
            return null;
        }

        return $fileEntity;
    }

    private function prepareDate(): void
    {
        $this->date = date('Y-m-d');
    }

    private function getDate(): string
    {
        return $this->date;
    }

    private function preparePath(string $name): string
    {
        return $this->getDate().'/'.$name;
    }
}
