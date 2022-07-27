<?php

namespace FileManager\Modules\Http;

class HeaderUtils
{
    public const DISPOSITION_ATTACHMENT = 'attachment';
    public const DISPOSITION_INLINE = 'inline';

    private function __construct()
    {
    }

    /**
     * Разбивает HTTP-заголовок на один или несколько разделителей.
     *
     * Пример:
     *
     *     HeaderUtils::split("da, en-gb;q=0.8", ",;")
     *     // => ['da'], ['en-gb', 'q=0.8']]
     *
     * @param string $separators Список символов для разделения в порядке приоритета ",", ";=", ",;="
     *
     * @return array Nested array with as many levels as there are characters in
     *               $separators
     */
    public static function split(string $header, string $separators): array
    {
        $quotedSeparators = preg_quote($separators, '/');

        preg_match_all('
            /
                (?!\s)
                    (?:
                        # quoted-string
                        "(?:[^"\\\\]|\\\\.)*(?:"|\\\\|$)
                    |
                        # token
                        [^"'.$quotedSeparators.']+
                    )+
                (?<!\s)
            |
                # separator
                \s*
                (?<separator>['.$quotedSeparators.'])
                \s*
            /x', trim($header), $matches, \PREG_SET_ORDER);

        return self::groupParts($matches, $separators);
    }

    /**
     * Объединяет массив массивов в один ассоциативный массив.
     *
     * Каждый из вложенных массивов должен содержать один или два элемента. Первый
     * значение будет использоваться в качестве ключей в ассоциативном массиве, а второй
     * будет использоваться в качестве значений или true, если вложенный массив содержит только один элемент.
     * Ключи массива записываются в нижнем регистре.
     *
     * Пример:
     *
     *     HeaderUtils::combine([["foo", "abc"], ["bar"]])
     *     // => ["foo" => "abc", "bar" => true]
     */
    public static function combine(array $parts): array
    {
        $assoc = [];
        foreach ($parts as $part) {
            $name = strtolower($part[0]);
            $value = $part[1] ?? true;
            $assoc[$name] = $value;
        }

        return $assoc;
    }

    /**
     * Объединяет ассоциативный массив в строку для использования в заголовке HTTP.
     *
     * Ключ и значение каждой записи объединяются с помощью "=", и все записи
     * соединяются указанным разделителем и дополнительным пробелом (для удобства чтения).
     * При необходимости значения приводятся в кавычках.
     *
     * Пример:
     *
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
     * При необходимости кодирует строку в виде строки в кавычках.
     *
     * Если строка содержит символы, не разрешенные конструкцией "token" в
     * спецификации HTTP, она экранируется обратной косой чертой и заключена в кавычки,
     * чтобы соответствовать конструкции "строка в кавычках".
     */
    public static function quote(string $s): string
    {
        if (preg_match('/^[a-z0-9!#$%&\'*.^_`|~-]+$/i', $s)) {
            return $s;
        }

        return '"'.addcslashes($s, '"\\"').'"';
    }

    /**
     * Декодирует строку, заключенную в кавычки.
     *
     * Если передается строка без кавычек, которая соответствует конструкции "token" (как
     * определено в спецификации HTTP), она передается дословно.
     */
    public static function unquote(string $s): string
    {
        return preg_replace('/\\\\(.)|"/', '$1', $s);
    }

    /**
     * Генерирует значение поля HTTP Content-Disposition.
     *
     * @param string $disposition      One of "inline" or "attachment"
     * @param string $filename         A unicode string
     * @param string $filenameFallback A string containing only ASCII characters that
     *                                 is semantically equivalent to $filename. If the filename is already ASCII,
     *                                 it can be omitted, or just copied from $filename
     *
     * @throws \InvalidArgumentException
     *
     * @see RFC 6266
     */
    public static function makeDisposition(string $disposition, string $filename, string $filenameFallback = ''): string
    {
        if (!\in_array($disposition, [self::DISPOSITION_ATTACHMENT, self::DISPOSITION_INLINE])) {
            throw new \InvalidArgumentException(sprintf('The disposition must be either "%s" or "%s".', self::DISPOSITION_ATTACHMENT, self::DISPOSITION_INLINE));
        }

        if ('' === $filenameFallback) {
            $filenameFallback = $filename;
        }

        // filenameFallback is not ASCII.
        if (!preg_match('/^[\x20-\x7e]*$/', $filenameFallback)) {
            throw new \InvalidArgumentException('The filename fallback must only contain ASCII characters.');
        }

        // percent characters aren't safe in fallback.
        if (str_contains($filenameFallback, '%')) {
            throw new \InvalidArgumentException('The filename fallback cannot contain the "%" character.');
        }

        // path separators aren't allowed in either.
        if (str_contains($filename, '/') || str_contains($filename, '\\') || str_contains($filenameFallback, '/') || str_contains($filenameFallback, '\\')) {
            throw new \InvalidArgumentException('The filename and the fallback cannot contain the "/" and "\\" characters.');
        }

        $params = ['filename' => $filenameFallback];
        if ($filename !== $filenameFallback) {
            $params['filename*'] = "utf-8''".rawurlencode($filename);
        }

        return $disposition.'; '.self::toString($params, ';');
    }

    /**
     * Как parse_str(), но сохраняет точки в именах переменных.
     */
    public static function parseQuery(string $query, bool $ignoreBrackets = false, string $separator = '&'): array
    {
        $q = [];

        foreach (explode($separator, $query) as $v) {
            if (false !== $i = strpos($v, "\0")) {
                $v = substr($v, 0, $i);
            }

            if (false === $i = strpos($v, '=')) {
                $k = urldecode($v);
                $v = '';
            } else {
                $k = urldecode(substr($v, 0, $i));
                $v = substr($v, $i);
            }

            if (false !== $i = strpos($k, "\0")) {
                $k = substr($k, 0, $i);
            }

            $k = ltrim($k, ' ');

            if ($ignoreBrackets) {
                $q[$k][] = urldecode(substr($v, 1));

                continue;
            }

            if (false === $i = strpos($k, '[')) {
                $q[] = bin2hex($k).$v;
            } else {
                $q[] = bin2hex(substr($k, 0, $i)).rawurlencode(substr($k, $i)).$v;
            }
        }

        if ($ignoreBrackets) {
            return $q;
        }

        parse_str(implode('&', $q), $q);

        $queryMap = [];

        foreach ($q as $k => $v) {
            if (false !== $i = strpos($k, '_')) {
                $queryMap[substr_replace($k, hex2bin(substr($k, 0, $i)).'[', 0, 1 + $i)] = $v;
            } else {
                $queryMap[hex2bin($k)] = $v;
            }
        }

        return $queryMap;
    }

    private static function groupParts(array $matches, string $separators, bool $first = true): array
    {
        $separator = $separators[0];
        $partSeparators = substr($separators, 1);

        $i = 0;
        $partMatches = [];
        $previousMatchWasSeparator = false;
        foreach ($matches as $match) {
            if (!$first && $previousMatchWasSeparator && isset($match['separator']) && $match['separator'] === $separator) {
                $previousMatchWasSeparator = true;
                $partMatches[$i][] = $match;
            } elseif (isset($match['separator']) && $match['separator'] === $separator) {
                $previousMatchWasSeparator = true;
                ++$i;
            } else {
                $previousMatchWasSeparator = false;
                $partMatches[$i][] = $match;
            }
        }

        $parts = [];
        if ($partSeparators) {
            foreach ($partMatches as $matches) {
                $parts[] = self::groupParts($matches, $partSeparators, false);
            }
        } else {
            foreach ($partMatches as $matches) {
                $parts[] = self::unquote($matches[0][0]);
            }

            if (!$first && 2 < \count($parts)) {
                $parts = [
                    $parts[0],
                    implode($separator, \array_slice($parts, 1)),
                ];
            }
        }

        return $parts;
    }
}
