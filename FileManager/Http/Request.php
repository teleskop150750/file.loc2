<?php

namespace FileManager\Http;

class Request
{
    /**
     * Query string parameters ($_GET).
     *
     * @var ParameterBag
     */
    public ParameterBag $query;

    /**
     * Uploaded files ($_FILES).
     *
     * @var FileBag
     */
    public FileBag $files;

    private function __construct(array $query = [], array $files = [])
    {
        $this->initialize($query, $files);
    }
    
    public static function createFromGlobals(): static
    {
        return new static($_GET, $_FILES);
    }
    public function initialize(array $query, array $files): void
    {
        $this->query = new ParameterBag($query);
        $this->files = new FileBag($files);
    }
}
