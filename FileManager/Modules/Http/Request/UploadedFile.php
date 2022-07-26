<?php


namespace FileManager\Modules\Http\Request;

use Psr\Http\Message\StreamInterface;
use RuntimeException;
use const LOCK_EX;
use const UPLOAD_ERR_CANT_WRITE;
use const UPLOAD_ERR_EXTENSION;
use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_NO_TMP_DIR;
use const UPLOAD_ERR_OK;
use const UPLOAD_ERR_PARTIAL;

class UploadedFile
{
    /**
     * Полный путь к файлу, предоставленному клиентом.
     */
    protected ?string $file;

    /**
     * Поток, представляющий загруженный файл.
     */
    protected ?StreamInterface $stream = null;

    /**
     * Копировать ли файл в поток при первом вызове getStream().
     */
    protected bool $isFileToStream = false;

    /**
     * Размер файла на основе ключа «size» в массиве $_FILES..
     */
    protected ?int $size;

    /**
     * Имя файла на основе ключа «name» в массиве $_FILES..
     */
    protected ?string $name;

    /**
     * Тип файла. Это значение основано на ключе «type» в массиве $_FILES..
     */
    protected ?string $type;

    /**
     * Код ошибки, связанный с загруженным файлом.
     */
    protected int $error;

    /**
     * Проверьте, был ли загруженный файл перемещен или нет.
     */
    protected bool $isMoved = false;

    private string $sapi;

    /**
     * UploadedFile constructor.
     *
     * @param  string|StreamInterface  $source  Полный путь к файлу или потоку.
     * @param  string|null  $name  Имя файла.
     * @param  string|null  $type  Media type.
     * @param  int|null  $size  Размер файла в байтах.
     * @param  int  $error  Код состояния загрузки.
     */
    public function __construct(
        StreamInterface|string $source,
        ?string $name = null,
        ?string $type = null,
        ?int $size = null,
        int $error = 0,
    ) {
        $this->file = $source;
        $this->name = $name;
        $this->type = $type;
        $this->size = $size;
        $this->error = $error;
        $this->sapi = PHP_SAPI;
    }

    public function getStream(): StreamInterface
    {
        if ($this->isMoved) {
            throw new RuntimeException('Поток был перемещен.');
        }

        if (!$this->isFileToStream && !$this->stream) {
            $resource = @fopen($this->file, 'rb');

            if (is_resource($resource)) {
                $this->stream = new Stream($resource);
            }

            $this->isFileToStream = true;
        }

        if (!$this->stream) {
            throw new RuntimeException('Поток недоступен или не может быть создан.');
        }

        return $this->stream;
    }

    public function moveTo($targetPath): void
    {
        if ($this->isMoved) {
            throw new RuntimeException('Загруженный файл уже перемещен');
        }

        if (!is_writable(dirname($targetPath))) {
            throw new RuntimeException(sprintf('Целевой путь "%s" недоступен для записи.', $targetPath));
        }

        if (is_string($this->file) && !empty($this->file)) {
            $this->moveFile($targetPath);
        } elseif ($this->stream instanceof StreamInterface) {
            $this->moveStream($targetPath);
        }

        $this->isMoved = true;
    }

    private function moveFile(string $targetPath): void
    {
        if ($this->sapi === 'cli') {
            if (!rename($this->file, $targetPath)) {
                throw new RuntimeException(
                    sprintf('Не удалось переименовать файл в целевой путь "%s".', $targetPath)
                );
            }
        } elseif (!is_uploaded_file($this->file) || !move_uploaded_file($this->file, $targetPath)) {
            throw new RuntimeException(
                sprintf('Не удалось переместить файл по целевому пути "%s".', $targetPath)
            );
        }
    }

    private function moveStream(string $targetPath): void
    {
        $content = $this->stream->getContents();
        file_put_contents($targetPath, $content, LOCK_EX);

        if (!file_exists($targetPath)) {
            throw new RuntimeException(
                sprintf('Не удалось переместить поток в целевой путь "%s".', $targetPath)
            );
        }

        unset($content, $this->stream);
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->name;
    }

    public function getClientMediaType(): ?string
    {
        return $this->type;
    }

    public function getFile(): StreamInterface|string|null
    {
        return $this->file;
    }

    /**
     * Получить сообщение об ошибке при загрузке файлов.
     *
     * @return string
     */
    public function getErrorMessage(): string
    {
        $message = [
            UPLOAD_ERR_INI_SIZE => 'Загруженный файл превышает директиву upload_max_filesize в php.ini',
            UPLOAD_ERR_FORM_SIZE => 'Загруженный файл превышает директиву MAX_FILE_SIZE, указанную в HTML-форме.',
            UPLOAD_ERR_PARTIAL => 'Загруженный файл был загружен только частично.',
            UPLOAD_ERR_NO_FILE => 'Ни один файл не был загружен.',
            UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка.',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск.',
            UPLOAD_ERR_EXTENSION => 'Загрузка файла остановлена расширением.',
            UPLOAD_ERR_OK => 'Ошибки нет, файл успешно загружен.',
        ];

        return $message[$this->error] ?? 'Неизвестная ошибка загрузки.';
    }
}
