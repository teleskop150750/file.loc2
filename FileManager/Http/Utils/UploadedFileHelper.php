<?php

namespace FileManager\Http\Utils;

use FileManager\Http\Request\UploadedFile;


class UploadedFileHelper
{
    /**
     * Создайте массив для необходимого загруженного файла PSR-7.
     *
     * @param  array  $files  Array из $_FILES
     * @return UploadedFile|array|string
     */
    public static function uploadedFileParse(array $files): UploadedFile|array|string
    {
        $specTree = [];

        $specFields = [
            0 => 'tmp_name',
            1 => 'name',
            2 => 'type',
            3 => 'size',
            4 => 'error',
        ];

        foreach ($files as $fileKey => $fileValue) {
            if (!isset($fileValue['tmp_name'])) {
                return [];
            }

            if (is_string($fileValue['tmp_name']) || is_numeric($fileValue['tmp_name'])) {
                $specTree[$fileKey] = $fileValue;
            } elseif (is_array($fileValue['tmp_name'])) {
                $tmp = [];

                // Мы хотим узнать, сколько у него уровней массива.
                foreach ($specFields as $i => $attr) {
                    $tmp[$i] = self::uploadedFileNestedFields($fileValue, $attr);
                }

                $parsedTree = array_merge_recursive(
                    $tmp[0], // tmp_name
                    $tmp[1], // name
                    $tmp[2], // type
                    $tmp[3], // size
                    $tmp[4]  // error
                );

                $specTree[$fileKey] = $parsedTree;
                unset($tmp, $parsedTree);
            }
        }

        return self::uploadedFileArrayTrim($specTree);
    }

    /**
     * Узнать, сколько уровней массива имеет.
     *
     * @param  array  $files  Структура данных из $_FILES.
     * @param  string  $attr  Атрибуты файла.
     *
     * @return array
     */
    public static function uploadedFileNestedFields(array $files, string $attr): array
    {
        $result = [];
        $values = $files[$attr];

        foreach ($values as $key => $value) {
            if (is_numeric($key)) {
                $key .= '_';
            }

            $result[$key][$attr] = $value;
        }

        return $result;
    }

    /**
     * @param  array|string  $values
     *
     * @return array|string
     */
    public static function uploadedFileArrayTrim(array|string $values): array|string
    {
        $result = [];

        if (!is_array($values)) {
            return $result;
        }

        foreach ($values as $key => $value) {
            // Восстановите исходные ключи.
            $key = rtrim($key, '_');

            if (is_array($value)) {
                $result[$key] = self::uploadedFileArrayTrim($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Преобразуйте готовый к синтаксическому анализу массив в спецификации PSR-7.
     *
     * @param  array|string  $values
     *
     * @return UploadedFile|array
     */
    public static function uploadedFileSpecsConvert(array|string $values): UploadedFile|array
    {
        $result = [];
        if (!is_array($values)) {
            return $result;
        }

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                // Продолжайте запрашивать self, пока не будет найдена строка.
                $result[$key] = self::uploadedFileSpecsConvert($value);
            } elseif ($key === 'tmp_name') {
                $result = new UploadedFile(
                    $values['tmp_name'],
                    $values['name'],
                    $values['type'],
                    $values['size'],
                    $values['error']
                );
            }
        }

        return $result;
    }
}
