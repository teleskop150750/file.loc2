<?php

namespace App\Http\Psr7;

use Psr\Http\Message\UriInterface;
use InvalidArgumentException;

class Uri implements UriInterface
{
    /**
     *    foo://example.com:8042/over/there?name=ferret#nose
     *    \_/   \______________/\_________/ \_________/ \__/
     *     |           |            |            |        |
     *  scheme     authority       path        query   fragment
     */

    protected string $scheme;

    /**
     * Пользовательский компонент URI.
     * Например, https://foo:1234@terryl.in
     * В данном случае "foo" - это пользователь.
     */
    protected string $user;

    /**
     * Пользовательский компонент URI.
     * Например, https://foo:1234@terryl.in
     * В данном случае "1234" - это пользователь.
     */
    protected string $pass;

    protected string $host;

    protected ?int $port;

    protected string $path;

    protected string $query;

    protected string $fragment;

    public function __construct(string $uri = '')
    {
        $this->assertString($uri, 'uri');
        $this->init((array) parse_url($uri));
    }

    /**
     * {@inheritdoc}
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthority(): string
    {
        $authority = '';

        if ($this->getUserInfo()) {
            $authority .= $this->getUserInfo().'@';
        }

        $authority .= $this->getHost();

        if (!is_null($this->getPort())) {
            $authority .= ':'.$this->getPort();
        }

        return $authority;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserInfo(): string
    {
        $userInfo = $this->user;

        if ($this->pass !== '') {
            $userInfo .= ':'.$this->pass;
        }

        return $userInfo;
    }

    /**
     * {@inheritdoc}
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * {@inheritdoc}
     */
    public function getPort(): ?int
    {
        return $this->port;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * {@inheritdoc}
     */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * {@inheritdoc}
     */
    public function withScheme($scheme): UriInterface|Uri
    {
        $this->assertScheme($scheme);

        $scheme = $this->filter('scheme', ['scheme' => $scheme]);

        $clone = clone $this;
        $clone->scheme = $scheme;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withUserInfo($user, $password = null): UriInterface|Uri
    {
        $this->assertString($user, 'user');
        $user = $this->filter('user', ['user' => $user]);

        if ($password) {
            $this->assertString($password, 'pass');
            $password = $this->filter('pass', ['pass' => $password]);
        }

        $clone = clone $this;
        $clone->user = $user;
        $clone->pass = $password;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withHost($host): UriInterface|Uri
    {
        $this->assertHost($host);

        $host = $this->filter('host', ['host' => $host]);

        $clone = clone $this;
        $clone->host = $host;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withPort($port): UriInterface|Uri
    {
        $this->assertPort($port);

        $port = $this->filter('port', ['port' => $port]);

        $clone = clone $this;
        $clone->port = $port;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withPath($path): UriInterface|Uri
    {
        $this->assertString($path, 'path');

        $path = $this->filter('path', ['path' => $path]);

        $clone = clone $this;
        $clone->path = '/'.rawurlencode(ltrim($path, '/'));

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withQuery($query): UriInterface|Uri
    {
        $this->assertString($query, 'query');

        $query = $this->filter('query', ['query' => $query]);

        // & => %26
        // ? => %3F

        $clone = clone $this;
        $clone->query = $query;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withFragment($fragment): UriInterface|Uri
    {
        $this->assertString($fragment, 'fragment');

        $fragment = $this->filter('fragment', ['fragment' => $fragment]);

        $clone = clone $this;
        $clone->fragment = $fragment;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        $uri = '';

        // Если схема присутствует, она ДОЛЖНА иметь суффикс ":".
        if ($this->getScheme() !== '') {
            $uri .= $this->getScheme().':';
        }

        // Если присутствует полномочие, оно ДОЛЖНО иметь префикс "//".
        if ($this->getAuthority() !== '') {
            $uri .= '//'.$this->getAuthority();
        }

        // Если путь не имеет корней и присутствует авторитет, путь ДОЛЖЕН
        // должно иметь префикс "/".
        $uri .= '/'.ltrim($this->getPath(), '/');

        // Если запрос присутствует, он ДОЛЖЕН иметь префикс "?".
        if ($this->getQuery() !== '') {
            $uri .= '?'.$this->getQuery();
        }

        // Если фрагмент присутствует, он ДОЛЖЕН иметь префикс "#".
        if ($this->getFragment() !== '') {
            $uri .= '#'.$this->getFragment();
        }

        return $uri;
    }


    /**
     * Инициализировать.
     *
     * @param  array  $data  Parsed URL data.
     *
     * @return void
     */
    protected function init(array $data = []): void
    {
        $components = [
            'scheme',
            'user',
            'pass',
            'host',
            'port',
            'path',
            'query',
            'fragment',
        ];

        foreach ($components as $v) {
            $this->{$v} = $this->filter($v, $data);
        }
    }

    protected function filter(string $key, array $data): int|string|null
    {
        $notExists = [
            'scheme' => '',
            'user' => '',
            'pass' => '',
            'host' => '',
            'port' => null,
            'path' => '',
            'query' => '',
            'fragment' => '',
        ];

        if (!isset($data[$key])) {
            return $notExists[$key];
        }

        $value = $data[$key];

        // gen-delims  = ":" / "/" / "?" / "#" / "[" / "]" / "@"
//         $genDelims = ':/\?#\[\]@';

        // sub-delims  = "!" / "$" / "&" / "'" / "(" / ")"
        //             / "*" / "+" / "," / ";" / "="
        $subDelims = '!\$&\'\(\)\*\+,;=';

        // $unreserved  = ALPHA / DIGIT / "-" / "." / "_" / "~"
        $unReserved = 'a-zA-Z0-9\-\._~';

        // Закодированные символы, такие как "?", закодированные в "%3F".
        $encodePattern = '%(?![A-Fa-f0-9]{2})';

        $regex = '';

        switch ($key) {
            case 'host':
            case 'scheme':
                return strtolower($value);
                break;

            case 'query':
            case 'fragment':
                $specPattern = '%:@\/\?';
                $regex = '/(?:[^'.$unReserved.$subDelims.$specPattern.']+|'.$encodePattern.')/';
                break;

            case 'path':
                $specPattern = '%:@\/';
                $regex = '/(?:[^'.$unReserved.$subDelims.$specPattern.']+|'.$encodePattern.')/';
                break;

            case 'user':
            case 'pass':
                $regex = '/(?:[^%'.$unReserved.$subDelims.']+|'.$encodePattern.')/';
                break;

            case 'port':
                if ($this->scheme === 'http' && (int) $value !== 80) {
                    return (int) $value;
                }
                if ($this->scheme === 'https' && (int) $value !== 443) {
                    return (int) $value;
                }
                if ($this->scheme === '') {
                    return (int) $value;
                }

                return null;
        }

        if ($regex) {
            return preg_replace_callback(
                $regex,
                static function ($match) {
                    return rawurlencode($match[0]);
                },
                $value
            );
        }

        return $value;
    }


    protected function assertScheme(string $scheme): void
    {
        $this->assertString($scheme, 'scheme');

        $validSchemes = [
            0 => '',
            1 => 'http',
            2 => 'https',
        ];

        if (!in_array($scheme, $validSchemes)) {
            throw new InvalidArgumentException(
                sprintf('Строка "%s" не является допустимой схемой.', $scheme)
            );
        }
    }

    protected function assertString($value): void
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                sprintf('Должно быть строкой, но %s указано.', gettype($value))
            );
        }
    }

    protected function assertHost(string $host): void
    {
        $this->assertString($host);

        if (empty($host)) {
            return;
        }

        if (!filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new InvalidArgumentException(
                sprintf('"%s" не является допустимым хостом', $host)
            );
        }
    }

    protected function assertPort(?int $port): void
    {
        if (!is_null($port) && !is_int($port)) {
            throw new InvalidArgumentException(
                sprintf('Порт должен быть целым числом или нулевым значением, но %s указан.', gettype($port))
            );
        }

        if (!($port > 0 && $port < 65535)) {
            throw new InvalidArgumentException(
                sprintf('Номер порта должен находиться в диапазоне 0-65535, но %s предоставляется.', $port)
            );
        }
    }
}
