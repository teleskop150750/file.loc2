<?php

namespace FileManager\Modules\Http;

use FileManager\Modules\Http\File\UploadedFile;

class FileBag extends ParameterBag
{
    private const FILE_KEYS = ['error', 'name', 'size', 'tmp_name', 'type'];

    /**
     * @param array|UploadedFile[] $parameters Массив HTTP-файлов
     */
    public function __construct(array $parameters = [])
    {
        parent::__construct();
        $this->replace($parameters);
    }

    public function replace(array $items = []): void
    {
        $this->parameters = [];
        $this->add($items);
    }

    public function set(string $key, mixed $value): void
    {
        if (!\is_array($value) && !$value instanceof UploadedFile) {
            throw new \InvalidArgumentException('Загруженный файл должен быть массивом или экземпляром UploadedFile.');
        }

        parent::set($key, $this->convertFileInformation($value));
    }

    public function add(array $items = []): void
    {
        foreach ($items as $key => $file) {
            $this->set($key, $file);
        }
    }

    /**
     * Преобразует загруженные файлы в экземпляры UploadedFile.
     *
     * @return UploadedFile[]|UploadedFile|null
     */
    protected function convertFileInformation(array|UploadedFile $file): array|UploadedFile|null
    {
        if ($file instanceof UploadedFile) {
            return $file;
        }

        $file = $this->fixPhpFilesArray($file);
        $keys = array_keys($file);
        sort($keys);

        if (self::FILE_KEYS == $keys) {
            if (\UPLOAD_ERR_NO_FILE == $file['error']) {
                $file = null;
            } else {
                $file = new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['error'], false);
            }
        } else {
            $file = array_map(function ($v) { return $v instanceof UploadedFile || \is_array($v) ? $this->convertFileInformation($v) : $v; }, $file);
            if (array_keys($keys) === $keys) {
                $file = array_filter($file);
            }
        }

        return $file;
    }

    /**
     * Исправлен неправильный массив PHP $_FILES.
     *
     * В PHP есть ошибка, из-за которой формат массива $_FILES отличается в зависимости от того,
     * имели ли загруженные поля файла обычные имена полей или похожие на массив
     * имена полей ("обычные" или "родительские [дочерние]").
 *
     * Этот метод исправляет массив, чтобы он выглядел как "обычный" массив $_FILES.
     *
     * Безопасно передавать уже преобразованный массив, и в этом случае этот метод
     * просто возвращает исходный массив без изменений.
     */
    protected function fixPhpFilesArray(array $data): array
    {
        // Remove extra key added by PHP 8.1.
        unset($data['full_path']);
        $keys = array_keys($data);
        sort($keys);

        if (self::FILE_KEYS != $keys || !isset($data['name']) || !\is_array($data['name'])) {
            return $data;
        }

        $files = $data;
        foreach (self::FILE_KEYS as $k) {
            unset($files[$k]);
        }

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
