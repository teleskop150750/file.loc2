<?php

namespace FileManager\Modules\Http\Request;

class Uri
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
        $this->init((array) parse_url($uri));
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

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

    public function getUserInfo(): string
    {
        $userInfo = $this->user;

        if ($this->pass !== '') {
            $userInfo .= ':'.$this->pass;
        }

        return $userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

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
}
