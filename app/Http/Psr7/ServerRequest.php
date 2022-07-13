<?php

namespace App\Http\Psr7;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;
use App\Http\Psr7\Request;
use App\Http\Psr7\Utils\UploadedFileHelper;
use InvalidArgumentException;

use const JSON_ERROR_NONE;

class ServerRequest extends Request implements ServerRequestInterface
{
    /**
     * Обычно производный от суперглобального PHP $_SERVER.
     */
    protected array $serverParams;

    /**
     * Обычно производный от PHP $_COOKIE.
     */
    protected array $cookieParams;

    /**
     * Обычно производный от PHP $_POST.
     */
    protected array|null|object $parsedBody;

    /**
     * Typically derived from PHP's $_GET.
     */
    protected array $queryParams;

    /**
     * Обычно производный от PHP $_FILES.
     */
    protected array|UploadedFile $uploadedFiles;

    protected array $attributes;

    /**
     * ServerRequest constructor.
     *
     * @param  string  $method  Request HTTP method
     * @param  string|UriInterface  $uri  Request URI object URI or URL
     * @param  string|StreamInterface  $body  Request body
     * @param  array  $headers  Request headers
     * @param  string  $version  Request protocol version
     * @param  array  $serverParams  $_SERVER
     * @param  array  $cookieParams  $_COOKIE
     * @param  array  $postParams  $_POST
     * @param  array  $getParams  $_GET
     * @param  array  $filesParams  $_FILES
     */
    public function __construct(
        string $method = 'GET',
        UriInterface|string $uri = '',
        StreamInterface|string $body = '',
        array $headers = [],
        string $version = '1.1',
        array $serverParams = [],
        array $cookieParams = [],
        array $postParams = [],
        array $getParams = [],
        array $filesParams = []
    ) {
        parent::__construct($method, $uri, $body, $headers, $version);

        $this->serverParams = $serverParams;
        $this->cookieParams = $cookieParams;
        $this->queryParams = $getParams;
        $this->attributes = [];

        $this->determineParsedBody($postParams);

        $this->uploadedFiles = [];

        if (!empty($filesParams)) {
            $this->uploadedFiles = UploadedFileHelper::uploadedFileSpecsConvert(
                UploadedFileHelper::uploadedFileParse($filesParams)
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * {@inheritdoc}
     */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * {@inheritdoc}
     */
    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->cookieParams = $cookies;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * {@inheritdoc}
     */
    public function withQueryParams(array $query): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->queryParams = $query;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function getUploadedFile(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * {@inheritdoc}
     */
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        $this->assertUploadedFiles($uploadedFiles);

        $clone = clone $this;
        $clone->uploadedFiles = $uploadedFiles;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getParsedBody(): object|array|null
    {
        return $this->parsedBody;
    }

    /**
     * {@inheritdoc}
     */
    public function withParsedBody($data): ServerRequestInterface
    {
        $this->assertParsedBody($data);

        $clone = clone $this;
        $clone->parsedBody = $data;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function withAttribute($name, $value): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutAttribute($name): ServerRequestInterface
    {
        $clone = clone $this;

        if (isset($this->attributes[$name])) {
            unset($clone->attributes[$name]);
        }

        return $clone;
    }

    protected function assertUploadedFiles(array $values): void
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                $this->assertUploadedFiles($value);
            } elseif (!($value instanceof UploadedFileInterface)) {
                throw new InvalidArgumentException(
                    'Недопустимая структура массива PSR-7 для обработки загруженного файла.'
                );
            }
        }
    }

    protected function assertParsedBody(mixed $data): void
    {
        if (
            !is_null($data) &&
            !is_array($data) &&
            !is_object($data)
        ) {
            throw new InvalidArgumentException(
                sprintf('Принимает только массив, объект и null. Получен "%s".', gettype($data))
            );
        }
    }

    protected function determineParsedBody(array $postParams): void
    {
        $headerContentType = $this->getHeaderLine('Content-Type');
        $contentTypeArr = preg_split('/\s*[;,]\s*/', $headerContentType);
        $contentType = strtolower($contentTypeArr[0]);
        $httpMethod = strtoupper($this->getMethod());

        // Является ли это отправкой формы или нет.
        $isForm = false;

        if ($httpMethod === 'POST') {

            // Если тип содержимого запроса является либо application/x-www-form-urlencoded
            // или multipart/form-data, и метод запроса POST, этот метод ДОЛЖЕН
            // возвращает содержимое $_POST.
            $postRequiredContentTypes = [
                '',
                'application/x-www-form-urlencoded',
                'multipart/form-data',
            ];

            if (in_array($contentType, $postRequiredContentTypes)) {
                $this->parsedBody = $postParams ?? null;
                $isForm = true;
            }
        }

        // Возможно, другие методы http, такие как PUT, DELETE и т.Д...
        if ($httpMethod !== 'GET' && !$isForm) {

            // Если это строка в формате JSON?
            $isJson = false;
            $jsonParsedBody = null;

            // Получать содержимое из ввода PHP stdin, если оно существует.
            $rawText = file_get_contents('php://input');

            if (!empty($rawText)) {

                if ($contentType === 'application/json') {
                    $jsonParsedBody = json_decode($rawText);
                    $isJson = (json_last_error() === JSON_ERROR_NONE);
                }

                // Условие 1 - Это JSON, теперь тело является объектом JSON.
                if ($isJson) {
                    $this->parsedBody = $jsonParsedBody ?: null;
                }

                // Условие 2 - Это не JSON, может быть http-запрос на сборку.
                if (!$isJson) {
                    parse_str($rawText, $parsedStr);
                    $this->parsedBody = $parsedStr ?: null;
                }
            }
        }
    }
}
