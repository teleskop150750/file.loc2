<?php
/*
 * This file is part of the App\Http package.
 *
 * (c) Terry L. <contact@terryl.in>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Http\Psr7;

use Psr\Http\Message\StreamInterface;
use InvalidArgumentException;
use RuntimeException;


use const SEEK_CUR;
use const SEEK_END;
use const SEEK_SET;
use const PREG_OFFSET_CAPTURE;

class Stream implements StreamInterface
{
    protected bool $readable;

    protected bool $writable;

    protected bool $seekable;

    protected ?int $size;

    protected array $meta;

    /**
     * @var resource
     */
    protected $stream;

    /**
     * @param  resource  $stream
     */
    public function __construct($stream)
    {
        $this->assertStream($stream);
        $this->stream = $stream;

        $meta = $this->getMetadata();

        $this->readable = false;
        $this->writable = false;

        // Параметр mode указывает тип доступа, который вам требуется для
        // потока. @see https://www.php.net/manual/ru/function.fopen.php
        if (str_contains($meta['mode'], '+')) {
            $this->readable = true;
            $this->writable = true;
        }

        if (preg_match('/^[waxc][t|b]?$/', $meta['mode'], $matches, PREG_OFFSET_CAPTURE)) {
            $this->writable = true;
        }

        if (str_contains($meta['mode'], 'r')) {
            $this->readable = true;
        }

        $this->seekable = $meta['seekable'];
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable(): bool
    {
        return $this->readable;
    }

    /**
     * {@inheritdoc}
     */
    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        if ($this->isStream()) {
            fclose($this->stream);
        }

        $this->detach();
    }

    /**
     * {@inheritdoc}
     */
    public function detach()
    {
        if (!$this->isStream()) {
            return null;
        }

        $legacy = $this->stream;

        $this->readable = false;
        $this->writable = false;
        $this->seekable = false;
        $this->size = null;
        $this->meta = [];

        unset($this->stream);

        return $legacy;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
        if (!$this->isStream()) {
            return null;
        }

        if ($this->size === null) {
            $stats = fstat($this->stream);
            $this->size = $stats['size'] ?? null;
        }

        return $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function tell(): int
    {
        $this->assertPropertyStream();

        $pointer = false;

        if ($this->stream) {
            $pointer = ftell($this->stream);
        }

        if ($pointer === false) {
            throw new RuntimeException('Не удалось получить положение указателя файла в потоке.');
        }

        return $pointer;
    }

    /**
     * {@inheritdoc}
     */
    public function eof(): bool
    {
        return !$this->stream || feof($this->stream);
    }

    /**
     * {@inheritdoc}
     */
    public function seek($offset, $whence = SEEK_SET): void
    {
        $this->assertPropertyStream();

        if (!$this->seekable) {
            throw new RuntimeException('Поток не доступен для поиска.');
        }

        $offset = (int) $offset;
        $whence = (int) $whence;

        $message = [
            SEEK_CUR => 'Установить позицию в текущее местоположение плюс смещение.',
            SEEK_END => 'Установить позицию в конец потока плюс смещение.',
            SEEK_SET => 'Установить позицию, равную байтам смещения.',
        ];

        $errorMsg = $message[$whence] ?? 'Неизвестная ошибка.';

        if (fseek($this->stream, $offset, $whence) === -1) {
            throw new RuntimeException(
                sprintf('%s. Невозможно выполнить поиск потока в позиции %s', $errorMsg, $offset)
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * {@inheritdoc}
     */
    public function write($string): int
    {
        $this->assertPropertyStream();
        $size = 0;

        if ($this->isWritable()) {
            $size = fwrite($this->stream, $string);
        }

        if ($size === false) {
            throw new RuntimeException(
                'Не удается выполнить запись в поток.'
            );
        }

        $this->size = null;

        return $size;
    }

    /**
     * {@inheritdoc}
     */
    public function read($length): string
    {
        $this->assertPropertyStream();
        $string = false;

        if ($this->isReadable()) {
            $string = fread($this->stream, $length);
        }

        if ($string === false) {
            throw new RuntimeException('Не удается прочитать из потока.');
        }

        return $string;
    }

    /**
     * {@inheritdoc}
     */
    public function getContents(): string
    {
        $this->assertPropertyStream();
        $string = false;

        if ($this->isReadable()) {
            $string = stream_get_contents($this->stream);
        }

        if ($string === false) {
            throw new RuntimeException('Не удается прочитать содержимое потока.');
        }

        return $string;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($key = null)
    {
        if ($this->isStream()) {
            $this->meta = stream_get_meta_data($this->stream);

            if (!$key) {
                return $this->meta;
            }

            if (isset($this->meta[$key])) {
                return $this->meta[$key];
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        if ($this->isSeekable()) {
            $this->rewind();
        }

        return $this->getContents();
    }


    protected function assertStream($stream): void
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException(
                sprintf('Поток должен быть ресурсом, но "%s" предоставляется.', gettype($stream))
            );
        }
    }

    protected function assertPropertyStream(): void
    {
        if (!$this->isStream()) {
            throw new RuntimeException('Поток не существует.');
        }
    }

    protected function isStream(): bool
    {
        return (isset($this->stream) && is_resource($this->stream));
    }
}
