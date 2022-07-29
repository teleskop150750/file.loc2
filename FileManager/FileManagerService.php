<?php

namespace FileManager;

use FileManager\Entity\FileEntity;
use FileManager\FileSystem\Exception\FileException as FsFileException;
use FileManager\FileSystem\Storage;
use FileManager\Http\BinaryFileResponse;
use FileManager\Http\File\Exception\FileException;
use FileManager\Http\File\UploadedFile;
use FileManager\Http\JsonResponse;
use FileManager\Http\Request;
use FileManager\Http\Response;
use FileManager\Repositories\FileRepository;
use FileManager\Utils\Str;
use finfo;
use InvalidArgumentException;
use JetBrains\PhpStorm\NoReturn;
use Exception;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use SplFileInfo;

class FileManagerService
{
    /**
     * Ключ под которым находится загруженный файл в $_FILES
     */
    private const FILE_FIELD_NAME = 'file';

    /**
     * Ключ $_GET параметра содержащий id файла для скачивания
     */
    private const DOWNLOAD_GET_PARAMETER = 'get_file';

    /**
     * Ключ $_GET параметра содержащий id файла для удаления
     */
    private const DELETE_GET_PARAMETER = 'delete_file';

    private FileRepository $fileRepository;

    /**
     * Точка входа
     *
     * @return Response
     */
    public function execute(): Response
    {
        $this->fileRepository = new FileRepository();
        $request = Request::createFromGlobals();
        if ($file = $request->files->get(self::FILE_FIELD_NAME)) {
            $response = $this->upload($file);
        } elseif ($id = $request->query->get(self::DOWNLOAD_GET_PARAMETER)) {
            $response = $this->download($id);
        } elseif ($id = $request->query->get(self::DELETE_GET_PARAMETER)) {
            $response = $this->delete($id);
        } else {
            return (new JsonResponse())->setContent([
                'status' => 'error',
                'message' => 'Bad Request',
            ])->setStatusCode(400);
        }

        return $response;
    }

    /**
     * Загрузить файл
     *
     * @param  UploadedFile  $file
     *
     * @return Response
     */
    private function upload(UploadedFile $file): Response
    {
        try {
            if (!Auth::check()) {
                return (new JsonResponse())->setContent([
                    'status' => 'error',
                    'message' => 'Вы не авторизованы',
                ])->setStatusCode(403);
            }

            if (!$file->isValid()) {
                throw new FileException($file->getErrorMessage());
            }

            $fileEntity = new FileEntity();
            $fileEntity->setId($this->makeFileId());
            $fileEntity->setName(Storage::getHashName($file->getClientOriginalName()));
            $fileEntity->setOriginName($file->getClientOriginalName());
            $folder = date('Y-m-d');
            $fileEntity->setPath($folder.'/'.$fileEntity->getName());
            $fileEntity->setUrl(Storage::url($fileEntity->getPath()));
            $fileEntity->setHash(Storage::hash($file->getRealPath()));
            $fileEntity->setUserId(Auth::id());

            if (!$foundFile = $this->fileRepository->findByHash($fileEntity->getHash())) {
                $file->move(Storage::prefixPath($folder), $fileEntity->getName());
            } else {
                $fileEntity->setName($foundFile->getName());
                $fileEntity->setUrl($foundFile->getUrl());
                $fileEntity->setPath($foundFile->getPath());
            }

            $this->fileRepository->save($fileEntity);

            return (new JsonResponse())->setContent([
                'status' => 'success',
                'message' => 'Файл загружен',
                'data' => ['id' => $fileEntity->getId(),],
            ])->setStatusCode(201);
        } catch (Exception $exception) {
            return (new JsonResponse())->setContent([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ])->setStatusCode(500);
        }
    }

    /**
     * Скачать файл
     *
     * @param  string  $id
     *
     * @return Response
     */
    private function download(string $id): Response
    {
        try {
            $fileEntity = $this->fileRepository->find($id);

            if (!$fileEntity) {
                return (new JsonResponse())->setContent([
                    'status' => 'error',
                    'message' => 'Файл не найден',
                ])->setStatusCode(404);
            }

            $location = Storage::prefixPath($fileEntity->getPath());

            if (!is_readable($location)) {
                throw new FsFileException('Файл должен быть читаемым.');
            }

            return (new BinaryFileResponse($location))
                ->setHeaders('Content-Type', Storage::getMimeType($fileEntity->getPath()) ?: 'text/plain')
                ->setHeaders('Content-Description', 'File Transfer')
                ->setHeaders('Content-Disposition', "attachment; filename={$fileEntity->getOriginName()}")
                ->setStatusCode(200);
        } catch (Exception $exception) {
            return (new JsonResponse())->setContent([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ])->setStatusCode(500);
        }
    }

    private function delete(string $id): Response
    {
        if ($file = $this->fileRepository->find($id)) {
            if ($this->fileRepository->countByHash($file->getHash()) === 1) {
                Storage::delete($file->getPath());
            }

            $this->fileRepository->delete($id);

            return (new JsonResponse)->setContent([
                'status' => 'success',
                'message' => "Файл удален",
                'data' => ['id' => $file->getId(),],
            ]);
        }

        return (new JsonResponse())->setContent([
            'status' => 'error',
            'message' => 'Файл не найден',
        ])->setStatusCode(404);
    }

    /**
     * Создать id файла
     *
     * @return string
     */
    private
    function makeFileId(): string
    {
        do {
            $id = Str::random();
        } while ($this->fileRepository->existsId($id));

        return $id;
    }
}
