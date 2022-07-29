<?php

namespace FileManager\DB;

use Exception;
use FileManager\DB\Exception\MysqlException;
use FileManager\Settings;
use mysqli;
use mysqli_result;
use mysqli_stmt;
use RuntimeException;

class DB
{
    /**
     * Instance
     *
     * @var DB |null
     */
    private static ?self $instance = null;

    /**
     * Mysqli
     *
     * @var mysqli|null
     */
    private ?mysqli $mysqli = null;

    /**
     * Mysqli Statement
     *
     * @var mysqli_stmt|null
     */
    private ?mysqli_stmt $stmt = null;

    private function __construct()
    {
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $this->mysqli = new mysqli(
                Settings::getDbHost(),
                Settings::getDbUser(),
                Settings::getDbPassword(),
                Settings::getDbName()
            );
            $this->mysqli->set_charset('utf8mb4');
        } catch (Exception $exception) {
            echo 'Mysqli Error: ' . $exception->getMessage();
        }
    }

    /**
     * Подготовить запрос
     *
     * @param  string  $sql
     *
     * @return static
     */
    public static function query(string $sql): static
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        self::$instance->prepare($sql);

        return self::$instance;
    }

    /**
     * Привязать параметры
     *
     * @param  int|float|string|array  $params
     *
     * @return $this
     */
    public function bind(int|float|string|array $params): static
    {
        $params = is_array($params) ? $params : [$params];
        $types = [];

        foreach ($params as $param) {
            $types[] = $this->getBindType($param);
        }

        $this->stmt->bind_param(implode('', $types), ...$params);

        return $this;
    }

    /**
     * Получить количество
     *
     * @return int
     */
    public function count(): int
    {
        $this->execute();

        return (int) $this->getResult()->num_rows;
    }

    /**
     * Получить первый или null
     *
     * @return array|null
     */
    public function first(): array|null
    {
        $this->execute();

        return $this->getResult()->fetch_array(MYSQLI_ASSOC) ?: null;
    }

    /**
     * Получить все
     *
     * @return array
     */
    public function all(): array
    {
        $this->execute();

        return $this->getResult()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Подготовить запрос
     *
     * @param  string  $sql
     *
     * @return void
     */
    private function prepare(string $sql): void
    {
        $this->stmt = $this->mysqli->prepare($sql);
    }

    private function getBindType(mixed $value): string
    {
        return match (true) {
            is_int($value) => 'i',
            is_float($value) => 'd',
            is_string($value) => 's',
            default => 'b',
        };
    }

    /**
     * @return void
     * @throws MysqlException
     */
    public function execute(): void
    {
        if (!$result = $this->stmt->execute()) {
            throw new MysqlException('Ошибка при выполнении запроса', 500);
        }
    }

    private function getResult(): mysqli_result
    {
        return $this->stmt->get_result();
    }

    /**
     * Возвращает сообщение об ошибке mysqli
     *
     * @return string
     */
    public function error(): string
    {
        return $this->mysqli->error;
    }

    /**
     * Закрыть соединение с бд
     *
     * @return void
     */
    public function close(): void
    {
        $this->mysqli->close();
    }

    /**
     * Возвращает необработанное соединение в случае, если это необходимо для чего-то
     *
     * @return mysqli
     */
    public function connection(): mysqli
    {
        return $this->mysqli;
    }

    protected function __clone()
    {
    }

    public function __wakeup()
    {
        throw new RuntimeException('Cannot unserialize singleton');
    }
}
