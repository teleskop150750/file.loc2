<?php

namespace FileManager\FileSystem;

use FileManager\Utils\Str;
use JetBrains\PhpStorm\NoReturn;
use RuntimeException;

class Storage
{

    private const STORAGE_PATH = '/public/storage/';

    /**
     * Получить хеш MD5 файла по заданному пути.
     *
     * @param  string  $path
     * @return string
     */
    public static function hash(string $path): string
    {
        return md5_file($path);
    }

    /**
     * Удалить файл по указному пути.
     *
     * @param  string  $path
     * @return bool
     */
    #[NoReturn]
    public static function delete(string $path): bool
    {
        if (@unlink($path)) {
            clearstatcache(false, $path);

            return true;
        }

        return false;
    }

    /**
     * Извлечь расширение файла из пути к файлу.
     *
     * @param  string  $path
     * @return string
     */
    public static function extension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * Get a filename for the file.
     *
     * @param  string|null  $path
     * @return string
     */
    public static function hashName(string $path = null): string
    {
        if ($path) {
            $path = rtrim($path, '/');
        }

        $hash = Str::random();

        if ($extension = self::extension($path)) {
            $extension = '.'.$extension;
        }

        return $hash.$extension;
    }

    /**
     * @param  string  $path
     * @param  mixed  $file
     * @return void
     */
    public static function store(string $path, mixed $file): void
    {
        self::checkAndCreateDir($path);
        $result = move_uploaded_file($file, $path);

        if ($result === false) {
            throw new RuntimeException("Не удалось сохранить файл $path");
        }
    }

    /**
     * Создать директорию
     *
     * @param  string  $dir
     * @return void
     */
    public static function makeDirectory(string $dir): void
    {
        $result = mkdir($dir, 0777, true);

        if (!$result) {
            throw new RuntimeException("Не удалось создать директорию $dir");
        }
    }

    /**
     * Проверить наличие файла
     *
     * @param  string  $path
     * @return bool
     */
    private static function checkFileExtension(string $path): bool
    {
        $pathInfo = pathinfo($path);

        return isset($pathInfo['extension']);
    }

    /**
     * Проверить наличие директории и создать если ее нет
     *
     * @param  string  $path
     * @return void
     */
    private static function checkAndCreateDir(string $path): void
    {
        if (!self::pathContainsDirectory($path)) {
            return;
        }

        $dir = self::getDirFromPath($path);

        if (!self::isDirectory($dir)) {
            self::makeDirectory($dir);
        }
    }

    /**
     * Проверить наличие директории в пути
     *
     * @param  string  $path
     * @return bool
     */
    private static function pathContainsDirectory(string $path): bool
    {
        $pathInfo = pathinfo($path);

        return $pathInfo['dirname'] !== '.';
    }

    /**
     * Получить директорию из пути
     *
     * @param  string  $path
     * @return string
     */
    private static function getDirFromPath(string $path): string
    {
        $pathInfo = pathinfo($path);

        return $pathInfo['dirname'].DIRECTORY_SEPARATOR;
    }

    /**
     * Определить, является ли данный путь каталогом
     *
     * @param  string  $directory
     * @return bool
     */
    private static function isDirectory(string $directory): bool
    {
        return is_dir($directory);
    }

    /**
     * Получить путь к файлу
     *
     * @param  string  $path
     * @return string
     */
    public static function getFullPath(string $path): string
    {
        return self::getRootDir().$path;
    }

    /**
     * Получить URL-адрес файла по заданному пути
     *
     * @param  string  $path
     * @return string
     */
    public static function url(string $path): string
    {
        return self::STORAGE_PATH.$path;
    }

    /**
     * Получить корневую директорию
     *
     * @return string
     */
    private static function getRootDir(): string
    {
        return $_SERVER['DOCUMENT_ROOT'].self::STORAGE_PATH;
    }

    /**
     * Удалить лишние символы из пути
     *
     * @param  string  $path
     * @return string
     */
    private static function trimPath(string $path): string
    {
        return trim($path, " \n\r\t\v\x00".DIRECTORY_SEPARATOR);
    }
}
