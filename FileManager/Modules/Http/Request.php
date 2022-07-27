<?php

namespace FileManager\Modules\Http;

use FileManager\Modules\Http\Exception\SessionNotFoundException;
use FileManager\Modules\Http\Exception\SuspiciousOperationException;
use JsonException;
use Locale;

class Request
{
    public const HEADER_FORWARDED = 0b000001;
    public const HEADER_X_FORWARDED_FOR = 0b000010;
    public const HEADER_X_FORWARDED_HOST = 0b000100;
    public const HEADER_X_FORWARDED_PROTO = 0b001000;
    public const HEADER_X_FORWARDED_PORT = 0b010000;
    public const HEADER_X_FORWARDED_PREFIX = 0b100000;

    public const HEADER_X_FORWARDED_AWS_ELB = 0b0011010;
    public const HEADER_X_FORWARDED_TRAEFIK = 0b0111110;

    public const METHOD_HEAD = 'HEAD';
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_PATCH = 'PATCH';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_PURGE = 'PURGE';
    public const METHOD_OPTIONS = 'OPTIONS';
    public const METHOD_TRACE = 'TRACE';
    public const METHOD_CONNECT = 'CONNECT';

    /**
     * Доверенные прокси-серверы
     *
     * @var string[]
     */
    protected static array $trustedProxies = [];

    /**
     * Шаблоны доверенных хостов
     *
     * @var string[]
     */
    protected static array $trustedHostPatterns = [];

    /**
     * Доверенные хосты
     *
     * @var string[]
     */
    protected static array $trustedHosts = [];

    /**
     * Переопределение параметров метода http
     */
    protected static bool $httpMethodParameterOverride = false;

    /**
     * Пользовательские параметры.
     */
    public ParameterBag $attributes;

    /**
     * Параметры тела запроса ($_POST).
     */
    public InputBag $request;

    /**
     * Параметры строки запроса ($_GET).
     */
    public InputBag $query;

    /**
     * Параметры сервера и среды выполнения ($_SERVER).
     */
    public ServerBag $server;

    /**
     * Загруженные файлы ($_FILES).
     */
    public FileBag $files;

    /**
     * Cookies ($_COOKIE).
     */
    public InputBag $cookies;

    /**
     * Заголовки (взяты из $_SERVER).
     */
    public HeaderBag $headers;

    /** @var string|resource|false|null */
    protected $content;

    protected ?array $languages;

    protected ?array $charsets;

    protected ?array $encodings;

    protected ?array $acceptableContentTypes;

    protected ?string $pathInfo;

    protected ?string $requestUri;

    protected ?string $baseUrl;

    protected ?string $basePath;

    protected ?string $method;

    protected ?string $format;

    protected static array $formats;

    private bool $isHostValid = true;
    private bool $isForwardedValid = true;

    private static int $trustedHeaderSet = -1;

    private const FORWARDED_PARAMS = [
        self::HEADER_X_FORWARDED_FOR => 'for',
        self::HEADER_X_FORWARDED_HOST => 'host',
        self::HEADER_X_FORWARDED_PROTO => 'proto',
        self::HEADER_X_FORWARDED_PORT => 'host',
    ];

    /**
     * Имена для заголовков, которым можно доверять, когда
     * использование доверенных прокси-серверов.
     *
     * Перенаправленный заголовок является стандартным по состоянию на rfc7239.
     *
     * Другие заголовки являются нестандартными, но широко используемыми
     * с помощью популярных обратных прокси-серверов (таких как Apache mod_proxy или Amazon EC2).
     */
    private const TRUSTED_HEADERS = [
        self::HEADER_FORWARDED => 'FORWARDED',
        self::HEADER_X_FORWARDED_FOR => 'X_FORWARDED_FOR',
        self::HEADER_X_FORWARDED_HOST => 'X_FORWARDED_HOST',
        self::HEADER_X_FORWARDED_PROTO => 'X_FORWARDED_PROTO',
        self::HEADER_X_FORWARDED_PORT => 'X_FORWARDED_PORT',
        self::HEADER_X_FORWARDED_PREFIX => 'X_FORWARDED_PREFIX',
    ];

