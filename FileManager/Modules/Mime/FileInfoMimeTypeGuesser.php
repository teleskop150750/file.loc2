<?php

namespace FileManager\Modules\Mime;

use InvalidArgumentException;
use LogicException;

class FileInfoMimeTypeGuesser implements \FileManager\Modules\Mime\MimeTypeGuesserInterface
{
    private ?string $magicFile;

    public function __construct(string $magicFile = null)
    {
        $this->magicFile = $magicFile;
    }

    public function isGuesserSupported(): bool
    {
        return \function_exists('finfo_open');
    }

    public function guessMimeType(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException(sprintf('Файл "%s" не существует или недоступен для чтения.', $path));
        }

        if (!$this->isGuesserSupported()) {
            throw new LogicException(sprintf('Файл "%s" не существует или недоступен для чтения.', __CLASS__));
        }

        if (!$fileInfo = new \finfo(\FILEINFO_MIME_TYPE, $this->magicFile)) {
            return null;
        }

        $mimeType = $fileInfo->file($path);

        if ($mimeType && 0 === (\strlen($mimeType) % 2)) {
            $mimeStart = substr($mimeType, 0, \strlen($mimeType) >> 1);
            $mimeType = $mimeStart.$mimeStart === $mimeType ? $mimeStart : $mimeType;
        }

        return $mimeType;
    }
}
