<?php

namespace FileManager\Modules\Http;

use FileManager\Modules\Http\Exception\BadRequestException;

final class InputBag extends ParameterBag
{
    /**
     * Returns a scalar input value by name.
     *
     * @param  string|int|float|bool|null  $default  The default value if the input key does not exist
     */
    public function get(string $key, mixed $default = null): string|int|float|bool|null
    {
        if (null !== $default && !is_scalar($default) && !$default instanceof \Stringable) {
            throw new \InvalidArgumentException(sprintf('Excepted a scalar value as a 2nd argument to "%s()", "%s" given.',
                __METHOD__, get_debug_type($default)));
        }

        $value = parent::get($key, $this);

        if (null !== $value && $this !== $value && !is_scalar($value)) {
            throw new BadRequestException(sprintf('Input value "%s" contains a non-scalar value.', $key));
        }

        return $this === $value ? $default : $value;
    }

    /**
     * Replaces the current input values by a new set.
     */
    public function replace(array $items = []): void
    {
        $this->parameters = [];
        $this->add($items);
    }

    /**
     * Adds input values.
     */
    public function add(array $items = []): void
    {
        foreach ($items as $input => $value) {
            $this->set($input, $value);
        }
    }

    /**
     * Sets an input by name.
     *
     * @param  string|int|float|bool|array|null  $value
     */
    public function set(string $key, mixed $value): void
    {
        if (null !== $value && !is_scalar($value) && !\is_array($value) && !$value instanceof \Stringable) {
            throw new \InvalidArgumentException(sprintf('Исключен скаляр или массив в качестве 2-го аргумента для "%s()", "%s" задан.',
                __METHOD__, get_debug_type($value)));
        }

        $this->parameters[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function filter(
        string $key,
        mixed $default = null,
        int $filter = \FILTER_DEFAULT,
        mixed $options = []
    ): mixed {
        $value = $this->has($key) ? $this->all()[$key] : $default;

        // Всегда превращайте $options в массив - это позволяет использовать filter_var option shortcuts.
        if (!\is_array($options) && $options) {
            $options = ['flags' => $options];
        }

        if (\is_array($value) && !(($options['flags'] ?? 0) & (\FILTER_REQUIRE_ARRAY | \FILTER_FORCE_ARRAY))) {
            throw new BadRequestException(sprintf('Входное значение "%s" содержит массив, но флаги "FILTER_REQUIRE_ARRAY" или "FILTER_FORCE_ARRAY" не были установлены.',
                $key));
        }

        if ((\FILTER_CALLBACK & $filter) && !(($options['options'] ?? null) instanceof \Closure)) {
            throw new \InvalidArgumentException(sprintf('Замыкание должно быть передано в "%s()" при использовании FILTER_CALLBACK, задается "%s".',
                __METHOD__, get_debug_type($options['options'] ?? null)));
        }

        return filter_var($value, $filter, $options);
    }
}