    /**
     * @param  array                 $query       The GET parameters
     * @param  array                 $request     The POST parameters
     * @param  array                 $attributes  The request attributes (parameters parsed from the PATH_INFO, ...)
     * @param  array                 $cookies     The COOKIE parameters
     * @param  array                 $files       The FILES parameters
     * @param  array                 $server      The SERVER parameters
     * @param  string|resource|null  $content     The raw body data
     */
    public function __construct(
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        $content = null
    ) {
        $this->initialize($query, $request, $attributes, $cookies, $files, $server, $content);
    }

    /**
     * Задает параметры для этого запроса.
     *
     * Этот метод также повторно инициализирует все свойства.
     *
     * @param  array                 $query       The GET parameters
     * @param  array                 $request     The POST parameters
     * @param  array                 $attributes  Атрибуты запроса (параметры из PATH_INFO, ...)
     * @param  array                 $cookies     The COOKIE parameters
     * @param  array                 $files       The FILES parameters
     * @param  array                 $server      The SERVER parameters
     * @param  string|resource|null  $content     The raw body data
     */
    public function initialize(
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        $content = null
    ): void {
        $this->request = new InputBag($request);
        $this->query = new InputBag($query);
        $this->attributes = new ParameterBag($attributes);
        $this->cookies = new InputBag($cookies);
        $this->files = new FileBag($files);
        $this->server = new ServerBag($server);
        $this->headers = new HeaderBag($this->server->getHeaders());

        $this->content = $content;
        $this->languages = null;
        $this->charsets = null;
        $this->encodings = null;
        $this->acceptableContentTypes = null;
        $this->pathInfo = null;
        $this->requestUri = null;
        $this->baseUrl = null;
        $this->basePath = null;
        $this->method = null;
        $this->format = null;
    }

    /**
     * Создает новый запрос со значениями из супер super globals PHP.
     */
    public static function createFromGlobals(): static
    {
        $request = new static($_GET, $_POST, [], $_COOKIE, $_FILES, $_SERVER);

        if (str_starts_with($request->headers->get('CONTENT_TYPE', ''), 'application/x-www-form-urlencoded')
            && \in_array(strtoupper($request->server->get('REQUEST_METHOD', 'GET')), ['PUT', 'DELETE', 'PATCH'])
        ) {
            parse_str($request->getContent(), $data);
            $request->request = new InputBag($data);
        }

        return $request;
    }


    /**
     * Клонирует текущий запрос.
     *
     * Обратите внимание, что session не клонируются как дублированные запросы
     * большую часть времени являются вложенными запросами основного запроса.
     */
    public function __clone()
    {
        $this->query = clone $this->query;
        $this->request = clone $this->request;
        $this->attributes = clone $this->attributes;
        $this->cookies = clone $this->cookies;
        $this->files = clone $this->files;
        $this->server = clone $this->server;
        $this->headers = clone $this->headers;
    }

    /**
     * Нормализует строку запроса.
     *
     * Он создает нормализованную строку запроса, где пары ключи / значение расположены в алфавитном порядке,
     * имеет согласованное экранирование, а ненужные разделители удалены.
     */
    public static function normalizeQueryString(?string $qs): string
    {
        if ('' === ($qs ?? '')) {
            return '';
        }

        $qsMap = HeaderUtils::parseQuery($qs);
        ksort($qsMap);

        return http_build_query($qsMap, '', '&', \PHP_QUERY_RFC3986);
    }

    /**
     * Получает значение "параметра" из любого пакета.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($this !== $result = $this->attributes->get($key, $this)) {
            return $result;
        }

        if ($this->query->has($key)) {
            return $this->query->all()[$key];
        }

        if ($this->request->has($key)) {
            return $this->request->all()[$key];
        }

        return $default;
    }

    /**
     * Возвращает запрашиваемый путь относительно выполняемого скрипта.
     *
     * Информация о пути всегда начинается с /.
     *
     * Предположим, что этот запрос создается из /mysite на localhost:
     *
     * * http://localhost/mysite возвращает пустую строку
     * * http://localhost/mysite/about возвращает '/about'
     * * http://localhost/mysite/enco%20ded возвращает '/enco%20ded'
     * * http://localhost/mysite/about?var=1 возвращает '/about'
     *
     * @return string Необработанного пути (т.е. не urldecoded)
     */
    public function getPathInfo(): string
    {
        if (null === $this->pathInfo) {
            $this->pathInfo = $this->preparePathInfo();
        }

        return $this->pathInfo;
    }

