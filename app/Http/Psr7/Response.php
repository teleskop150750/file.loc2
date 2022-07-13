<?php

declare(strict_types=1);

namespace App\Http\Psr7;

use Psr\Http\Message\ResponseInterface;
use App\Http\Psr7\Message;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

class Response extends Message implements ResponseInterface
{

    protected int $status;

    protected string $reasonPhrase;

    protected static array $statusCode = [

        // 1xx: Informational
        // Request received, continuing process.
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',

        // 2xx: Success
        // The action was successfully received, understood, and accepted.
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',

        // 3xx: Redirection
        // Further action must be taken in order to complete the request.
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        //  => '309-399	Unassigned.'

        // 4xx: Client Error
        // The request contains bad syntax or cannot be fulfilled.
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        //  => '418-412: Unassigned'
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        //  =>  '432-450: Unassigned.'
        451 => 'Unavailable For Legal Reasons',
        //  =>  '452-499: Unassigned.'

        // 5xx: Server Error
        // The server failed to fulfill an apparently valid request.
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
        //  => '512-599	Unassigned.'
    ];

    public function __construct(
        int $status = 200,
        array $headers = [],
        StreamInterface|string $body = '',
        string $version = '1.1',
        string $reason = 'OK'
    ) {
        $this->assertStatus($status);
        $this->assertReasonPhrase($reason);
        $this->assertProtocolVersion($version);

        $this->setHeaders($headers);
        $this->setBody($body);

        $this->status = $status;
        $this->protocolVersion = $version;
        $this->reasonPhrase = $reason;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * {@inheritdoc}
     */
    public function withStatus($code, $reasonPhrase = ''): ResponseInterface
    {
        $this->assertStatus($code);
        $this->assertReasonPhrase($reasonPhrase);

        if ($reasonPhrase === '' && isset(self::$statusCode[$code])) {
            $reasonPhrase = self::$statusCode[$code];
        }

        $clone = clone $this;
        $clone->status = $code;
        $clone->reasonPhrase = $reasonPhrase;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    protected function assertStatus($code): void
    {
        if (!is_int($code)) {
            throw new InvalidArgumentException(
                sprintf('Код состояния должен быть целочисленным значением, но при условии "%s".', gettype($code))
            );
        }

        if (!($code > 100 && $code < 599)) {
            throw new InvalidArgumentException(
                sprintf('Код состояния должен находиться в диапазоне 100-599, но при условии "%s".', $code)
            );
        }
    }

    protected function assertReasonPhrase($reasonPhrase): void
    {
        if ($reasonPhrase === '') {
            return;
        }

        if (!is_string($reasonPhrase)) {
            throw new InvalidArgumentException(
                sprintf('Фраза причины должна быть строкой. Получено "%s".', gettype($reasonPhrase))
            );
        }

        // Специальные символы, такие как "разрывы строк", "табуляция" и другие...
        $escapeCharacters = [
            '\f', '\r', '\n', '\t', '\v', '\0', '[\b]', '\s', '\S', '\w', '\W', '\d', '\D', '\b', '\B', '\cX', '\xhh',
            '\uhhhh',
        ];

        $filteredPhrase = str_replace($escapeCharacters, '', $reasonPhrase);

        if ($reasonPhrase !== $filteredPhrase) {
            foreach ($escapeCharacters as $escape) {
                if (str_contains($reasonPhrase, $escape)) {
                    throw new InvalidArgumentException(
                        sprintf('Фраза причины содержит "%s", который считается запрещенным символом.', $escape)
                    );
                }
            }
        }
    }
}
