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

use Nette\Utils\{
	Arrays,
	Strings
};

final class Filesystem {
	private $parent = null;

	private $filesystem = [];

	private $children = [];

	public function __construct(?Filesystem $parent, $filesystem) {
		$this->_setParent($parent);

		$this->_setFilesystem($filesystem);

		$this->_initChildren();
	}

	public function getBasename() {
		$basename = $this->getPath();

		if ($this->hasParent()) {

			if (Strings::startsWith($this->getPath(), $this->getParentPath())) {

				$basename = Strings::substring($basename, Strings::length($this->getParentPath()));
			
			}

		}

		return $basename;
	}

	public function getName() {
		return $this->_getProperty('name');
	}

	public function hasParent() {
		$parent = $this->getParent();

		return (!is_null($parent) && ($parent instanceof self));
	}

	public function getParent() {
		return $this->parent;
	}

	public function getParentPath() {
		return $this->hasParent() ? $this->getParent()->getPath() : null;
	}

	public function getPath() {
		return $this->_getProperty('mounted-on');
	}

	public function getSize() {
		return $this->_getProperty('blocks');
	}

	public function getType() {
		return $this->_getProperty('type');
	}

	public function getUsed() {
		return $this->_getProperty('used');
	}

	public function getUsedPercent() {
		return $this->_getProperty('used-percent');
	}

	public function getHtmlClass(string $prefix = null, $parentPath = false) : string {
		$parent = $this->getParent();

		$prefix = $this->hasParent() ? "{$parent->getHtmlClass($prefix)}-" : "{$prefix}root";

		$suffix = $parentPath ? null : $this->getBasename();

		return Strings::webalize("{$prefix}{$suffix}");
	}

	public function getParentHtmlClass(string $prefix = null) : string {
		return $this->getHtmlClass($prefix, true);
	}

	public function isRoot() {
		return Strings::compare($this->getPath(), '/');
	}

	public function getChildrenAndSelf() {
		$filesystems = [$this,];

		foreach ($this->getChildren() as $child) {
			$children = $child->getChildrenAndSelf();

			$filesystems = array_merge($filesystems, $children);
		}

		return $filesystems;
	}

	public function hasChildren() {
		return !empty($this->children);
	}

	public function getChildren() {
		if (empty($this->children)) {
			$this->_initChildren();
		}

		return $this->children;
	}

	private function _getProperty($key, $default = null) {
		return Arrays::get($this->filesystem, $key, $default);
	}

	private function _initChildren() {
		$this->children = array_map(function($child) {
			return $this->_getNewFilesystemObject($child);
		}, $this->_getProperty('children'));
	}

	private function _setFilesystem($filesystem) {
		$this->filesystem = $filesystem;

		return $this;
	}

	private function _setParent(?Filesystem $parent) {
		$this->parent = $parent;

		return $this;
	}

	private function _getNewFilesystemObject($filesystem) {
		return (new Filesystem($this, $filesystem));
	}
}

?>