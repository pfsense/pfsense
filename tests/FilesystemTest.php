<?php
/*
 * FilesystemTest.php
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

use PHPUnit\Framework\TestCase;
use pfSense\Services\Filesystem\Filesystems;
use pfSense\Services\Filesystem\Provider\SystemProvider;
use Nette\Utils\Strings;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

/**
 * Unit tests for the filesystem tree service used by the dashboard disks
 * widget (www/widgets/include/disks.inc).
 *
 * The provider shells out to df, so the tests seed the provider's cache with
 * representative output instead. That keeps the tests deterministic and avoids
 * depending on the machine's real mounts.
 */
class FilesystemTest extends TestCase {
	/**
	 * Filesystem entries equivalent to `df --libxo=json -h -T` output,
	 * sorted by mount point as the provider sorts them.
	 */
	private const FILESYSTEMS = array(
		array('mounted-on' => '/', 'type' => 'ufs', 'blocks' => '20G', 'used' => '5G', 'used-percent' => '30%'),
		array('mounted-on' => '/tmpfs', 'type' => 'tmpfs', 'blocks' => '1G', 'used' => '10M', 'used-percent' => '2%'),
		array('mounted-on' => '/var', 'type' => 'ufs', 'blocks' => '5G', 'used' => '1G', 'used-percent' => '20%'),
		array('mounted-on' => '/var/db', 'type' => 'ufs', 'blocks' => '2G', 'used' => '900M', 'used-percent' => '45%'),
	);

	private $cacheDir = null;

	protected function setUp(): void {
		$this->cacheDir = sys_get_temp_dir() . '/pfsense-filesystem-test-' . getmypid() . '-' . uniqid();
	}

	protected function tearDown(): void {
		if (is_null($this->cacheDir) || !is_dir($this->cacheDir)) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->cacheDir, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($iterator as $entry) {
			$entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
		}
		rmdir($this->cacheDir);
	}

	private function getProvider(): SystemProvider {
		$cache = new PhpFilesAdapter('filesystem', 10, $this->cacheDir);
		$key = Strings::webalize(SystemProvider::class . '::_queryFilesystemProvider');
		$cache->get($key, function () {
			return self::FILESYSTEMS;
		});

		return new SystemProvider($cache);
	}

	private function getFilesystems(): Filesystems {
		return new Filesystems($this->getProvider());
	}

	/**
	 * A mount's parent is the closest mount point that is a strict prefix of
	 * it, and the root has no parent.
	 */
	public function test_getParentFilesystemPath(): void {
		$provider = $this->getProvider();

		$this->assertEquals('/', $provider->_getParentFilesystemPath('/var'));
		$this->assertEquals('/', $provider->_getParentFilesystemPath('/tmpfs'));
		$this->assertEquals('/var', $provider->_getParentFilesystemPath('/var/db'));
		$this->assertNull($provider->_getParentFilesystemPath('/'));
	}

	public function test_getFilesystemsBuildsTree(): void {
		$roots = $this->getProvider()->getFilesystems();

		$this->assertCount(1, $roots, 'Only / is a root mount');
		$this->assertArrayHasKey('/', $roots);
		$this->assertArrayHasKey('/var', $roots['/']['children']);
		$this->assertArrayHasKey('/tmpfs', $roots['/']['children']);
		$this->assertArrayHasKey('/var/db', $roots['/']['children']['/var']['children']);
	}

	/**
	 * getBasename() is the mount name relative to its parent. For every mount
	 * the result must be a bare name, never a path fragment that still carries
	 * the path separator.
	 *
	 * Before the fix /var/db returned '/db' while /var returned 'var', so a
	 * caller reading the basename got two different shapes for the same tree.
	 */
	public function test_getBasenameIsAlwaysABareName(): void {
		$expected = array(
			'/' => '/',
			'/tmpfs' => 'tmpfs',
			'/var' => 'var',
			'/var/db' => 'db',
		);

		foreach ($this->getFilesystems()->getFilesystemsFlattened() as $fs) {
			$this->assertArrayHasKey($fs->getPath(), $expected);
			$this->assertEquals(
				$expected[$fs->getPath()],
				$fs->getBasename(),
				"Unexpected basename for {$fs->getPath()}"
			);
		}
	}

	public function test_getHtmlClass(): void {
		$classes = array();
		foreach ($this->getFilesystems()->getFilesystemsFlattened() as $fs) {
			$classes[$fs->getPath()] = $fs->getHtmlClass();
		}

		$this->assertEquals('root', $classes['/']);
		$this->assertEquals('root-var', $classes['/var']);
		$this->assertEquals('root-var-db', $classes['/var/db']);
	}

	public function test_getMounts(): void {
		$mounts = $this->getFilesystems()->getMounts('ufs');
		$this->assertContains('/var', $mounts);
		$this->assertContains('/var/db', $mounts);
		$this->assertNotContains('/tmpfs', $mounts, 'tmpfs is filtered out');
	}

	/**
	 * The type filter helpers are variadic, so calling them with no arguments
	 * is valid and must return everything rather than reading an undefined
	 * index.
	 *
	 * Before the fix each of these read $types[0] unconditionally and emitted
	 * "Undefined array key 0" on every call that passed no filter.
	 */
	public function test_typeFiltersAcceptNoArguments(): void {
		$filesystems = $this->getFilesystems();

		$this->assertCount(4, $filesystems->getFilesystemsFlattened());
		$this->assertCount(4, $filesystems->getMounts());
		$this->assertCount(3, $filesystems->getNonRootFilesystems());
	}

	/**
	 * Passing an array of types must keep working alongside the no-argument
	 * form.
	 */
	public function test_typeFiltersAcceptArrayArgument(): void {
		$filesystems = $this->getFilesystems();

		$this->assertCount(1, $filesystems->getMounts(array('tmpfs')));
		$this->assertCount(1, $filesystems->getMounts('tmpfs'));
	}
}
