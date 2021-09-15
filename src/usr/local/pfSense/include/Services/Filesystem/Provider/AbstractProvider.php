<?php
/*
 * AbstractProvider.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 Rubicon Communications, LLC (Netgate)
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

namespace pfSense\Services\Filesystem\Provider;

use mikehaertl\shellcommand\Command;

use Nette\Utils\{
	Arrays,
	Strings
};

use Symfony\{
	Contracts\Cache\ItemInterface,
	Component\Cache\Adapter\AbstractAdapter,
	Component\Cache\Adapter\PhpFilesAdapter
};

abstract class AbstractProvider {
	private $cacheAdapter = null;

	public function __construct(AbstractAdapter $cacheAdapter = null) {
		if (is_null($cacheAdapter) || (!($cacheAdapter instanceof AbstractAdapter))) {
			require_once('globals.inc');

			global $g;

			$tmpPath = Arrays::get($g, 'tmp_path', '/tmp');

			$cacheAdapter = new PhpFilesAdapter(
				$namespace = 'filesystem',
				$defaultLifetime = 10,
				$directory = "{$tmpPath}/symfony-cache"
			);
		}

		$this->setCacheAdapter($cacheAdapter);
	}

	public function setCacheAdapter(AbstractAdapter $cacheAdapter) {
		$this->cacheAdapter = $cacheAdapter;

		return $this;
	}

	public function getCacheAdapter() {
		return $this->cacheAdapter;
	}

	abstract function getFilesystems();
}

?>