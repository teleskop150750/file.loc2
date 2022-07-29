<?php

namespace FileManager\Utils;

use Exception;

class Str
{
    /**
     * Сгенерировать случайную строку
     *
     * @param  int  $length
     *
     * @return string
     */
    public static function random(int $length = 40): string
    {
        $string = '';

        while (($len = strlen($string)) < $length) {
            $size = $length - $len;

            try {
                $bytes = random_bytes($size);
            } catch (Exception $exception) {
                echo 'random_bytes: '.$exception->getMessage();
            }

            $string .= substr(str_replace(['/', '+', '=', ' '], '', base64_encode($bytes)), 0, $size);
        }

        return $string;
    }
}
