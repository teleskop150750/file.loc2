<?php
declare(strict_types=1);

namespace App\Http\Psr17;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use App\Http\Psr7\UploadedFile;
use App\Http\Psr7\Utils\UploadedFileHelper;
use InvalidArgumentException;

class UploadedFileFactory implements UploadedFileFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createUploadedFile(
        StreamInterface $stream,
        int $size = null,
        int $error = \UPLOAD_ERR_OK,
        string $clientFilename = null,
        string $clientMediaType = null
    ): UploadedFileInterface {
        if (!$stream->isReadable()) {
            throw new InvalidArgumentException('Файл не читается.');
        }

        return new UploadedFile(
            $stream,
            $clientFilename,
            $clientMediaType,
            $size,
            $error
        );
    }

    public static function fromGlobal(): array
    {
        $filesParams = $_FILES ?? [];
        $uploadedFiles = [];

        if (!empty($filesParams)) {
            $uploadedFiles = UploadedFileHelper::uploadedFileSpecsConvert(
                UploadedFileHelper::uploadedFileParse($filesParams)
            );
        }

        return $uploadedFiles;
    }
}