    /**
     * Возвращает корневой URL-адрес, с которого выполняется этот запрос.
     *
     * Базовый URL-адрес никогда не заканчивается символом /.
     *
     * Это похоже на getBasePath(), за исключением того, что оно также включает в себя
     * имя файла скрипта (например index.php ), если таковой существует.
     *
     * @return string Необработанный URL-адрес (т.е. не декодированный URL-адрес)
     */
    public function getBaseUrl(): string
    {
        $trustedPrefix = '';

        // префикс прокси-сервера должен быть добавлен к любому префиксу, необходимому на уровне веб-сервера
        if ($this->isFromTrustedProxy() && $trustedPrefixValues = $this->getTrustedValues(self::HEADER_X_FORWARDED_PREFIX)) {
            $trustedPrefix = rtrim($trustedPrefixValues[0], '/');
        }

        return $trustedPrefix.$this->getBaseUrlReal();
    }

    /**
     * Возвращает реальный базовый URL-адрес, полученный веб-сервером, с которого выполняется этот запрос.
     * URL-адрес не содержит префикса доверенного обратного прокси-сервера.
     *
     * @return string Необработанный URL-адрес (т.е. не декодированный URL-адрес)
     */
    public function getBaseUrlReal(): string
    {
        if (null === $this->baseUrl) {
            $this->baseUrl = $this->prepareBaseUrl();
        }

        return $this->baseUrl;
    }

    /**
     * Получает Scheme запроса.
     */
    public function getScheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    /**
     * Возвращает порт, на который был сделан запрос.
     *
     * Этот метод может считывать порт клиента из заголовка "X-Forwarded-Port".
     * когда доверенные прокси-серверы были установлены с помощью "setTrustedProxies()".
     *
     * Заголовок "X-Forwarded-Port" должен содержать порт клиента.
     *
     * @return int|string|null Может быть строкой, если она извлечена server bag
     */
    public function getPort(): int|string|null
    {
        if ($this->isFromTrustedProxy() && $host = $this->getTrustedValues(self::HEADER_X_FORWARDED_PORT)) {
            $host = $host[0];
        } elseif ($this->isFromTrustedProxy() && $host = $this->getTrustedValues(self::HEADER_X_FORWARDED_HOST)) {
            $host = $host[0];
        } elseif (!$host = $this->headers->get('HOST')) {
            return $this->server->get('SERVER_PORT');
        }

        if ('[' === $host[0]) {
            $pos = strpos($host, ':', strrpos($host, ']'));
        } else {
            $pos = strrpos($host, ':');
        }

        if (false !== $pos && $port = substr($host, $pos + 1)) {
            return (int) $port;
        }

        return 'https' === $this->getScheme() ? 443 : 80;
    }

    /**
     * Возвращает запрашиваемый HTTP-хост.
     *
     * Имя порта будет добавлено к хосту, если оно нестандартное.
     */
    public function getHttpHost(): string
    {
        $scheme = $this->getScheme();
        $port = $this->getPort();

        if (('http' === $scheme && 80 === (int) $port) || ('https' === $scheme && 443 === (int) $port)) {
            return $this->getHost();
        }

        return $this->getHost().':'.$port;
    }

