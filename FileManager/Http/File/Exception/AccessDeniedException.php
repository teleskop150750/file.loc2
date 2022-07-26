<?php

namespace FileManager\Http\File\Exception;

/**
 * Выброшено, когда доступ к файлу был запрещен.
 */
class AccessDeniedException extends FileException
{
    public function __construct(string $path)
    {
        parent::__construct(sprintf('Не удалось получить доступ к файлу %s', $path));
    }
}
