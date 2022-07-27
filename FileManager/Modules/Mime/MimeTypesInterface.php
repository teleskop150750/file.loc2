<?php

namespace FileManager\Modules\Mime;

interface MimeTypesInterface extends MimeTypeGuesserInterface
{
    /**
     * Возвращает расширения для данного типа MIME в порядке убывания предпочтения.
     *
     * @return string[]
     */
    public function getExtensions(string $mimeType): array;

    /**
     * Возвращает типы MIME для данного расширения в порядке убывания предпочтения.
     *
     * @return string[]
     */
    public function getMimeTypes(string $ext): array;
}
