<?php

namespace FileManager\Http\File\Exception;

class UnexpectedTypeException extends FileException
{
    public function __construct(mixed $value, string $expectedType)
    {
        parent::__construct(sprintf('Ожидаемый аргумент типа %s, задано %s', $expectedType, get_debug_type($value)));
    }
}
