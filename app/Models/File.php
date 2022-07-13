<?php

namespace App\Models;

use App\Database;
use App\Helper;

class File
{
    /**
     * READ all
     *
     * @param  integer  $count
     * @return array
     */
    public static function index(int $count = 0): array
    {
        if ($count === 0) {
            Database::query("SELECT * FROM files ORDER BY created_at DESC");
        } else {
            Database::query("SELECT * FROM files ORDER BY created_at DESC LIMIT :count");
            Database::bind(':count', $count);
        }

        return Database::fetchAll();
    }

    /**
     * READ one
     *
     * @param  string  $id
     * @return array|bool
     */
    public static function show(string $id): array|bool
    {
        Database::query("SELECT * FROM files WHERE id = :id");
        Database::bind(':id', $id);

        return Database::fetch();
    }

    /**
     * Get by hash
     *
     * @param  string  $hash
     * @return array|bool
     */
    public static function getByHash(string $hash): array|bool
    {
        Database::query("SELECT * FROM files WHERE hash = :hash");
        Database::bind(':hash', $hash);

        return Database::fetch();
    }

    /**
     * STORE
     *
     * @param object $request
     * @return bool
     */
    public static function store(object $request): bool
    {
        Database::query("INSERT INTO files (
            `id`,
            `name`,
            `url`,
            `hash`
        ) VALUES (:id, :name, :url, :hash)");
        Database::bind([
            ':id' => $request->id,
            ':name' => $request->name,
            ':url' => $request->url,
            ':hash' => $request->hash,
        ]);

        if (Database::execute()) {
            return true;
        }

        return false;
    }

    /**
     * DELETE
     *
     * @param  string  $id
     * @return bool
     */
    public static function delete(string $id): bool
    {
        Database::query("DELETE FROM files WHERE id = :id");
        Database::bind(':id', $id);

        if (Database::execute()) {
            return true;
        }

        return false;
    }
}
