<?php
function pfsense_www_class_autoloader($classname) {
	// Convert classname to match filename conventions
	$filename = str_replace('_', '/', $classname);

	// Build the full path, load it if it exists
	$filepath = "/usr/local/www/classes/$filename.class.php";
	if (file_exists($filepath)) {
		require_once($filepath);
	}
}
spl_autoload_register('pfsense_www_class_autoloader');