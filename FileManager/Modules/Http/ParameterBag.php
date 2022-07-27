<?php

namespace FileManager\Modules\Http;

use ArrayIterator;
use Countable;
use FileManager\Modules\Http\Exception\BadRequestException;
use InvalidArgumentException;
use IteratorAggregate;
use function count;

class ParameterBag implements IteratorAggregate, Countable
{
    /**
     * Хранение параметров.
     */
    protected array $parameters;

    public function __construct(array $parameters = [])
    {
        $this->parameters = $parameters;
    }

    /**
     * Возвращает параметры.
     *
     * @param string|null $key Имя возвращаемого параметра или null, чтобы получить их все
     */
    public function all(string $key = null): array
    {
        if (null === $key) {
            return $this->parameters;
        }

        if (!\is_array($value = $this->parameters[$key] ?? [])) {
            throw new BadRequestException(sprintf('Неожиданное значение для параметра "%s": ожидая "array", получил "%s".', $key, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * Возвращает ключи параметров.
     */
    public function keys(): array
    {
        return array_keys($this->parameters);
    }

    public function replace(array $items = []): void
    {
        $this->parameters = $items;
    }

    public function add(array $items = []): void
    {
        $this->parameters = array_replace($this->parameters, $items);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return \array_key_exists($key, $this->parameters) ? $this->parameters[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->parameters[$key] = $value;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->parameters);
    }

    public function remove(string $key)
    {
        unset($this->parameters[$key]);
    }

    public function getAlpha(string $key, string $default = ''): string
    {
        return preg_replace('/[^[:alpha:]]/', '', $this->get($key, $default));
    }

    public function getAlnum(string $key, string $default = ''): string
    {
        return preg_replace('/[^[:alnum:]]/', '', $this->get($key, $default));
    }

    public function getDigits(string $key, string $default = ''): string
    {
        // we need to remove - and + because they're allowed in the filter
        return str_replace(['-', '+'], '', $this->filter($key, $default, \FILTER_SANITIZE_NUMBER_INT));
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function getBoolean(string $key, bool $default = false): bool
    {
        return $this->filter($key, $default, \FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Filter key.
     *
     * @param int $filter FILTER_* constant
     */
    public function filter(string $key, mixed $default = null, int $filter = \FILTER_DEFAULT, mixed $options = []): mixed
    {
        $value = $this->get($key, $default);

        // Всегда превращайте $options в массив - это позволяет использовать ярлыки опций filter_var.
        if (!\is_array($options) && $options) {
            $options = ['flags' => $options];
        }

        // Add a convenience check for arrays.
        if (\is_array($value) && !isset($options['flags'])) {
            $options['flags'] = \FILTER_REQUIRE_ARRAY;
        }

        if ((\FILTER_CALLBACK & $filter) && !(($options['options'] ?? null) instanceof \Closure)) {
            throw new InvalidArgumentException(sprintf('Замыкание должно быть передано в "%s()" при использовании FILTER_CALLBACK, получено "%s".', __METHOD__, get_debug_type($options['options'] ?? null)));
        }

        return filter_var($value, $filter, $options);
    }

    /**
     * Возвращает итератор для параметров.
     *
     * @return ArrayIterator<string, mixed>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->parameters);
    }

    /**
     * Возвращает количество параметров.
     */
    public function count(): int
    {
        return count($this->parameters);
    }
}
