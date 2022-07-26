<?php

use App\Controllers\FileController;
use App\Controllers\HomeController;
use App\View;
use App\Router;

/**
 * Web routes
 */
Router::get('/', [HomeController::class, 'index']);
Router::get('/test', [HomeController::class, 'test']);
Router::get('/files', [FileController::class, 'index']);
Router::get('/files/create', [FileController::class, 'create']);
Router::post('/file-manager', [FileController::class, 'fileManager']);
Router::get('/file-manager', [FileController::class, 'fileManager']);

/**
 * Маршрут не определен
 */
Router::error(static function () {
	return View::make(
		'Error/404',
		[
			'page_title' => '404',
			'page_subtitle' => 'Ошибка',
		]
	);
});

/**
 * Раскомментируйте эту функцию для переноса таблиц
 * It will commented automatically again
 */
// createTables();

Router::dispatch();
