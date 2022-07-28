<?php

namespace FileManager;

use finfo;
use InvalidArgumentException;
use JetBrains\PhpStorm\NoReturn;
use Exception;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use SplFileInfo;

class FileManagerService
{
    /**
     * Ключ под которым находится загруженный файл в $_FILES
     */
    private const FILE_FIELD_NAME = 'file';

    /**
     * Ключ $_GET параметра содержащий id файла для скачивания
     */
    private const DOWNLOAD_GET_PARAMETER = 'get_file';

    /**
     * Ключ $_GET параметра содержащий id файла для удаления
     */
    private const DELETE_GET_PARAMETER = 'delete_file';

    // Response
    /**
     * Статус ответа в текстовом виде
     *
     * @var string
     */
    private string $statusText = 'OK';

    /**
     * Статус ответа
     *
     * @var int
     */
    private int $statusCode = 200;

    /**
     * Версия протокола HTTP
     *
     * @var string
     */
    private string $protocolVersion = '1.0';

    /**
     * Файл на скачивание
     *
     * @var SplFileInfo|null
     */
    private ?SplFileInfo $downloadedFile = null;

    /**
     * Заголовки ответа
     *
     * @var array<string, array >
     */
    private array $responseHeaders = [];

    /**
     * Тело ответа
     *
     * @var string|null
     */
    private string|null $responseContent = null;

    /**
     * Загруженный файл
     *
     * @var array{name: string, full_path: string, type: string, error: int, size: int}
     */
    private array $uploadedFile;

    private const STORAGE_PATH = '/public/storage/';

    // DB
    private static ?PDO $db = null;
    private static ?PDOStatement $dbStmt = null;
    private static string $dbType = 'mysql';
    private static string $dbHost = 'localhost';
    private static string $dbPort = '3306';
    private static string $dbName = 'db_file';
    private static string $dbUser = 'root';
    private static string $dbPass = 'root';

    /**
     * Точка входа
     *
     * @return callable Отправить Response
     */
    public function execute(): callable
    {
        if (isset($_FILES[self::FILE_FIELD_NAME])) {
            $this->upload($_FILES[self::FILE_FIELD_NAME]);
        } elseif (isset($_GET[self::DOWNLOAD_GET_PARAMETER])) {
            $this->download($_GET[self::DOWNLOAD_GET_PARAMETER]);
        } elseif (isset($_GET[self::DELETE_GET_PARAMETER])) {
            $this->delete($_GET[self::DELETE_GET_PARAMETER]);
        } else {
            $this->statusCode = 400;
            $this->statusText = 'Bad Request';
            $this->setHeaders('Content-Type', 'application/json');
            $this->setContentToJson([
                'status' => 'error',
                'message' => 'Неподдерживаемый метод',
            ]);
        }

        return [$this, 'send'];
    }

