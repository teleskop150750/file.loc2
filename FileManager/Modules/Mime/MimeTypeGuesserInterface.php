<?php

namespace FileManager\Modules\Mime;

use InvalidArgumentException;
use LogicException;

/**
 * Угадывает MIME-тип файла.
 */
interface MimeTypeGuesserInterface
{
    /**
     * Возвращает значение true, если этот guesser поддерживается.
     */
    public function isGuesserSupported(): bool;

    /**
     * Угадывает MIME-тип файла с заданным путем.
     *
     * @throws LogicException Если средство угадывания не поддерживается
     * @throws InvalidArgumentException Если файл не существует или недоступен для чтения
     */
    public function guessMimeType(string $path): ?string;
}
