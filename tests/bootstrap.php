<?php
/*
 * bootstrap.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2026 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/*
 * Test bootstrap for running PHPUnit from a source checkout.
 *
 * composer.json maps the pfSense\ namespace to //usr/local/pfSense/include/,
 * which only exists on a built system. On a checkout that path does not
 * resolve, so register a PSR-4 autoloader that points at the in-tree sources.
 * The composer autoloader is still loaded first so vendor packages such as
 * Nette\Utils and Symfony Cache keep working.
 */

require_once(__DIR__ . '/../src/usr/local/pfSense/include/vendor/autoload.php');

spl_autoload_register(function (string $class): void {
	$prefix = 'pfSense\\';

	if (!str_starts_with($class, $prefix)) {
		return;
	}

	$relative = substr($class, strlen($prefix));
	$file = __DIR__ . '/../src/usr/local/pfSense/include/' . str_replace('\\', '/', $relative) . '.php';

	if (file_exists($file)) {
		require_once($file);
	}
});
