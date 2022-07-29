<?php

namespace FileManager\Http\File;

use App\Helper;
use RuntimeException;

class UploadedFile extends File
{
    private string $originalName;
    private string $mimeType;
    private int $error;

    /**
     * Принимает информацию о загруженном файле, предоставленную глобальным PHP $_FILES.
     *
     * Файловый объект создается только в том случае, если загруженный файл действителен (т. е. когда
     * метод isValid() возвращает значение true). В противном случае единственными методами, которые можно было бы назвать
     * в экземпляре UploadedFile:
     *
     *   * getClientOriginalName,
     *   * isValid,
     *   * getError.
     *
     * Вызов любого другого метода для недопустимого экземпляра приведет к непредсказуемому результату..
     *
     * @param  string    $path          Полный временный путь к файлу
     * @param  string    $originalName  Исходное имя загруженного файла.
     * @param  string    $mimeType      Тип файла, указанный в PHP; по умолчанию application/octet-stream
     * @param  int|null  $error         Константа ошибки загрузки (одна из констант PHP UPLOAD_ERR_XXX); null по умолчанию UPLOAD_ERR_OK
     */
    public function __construct(
        string $path,
        string $originalName,
        string $mimeType = 'application/octet-stream',
        int $error = null,
    ) {
        $this->originalName = $this->getName($originalName);
        $this->mimeType = $mimeType;
        $this->error = $error ?: \UPLOAD_ERR_OK;

        parent::__construct($path, \UPLOAD_ERR_OK === $this->error);
    }

    /**
     * Возвращает исходное имя файла.
     *
     * Извлекается из запроса, из которого был загружен файл.
     * Тогда его не следует рассматривать как безопасное значение.
     */
    public function getClientOriginalName(): string
    {
        return $this->originalName;
    }

    public function getClientMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Возвращает исходное расширение файла.
     *
     * Извлекается из исходного имени загруженного файла.
     * Тогда его не следует рассматривать как безопасное значение.
     */
    public function getClientOriginalExtension(): string
    {
        return pathinfo($this->originalName, \PATHINFO_EXTENSION);
    }

    /**
     * Возвращает ошибку загрузки.
     *
     * Если загрузка прошла успешно, возвращается константа UPLOAD_ERR_OK.
     * В противном случае возвращается одна из других констант UPLOAD_ERR_XXX.
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * Возвращает, был ли файл загружен с помощью HTTP и не произошла ли ошибка.
     */
    public function isValid(): bool
    {
        $isOk = \UPLOAD_ERR_OK === $this->error;

        return $isOk && is_uploaded_file($this->getPathname());
    }

    /**
     * Перемещает файл в новое местоположение.
     */
    public function move(string $directory, string $name = null): File
    {
        $target = $this->getTargetFile($directory, $name);

        set_error_handler(static function ($type, $msg) use (&$error) {
            $error = $msg;
        });

        try {
            $moved = move_uploaded_file($this->getPathname(), $target);
        } finally {
            restore_error_handler();
        }

        if (!$moved) {
            throw new RuntimeException(sprintf('Не удалось переместить файл "%s" в "%s" (%s).', $this->getPathname(),
                $target, strip_tags($error)));
        }

        @chmod($target, 0666 & ~umask());

        return $target;
    }

    /**
     * Возвращает максимальный размер загружаемого файла в соответствии с настройками в php.ini..
     *
     * @return int|float
     */
    public static function getMaxFilesize(): int|float
    {
        $sizePostMax = self::parseFilesize(ini_get('post_max_size'));
        $sizeUploadMax = self::parseFilesize(ini_get('upload_max_filesize'));

        return min($sizePostMax ?: \PHP_INT_MAX, $sizeUploadMax ?: \PHP_INT_MAX);
    }

    private static function parseFilesize(string $size): int|float
    {
        if ('' === $size) {
            return 0;
        }

        $size = strtolower($size);

        $max = ltrim($size, '+');
        if (str_starts_with($max, '0x')) {
            $max = \intval($max, 16);
        } elseif (str_starts_with($max, '0')) {
            $max = \intval($max, 8);
        } else {
            $max = (int) $max;
        }

        switch (substr($size, -1)) {
            case 't':
                $max *= 1024;
            case 'g':
                $max *= 1024;
            case 'm':
                $max *= 1024;
            case 'k':
                $max *= 1024;
        }

        return $max;
    }

    public function getErrorMessage(): string
    {
        static $errors = [
            \UPLOAD_ERR_INI_SIZE => 'Файл "%s" превышает размер вашей ini-директивы upload_max_filesize (предел %d КиБ).',
            \UPLOAD_ERR_FORM_SIZE => 'Файл "%s" превышает лимит загрузки, указанный в вашей форме.',
            \UPLOAD_ERR_PARTIAL => 'Файл "%s" был загружен только частично.',
            \UPLOAD_ERR_NO_FILE => 'Файл не загружен.',
            \UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл "%s" на диск.',
            \UPLOAD_ERR_NO_TMP_DIR => 'Не удалось загрузить файл: отсутствует временный каталог.',
            \UPLOAD_ERR_EXTENSION => 'Загрузка файла была остановлена расширением PHP.',
        ];

        $errorCode = $this->error;
        $maxFilesize = \UPLOAD_ERR_INI_SIZE === $errorCode ? self::getMaxFilesize() / 1024 : 0;
        $message = $errors[$errorCode] ?? 'Файл "%s" не был загружен из-за неизвестной ошибки.';

        return sprintf($message, $this->getClientOriginalName(), $maxFilesize);
    }
}
