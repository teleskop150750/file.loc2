<?php

namespace FileManager\Http\File;

use RuntimeException;
use SplFileInfo;

class File extends SplFileInfo
{
    public function __construct(string $path, bool $checkPath = true)
    {
        if ($checkPath && !is_file($path)) {
            throw new RuntimeException($path);
        }

        parent::__construct($path);
    }

    protected function getTargetFile(string $directory, string $name = null): self
    {
        if (!is_dir($directory)) {
            if (false === @mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Не удалось создать каталог "%s".', $directory));
            }
        } elseif (!is_writable($directory)) {
            throw new RuntimeException(sprintf('Невозможно записать в каталог "%s".', $directory));
        }

        $target = rtrim($directory, '/\\') . \DIRECTORY_SEPARATOR . (null === $name ? $this->getBasename() : $this->getName($name));

        return new self($target, false);
    }

    /**
     * Возвращает независимое от локали базовое имя заданного пути.
     */
    protected function getName(string $name): string
    {
        $originalName = str_replace('\\', '/', $name);
        $pos = strrpos($originalName, '/');

        return false === $pos ? $originalName : substr($originalName, $pos + 1);
    }
}
