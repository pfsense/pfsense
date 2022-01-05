<?php
/*
 * Filesystems.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021-2022 Rubicon Communications, LLC (Netgate)
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

use pfSense\Services\Filesystem\Provider\{
	AbstractProvider,
	SystemProvider
};

use Nette\Utils\{
	Arrays,
	Strings
};

final class Filesystems {
	private $filesystems = [];

	private $provider = null;

	public function __construct(AbstractProvider $provider = null) {
		if (is_null($provider)
		    || (!($df instanceof AbstractProvider))) {
			$provider = new SystemProvider();
		}

		$this->setProvider($provider);

		$this->_initFilesystems();
	}

	public function setProvider(AbstractProvider $provider) {
		$this->provider = $provider;

		return $this;
	}

	public function getProvider() {
		return $this->provider;
	}

	public function flushProviderCache() {
		$this->getProvider()->flushCache();
	}

	public function getRootFilesystem() {
		foreach ($this->_getFilesystemsTree() as $fs) {
			if ($fs->isRoot()) {
				return $fs;
			}
		}

		return false;
	}

	public function getNonRootFilesystems(...$types) {
		$types = (is_array($types[0])) ? $types[0] : $types;

		return array_filter($this->getFilesystemsFlattened($types), function ($fs) {
			return !$fs->isRoot();
		});
	}

	public function getMounts(...$types) {
		$types = (is_array($types[0])) ? $types[0] : $types;

		return array_map(function($fs) {
			return $fs->getPath();
		}, $this->getFilesystemsFlattened($types));
	}

	public function getFilesystemsFlattened(...$types) {
		$types = (is_array($types[0])) ? $types[0] : $types;

		$filesystems = [];

		foreach($this->_getFilesystemsTree() as $filesystem) {
			$filtered = array_filter($filesystem->getChildrenAndSelf(), function($fs) use ($types) {
				return (empty($types) || in_array($fs->getType(), $types));
			});

			$filesystems = array_merge($filesystems, $filtered);
		}

		return $filesystems;
	}

	private function _getFilesystemsTree() {
		if (empty($this->filesystems)){
			$this->_initFilesystems();
		}

		return $this->filesystems;
	}

	private function _initFilesystems() {
		$this->filesystems = array_map(function($filesystem) {
			return $this->_getNewFilesystemObject($filesystem);
		}, $this->getProvider()->getFilesystems());
	}

	private function _getNewFilesystemObject($filesystem) {
		return (new Filesystem(null, $filesystem));
	}
}

?>