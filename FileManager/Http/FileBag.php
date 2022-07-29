<?php

namespace FileManager\Http;

use App\Helper;
use FileManager\Http\File\UploadedFile;

class FileBag extends ParameterBag
{
    private const FILE_KEYS = ['error', 'name', 'size', 'tmp_name', 'type'];

    /**
     * @var UploadedFile[]
     */
    public array $parameters;

    /**
     * @param  array  $parameters  Массив HTTP-файлов
     */
    public function __construct(array $parameters = [])
    {
        parent::__construct();
        $this->add($parameters);
    }

    private function add(array $files = []): void
    {
        foreach ($files as $key => $file) {
            $this->set($key, $file);
        }
    }

    /**
     * @param  string      $key
     * @param  UploadedFile|null  $default
     *
     * @return UploadedFile|array|null
     */
    public function get(string $key, mixed $default = null): UploadedFile|array|null
    {
        return parent::get($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        parent::set($key, $this->convertFileInformation($value));
    }

    /**
     * Преобразует загруженные файлы в экземпляры UploadedFile.
     *
     * @return UploadedFile[]|UploadedFile|null
     */
    public function convertFileInformation(array|UploadedFile $file): array|UploadedFile|null
    {
        if ($file instanceof UploadedFile) {
            return $file;
        }

        $file = $this->fixPhpFilesArray($file);
        $keys = array_keys($file);
        sort($keys);

        if (self::FILE_KEYS === $keys) {
            if (UPLOAD_ERR_NO_FILE === $file['error']) {
                return null;
            }

            return new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['error']);
        }

        return array_map(function ($v) {
            return $this->convertFileInformation($v);
        }, $file);
    }

    /**
     * Исправить неправильный массив PHP $_FILES.
     *
     * В PHP есть ошибка, из-за которой формат массива $_FILES отличается в зависимости от того,
     * имели ли загруженные поля файла обычные имена полей или похожие на массив
     * имена полей ("normal" или "parent[child]").
     *
     * Этот метод исправляет массив, чтобы он выглядел как "обычный" массив $_FILES.
     *
     * Безопасно передавать уже преобразованный массив, и в этом случае этот метод
     * просто возвращает исходный массив без изменений.
     */
    public function fixPhpFilesArray(array $data): array
    {
        // Удалить дополнительный ключ, добавленный PHP 8.1.
        unset($data['full_path']);
        $keys = array_keys($data);
        sort($keys);

        if (!is_array($data['name'])) {
            return $data;
        }

        $files = [];

        foreach ($data['name'] as $key => $name) {
            $files[$key] = $this->fixPhpFilesArray([
                'error' => $data['error'][$key],
                'name' => $name,
                'type' => $data['type'][$key],
                'tmp_name' => $data['tmp_name'][$key],
                'size' => $data['size'][$key],
            ]);
        }

        return $files;
    }
}
