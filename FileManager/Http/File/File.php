<?php

namespace FileManager\Http\File;

use FileManager\Http\File\Exception\FileException;
use RuntimeException;

class File extends \SplFileInfo
{
    public function __construct(string $path)
    {
        if (!is_file($path)) {
            throw new RuntimeException($path);
        }

        parent::__construct($path);
    }

    protected function getTargetFile(string $directory, string $name = null): self
    {
        if (!is_dir($directory)) {
            if (false === @mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new FileException(sprintf('Не удалось создать каталог "%s".', $directory));
            }
        } elseif (!is_writable($directory)) {
            throw new FileException(sprintf('Невозможно записать в каталог "%s".', $directory));
        }

        $target = rtrim($directory, '/\\') . \DIRECTORY_SEPARATOR . (null === $name ? $this->getBasename() : $this->getName($name));

        return new self($target, false);
    }

    /**
     * @throws RuntimeException
     */
    public function getContent(): string
    {
        $content = file_get_contents($this->getPathname());

        if (false === $content) {
            throw new RuntimeException(sprintf('Не удалось получить содержимое файла "%s".', $this->getPathname()));
        }

        return $content;
    }

    protected function getName(string $name): string
    {
        $originalName = str_replace('\\', '/', $name);
        $pos = strrpos($originalName, '/');

        return false === $pos ? $originalName : substr($originalName, $pos + 1);
    }
}
