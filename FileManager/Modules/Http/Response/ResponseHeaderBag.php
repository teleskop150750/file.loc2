<?php

namespace FileManager\Modules\Http\Response;


use function FileManager\Http\Response\strtr;

class ResponseHeaderBag
{
    public const DISPOSITION_ATTACHMENT = 'attachment';
    public const DISPOSITION_INLINE = 'inline';
    protected const UPPER = '_ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    protected const LOWER = '-abcdefghijklmnopqrstuvwxyz';
    protected array $headerNames = [];

    /**
     * @var array<string, list<string|null>>
     */
    protected array $headers = [];
    protected array $cacheControl = [];

    public function __construct(array $headers = [])
    {
        foreach ($headers as $key => $values) {
            $this->set($key, $values);
        }
    }

    public function __toString(): string
    {
        if (!$headers = $this->all()) {
            return '';
        }

        ksort($headers);
        $max = max(array_map('strlen', array_keys($headers))) + 1;
        $content = '';

        foreach ($headers as $name => $values) {
            $name = ucwords($name, '-');
            foreach ($values as $value) {
                $content .= sprintf("%-{$max}s %s\r\n", $name.':', $value);
            }
        }

        return $content;
    }

    public function all(string $key = null): array
    {
        if (null !== $key) {
            $uniqueKey = strtr($key, self::UPPER, self::LOWER);
            $this->headerNames[$uniqueKey] = $key;

            return $this->headers[strtr($key, self::UPPER, self::LOWER)] ?? [];
        }

        return $this->headers;
    }


    public function set(string $key, string|array|null $values, bool $replace = true): void
    {
        $uniqueKey = strtr($key, self::UPPER, self::LOWER);
        $this->headerNames[$uniqueKey] = $key;

        $key = strtr($key, self::UPPER, self::LOWER);

        if (\is_array($values)) {
            $values = array_values($values);

            if (true === $replace || !isset($this->headers[$key])) {
                $this->headers[$key] = $values;
            } else {
                $this->headers[$key] = array_merge($this->headers[$key], $values);
            }
        } elseif (true === $replace || !isset($this->headers[$key])) {
            $this->headers[$key] = [$values];
        } else {
            $this->headers[$key][] = $values;
        }
    }

    public function allPreserveCase(): array
    {
        $headers = [];
        foreach ($this->all() as $name => $value) {
            $headers[$this->headerNames[$name] ?? $name] = $value;
        }

        return $headers;
    }

    public function allPreserveCaseWithoutCookies(): array
    {
        $headers = $this->allPreserveCase();
        if (isset($this->headerNames['set-cookie'])) {
            unset($headers[$this->headerNames['set-cookie']]);
        }

        return $headers;
    }

    public function makeDisposition(string $disposition, string $filename, string $filenameFallback = ''): string
    {
        return HeaderUtils::makeDisposition($disposition, $filename, $filenameFallback);
    }
}
