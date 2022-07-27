<?php


namespace FileManager\Modules\Http;

use FileManager\Modules\Http\File\File;
use LogicException;
use RuntimeException;

class BinaryFileResponse extends Response
{
    protected static bool $trustXSendfileTypeHeader = false;

    protected File $file;
    protected int $offset = 0;
    protected int $maxLength = -1;
    protected bool $deleteFileAfterSend = false;

    public function __construct(
        \SplFileInfo|string $file,
        int $status = 200,
        array $headers = [],
        string $contentDisposition = null,
    ) {
        parent::__construct(null, $status, $headers);

        $this->setFile($file, $contentDisposition);
    }

    public function setFile(
        \SplFileInfo|string $file,
        string $contentDisposition = null,
    ): static {
        if (!$file instanceof File) {
            if ($file instanceof \SplFileInfo) {
                $file = new File($file->getPathname());
            } else {
                $file = new File((string) $file);
            }
        }

        if (!$file->isReadable()) {
            throw new RuntimeException('Файл должен быть читаемым.');
        }

        $this->file = $file;

        if ($contentDisposition) {
            $this->setContentDisposition($contentDisposition);
        }

        return $this;
    }

    public function getFile(): File
    {
        return $this->file;
    }

    public function setContentDisposition(
        string $disposition,
        string $filename = '',
        string $filenameFallback = ''
    ): static {
        if ('' === $filename) {
            $filename = $this->file->getFilename();
        }

        if (
            '' === $filenameFallback
            && (
                !preg_match('/^[\x20-\x7e]*$/', $filename)
                || str_contains($filename, '%')
            )
        ) {
            $encoding = mb_detect_encoding($filename, null, true) ?: '8bit';

            for ($i = 0, $filenameLength = mb_strlen($filename, $encoding); $i < $filenameLength; ++$i) {
                $char = mb_substr($filename, $i, 1, $encoding);

                if ('%' === $char || \ord($char) < 32 || \ord($char) > 126) {
                    $filenameFallback .= '_';
                } else {
                    $filenameFallback .= $char;
                }
            }
        }

        $dispositionHeader = $this->headers->makeDisposition($disposition, $filename, $filenameFallback);
        $this->headers->set('Content-Disposition', $dispositionHeader);

        return $this;
    }

    public function sendContent(): static
    {
        if (!$this->isSuccessful()) {
            return parent::sendContent();
        }

        if (0 === $this->maxLength) {
            return $this;
        }

        $out = fopen('php://output', 'wb');
        $file = fopen($this->file->getPathname(), 'rb');

        stream_copy_to_stream($file, $out, $this->maxLength, $this->offset);

        fclose($out);
        fclose($file);

        if ($this->deleteFileAfterSend && is_file($this->file->getPathname())) {
            unlink($this->file->getPathname());
        }

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
}
