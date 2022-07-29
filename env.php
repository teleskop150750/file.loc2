<?php

// General
const APP_ROOT = __DIR__;
const URL_ROOT = 'http://file.loc2';
const COOKIE_DAYS = 180;

// Debug
const DISPLAY_ERRORS = true;
const ERROR_REPORTING = E_ALL;
const LOG_DIR = APP_ROOT . '/storage/logs/';
const LOG_FILE_BASENAME = 'log_';

// Information
const TITLE = 'FILE';
const SUBTITLE = 'FILE';
const THEME_COLOR = '#f0e6dc';
const MASK_COLOR = '#008044';

// DB
const DB_TYPE = 'mysql';
const DB_HOST = 'localhost';
const DB_PORT = '3306';
const DB_USER = 'root';
const DB_PASS = 'root';
const DB_NAME = 'db_file';
// Keep this empty, if you don't use NoSQL DB like SQLite
const NO_SQL_ADDRESS = '';
