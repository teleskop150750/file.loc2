<?php

namespace FileManager\Repositories;

use FileManager\DB\DB;
use FileManager\Entity\UserEntity;

class UserRepository extends Repository
{
    public function find(int $id): UserEntity|null
    {
        $table = UserEntity::$table;

        if ($file = DB::query("SELECT * FROM `{$table}` WHERE id = ?")->bind($id)->first()) {
            return new UserEntity(
                (int) $file['id'],
                $file['login'],
                $file['password'],
            );
        }

        return null;
    }

    public function findByLogin(string $login): UserEntity|null
    {
        $table = UserEntity::$table;

        if ($file = DB::query("SELECT * FROM `{$table}` WHERE login = ?")->bind($login)->first()) {
            return new UserEntity(
                (int) $file['id'],
                $file['login'],
                $file['password'],
            );
        }

        return null;
    }
}
