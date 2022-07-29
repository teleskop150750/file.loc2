<?php

namespace FileManager\Repositories;

use FileManager\Entity\UserEntity;

class UserRepository extends Repository
{
    public function __construct()
    {
        $this->entity = new UserEntity();
    }
}
