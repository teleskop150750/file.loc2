<?php

namespace FileManager\Http;

use RuntimeException;

class Response
{
    /**
     * Статус ответа в текстовом виде
     *
     * @var string
     */
    protected string $statusText = 'OK';

    /**
     * Статус ответа
     *
     * @var int
     */
    protected int $statusCode = 200;

    /**
     * Версия протокола HTTP
     *
     * @var string
     */
    protected string $protocolVersion = '1.0';

    /**
     * Заголовки ответа
     *
     * @var array<string, array >
     */
    protected array $headers = [];

    protected ?string $content = null;

    /**
     * @return string
     */
    public function getStatusText(): string
    {
        return $this->statusText;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @param  int          $code
     * @param  string|null  $text
     *
     * @return Response
     */
    public function setStatusCode(int $code, string $text = null): static
    {
        $this->statusCode = $code;

        if (null === $text) {
            $this->statusText = self::$statusTexts[$code] ?? 'unknown status';

            return $this;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * @param  string  $protocolVersion
     */
    public function setProtocolVersion(string $protocolVersion): void
    {
        $this->protocolVersion = $protocolVersion;
    }

    /**
     * Отправить Response
     *
     * @return void
     */
    public function send(): void
    {
        $this->sendHeaders();
        $this->sendContent();

        if (\function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * Отправить заголовки
     *
     * @return void
     */
    public function sendHeaders(): void
    {
        // headers
        foreach ($this->headers as $name => $values) {
            $replace = 0 === strcasecmp($name, 'Content-Type');
            foreach ($values as $value) {
                header($name.': '.$value, $replace, $this->statusCode);
            }
        }

        // status
        header(
            sprintf('HTTP/%s %s %s', $this->protocolVersion, $this->statusCode, $this->statusText),
            true,
            $this->statusCode
        );
    }

    /**
     * Отправить тело ответа
     *
     * @return void
     */
    protected function sendContent(): void
    {
        echo $this->content;
    }

    /**
     * Установить заголовки
     *
     * @param  string             $key      Ключ
     * @param  string|array|null  $values   Значение
     * @param  bool               $replace  Перезаписать
     *
     * @return static
     */
    public function setHeaders(string $key, string|array|null $values, bool $replace = true): static
    {
        if (is_array($values)) {
            $values = array_values($values);

            if (true === $replace || !isset($this->headers[$key])) {
                $this->headers[$key] = $values;
            } else {
                $this->headers[$key] = array_merge($this->headers[$key], $values);
            }
        } elseif (true === $replace || !isset($this->headers[$key])) {
            $this->headers[$key] = [$values];
        } else {
            $this->headers[$key][] = $values;
        }

        return $this;
    }

    /**
     * Установить тело ответа
     *
     * @param  string|null $content
     *
     * @return Response
     */
    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public static array $statusTexts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',            // RFC2518
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',          // RFC4918
        208 => 'Already Reported',      // RFC5842
        226 => 'IM Used',               // RFC3229
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',    // RFC7238
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Content Too Large',                                           // RFC-ietf-httpbis-semantics
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',                                               // RFC2324
        421 => 'Misdirected Request',                                         // RFC7540
        422 => 'Unprocessable Content',                                       // RFC-ietf-httpbis-semantics
        423 => 'Locked',                                                      // RFC4918
        424 => 'Failed Dependency',                                           // RFC4918
        425 => 'Too Early',                                                   // RFC-ietf-httpbis-replay-04
        426 => 'Upgrade Required',                                            // RFC2817
        428 => 'Precondition Required',                                       // RFC6585
        429 => 'Too Many Requests',                                           // RFC6585
        431 => 'Request Header Fields Too Large',                             // RFC6585
        451 => 'Unavailable For Legal Reasons',                               // RFC7725
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',                                     // RFC2295
        507 => 'Insufficient Storage',                                        // RFC4918
        508 => 'Loop Detected',                                               // RFC5842
        510 => 'Not Extended',                                                // RFC2774
        511 => 'Network Authentication Required',                             // RFC6585
    ];
}