    /**
     * Загрузить файл
     *
     * @param  array{name: string, full_path: string, type: string, error: int, size: int}  $file
     *
     * @return void
     */
    public function upload(array $file): void
    {
        $this->setHeaders('Content-Type', 'application/json');

        try {
            $this->uploadedFile = $file;

            if (!$this->checkUploadedFile($this->uploadedFile)) {
                throw new RuntimeException($this->getFileErrorMessage($this->uploadedFile));
            }

            $fileId = $this->makeFileId();
            $fileHashName = $this->getFileHashName($this->uploadedFile['name']);
            $folder = date('Y-m-d');
            $filePath = $folder.'/'.$fileHashName;
            $fileUrl = $this->getFileUrl($filePath);
            $fileHash = $this->getFileHash($this->uploadedFile['tmp_name']);

            if (!$foundFile = $this->findByHash($fileHash)) {
                $this->storeInFileSystem($folder, $this->uploadedFile['tmp_name'], $fileHashName);
            } else {
                $fileHashName = $foundFile['name'];
                $fileUrl = $foundFile['url'];
                $filePath = $foundFile['path'];
            }

            $this->saveInDb([
                'id' => $fileId,
                'origin_name' => $this->uploadedFile['name'],
                'name' => $fileHashName,
                'url' => $fileUrl,
                'path' => $filePath,
                'hash' => $fileHash,
            ]);

            $this->setContentToJson([
                'status' => 'success',
                'message' => 'Файл загружен',
                'data' => [
                    'id' => $fileId,
                ],
            ]);
        } catch (Exception $exception) {
            $this->statusCode = 400;
            $this->statusText = 'Bad Request';
            $this->setContentToJson([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Скачать файл
     *
     * @param  string  $id
     *
     * @return void
     */
    public function download(string $id): void
    {
        try {
            $file = $this->find($id);
            $path = self::getStorePath().trim($file['path'], '/');
            $this->downloadedFile = new SplFileInfo($path);

            if (!is_readable($path)) {
                throw new RuntimeException('Файл должен быть читаемым.');
            }

            $this->statusCode = 201;
            $this->statusText = 'Created';
            $this->setHeaders('Content-Type', $this->getMimeType($path) ?: 'text/plain');
            $this->setHeaders('Content-Description', "File Transfer");
            $this->setHeaders('Content-Disposition', "attachment; filename={$file['origin_name']}");
        } catch (Exception $exception) {
            $this->statusCode = 400;
            $this->statusText = 'Bad Request';
            $this->setHeaders('Content-Type', 'application/json');
            $this->setContentToJson([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
            $this->downloadedFile = null;
        }
    }

    public function delete(string $id): void
    {
        $this->setHeaders('Content-Type', 'application/json');

        if ($file = $this->find($id)) {
            if ($this->getCountFilesByHash($file['hash']) === 1) {
                $this->deleteFromFileSystem($file['path']);
            }

            $this->deleteFromDb($id);
            $this->setContentToJson([
                'status' => 'success',
                'message' => "Файл удален",
                'data' => [
                    'id' => $file['id'],
                ],
            ]);
        } else {
            $this->statusCode = 404;
            $this->statusText = 'NOT FOUND';
            $this->setContentToJson([
                'status' => 'error',
                'message' => "Неверный id: $id",
            ]);
        }
    }

    // Response

    /**
     * Отправить Response
     *
     * @return void
     */
    public function send(): void
    {
        $this->sendHeaders();

        if ($this->downloadedFile === null) {
            $this->sendTextContent();
        } else {
            $this->sendFileContent();
        }

        if (\function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * Получить полный путь
     *
     * @param  string  $path
     *
     * @return string
     */
    public static function getFullPath(string $path): string
    {
        return self::getStorePath().trim($path, '/');
    }

    /**
     * Отправить заголовки
     *
     * @return void
     */
    private function sendHeaders(): void
    {
        // headers
        foreach ($this->responseHeaders as $name => $values) {
            $replace = 0 === strcasecmp($name, 'Content-Type');
            foreach ($values as $value) {
                header($name.': '.$value, $replace, $this->statusCode);
            }
        }

        // status
        header(
            sprintf('HTTP/%s %s %s', $this->protocolVersion, $this->statusCode, $this->statusText),
            true,
            $this->statusCode
        );
    }

    /**
     * Установить заголовки
     *
     * @param  string             $key      Ключ
     * @param  string|array|null  $values   Значение
     * @param  bool               $replace  Перезаписать
     *
     * @return void
     */
    private function setHeaders(string $key, string|array|null $values, bool $replace = true): void
    {
        if (is_array($values)) {
            $values = array_values($values);

            if (true === $replace || !isset($this->headers[$key])) {
                $this->responseHeaders[$key] = $values;
            } else {
                $this->responseHeaders[$key] = array_merge($this->headers[$key], $values);
            }
        } elseif (true === $replace || !isset($this->headers[$key])) {
            $this->responseHeaders[$key] = [$values];
        } else {
            $this->responseHeaders[$key][] = $values;
        }
    }


    /**
     * Отправить тело ответа
     *
     * @return void
     */
    private function sendTextContent(): void
    {
        echo $this->responseContent;
    }

    /**
     * Отправить файл в ответе
     *
     * @return void
     */
    private function sendFileContent(): void
    {
        $out = fopen('php://output', 'wb');
        $file = fopen($this->downloadedFile->getPathname(), 'rb');

        stream_copy_to_stream($file, $out, -1, 0);

        fclose($out);
        fclose($file);
    }

    /**
     * Установить тело ответа в формате Json
     *
     * @param  array  $data
     *
     * @return void
     */
    private function setContentToJson(array $data): void
    {
        $this->responseContent = json_encode($data);
    }

    /**
     * Получить MimeType файла
     *
     * @param  string  $path
     *
     * @return string|null
     */
    private function getMimeType(string $path): string|null
    {
        if (!$fileInfo = new finfo(\FILEINFO_MIME_TYPE)) {
            return null;
        }

        $mimeType = $fileInfo->file($path);

        if ($mimeType && 0 === (\strlen($mimeType) % 2)) {
            $mimeStart = substr($mimeType, 0, \strlen($mimeType) >> 1);
            $mimeType = $mimeStart.$mimeStart === $mimeType ? $mimeStart : $mimeType;
        }

        return $mimeType ?: null;
    }

    // upload
    // uploaded File

    /**
     * Проверить загруженный файл
     *
     * @param  array{name: string, full_path: string, type: string, error: int, size: int}  $file
     *
     * @return bool
     */
    private function checkUploadedFile(array $file): bool
    {
        return $file['error'] === 0;
    }

    /**
     * Получить сообщение об ошибке при загрузке файла
     *
     * @param  array{name: string, full_path: string, type: string, error: int, size: int}  $file
     *
     * @return string
     */
    private function getFileErrorMessage(array $file): string
    {
        static $errors = [
            \UPLOAD_ERR_INI_SIZE => 'Файл "%s" превышает размер вашей ini-директивы upload_max_filesize.',
            \UPLOAD_ERR_FORM_SIZE => 'Файл "%s" превышает лимит загрузки, указанный в вашей форме.',
            \UPLOAD_ERR_PARTIAL => 'Файл "%s" был загружен только частично.',
            \UPLOAD_ERR_NO_FILE => 'Файл не загружен.',
            \UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл "%s" на диск.',
            \UPLOAD_ERR_NO_TMP_DIR => 'Не удалось загрузить файл: отсутствует временный каталог.',
            \UPLOAD_ERR_EXTENSION => 'Загрузка файла была остановлена расширением PHP.',
        ];

        $errorCode = $file['error'];
        $message = $errors[$errorCode] ?? 'Файл "%s" не был загружен из-за неизвестной ошибки.';

        return sprintf($message, $this->uploadedFile['name']);
    }

    // FileSystem

    /**
     * Получить Hash содержимого файла
     *
     * @param  string  $path
     *
     * @return string
     */
    private function getFileHash(string $path): string
    {
        return md5_file($path);
    }

    /**
     * Получить Hash имени файла
     *
     * @param  string|null  $path
     *
     * @return string
     */
    private function getFileHashName(string $path = null): string
    {
        if ($path) {
            $path = rtrim($path, ' /');
        }

        $hash = $this->randomStr();
        $hash = preg_replace('/(\/|\\| )/', '_', $hash);
        $hash = strtolower($hash);

        if ($extension = $this->getFileExtension($path)) {
            $extension = '.'.$extension;
        }

        return $hash.$extension;
    }

    /**
     * Получить расширение файла
     *
     * @param  string  $path
     *
     * @return string
     */
    private function getFileExtension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * Сохранить файл в файловой системе
     *
     * @param  string           $path
     * @param  string|resource  $file
     * @param  string           $name
     *
     * @return void
     */
    private function storeInFileSystem(string $path, mixed $file, string $name): void
    {
        $path = self::getStorePath().trim($path.'/'.$name, ' /');
        $this->checkAndCreateDir($path);

        if (file_exists($path)) {
            throw new InvalidArgumentException("Файл уже существует $path");
        }

        $result = move_uploaded_file($file, $path);

        if ($result === false) {
            throw new RuntimeException("Не удалось сохранить файл $path");
        }
    }

    /**
     * Удалить файл по указному пути
     *
     * @param  string  $path
     *
     * @return bool
     */
    #[NoReturn]
    private function deleteFromFileSystem(string $path): bool
    {
        $path = self::getStorePath().trim($path, ' /');
        if (@unlink($path)) {
            clearstatcache(false, $path);

            return true;
        }

        return false;
    }

    /**
     * Проверить наличие директории и создать если ее нет
     *
     * @param  string  $path
     *
     * @return void
     */
    private function checkAndCreateDir(string $path): void
    {
        if (!$this->pathContainsDirectory($path)) {
            return;
        }

        $dir = $this->getDirFromPath($path);

        if (!$this->isDirectory($dir)) {
            $this->makeDirectory($dir);
        }
    }

    /**
     * Проверить наличие директории в пути
     *
     * @param  string  $path
     *
     * @return bool
     */
    private function pathContainsDirectory(string $path): bool
    {
        $pathInfo = pathinfo($path);

        return $pathInfo['dirname'] !== '.';
    }

    /**
     * Определить, является ли данный путь каталогом
     *
     * @param  string  $directory
     *
     * @return bool
     */
    private function isDirectory(string $directory): bool
    {
        return is_dir($directory);
    }

    /**
     * Получить директорию из пути
     *
     * @param  string  $path
     *
     * @return string
     */
    private function getDirFromPath(string $path): string
    {
        $pathInfo = pathinfo($path);

        return $pathInfo['dirname'].DIRECTORY_SEPARATOR;
    }

    /**
     * Создать директорию
     *
     * @param  string  $dir
     *
     * @return void
     */
    private function makeDirectory(string $dir): void
    {
        $result = @mkdir($dir, 0777, true);

        if (!$result) {
            throw new RuntimeException("Не удалось создать директорию $dir");
        }
    }

    /**
     * Получить URL-адрес файла по заданному пути
     *
     * @param  string  $path
     *
     * @return string
     */
    private function getFileUrl(string $path): string
    {
        return self::STORAGE_PATH.$path;
    }

    /**
     * Получить путь до storage
     *
     * @return string
     */
    private static function getStorePath(): string
    {
        return $_SERVER['DOCUMENT_ROOT'].self::STORAGE_PATH;
    }

    // File Db

    /**
     * Создать id файла
     *
     * @return string
     */
    private function makeFileId(): string
    {
        do {
            $id = $this->randomStr();
        } while ($this->existsIdInDb($id));

        return $id;
    }

    /**
     * Найти файл
     *
     * @param  string  $id
     *
     * @return array{id: string, name: string, origin_name: string, path: string, url: string, hash: string}|null
     */
    private function find(string $id): array|null
    {
        self::dbQuery("SELECT * FROM files WHERE id = :id");
        self::stmtBind(':id', $id);

        return self::stmtFetch() ?: null;
    }

    /**
     * Найти файл по Hash
     *
     * @param  string  $hash
     *
     * @return array{id: string, name: string, origin_name: string, path: string, url: string, hash: string}|null
     */
    private function findByHash(string $hash): array|null
    {
        self::dbQuery("SELECT * FROM files WHERE hash = :hash");
        self::stmtBind(':hash', $hash);

        return self::stmtFetch() ?: null;
    }

    /**
     * Удалить файл из БД
     *
     * @param  string  $id
     *
     * @return void
     */
    private function deleteFromDb(string $id): void
    {
        self::dbQuery("DELETE FROM files WHERE id = :id");
        self::stmtBind(':id', $id);

        if (!self::stmtExecute()) {
            throw  new RuntimeException("Не удалось удалить id: $id");
        }
    }

    /**
     * Проверить, существует ли файл с там же id
     *
     * @param  string  $id
     *
     * @return bool
     */
    private function existsIdInDb(string $id): bool
    {
        self::dbQuery("SELECT id FROM files WHERE id = :id");
        self::stmtBind(':id', $id);

        return (bool) self::stmtFetch();
    }

    /**
     * Получить количество одинаковых файлов в БД
     *
     * @param  string  $hash
     *
     * @return int
     */
    private function getCountFilesByHash(string $hash): int
    {
        self::dbQuery("SELECT id FROM files WHERE hash = :hash");
        self::stmtBind(':hash', $hash);
        self::stmtExecute();

        return self::dbStmt()->rowCount();
    }

    /**
     * Сохранить файл в БД
     *
     * @param  array{id: string, name: string, origin_name: string, path: string, url: string, hash: string}  $file
     *
     * @throws RuntimeException
     */
    private function saveInDb(array $file): void
    {
        self::dbQuery(
            "INSERT INTO files ( `id`,  `name`, `origin_name`, `path`, `url`, `hash` ) 
                VALUES (:id, :name, :origin_name, :path, :url, :hash)"
        );

        self::stmtBind([
            ':id' => $file['id'],
            ':name' => $file['name'],
            ':origin_name' => $file['origin_name'],
            ':path' => $file['path'],
            ':url' => $file['url'],
            ':hash' => $file['hash'],
        ]);

        if (!self::stmtExecute()) {
            throw new RuntimeException('Не удалось сохранить в DB');
        }
    }

    // DB

    /**
     * Получить экземпляр PDO
     *
     * @return PDO
     */
    private static function db(): PDO
    {
        if (self::$db === null) {
            self::makeDb();
        }

        return self::$db;
    }

    /**
     * Получить экземпляр PDOStatement
     *
     * @return PDOStatement
     */
    private static function dbStmt(): PDOStatement
    {
        if (self::$dbStmt === null) {
            throw new RuntimeException('PDOStatement не инициализирован');
        }

        return self::$dbStmt;
    }

    /**
     * Создать экземпляр PDO
     *
     * @return void
     */
    private static function makeDb(): void
    {
        $dsn = self::$dbType.':host='.self::$dbHost.';port='.self::$dbPort.';dbname='.self::$dbName;
        $options = [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ];

        try {
            self::$db = new PDO($dsn, self::$dbUser, self::$dbPass, $options);
            self::$db->exec("set names utf8mb4");
        } catch (PDOException $exception) {
            echo 'PDO Error: '.$exception->getMessage();
        }
    }

    /**
     * Построить запрос
     *
     * @param  string  $sql
     *
     * @return void
     */
    private static function dbQuery(string $sql): void
    {
        try {
            self::$dbStmt = self::db()->prepare($sql);
        } catch (PDOException $exception) {
            echo 'PDO Error: '.$exception->getMessage();
        }
    }

    /**
     * Привязать значения
     *
     * @param  string|array  $param
     * @param  mixed|null    $value
     * @param  mixed|null    $type
     *
     * @return void
     */
    private static function stmtBind(string|array $param, mixed $value = null, mixed $type = null): void
    {
        try {
            if (is_array($param)) {
                foreach ($param as $k => $v) {
                    self::stmtBindValue($type, $v, $k);
                }
            } else {
                self::stmtBindValue($type, $value, $param);
            }
        } catch (PDOException $exception) {
            echo 'PDO Error: '.$exception->getMessage();
        }
    }

    /**
     * Привязать значение
     *
     * @param  mixed         $type
     * @param  mixed         $value
     * @param  array|string  $param
     *
     * @return void
     */
    private static function stmtBindValue(mixed $type, mixed $value, array|string $param): void
    {
        if (is_null($type)) {
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
        }

        self::dbStmt()->bindValue($param, $value, $type);
    }

    /**
     * Выполнить запрос
     *
     * @return bool
     */
    private static function stmtExecute(): bool
    {
        try {
            return self::dbStmt()->execute();
        } catch (PDOException $exception) {
            echo 'PDO Error: '.$exception->getMessage();

            return false;
        }
    }

    /**
     * Получить первый элемент
     *
     * @return array|bool
     */
    private static function stmtFetch(): array|bool
    {
        try {
            self::dbStmt()->execute();
        } catch (PDOException $exception) {
            echo 'PDO Error: '.$exception->getMessage();
        }

        return self::dbStmt()->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Получить все
     *
     * @return array|bool
     */
    private static function stmtFetchAll(): array|bool
    {
        try {
            self::dbStmt()->execute();
        } catch (PDOException $exception) {
            echo 'PDO Error: '.$exception->getMessage();
        }

        return self::dbStmt()->fetchAll(PDO::FETCH_ASSOC);
    }

    // String

    /**
     * Сгенерировать случайную строку
     *
     * @param  int  $length
     *
     * @return string
     */
    private function randomStr(int $length = 40): string
    {
        $string = '';

        while (($len = strlen($string)) < $length) {
            $size = $length - $len;

            try {
                $bytes = random_bytes($size);
            } catch (Exception $exception) {
                echo 'random_bytes: '.$exception->getMessage();
            }

            $string .= substr(str_replace(['/', '+', '=', ' '], '', base64_encode($bytes)), 0, $size);
        }

        return $string;
    }
}
