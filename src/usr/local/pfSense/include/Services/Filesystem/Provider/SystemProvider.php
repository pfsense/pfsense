<?php
/*
 * SystemProvider.php
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

final class SystemProvider extends AbstractProvider {
	private const DF_BINARY_PATH = '/bin/df';

	public function getFilesystems() {
		$roots = [];

		foreach ($this->_queryFilesystemProvider() as $idx => $filesystem) {
			$path = Arrays::get($filesystem, 'mounted-on', '');

			$filesystems[$path] = $filesystem;

			$filesystems[$path]['children'] = [];

			$parent = $this->_getParentFilesystemPath($path);

			$filesystems[$parent]['children'][$path] = &Arrays::getRef($filesystems, $path);

			if (is_null($parent)) {
				$roots[$path] = $path;
			}
		}

		return array_filter($filesystems, function($filesystem) use ($roots) {
			return array_key_exists($filesystem, $roots);
		}, ARRAY_FILTER_USE_KEY);
	}

	public function flushCache() {
		$this->getCacheAdapter()->prune();
	}

	public function _getParentFilesystemPath($path) {
		$filesystems = $this->_queryFilesystemProvider();

		foreach (array_reverse($filesystems) as $filesystem) {
			$mount = Arrays::get($filesystem, 'mounted-on', '');

			if (!Strings::compare($path, $mount)
			    && Strings::startsWith($path, $mount)) {
				return $mount;
			}
		}
	}

	private function _queryFilesystemProvider() {
		$cacheKey = Strings::webalize(__METHOD__);

		return $this->getCacheAdapter()->get($cacheKey, function (ItemInterface $item, &$save) {
			$cmd = new Command(self::DF_BINARY_PATH);

			$cmd->addArg('--libxo=json')->addArg('-h')->addArg('-T');

			$cmd->execute();

			$save = ($cmd->getExitCode() === 0) && !empty($cmd->getOutput());

			$retArray = $save ? json_decode($cmd->getOutput(), true) : array();

			array_walk_recursive($retArray, function (&$x) { $x = trim($x); });

			$filesystems = Arrays::get($retArray, ['storage-system-information', 'filesystem'], array());

			usort($filesystems, function($a, $b) {
				return (Arrays::get($a, 'mounted-on', '') <=> Arrays::get($b, 'mounted-on', ''));
			});

			return $filesystems;
		});
	}
}

?>