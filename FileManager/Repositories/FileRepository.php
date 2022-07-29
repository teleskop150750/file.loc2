<?php

namespace FileManager\Repositories;

use FileManager\DB\DB;
use FileManager\Entity\FileEntity;
use FileManager\Entity\UserEntity;
use RuntimeException;

class FileRepository extends Repository
{
    public function save(FileEntity $file): void
    {
        $table = FileEntity::$table;

        DB::query(
            "INSERT INTO `{$table}` ( `id`, `name`, `origin_name`, `path`, `url`, `hash`, `user_id`)  VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->bind([
            $file->getId(),
            $file->getName(),
            $file->getOriginName(),
            $file->getPath(),
            $file->getUrl(),
            $file->getHash(),
            $file->getUserId(),
        ])->execute();
    }

    public function find(string $id): FileEntity|null
    {
        $table = FileEntity::$table;

        if ($file = DB::query("SELECT * FROM `{$table}` WHERE id = ?")->bind($id)->first()) {
            return new FileEntity(
                $file['id'],
                $file['name'],
                $file['origin_name'],
                $file['path'],
                $file['url'],
                $file['hash'],
                (int) $file['user_id'],
                $file['created_at'],
            );
        }

        return null;
    }

    public function findByHash(string $hash): FileEntity|null
    {
        $table = FileEntity::$table;

        if ($file = DB::query("SELECT * FROM `{$table}` WHERE hash = ?")->bind($hash)->first()) {
            return new FileEntity(
                $file['id'],
                $file['name'],
                $file['origin_name'],
                $file['path'],
                $file['url'],
                $file['hash'],
                (int) $file['user_id'],
                $file['created_at'],
            );
        }

        return null;
    }

    public function all(): array
    {
        $table = FileEntity::$table;
        $files = DB::query("SELECT * FROM `{$table}`")->all();

        return array_map(static function ($file) {
            return new FileEntity(
                $file['id'],
                $file['name'],
                $file['origin_name'],
                $file['path'],
                $file['url'],
                $file['hash'],
                (int) $file['user_id'],
                $file['created_at'],
            );
        }, $files);
    }

    public function delete(string $id): void
    {
        $table = FileEntity::$table;
        DB::query("DELETE FROM `{$table}` WHERE id = ?")->bind($id)->execute();
    }

    public function existsId(string $id): bool
    {
        $table = FileEntity::$table;

        return (bool) DB::query("SELECT id FROM `{$table}` WHERE id = ?")->bind($id)->first();
    }

    public function countByHash(string $hash): int
    {
        $table = FileEntity::$table;

        return DB::query("SELECT id FROM `{$table}` WHERE hash = ?")->bind($hash)->count();
    }

    public function user(int $id): UserEntity
    {
        $user = (new UserRepository())->find($id);

        if (!$user) {
            throw new RuntimeException('Владелец файла не найден');
        }

        return $user;
    }
}