    protected function prepareBaseUrl(): string
    {
        $filename = basename($this->server->get('SCRIPT_FILENAME', ''));

        if (basename($this->server->get('SCRIPT_NAME', '')) === $filename) {
            $baseUrl = $this->server->get('SCRIPT_NAME');
        } elseif (basename($this->server->get('PHP_SELF', '')) === $filename) {
            $baseUrl = $this->server->get('PHP_SELF');
        } elseif (basename($this->server->get('ORIG_SCRIPT_NAME', '')) === $filename) {
            $baseUrl = $this->server->get('ORIG_SCRIPT_NAME'); // 1and1 shared hosting compatibility
        } else {
            // Backtrack up the script_filename to find the portion matching
            // php_self
            $path = $this->server->get('PHP_SELF', '');
            $file = $this->server->get('SCRIPT_FILENAME', '');
            $segs = explode('/', trim($file, '/'));
            $segs = array_reverse($segs);
            $index = 0;
            $last = \count($segs);
            $baseUrl = '';
            do {
                $seg = $segs[$index];
                $baseUrl = '/'.$seg.$baseUrl;
                ++$index;
            } while ($last > $index && (false !== $pos = strpos($path, $baseUrl)) && 0 != $pos);
        }

        // Does the baseUrl have anything in common with the request_uri?
        $requestUri = $this->getRequestUri();
        if ('' !== $requestUri && '/' !== $requestUri[0]) {
            $requestUri = '/'.$requestUri;
        }

        if ($baseUrl && null !== $prefix = $this->getUrlencodedPrefix($requestUri, $baseUrl)) {
            // full $baseUrl matches
            return $prefix;
        }

        if ($baseUrl && null !== $prefix = $this->getUrlencodedPrefix($requestUri,
                rtrim(\dirname($baseUrl), '/'.\DIRECTORY_SEPARATOR).'/')) {
            // directory portion of $baseUrl matches
            return rtrim($prefix, '/'.\DIRECTORY_SEPARATOR);
        }

        $truncatedRequestUri = $requestUri;
        if (false !== $pos = strpos($requestUri, '?')) {
            $truncatedRequestUri = substr($requestUri, 0, $pos);
        }

        $basename = basename($baseUrl ?? '');
        if (empty($basename) || !strpos(rawurldecode($truncatedRequestUri), $basename)) {
            // no match whatsoever; set it blank
            return '';
        }

        // При использовании mod_rewrite или ISAPI_Rewrite удалите имя файла скрипта
        // out of baseUrl. $pos !== 0 гарантирует, что он не соответствует значению
        // из PATH_INFO или QUERY_STRING
        if
        (
            (false !== $pos = strpos($requestUri, $baseUrl))
            && 0 !== $pos
            && \strlen($requestUri) >= \strlen($baseUrl)
        ) {
            $baseUrl = substr($requestUri, 0, $pos + \strlen($baseUrl));
        }

        return rtrim($baseUrl, '/'.\DIRECTORY_SEPARATOR);
    }

    /**
     * Возвращает запрошенный URL-адрес (путь и строку запроса).
     *
     * @return string Необработанный URL-адрес (т.е. не декодированный URL-адрес)
     */
    public function getRequestUri(): string
    {
        if (null === $this->requestUri) {
            $this->requestUri = $this->prepareRequestUri();
        }

        return $this->requestUri;
    }

    /**
     * Получает схему и HTTP-host.
     *
     * Если URL-адрес был вызван с помощью базовой аутентификации, пользователь
     * и пароль не добавляются в сгенерированную строку.
     */
    public function getSchemeAndHttpHost(): string
    {
        return $this->getScheme().'://'.$this->getHttpHost();
    }

    /**
     * Генерирует нормализованный URI (URL) для запроса.
     *
     * @see getQueryString()
     */
    public function getUri(): string
    {
        if (null !== $qs = $this->getQueryString()) {
            $qs = '?'.$qs;
        }

        return $this->getSchemeAndHttpHost().$this->getBaseUrl().$this->getPathInfo().$qs;
    }

    /**
     * Генерирует нормализованную строку запроса для запроса.
     *
     * Он создает нормализованную строку запроса, где пары ключи/значение расположены в алфавитном порядке
     * и имеют согласованное экранирование.
     */
    public function getQueryString(): ?string
    {
        $qs = static::normalizeQueryString($this->server->get('QUERY_STRING'));

        return '' === $qs ? null : $qs;
    }

    /**
     * Проверяет, является ли запрос безопасным или нет.
     *
     * Этот метод может считывать клиентский протокол из заголовка "X-Forwarded-Proto".
     * когда доверенные прокси-серверы были установлены с помощью "setTrustedProxies()".
     *
     * Заголовок "X-Forwarded-Proto" должен содержать протокол: "https" или "http".
     */
    public function isSecure(): bool
    {
        if ($this->isFromTrustedProxy() && $proto = $this->getTrustedValues(self::HEADER_X_FORWARDED_PROTO)) {
            return \in_array(strtolower($proto[0]), ['https', 'on', 'ssl', '1'], true);
        }

        $https = $this->server->get('HTTPS');

        return !empty($https) && 'off' !== strtolower($https);
    }

