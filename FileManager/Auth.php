<?php

namespace FileManager;

use FileManager\Entity\UserEntity;

class Auth
{
    public static function check(): bool
    {
        return true;
    }

    public static function id(): ?int
    {
        return 1;
    }

    public static function user(): ?UserEntity
    {
        $user = new UserEntity();
        $user->setId(1);

        return $user;
    }
}
