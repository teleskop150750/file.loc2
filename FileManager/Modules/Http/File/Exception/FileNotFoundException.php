<?php

namespace FileManager\Modules\Http\File\Exception;

/**
 * Брошен, когда файл не найден.
 */
class FileNotFoundException extends FileException
{
    public function __construct(string $path)
    {
        parent::__construct(sprintf('Файл "%s" не существует', $path));
    }
}
