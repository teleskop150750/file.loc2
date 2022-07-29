<?php

namespace FileManager\Repositories;

use FileManager\DB\DB;
use FileManager\Entity\FileEntity;

class FileRepository extends Repository
{
    public function __construct()
    {
        $this->entity = new FileEntity();
    }

    public function save(FileEntity $file): void
    {
        DB::query(
            "INSERT INTO files ( `id`, `name`, `origin_name`, `path`, `url`, `hash`, `user_id`)  VALUES (?, ?, ?, ?, ?, ?, ?)"
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
        if ($file = DB::query("SELECT * FROM `{$this->entity->table}` WHERE id = ?")->bind($id)->first()) {
            return new FileEntity(
                $file['id'],
                $file['name'],
                $file['origin_name'],
                $file['path'],
                $file['url'],
                $file['hash'],
                $file['user_id'],
                $file['created_at'],
            );
        }

        return null;
    }

    public function findByHash(string $hash): FileEntity|null
    {
        if ($file = DB::query("SELECT * FROM `{$this->entity->table}` WHERE hash = ?")->bind($hash)->first()) {
            return new FileEntity(
                $file['id'],
                $file['name'],
                $file['origin_name'],
                $file['path'],
                $file['url'],
                $file['hash'],
                $file['user_id'],
                $file['created_at'],
            );
        }

        return null;
    }

    public function all(): array
    {
        $files = DB::query("SELECT * FROM `{$this->entity->table}`")->all();

        return array_map(static function ($file) {
            return new FileEntity(
                $file['id'],
                $file['name'],
                $file['origin_name'],
                $file['path'],
                $file['url'],
                $file['hash'],
                $file['user_id'],
                $file['created_at'],
            );
        }, $files);
    }

    public function delete(string $id): void
    {
        DB::query("DELETE FROM files WHERE id = ?")->bind($id)->execute();
    }

    public function existsId(string $id): bool
    {
        return (bool) DB::query("SELECT id FROM `{$this->entity->table}` WHERE id = ?")->bind($id)->first();
    }

    public function countByHash(string $hash): int
    {
        return DB::query("SELECT id FROM files WHERE hash = ?")->bind($hash)->count();
    }
}
