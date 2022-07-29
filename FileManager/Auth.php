<?php

namespace FileManager;

use App\Helper;
use FileManager\Entity\UserEntity;
use FileManager\Hashing\Hasher;
use FileManager\Http\Request;
use FileManager\Repositories\UserRepository;

class Auth
{
    public static ?UserEntity $user = null;

    public static function check(): bool
    {
        if (!$user = self::getUser()) {
            return false;
        }

        $request = Request::createFromGlobals();

        return Hasher::check($request->input('password'), $user->getPassword());
    }

    public static function id(): ?int
    {
        return self::getUser()?->getId();
    }

    public static function getUser(): ?UserEntity
    {
        if(is_null(self::$user)) {
            $request = Request::createFromGlobals();

            if (!$login = $request->input('login'))
            {
                return null;
            }

            self::$user = (new UserRepository())->findByLogin($login);
        }

        return self::$user;
    }
}
