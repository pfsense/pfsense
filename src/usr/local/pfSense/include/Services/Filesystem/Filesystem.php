<?php
/*
 * Filesystem.php
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

final class Filesystem {
	private $filesystem = [];

	public function __construct(array $filesystem) {
		$this->filesystem = $filesystem;
	}

	public function getProperty($key, $default = null) {
		return Arrays::Get($this->filesystem, $key, $default);
	}

	public function getName() {
		return $this->getProperty('name');
	}

	public function getType() {
		return $this->getProperty('type');
	}

	public function getUsedPercent() {
		return $this->getProperty('used-percent');
	}

	public function getPath() {
		return $this->getProperty('mounted-on');
	}

	public function getUsed() {
		return $this->getProperty('used');
	}

	public function getSize() {
		return $this->getProperty('blocks');
	}

	public function getParentPath() {
		return dirname($this->getPath(), 1);
	}

	public function getHtmlClass() {
		return Strings::webalize("root{$this->getPath()}");
	}

	public function getParentHtmlClass() {
		return Strings::webalize("root{$this->getParentPath()}");
	}

	public function isRoot() {
		return Strings::compare($this->getPath(), '/');
	}
}

?>