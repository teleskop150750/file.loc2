<?php

namespace FileManager\Http;

use App\Helper;
use FileManager\Utils\Str;

class Request
{
    /**
     * Query string parameters ($_GET).
     */
    public ParameterBag $query;

    /**
     * Query string parameters ($_GET).
     */
    public ParameterBag $request;

    /**
     * Uploaded files ($_FILES).
     */
    public FileBag $files;

    protected ParameterBag $json;

    public HeaderBag $headers;

    /**
     * @var string|false|null
     */
    protected string|null|false $content;

    /**
     * Server and execution environment parameters ($_SERVER).
     */
    public ServerBag $server;

    protected static array $formats;

    private function __construct(array $query = [], array $request = [], array $files = [], array $server = [], mixed $content = null)
    {
        $this->initialize($query, $request, $files, $server, $content);
    }

    public static function createFromGlobals(): static
    {
        return new static($_GET, $_POST, $_FILES, $_SERVER);
    }

    public function initialize(array $query, array $request, array $files, array $server, mixed $content = null): void
    {
        $this->query = new ParameterBag($query);
        $this->request = new ParameterBag($request);
        $this->files = new FileBag($files);
        $this->server = new ServerBag($server);
        $this->headers = new HeaderBag($this->server->getHeaders());

        $this->content = $content;
    }

    public function input($key = null, $default = null)
    {
        return $this->getDotDate($this->getInputSource()->all() + $this->query->all(), $key, $default);
    }

    public function query($key = null, $default = null)
    {
        if ($this->query->has($key)) {
            return $this->query->all()[$key];
        }

        return $default;
    }

    public function file($key = null, $default = null)
    {
        if ($this->files->has($key)) {
            return $this->files->get($key);
        }

        return $default;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->query->has($key)) {
            return $this->query->all()[$key];
        }

        if ($this->request->has($key)) {
            return $this->request->all()[$key];
        }

        return $default;
    }

    public function isJson(): bool
    {
        return Str::contains($this->headers->get('CONTENT_TYPE') ?? '', ['/json', '+json']);
    }

    /**
     * Get the JSON payload for the request.
     *
     * @param  string|null  $key
     * @param  mixed        $default
     *
     * @return ParameterBag|mixed
     */
    public function json(string $key = null, $default = null): mixed
    {
        if (!isset($this->json)) {
            $this->json = new ParameterBag((array) json_decode($this->getContent(), true));
        }

        if (is_null($key)) {
            return $this->json;
        }

        return $this->getDotDate($this->json->all(), $key, $default);
    }

    public function getRealMethod(): string
    {
        return strtoupper($this->server->get('REQUEST_METHOD', 'GET'));
    }

    public function getInputSource(): ParameterBag
    {
        if ($this->isJson()) {
            return $this->json();
        }

        return in_array($this->getRealMethod(), ['GET', 'HEAD']) ? $this->query : $this->request;
    }

    /**
     * @return bool|string|null
     */
    public function getContent(): bool|string|null
    {
        if (null === $this->content || false === $this->content) {
            $this->content = file_get_contents('php://input');
        }

        return $this->content;
    }

    protected function getDotDate($target, $key, $default = null)
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $i => $segment) {
            unset($key[$i]);

            if (is_null($segment)) {
                return $target;
            }

            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return $default;
            }
        }

        return $target;
    }
}