    /**
     * Возвращает имя хоста.
     *
     * Этот метод может считывать имя хоста клиента из заголовка "X-Forwarded-Host".
     * когда доверенные прокси-серверы были установлены с помощью "setTrustedProxies()".
     *
     * Заголовок "X-Forwarded-Host" должен содержать имя хоста клиента.
     *
     * @выдает исключение SuspiciousOperationException, когда имя хоста является недопустимым или ненадежным
     */
    public function getHost(): string
    {
        if ($this->isFromTrustedProxy() && $host = $this->getTrustedValues(self::HEADER_X_FORWARDED_HOST)) {
            $host = $host[0];
        } elseif (!$host = $this->headers->get('HOST')) {
            if (!$host = $this->server->get('SERVER_NAME')) {
                $host = $this->server->get('SERVER_ADDR', '');
            }
        }

        // обрезать и удалить номер порта с хоста
        // хост в нижнем регистре в соответствии с RFC 952/2181
        $host = strtolower(preg_replace('/:\d+$/', '', trim($host)));

        // Поскольку хост может исходить от пользователя (HTTP_HOST и, в зависимости от конфигурации, ИМЯ_СЕРВЕРА тоже может исходить от пользователя)
        // убедитесь, что он не содержит запрещенных символов (см. RFC 952 и RFC 2181)
        // используйте preg_replace() вместо preg_match() для предотвращения DoS-атак с длинными именами хостов
        if ($host && '' !== preg_replace('/(?:^\[)?[a-zA-Z0-9-:\]_]+\.?/', '', $host)) {
            if (!$this->isHostValid) {
                return '';
            }

            $this->isHostValid = false;

            throw new SuspiciousOperationException(sprintf('Invalid Host "%s".', $host));
        }

        if (\count(self::$trustedHostPatterns) > 0) {
            // чтобы избежать атак с внедрением заголовка хоста, вы должны предоставить список шаблонов доверенных хостов

            if (\in_array($host, self::$trustedHosts)) {
                return $host;
            }

            foreach (self::$trustedHostPatterns as $pattern) {
                if (preg_match($pattern, $host)) {
                    self::$trustedHosts[] = $host;

                    return $host;
                }
            }

            if (!$this->isHostValid) {
                return '';
            }
            $this->isHostValid = false;

            throw new SuspiciousOperationException(sprintf('Ненадежный Host "%s".', $host));
        }

        return $host;
    }

