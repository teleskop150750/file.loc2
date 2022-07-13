<?php

use App\Database;

/**
 * Create DB tables, indexes & relations
 *
 * @return void
 */
function createTables()
{
	/**
	 * Tables' structure
	 */
	$tablesStructures = [

		"CREATE TABLE IF NOT EXISTS `files` (
            `id` CHAR(40) UNIQUE NOT NULL,
            `name` NVARCHAR(250) NOT NULL,
            `url` NVARCHAR(250) NOT NULL,
            `hash` NVARCHAR(250) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;",
	];

	foreach ($tablesStructures as $tablesStructure) {
		Database::query($tablesStructure);
		Database::execute();
	}

	/**
	 * Prevent to create existed tables by commenting a command that call this function
	 */
	$path_to_file = dirname(__DIR__) . '/src/routes.php';
	$file_contents = file_get_contents($path_to_file);
	$file_contents = str_replace("createTables();", "// createTables();", $file_contents);
	file_put_contents($path_to_file, $file_contents);
}
