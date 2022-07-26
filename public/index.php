<?php

/**
 * Случайный токен для использования в качестве CSRF с истекшим сроком действия
 */

use App\Helper;

session_start();
if (!isset($_SESSION['token']) || (isset($_SESSION['token-expire']) && time() > $_SESSION['token-expire'])) {
    $_SESSION['token'] = substr(base_convert(sha1(uniqid(mt_rand(), true)), 16, 36), 0, 32);
    $_SESSION['token-expire'] = time() + 3600;
}

/**
 * Настройка автоматической загрузки
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/env.php';
require_once dirname(__DIR__) . '/routes/routes.php';

/**
 * Контроль сообщения об ошибках через переменные среды
 */
ini_set('display_errors', DISPLAY_ERRORS);
error_reporting(ERROR_REPORTING);

$r = \FileManager\Modules\Http\Request::createFromGlobals();
echo '<pre>';
var_export($r->getBaseUrlReal());
echo '</pre>';
echo '<pre>';
var_export($r->getBasePath());
echo '</pre>';
echo '<pre>';
var_export($r->getBaseUrl());
echo '</pre>';
echo '<pre>';
var_export($r->getUri());
echo '</pre>';
echo '<pre>';
var_export($r->getRequestUri());
echo '</pre>';
echo '<pre>';
var_export($r->getPathInfo());
echo '</pre>';
