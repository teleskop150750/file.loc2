<?php

declare(strict_types=1);

namespace App\Http\Psr17;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\StreamFactoryInterface;
use App\Http\Psr7\Stream;
use InvalidArgumentException;
use RuntimeException;

use function fopen;
use function fwrite;
use function is_resource;
use function preg_match;
use function rewind;

class StreamFactory implements StreamFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createStream(string $content = ''): StreamInterface
    {
        $resource = @fopen('php://temp', 'r+');

        self::assertResource($resource);

        fwrite($resource, $content);
        rewind($resource);

        return $this->createStreamFromResource($resource);
    }

    /**
     * {@inheritdoc}
     */
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        if ($mode === '' || !preg_match('/^[rwaxce]{1}[bt]{0,1}[+]{0,1}+$/', $mode)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid file opening mode "%s"',
                    $mode
                )
            );
        }

        $resource = @fopen($filename, $mode);

        if (!is_resource($resource)) {
            throw new RuntimeException(
                sprintf(
                    'Unable to open file at "%s"',
                    $filename
                )
            );
        }

        return new Stream($resource);
    }

    /**
     * {@inheritdoc}
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        if (!is_resource($resource)) {
            $resource = @fopen('php://temp', 'r+');
        }

        self::assertResource($resource);

        return new Stream($resource);
    }

    public static function fromNew(): StreamInterface
    {
        $resource = @fopen('php://temp', 'rb+');
        self::assertResource($resource);

        return new Stream($resource);
    }


    protected static function assertResource(mixed $resource): void
    {
        if (!is_resource($resource)) {
            throw new RuntimeException(
                'Не удается открыть ресурс "php://temp".'
            );
        }
    }
}
