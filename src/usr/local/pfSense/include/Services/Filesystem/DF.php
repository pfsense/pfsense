<?php
/*
 * DF.php
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

namespace pfSense\Services\Filesystem;

use mikehaertl\shellcommand\Command;

use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

// Pull in pfSense globals
require_once('globals.inc');

final class DF {
	private const DF_PATH = '/bin/df';

	private const DF_CACHE_KEY  = 'DF';

	private $cache = null;

	public function __construct(AbstractAdapter $cache = null) {
		global $g;

		if (is_null($cache) || (!($cache instanceof AbstractAdapter))) {
			$cache = new PhpFilesAdapter(
				$namespace = $g['product_name'],
				$defaultLifetime = 30,
				$directory = "{$g['tmp_path']}/symfony-cache"
			);
		}

		$this->cache = $cache;
	}

	public function getCache() {
		return $this->cache;
	}

	public function setCache(AbstractAdapter $cache) {
		$this->cache = $cache;

		return $this;
	}

	public function flushCache() {
		$this->getCache()->delete(self::DF_CACHE_KEY);
	}

	public function getDfData($flush = false) {
		if ($flush) {
			$this->flushCache();
		}

		return $this->getCache()->get(self::DF_CACHE_KEY, function (ItemInterface $item, &$save) {
			$item->expiresAfter(30);

			$cmd = new Command(self::DF_PATH);

			$cmd->addArg('--libxo=json')->addArg('-h')->addArg('-T');

			$cmd->execute();

			$save = ($cmd->getExitCode() === 0) && !empty($cmd->getOutput());

			$retArray = $save ? json_decode($cmd->getOutput(), true) : array();

			array_walk_recursive($retArray, function (&$x) { $x = trim($x); });

			return $retArray;
		});
	}
}

?>