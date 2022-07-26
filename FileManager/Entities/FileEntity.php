<?php

namespace FileManager\Entities;

class FileEntity
{
    public function __construct(
        public string $id,
        public string $name,
        public string $url,
        public string $hash,
    ) {
    }
}
