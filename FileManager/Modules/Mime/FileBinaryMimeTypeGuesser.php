<?php

namespace FileManager\Modules\Mime;

use InvalidArgumentException;
use LogicException;

class FileBinaryMimeTypeGuesser implements MimeTypeGuesserInterface
{
    private string $cmd;

    /**
     *
     * Шаблон $cmd должен содержать строку "%s", которая будет заменена именем файла.
     * Вывод команды должен начинаться с MIME-типа файла.
     *
     * @param string $cmd Команда, которую нужно выполнить, чтобы получить MIME-тип файла
     */
    public function __construct(string $cmd = 'file -b --mime -- %s 2>/dev/null')
    {
        $this->cmd = $cmd;
    }

    /**
     * {@inheritdoc}
     */
    public function isGuesserSupported(): bool
    {
        static $supported = null;

        if (null !== $supported) {
            return $supported;
        }

        if ('\\' === \DIRECTORY_SEPARATOR || !\function_exists('passthru') || !\function_exists('escapeshellarg')) {
            return $supported = false;
        }

        ob_start();
        passthru('command -v file', $exitStatus);
        $binPath = trim(ob_get_clean());

        return 0 === $exitStatus && '' !== $binPath;
    }

    /**
     * {@inheritdoc}
     */
    public function guessMimeType(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException(sprintf('Файл "%s" не существует или недоступен для чтения.', $path));
        }

        if (!$this->isGuesserSupported()) {
            throw new LogicException(sprintf('Guesser "%s" не поддерживается.', __CLASS__));
        }

        ob_start();

        passthru(sprintf($this->cmd, escapeshellarg((str_starts_with($path, '-') ? './' : '').$path)), $return);

        if ($return > 0) {
            ob_end_clean();

            return null;
        }

        $type = trim(ob_get_clean());

        if (!preg_match('#^([a-z\d\-]+/[a-z\d\-+.]+)#i', $type, $match)) {
            return null;
        }

        return $match[1];
    }
}
