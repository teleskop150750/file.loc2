<?php

namespace App;

class View
{
    private static array $jsFiles = [];

    /**
     * Загрузите файл представления, например Home/home, и назначьте ему данные
     *
     * @param  string  $view
     * @param  array  $data
     * @param  string  $layout
     * @return string
     */
    public static function make(string $view, array $data = [], string $layout = 'default'): string
    {
        $layoutPath = APP_ROOT.'/app/templates/Views/Layouts/'.$layout.'.php';
        $file = APP_ROOT.'/app/templates/Views/'.$view.'.php';

        $content = null;

        if (!is_readable($file)) {
            die('404 Page not found');
        }

        ob_start();
        require_once $file;
        $content = ob_get_clean();

        if (!is_readable($layoutPath)) {
            die("Не найден шаблон {$layout}");
        }
        ob_start();
        require_once $layoutPath;

        return ob_get_clean();
    }

    /**
     * Добавить js
     * @param  string  $string
     */
    public static function setJs(string $string): void
    {
        self::$jsFiles[] = $string;
    }

    public static function printJs(): void
    {
        $html = '';

        foreach (self::$jsFiles as $path) {
            $html .= "<script src=\"$path\"></script>";
        }

        self::$jsFiles = [];
        echo $html;
    }
}
