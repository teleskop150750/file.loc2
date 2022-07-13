<?php

namespace App\Http\Main;

class HeaderUtils
{
    public const DISPOSITION_ATTACHMENT = 'attachment';
    public const DISPOSITION_INLINE = 'inline';

    private function __construct()
    {
    }

    /**
     * Example:
     *     HeaderUtils::toString(["foo" => "abc", "bar" => true, "baz" => "a b c"], ",")
     *     // => 'foo=abc, bar, baz="a b c"'
     */
    public static function toString(array $assoc, string $separator): string
    {
        $parts = [];

        foreach ($assoc as $name => $value) {
            if (true === $value) {
                $parts[] = $name;
            } else {
                $parts[] = $name.'='.self::quote($value);
            }
        }

        return implode($separator.' ', $parts);
    }

    /**
     * Кодирует строку как строку в кавычках, если это необходимо
     *
     * @param  string  $s
     * @return string
     */
    public static function quote(string $s): string
    {
        if (preg_match('/^[a-z\d!#$%&\'*.^_`|~-]+$/i', $s)) {
            return $s;
        }

        return '"'.addcslashes($s, '"\\"').'"';
    }

    public static function makeDisposition(string $disposition, string $filename, string $filenameFallback = ''): string
    {
        if (!\in_array($disposition, [self::DISPOSITION_ATTACHMENT, self::DISPOSITION_INLINE])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Расположение должно быть либо "%s", либо "%s".',
                    self::DISPOSITION_ATTACHMENT,
                    self::DISPOSITION_INLINE
                )
            );
        }

        if ('' === $filenameFallback) {
            $filenameFallback = $filename;
        }

        // запасной вариант имени файла не является ASCII.
        if (!preg_match('/^[\x20-\x7e]*$/', $filenameFallback)) {
            throw new \InvalidArgumentException('Резервная копия имени файла должна содержать только символы ASCII.');
        }

        // процентные символы небезопасны в резервном варианте.
        if (str_contains($filenameFallback, '%')) {
            throw new \InvalidArgumentException('Резервная копия имени файла не может содержать символ "%"..');
        }

        // разделители путей также не разрешены.
        if (
            str_contains($filename, '/')
            || str_contains($filename, '\\')
            || str_contains($filenameFallback, '/')
            || str_contains($filenameFallback, '\\')
        ) {
            throw new \InvalidArgumentException('Имя файла и резервная копия не могут содержать символы "/" и "\\".');
        }

        $params = ['filename' => $filenameFallback];

        if ($filename !== $filenameFallback) {
            $params['filename*'] = "utf-8''".rawurlencode($filename);
        }

        return $disposition.'; '.self::toString($params, ';');
    }
}
