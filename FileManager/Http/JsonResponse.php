<?php

namespace FileManager\Http;

use RuntimeException;

class JsonResponse extends Response
{
    public function __construct()
    {
        $this->setHeaders('Content-Type', 'application/json');
    }

    public function setContent(mixed $data): static
    {
        $json = json_encode($data);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(json_last_error_msg());
        }

        $this->content = $json;

        return $this;
    }
}
