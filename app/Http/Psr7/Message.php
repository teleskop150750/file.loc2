<?php
declare(strict_types=1);

namespace App\Http\Psr7;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;
use App\Http\Psr7\Stream;
use InvalidArgumentException;
use const PREG_SET_ORDER;

class Message implements MessageInterface
{
    protected string $protocolVersion = '1.1';

    protected StreamInterface $body;

    protected array $headers = [];

    protected array $headerNameMapping = [];

    protected array $validProtocolVersions = [
        '1.0',
        '1.1',
        '2.0',
        '3.0',
    ];

    /**
     * {@inheritdoc}
     */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * {@inheritdoc}
     */
    public function withProtocolVersion($version): MessageInterface
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders(): array
    {
        $headers = $this->headers;

        foreach ($this->headerNameMapping as $origin) {
            $name = strtolower($origin);
            if (isset($headers[$name])) {
                $value = $headers[$name];
                unset($headers[$name]);
                $headers[$origin] = $value;
            }
        }

        return $headers;
    }

    /**
     * {@inheritdoc}
     */
    public function hasHeader($name): bool
    {
        $name = strtolower($name);

        return isset($this->headers[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader($name): array
    {
        $name = strtolower($name);

        if (isset($this->headers[$name])) {
            return $this->headers[$name];
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaderLine($name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * {@inheritdoc}
     */
    public function withHeader($name, $value): Message|MessageInterface
    {
        $origName = $name;

        $name = $this->normalizeHeaderFieldName($name);
        $value = $this->normalizeHeaderFieldValue($value);

        $clone = clone $this;
        $clone->headers[$name] = $value;
        $clone->headerNameMapping[$name] = $origName;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withAddedHeader($name, $value): MessageInterface
    {
        $origName = $name;

        $name = $this->normalizeHeaderFieldName($name);
        $value = $this->normalizeHeaderFieldValue($value);

        $clone = clone $this;
        $clone->headerNameMapping[$name] = $origName;

        if (isset($clone->headers[$name])) {
            $clone->headers[$name] = array_merge($this->headers[$name], $value);
        } else {
            $clone->headers[$name] = $value;
        }

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutHeader($name): MessageInterface
    {
        $origName = $name;
        $name = strtolower($name);

        $clone = clone $this;
        unset($clone->headers[$name], $clone->headerNameMapping[$name]);

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    /**
     * {@inheritdoc}
     */
    public function withBody(StreamInterface $body): MessageInterface
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    /*
    |--------------------------------------------------------------------------
    | Non PSR-7 Methods.
    |--------------------------------------------------------------------------
    */

    /**
     * Set headers to property $headers.
     *
     * @param  array  $headers  A collection of header information.
     *
     * @return void
     */
    protected function setHeaders(array $headers): void
    {
        $arr = [];
        $origArr = [];

        foreach ($headers as $name => $value) {
            $origName = $name;
            $name = $this->normalizeHeaderFieldName($name);
            $value = $this->normalizeHeaderFieldValue($value);

            $arr[$name] = $value;
            $origArr[$name] = $origName;
        }

        $this->headers = $arr;
        $this->headerNameMapping = $origArr;
    }

    protected function setBody(StreamInterface|string $body): void
    {
        if ($body instanceof StreamInterface) {
            $this->body = $body;
        } else {
            $resource = fopen('php://temp', 'rb+');

            if ($body !== '') {
                fwrite($resource, $body);
                fseek($resource, 0);
            }

            $this->body = new Stream($resource);
        }
    }

    public static function parseRawHeader(string $message): array
    {
        preg_match_all('/^([^:\n]*): ?(.*)$/m', $message, $headers, PREG_SET_ORDER);

        $num = count($headers);

        if ($num > 1) {
            $headers = array_merge(...array_map(static function ($line) {
                $name = trim($line[1]);
                $field = trim($line[2]);

                return [$name => $field];
            }, $headers));

            return $headers;
        }

        if ($num === 1) {
            $name = trim($headers[0][1]);
            $field = trim($headers[0][2]);

            return [$name => $field];
        }

        return [];
    }

    protected function normalizeHeaderFieldName(string $name): string
    {
        $this->assertHeaderFieldName($name);

        return strtolower(trim($name));
    }

    protected function normalizeHeaderFieldValue(mixed $value): array
    {
        $this->assertHeaderFieldValue($value);

        $result = [];

        if (is_string($value)) {
            $result = [trim($value)];
        } elseif (is_array($value)) {
            foreach ($value as $v) {
                if (is_string($v)) {
                    $value[] = trim($v);
                }
            }
            $result = $value;
        } elseif (is_float($value) || is_int($value)) {
            $result = [(string) $value];
        }

        return $result;
    }

    protected function assertHeaderFieldName($name): void
    {
        if (!is_string($name)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Имя поля заголовка должно быть строкой, но указано "%s"..',
                    gettype($name)
                )
            );
        }
        // @see https://tools.ietf.org/html/rfc7230#section-3.2.6
        // alpha  => a-zA-Z
        // digit  => 0-9
        // others => !#$%&\'*+-.^_`|~

        if (!preg_match('/^[a-zA-Z\d!#$%&\'*+-.^_`|~]+$/', $name)) {
            throw new InvalidArgumentException(
                sprintf('"%s" - недопустимое имя заголовка, оно должно быть строкой, совместимой с RFC 7230..', $name)
            );
        }
    }

    protected function assertHeaderFieldValue($value = null): void
    {
        if (is_scalar($value) && !is_bool($value)) {
            $value = [(string) $value];
        }

        if (empty($value)) {
            throw new InvalidArgumentException('Пустой массив не допускается.');
        }

        if (is_array($value)) {
            foreach ($value as $item) {

                if ($item === '') {
                    return;
                }

                if (!is_scalar($item) || is_bool($item)) {
                    throw new InvalidArgumentException(
                        sprintf('Значения заголовка принимают только строку и число, но "%s" указано.', gettype($item))
                    );
                }

                // https://www.rfc-editor.org/rfc/rfc7230.txt (page.25)
                // field-content = field-vchar [ 1*( SP / HTAB ) field-vchar ]
                // field-vchar   = VCHAR / obs-text
                // obs-text      = %x80-FF
                // SP            = space
                // HTAB          = horizontal tab
                // VCHAR         = any visible [USASCII] character. (x21-x7e)
                // %x80-FF       = character range outside ASCII.

                if (!preg_match('/^[ \t\x21-\x7e]+$/', $item)) {
                    throw new InvalidArgumentException(
                        sprintf(
                            '"%s" - недопустимое значение заголовка, оно должно содержать только видимые символы ASCII.',
                            $item
                        )
                    );
                }
            }
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    'Значение поля заголовка принимает только строку и массив, но "%s" указано.',
                    gettype($value)
                )
            );
        }
    }


    protected function assertProtocolVersion(string $version): void
    {
        if (!in_array($version, $this->validProtocolVersions, true)) {
            throw new InvalidArgumentException(
                sprintf('Неподдерживаемый номер версии протокола HTTP. предоставленный "%s".', $version)
            );
        }
    }
}
