<?php

namespace FileManager\Http;

use FileManager\FileSystem\Exception\FileException;
use FileManager\Http\File\File;
use LogicException;
use SplFileInfo;

class BinaryFileResponse extends Response
{
    protected File $file;

    public function __construct(SplFileInfo|string $file)
    {
        $this->setFile($file);
    }

    private function setFile(SplFileInfo|string $file): static
    {
        if (!$file instanceof File) {
            if ($file instanceof SplFileInfo) {
                $file = new File($file->getPathname());
            } else {
                $file = new File((string) $file);
            }
        }

        if (!$file->isReadable()) {
            throw new FileException('Файл должен быть удобочитаемым.');
        }

        $this->file = $file;

        return $this;
    }

    public function setContent(?string $content): static
    {
        if (null !== $content) {
            throw new LogicException('Содержимое не может быть установлено в экземпляре BinaryFileResponse.');
        }

        return $this;
    }

    public function getContent(): string|false
    {
        return false;
    }

    protected function sendContent(): void
    {
        $out = fopen('php://output', 'wb');
        $file = fopen($this->file->getPathname(), 'rb');

        stream_copy_to_stream($file, $out, -1, 0);

        fclose($out);
        fclose($file);
    }
}