    /**
     * Возвращает метод запроса "предполагаемый".
     *
     * Если установлен заголовок X-HTTP-Method-Override, и если метод является POST,
     * то он используется для определения "реального" предполагаемого HTTP-метода.
     *
     * Параметр запроса _method также может использоваться для определения метода HTTP,
     * но только в том случае, если был вызван enableHttpMethodParameterOverride().
     *
     * Метод всегда представляет собой строку в верхнем регистре.
     *
     * @see getRealMethod()
     */
    public function getMethod(): string
    {
        if (null !== $this->method) {
            return $this->method;
        }

        $this->method = strtoupper($this->server->get('REQUEST_METHOD', 'GET'));

        if ('POST' !== $this->method) {
            return $this->method;
        }

        $method = $this->headers->get('X-HTTP-METHOD-OVERRIDE');

        if (!$method && self::$httpMethodParameterOverride) {
            $method = $this->request->get('_method', $this->query->get('_method', 'POST'));
        }

        if (!\is_string($method)) {
            return $this->method;
        }

        $method = strtoupper($method);

        if (\in_array($method,
            ['GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'CONNECT', 'OPTIONS', 'PATCH', 'PURGE', 'TRACE'], true)) {
            return $this->method = $method;
        }

        if (!preg_match('/^[A-Z]++$/D', $method)) {
            throw new SuspiciousOperationException(sprintf('Недопустимое переопределение метода "%s".', $method));
        }

        return $this->method = $method;
    }

    /**
     * Возвращает "реальный" метод запроса.
     *
     * @see getMethod()
     */
    public function getRealMethod(): string
    {
        return strtoupper($this->server->get('REQUEST_METHOD', 'GET'));
    }

    /**
     * Возвращает содержимое тела запроса.
     *
     * @param  bool  $asResource  Если значение true, то будет возвращен ресурс
     *
     * @return string|resource
     */
    public function getContent(bool $asResource = false)
    {
        $currentContentIsResource = \is_resource($this->content);

        if (true === $asResource) {
            if ($currentContentIsResource) {
                rewind($this->content);

                return $this->content;
            }

            // Содержимое, переданное в параметре (тест)
            if (\is_string($this->content)) {
                $resource = fopen('php://temp', 'rb+');
                fwrite($resource, $this->content);
                rewind($resource);

                return $resource;
            }

            $this->content = false;

            return fopen('php://input', 'rb');
        }

        if ($currentContentIsResource) {
            rewind($this->content);

            return stream_get_contents($this->content);
        }

        if (null === $this->content || false === $this->content) {
            $this->content = file_get_contents('php://input');
        }

        return $this->content;
    }


    protected function prepareRequestUri()
    {
        $requestUri = '';

        if ('1' == $this->server->get('IIS_WasUrlRewritten') && '' != $this->server->get('UNENCODED_URL')) {
            // IIS7 с перезаписью URL: убедитесь, что мы получаем незашифрованный URL (проблема с двойной косой чертой)
            $requestUri = $this->server->get('UNENCODED_URL');
            $this->server->remove('UNENCODED_URL');
            $this->server->remove('IIS_WasUrlRewritten');
        } elseif ($this->server->has('REQUEST_URI')) {
            $requestUri = $this->server->get('REQUEST_URI');

            if ('' !== $requestUri && '/' === $requestUri[0]) {
                // Чтобы использовать только путь и запрос, удалите фрагмент.
                if (false !== $pos = strpos($requestUri, '#')) {
                    $requestUri = substr($requestUri, 0, $pos);
                }
            } else {
                // HTTP-прокси запрашивает URI запроса настройки со схемой и хостом [и портом] + путь URL,
                // используйте только путь URL.
                $uriComponents = parse_url($requestUri);

                if (isset($uriComponents['path'])) {
                    $requestUri = $uriComponents['path'];
                }

                if (isset($uriComponents['query'])) {
                    $requestUri .= '?'.$uriComponents['query'];
                }
            }
        } elseif ($this->server->has('ORIG_PATH_INFO')) {
            // IIS 5.0, PHP как CGI
            $requestUri = $this->server->get('ORIG_PATH_INFO');
            if ('' != $this->server->get('QUERY_STRING')) {
                $requestUri .= '?'.$this->server->get('QUERY_STRING');
            }
            $this->server->remove('ORIG_PATH_INFO');
        }

        // нормализуйте URI запроса, чтобы упростить создание вложенных запросов из этого запроса
        $this->server->set('REQUEST_URI', $requestUri);

        return $requestUri;
    }

    /**
     * Подготавливает информацию о пути.
     */
    protected function preparePathInfo(): string
    {
        if (null === ($requestUri = $this->getRequestUri())) {
            return '/';
        }

        // Удалите строку запроса из REQUEST_URI
        if (false !== $pos = strpos($requestUri, '?')) {
            $requestUri = substr($requestUri, 0, $pos);
        }

        if ('' !== $requestUri && '/' !== $requestUri[0]) {
            $requestUri = '/'.$requestUri;
        }

        if (null === ($baseUrl = $this->getBaseUrlReal())) {
            return $requestUri;
        }

        $pathInfo = substr($requestUri, \strlen($baseUrl));
        if (false === $pathInfo || '' === $pathInfo) {
            // Если substr() возвращает false, то PATH_INFO устанавливается в пустую строку
            return '/';
        }

        return $pathInfo;
    }

    /**
     * Возвращает префикс, закодированный в строке, если строка начинается с заданного префикса,
     * в противном случае null.
     */
    private function getUrlencodedPrefix(string $string, string $prefix): ?string
    {
        if (!str_starts_with(rawurldecode($string), $prefix)) {
            return null;
        }

        $len = \strlen($prefix);

        if (preg_match(sprintf('#^(%%[[:xdigit:]]{2}|.){%d}#', $len), $string, $match)) {
            return $match[0];
        }

        return null;
    }

    /**
     * Указывает, исходил ли этот запрос от доверенного прокси-сервера.
     *
     * Это может быть полезно для определения того, следует ли доверять
     * содержимому заголовка, специфичного для прокси-сервера.
     */
    public function isFromTrustedProxy(): bool
    {
        return self::$trustedProxies && IpUtils::checkIp($this->server->get('REMOTE_ADDR', ''), self::$trustedProxies);
    }

    private function getTrustedValues(int $type, string $ip = null): array
    {
        $clientValues = [];
        $forwardedValues = [];

        if ((self::$trustedHeaderSet & $type) && $this->headers->has(self::TRUSTED_HEADERS[$type])) {
            foreach (explode(',', $this->headers->get(self::TRUSTED_HEADERS[$type])) as $v) {
                $clientValues[] = (self::HEADER_X_FORWARDED_PORT === $type ? '0.0.0.0:' : '').trim($v);
            }
        }

        if ((self::$trustedHeaderSet & self::HEADER_FORWARDED) && (isset(self::FORWARDED_PARAMS[$type])) && $this->headers->has(self::TRUSTED_HEADERS[self::HEADER_FORWARDED])) {
            $forwarded = $this->headers->get(self::TRUSTED_HEADERS[self::HEADER_FORWARDED]);
            $parts = HeaderUtils::split($forwarded, ',;=');
            $forwardedValues = [];
            $param = self::FORWARDED_PARAMS[$type];
            foreach ($parts as $subParts) {
                if (null === $v = HeaderUtils::combine($subParts)[$param] ?? null) {
                    continue;
                }
                if (self::HEADER_X_FORWARDED_PORT === $type) {
                    if (str_ends_with($v, ']') || false === $v = strrchr($v, ':')) {
                        $v = $this->isSecure() ? ':443' : ':80';
                    }
                    $v = '0.0.0.0'.$v;
                }
                $forwardedValues[] = $v;
            }
        }

        if (null !== $ip) {
            $clientValues = $this->normalizeAndFilterClientIps($clientValues, $ip);
            $forwardedValues = $this->normalizeAndFilterClientIps($forwardedValues, $ip);
        }

        if ($forwardedValues === $clientValues || !$clientValues) {
            return $forwardedValues;
        }

        if (!$forwardedValues) {
            return $clientValues;
        }

        if (!$this->isForwardedValid) {
            return null !== $ip ? ['0.0.0.0', $ip] : [];
        }
        $this->isForwardedValid = false;

        throw new ConflictingHeadersException(sprintf('The request has both a trusted "%s" header and a trusted "%s" header, conflicting with each other. You should either configure your proxy to remove one of them, or configure your project to distrust the offending one.',
            self::TRUSTED_HEADERS[self::HEADER_FORWARDED], self::TRUSTED_HEADERS[$type]));
    }

    private function normalizeAndFilterClientIps(array $clientIps, string $ip): array
    {
        if (!$clientIps) {
            return [];
        }
        $clientIps[] = $ip; // Complete the IP chain with the IP the request actually came from
        $firstTrustedIp = null;

        foreach ($clientIps as $key => $clientIp) {
            if (strpos($clientIp, '.')) {
                // Strip :port from IPv4 addresses. This is allowed in Forwarded
                // and may occur in X-Forwarded-For.
                $i = strpos($clientIp, ':');
                if ($i) {
                    $clientIps[$key] = $clientIp = substr($clientIp, 0, $i);
                }
            } elseif (str_starts_with($clientIp, '[')) {
                // Strip brackets and :port from IPv6 addresses.
                $i = strpos($clientIp, ']', 1);
                $clientIps[$key] = $clientIp = substr($clientIp, 1, $i - 1);
            }

            if (!filter_var($clientIp, \FILTER_VALIDATE_IP)) {
                unset($clientIps[$key]);

                continue;
            }

            if (IpUtils::checkIp($clientIp, self::$trustedProxies)) {
                unset($clientIps[$key]);

                // Fallback to this when the client IP falls into the range of trusted proxies
                if (null === $firstTrustedIp) {
                    $firstTrustedIp = $clientIp;
                }
            }
        }

        // Now the IP chain contains only untrusted proxies and the client IP
        return $clientIps ? array_reverse($clientIps) : [$firstTrustedIp];
    }
}
