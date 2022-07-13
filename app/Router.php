<?php

namespace App;

use App\Controllers\HomeController;

/**
 * Class Router
 * @package App
 *
 * @method static Router get(string $route, array $callback)
 * @method static Router post(string $route, array $callback)
 * @method static Router put(string $route, array $callback)
 * @method static Router delete(string $route, array $callback)
 * @method static Router options(string $route, array $callback)
 * @method static Router head(string $route, array $callback)
 */
class Router
{
    public static bool $halts = false;
    public static array $routes = [];
    public static array $methods = [];
    public static array $callbacks = [];
    public static array $patterns = [
        ':any' => '[^/]+',
        ':num' => '[0-9]+',
        ':all' => '.*',
    ];

    public static mixed $error_callback;

    /**
     * Определяет маршрут с обратным вызовом и метод
     *
     * @param  string  $method
     * @param  array  $params
     * @return void
     */
    public static function __callstatic(string $method, array $params): void
    {
        $uri = str_starts_with($params[0], '/') ? $params[0] : '/'.$params[0];
        $callback = $params[1];

        self::$routes[] = $uri;
        self::$methods[] = strtoupper($method);
        self::$callbacks[] = $callback;
    }

    /**
     * Определяет обратный вызов, если маршрут не найден
     *
     * @param $callback
     * @return void
     */
    public static function error($callback): void
    {
        self::$error_callback = $callback;
    }

    /**
     * Остановить согласованные методы
     *
     * @param  boolean  $flag
     * @return void
     */
    public static function haltOnMatch(bool $flag = true): void
    {
        self::$halts = $flag;
    }

    /**
     * Выполняет обратный вызов для данного запроса
     *
     * @return void
     */
    public static function dispatch(): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];
        $searches = array_keys(static::$patterns);
        $replaces = array_values(static::$patterns);
        $params = [];

        self::$routes = (array) preg_replace('/\/+/', '/', self::$routes);

        if (self::definedRouteWithoutReg($uri)) {
            $route_pos = array_keys(self::$routes, $uri);
            foreach ($route_pos as $route) {
                /**
                 * Использование опции ANY для сопоставления запросов GET и POST
                 */
                if (self::checkMethod($method, $route)) {
                    self::execute($route, $params);

                    return;
                }
            }

            $pos = array_search($uri, self::$routes, true);

            if (self::checkMethod($method, $pos)) {
                self::execute($pos, $params);

                return;
            }
        } else {
            $pos = 0;

            foreach (self::$routes as $route) {
                $route = str_replace($searches, $replaces, $route);

                if (!self::checkRouteWithReg($uri, $route)) {
                    $pos++;
                    continue;
                }

                if (!self::checkMethod($method, $pos)) {
                    $pos++;
                    continue;
                }

                $params = self::getRouteWithRegMatched($uri, $route);
                array_shift($params);
                self::execute($pos, $params);

                return;
            }
        }

        /**
         * Запустите обратный вызов с ошибкой, если маршрут не был найден
         */
        if (!self::$error_callback) {
            self::$error_callback = static function () {
                header($_SERVER['SERVER_PROTOCOL']." 404 Not Found");
                echo '404 Page not found';
                exit();
            };
        } elseif (is_array(self::$error_callback)) {
            self::get($_SERVER['REQUEST_URI'], self::$error_callback);
            self::$error_callback = null;
            self::dispatch();

            return;
        }

        echo call_user_func(self::$error_callback);
    }

    private static function execute(int $pos, $params): void
    {
        [$controllerClass, $method] = self::$callbacks[$pos];
        $controller = new $controllerClass();

        if (!method_exists($controller, $method)) {
            echo "controller and action not found";
        } else {
            echo call_user_func_array([$controller, $method], $params);
        }
    }

    private static function definedRouteWithoutReg(string $uri): bool
    {
        return in_array($uri, self::$routes, true);
    }

    private static function checkRouteWithReg(string $uri, string $route): bool
    {
        return preg_match("#^$route$#", $uri);
    }

    private static function getRouteWithRegMatched(string $uri, string $route): array
    {
        preg_match("#^$route$#", $uri, $matched);

        return $matched;
    }

    private static function checkMethod(string $method, int $index): bool
    {
        return self::$methods[$index] === $method || self::$methods[$index] === 'ANY';
    }
}
