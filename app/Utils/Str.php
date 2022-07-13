<?php

namespace App\Utils;

use voku\helper\ASCII;

class Str
{
    public static function random($length = 40): string
    {
        return (static function ($length) {
                $string = '';

                while (($len = strlen($string)) < $length) {
                    $size = $length - $len;

                    $bytes = random_bytes($size);

                    $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
                }

                return $string;
            })($length);
    }

    public static function ascii($value, $language = 'en'): string
    {
        return ASCII::to_ascii((string) $value, $language);
    }
}
