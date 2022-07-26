<?php

namespace FileManager\Http\Response\File;

use RuntimeException;

class File extends \SplFileInfo
{
    public function __construct(string $path)
    {
        if (!is_file($path)) {
            throw new RuntimeException($path);
        }

        parent::__construct($path);
    }
}
