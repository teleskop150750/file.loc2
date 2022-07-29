<?php

namespace FileManager\FileSystem;

use FileManager\FileSystem\Exception\FileException;
use FileManager\Settings;
use FileManager\Utils\Str;
use finfo;

class Storage
{
    /**
     * Получить URL-адрес файла по заданному пути
     *
     * @param  string  $path
     *
     * @return string
     */
    public static function url(string $path): string
    {
        return self::storageDir().'/'.trim($path, '/\\ ');
    }
    
    public static function prefixPath(string $path): string
    {
        return self::storagePath().'/'.trim($path, '/\\ ');
    }

    public static function storagePath(): string
    {
        return rtrim($_SERVER['DOCUMENT_ROOT'].'/'.self::storageDir(), '/\\ ');
    }

    public static function storageDir(): string
    {
        return Settings::getStorageDir();
    }

    /**
     * Получить Hash содержимого файла
     *
     * @param  string  $path
     *
     * @return string
     */
    public static function hash(string $path): string
    {
        return md5_file($path);
    }

    public static function delete(string $path): void
    {
        $location = self::prefixPath($path);

        if (!file_exists($location)) {
            return;
        }

        error_clear_last();

        if (!@unlink($location)) {
            throw new FileException(
                sprintf('Не удалось удалить %s. Ошибка %s', $location, error_get_last()['message'])
            );
        }

        clearstatcache(false, $location);
    }

    /**
     * Получить Hash имени файла
     *
     * @param  string  $path
     *
     * @return string
     */
    public static function getHashName(string $path): string
    {
        $path = rtrim($path, ' /\\');

        $hash = Str::random();
        $hash = strtolower($hash);

        if ($extension = self::getFileExtension($path)) {
            $extension = '.'.$extension;
        }

        return $hash.$extension;
    }

    /**
     * Получить расширение файла
     *
     * @param  string  $path
     *
     * @return string
     */
    public static function getFileExtension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    public static function getMimeType(string $path): string|null
    {
        $location = self::prefixPath($path);

        if (!$fileInfo = new finfo(FILEINFO_MIME_TYPE)) {
            return null;
        }

        $mimeType = $fileInfo->file($location);

        if ($mimeType && 0 === (\strlen($mimeType) % 2)) {
            $mimeStart = substr($mimeType, 0, \strlen($mimeType) >> 1);
            $mimeType = $mimeStart.$mimeStart === $mimeType ? $mimeStart : $mimeType;
        }

        return $mimeType ?: null;
    }
}
