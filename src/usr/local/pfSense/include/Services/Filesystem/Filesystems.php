<?php
/*
 * Filesystems.php
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

use Nette\Utils\Arrays;
use Nette\Utils\Strings;

final class Filesystems {
	private $filesystems = [];

	private $df = null;

	public function __construct(DF $df = null) {
		if (is_null($df) || (!($df instanceof DF))) {
			$df = new DF();
		}

		$this->df = $df;

		$this->_initFilesystems();
	}

	public function getDf() {
		return $this->df;
	}

	public function setDf(Df $df) {
		$this->__construct($df);

		return $this;
	}

	public function getFilesystems(...$types) {
		$types = (is_array($types[0])) ? $types[0] : $types;

		return array_values(array_filter($this->filesystems, function ($fs) use ($types) {
			return (empty($types) || in_array($fs->getProperty('type'), $types));
		}));
	}

	public function getRootFilesystem() {
		foreach ($this->getFilesystems() as $fs) {
			if ($fs->isRoot()) {
				return $fs;
			}
		}

		return false;
	}

	public function getNonRootFilesystems(...$types) {
		$types = (is_array($types[0])) ? $types[0] : $types;

		return array_values(array_filter($this->getFilesystems($types), function ($fs) {
			return !$fs->isRoot();
		}));
	}

	public function getMounts(...$types) {
		$types = (is_array($types[0])) ? $types[0] : $types;

		return array_map(function($fs) {
			return $fs->getProperty('mounted-on');
		}, $this->getFilesystems($types));
	}

	private function _initFilesystems() {
		$df = $this->getDf()->getDfData();

		$filesystems = Arrays::get($df, ['storage-system-information', 'filesystem'], array());

		foreach ($filesystems as $filesystem) {
			$filesystem = $this->_getNewFilesystemObject($filesystem);
			
			$this->filesystems[$filesystem->getProperty('mounted-on')] = $filesystem;
		}

		usort($this->filesystems, function ($fs1, $fs2) {
			return $fs1->getProperty('mounted-on') <=> $fs2->getProperty('mounted-on');
		});
	}

	private function _getNewFilesystemObject($filesystem) {
		return (new Filesystem($filesystem));
	}
}

?>