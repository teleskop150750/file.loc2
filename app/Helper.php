<?php

namespace App;

use JetBrains\PhpStorm\NoReturn;

/**
 * Class Helper
 * @package App
 */
class Helper
{
	/**
	 * Проверка меж сайтового запроса на подделку токена
	 *
	 * @param string $token
	 * @return bool
	 */
	public static function csrf(string $token): bool
	{
        return ($_SESSION['token'] === $token) && time() <= $_SESSION['token-expire'];
    }

	/**
	 * Уменьшите строку, чтобы сделать URL удобным для пользователя
	 *
	 * @param string $str
	 * @param string $delimiter
	 * @param bool $addDate
	 * @return string
	 */
	public static function slug(string $str, string $delimiter = '-', bool $addDate = true): string
	{
		$slug = strtolower(
			trim(
				preg_replace(
					'/[\s-]+/',
					$delimiter,
					preg_replace(
						'/[^A-Za-z\d-]+/',
						$delimiter,
						preg_replace(
							'/&/',
							'and',
							preg_replace(
								'/\'/',
								'',
								iconv('UTF-8', 'ASCII//TRANSLIT', $str)
							)
						)
					)
				),
				$delimiter
			)
		);
		return $slug . ($addDate ? '-' . date('d-m-Y') : '');
	}

	// https://gist.github.com/lindelius/4881d2b27fa04356b5736cad81b8c9de


    /**
     * Сбрасывает заданную переменную вместе с некоторыми дополнительными данными
     *
     * @param mixed $var
     * @param bool $pretty
     */
    #[NoReturn] public static function dump(mixed $var, bool $pretty = true): void
    {
        $backtrace = debug_backtrace();

        echo "<style>
            pre {
                background: dimgrey;
                border-left: 10px solid darkorange;
                color: whitesmoke;
                page-break-inside: avoid;
                font-family: monospace;
                font-size: 15px;
                line-height: 1.4;
                margin-bottom: 1.4em;
                max-width: 100%;
                overflow: auto;
                padding: 1em 1.4em;
                display: block;
                word-wrap: break-word;
            }
        </style>";
        echo "\n<pre>\n";
        if (isset($backtrace[0]['file'])) {
            echo "<i>" . $backtrace[0]['file'] . "</i>\n\n";
        }
        echo "<small>Type:</small> <strong>" . gettype($var) . "</strong>\n";
        echo "<small>Time: " . date('c') . "</small>\n";
        echo "--------------------------\n\n";
        ($pretty) ? var_export($var) : var_dump($var);
        echo "</pre>\n";
    }

	/**
	 * Сбрасывает заданную переменную вместе с некоторыми дополнительными данными
	 *
	 * @param mixed $var
	 * @param bool $pretty
	 */
	#[NoReturn] public static function dd(mixed $var, bool $pretty = true): void
    {
		self::dump($var, $pretty);
		die;
	}

	/**
	 * Регистрируйте пользовательские данные в файле журнала
	 *
	 * @param $message
	 */
	public static function log($message): void
    {
		$logInfo = '[' . date('D Y-m-d h:i:s A') . '] [client ' . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . '] ';

		$logFile = LOG_FILE_BASENAME . date('Ymd') . '.log';
		$fHandler = fopen(LOG_DIR . '/' . $logFile, 'a+');
		fwrite($fHandler, $logInfo . $message . PHP_EOL);
		fclose($fHandler);
	}
}
