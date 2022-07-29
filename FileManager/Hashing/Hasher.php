<?php

namespace FileManager\Hashing;


use RuntimeException;

class Hasher
{

    public static function make($value, array $options = []): string
    {
        return password_hash($value, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    /**
     * @param  string  $hashedValue
     *
     * @return array{algo: int, algoName: string, options: array}
     */
    public static function info(string $hashedValue): array
    {
        return password_get_info($hashedValue);
    }

    /**
     * @param  string  $value
     * @param  string  $hashedValue
     *
     * @return bool
     */
    public static function check(string $value, string $hashedValue): bool
    {
        if (self::info($hashedValue)['algoName'] !== 'bcrypt') {
            throw new RuntimeException('Этот пароль не использует алгоритм Bcrypt.');
        }

        if ($hashedValue === '') {
            return false;
        }

        return password_verify($value, $hashedValue);
    }
}
