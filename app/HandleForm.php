<?php

namespace App;

/**
 * Class HandleForm
 * @package App
 */
class HandleForm
{
    /**
     * Проверяет данные запроса с помощью массива, который включает:
     * значение, тип проверки и пользовательское сообщение об ошибке
     *
     * @param array $validates
     * @return array
     */
    public static function validations(array $validates): array
    {
        $output = [];
        $output['status'] = 'OK';
        $output['message'] = 'Этот процесс был успешно завершен!';

        $defaultMessages = [
            'required' => 'Поле не должно быть пустым!',
            'alphabet' => 'Поле должно быть заполнено буквами алфавита!',
            'number' => 'Поле должно быть заполнено цифрами!',
            'integer' => 'Поле должно быть заполнено целыми числами!',
            'email' => 'Это поле должно соответствовать электронной почте!',
            'ip' => 'Поле должно соответствовать IP-адресу!',
            'ipv6' => 'Поле должно соответствовать by IPv6-адресу!',
            'url' => 'Поле должно соответствовать URL-адресу!',
            'date(m/d/y)' => 'Поле должно быть заполнено date(m/d/y)!',
            'date(m-d-y)' => 'Поле должно быть заполнено date(m-d-y)!',
            'date(d/m/y)' => 'Поле должно быть заполнено date(d/m/y)!',
            'date(d.m.y)' => 'Поле должно быть заполнено date(d.m.y)!',
            'date(d-m-y)' => 'Поле должно быть заполнено date(m-d-y)!',
        ];

        foreach ($validates as $validate) {
            if (!self::validate($validate[0], $validate[1])) {
                $output['status'] = 'ERROR';
                $output['message'] = $validate[2] ?? $defaultMessages[$validate[1]];

                return $output;
            }
        }

        return $output;
    }

    /**
     * Правила проверки достоверности
     * Более подробная информация доступна по адресу https://www.w3resource.com/php/form/php-form-validation.php
     *
     * @param mixed $value
     * @param string $type
     * @return bool
     */
    private static function validate(mixed $value, string $type): bool
    {
        switch ($type) {
            case 'required':
                return !empty($value);
            case 'alphabet':
                preg_match('/^[a-zA-Z]*$/', $value, $matches);
                return !empty($value) && $matches[0];
            case 'number':
                preg_match('/^[0-9]*$/', $value, $matches);
                return !empty($value) && $matches[0];
            case 'integer':
                return !empty($value) && (filter_var($value, FILTER_VALIDATE_INT) === 0 || !filter_var($value, FILTER_VALIDATE_INT) === false);
            case 'email':
                preg_match('/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/', $value, $matches);
                return !empty($value) && $matches[0];
            case 'ip':
                return !empty($value) && !filter_var($value, FILTER_VALIDATE_IP) === false;
            case 'ipv6':
                return !empty($value) && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false;
            case 'url':
                return !empty($value) && !filter_var($value, FILTER_VALIDATE_URL) === false;
            case 'date(m/d/y)':
                $array = explode("/", $value);
                return !empty($value) && checkdate($array[0], $array[1], $array[2]);
            case 'date(m-d-y)':
                $array = explode("-", $value);
                return !empty($value) && checkdate($array[0], $array[1], $array[2]);
            case 'date(d/m/y)':
                $array = explode("/", $value);
                return !empty($value) && checkdate($array[1], $array[0], $array[2]);
            case 'date(d.m.y)':
                $array = explode(".", $value);
                return !empty($value) && checkdate($array[1], $array[0], $array[2]);
            case 'date(d-m-y)':
                $array = explode("-", $value);
                return !empty($value) && checkdate($array[1], $array[0], $array[2]);
            default:
                return false;
        }
    }
}
