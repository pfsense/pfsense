<?php
require_once("config.lib.inc");
use PHPUnit\Framework\TestCase;

class ConfigLibTest extends TestCase {
	public function test_config_get_path(): void {
		// Root element access
		$this->assertEquals("bar", config_get_path("foo"));
		// Unfound element returns default, even if non-null
		$this->assertNull(config_get_path("foobaz", null));
		$this->assertEquals("test", config_get_path("foobaz", "test"));
		// Subarray
		$this->assertIsArray(config_get_path("bar", null));
		$this->assertEquals("bang", config_get_path("bar/baz", null));
	}

	public function test_config_set_path(): void {
		// Root element
		$this->assertEquals("barbaz", config_set_path("baz", "barbaz"));
		$this->assertEquals("barbaz", config_get_path("baz", "barbaz"));
		// Root element already exists
		$this->assertEquals("barbaz", config_set_path("bar", "barbaz"));
		$this->assertEquals("barbaz", config_get_path("bar", "barbaz"));
		// Parent doesn't exist
		$this->assertEquals("bang", config_set_path("barbang/baz", "bang"));
		$this->assertEquals("bang", config_get_path("barbang/baz"));
		// Path doesn't exist
		$this->assertEquals("bang", config_set_path("foobar/foobaz/foobang", "bang"));
		$this->assertEquals("bang", config_get_path("foobar/foobaz/foobang"));
		// Parent is scalar, no changes are made
		$this->assertNull(config_set_path("barbang/baz/foo", "bar"));
		$this->assertEquals("bang", config_get_path("barbang/baz"));
		// Parent is scalar, non-null default return and no changes are made
		$this->assertEquals(-1, config_set_path("barbang/baz/foo", "bang", -1));
		$this->assertEquals("bang", config_get_path("barbang/baz"));
		// Parent is empty scalar, replaced with array
		$this->assertEquals("bang", config_set_path("emptybar/baz", "bang"));
		$this->assertEquals("bang", config_get_path("emptybar/baz"));
		// Subarray
		$this->assertIsArray(config_set_path("barbang", []));
		$this->assertEquals("bang", config_set_path("barbang/baz", "bang"));
		$this->assertEquals("bang", config_get_path("barbang/baz"));
		// Key exists, replace with array
		$this->assertNotNull(config_get_path("foo"));
		$this->assertNotNull(config_set_path("foo", ["bar" => "barbaz", "baz" => "barbaz"]));
		$this->assertEquals("barbaz", config_get_path("foo/bar"));
		$this->assertEquals("barbaz", config_get_path("foo/baz"));
		// Key in subarray exists
		$this->assertIsArray(config_set_path("bar", []));
		$this->assertEquals("barbaz", config_set_path("bar/baz", "barbaz"));
		$this->assertEquals("barbaz", config_get_path("bar/baz"));
	}

	public function test_config_path_enabled(): void {
		// True value in enable
		$this->assertTrue(config_path_enabled('servicefoo'));
		// False value in enable
		$this->assertTrue(config_path_enabled('servicebar'));
		// null value in enable
		$this->assertFalse(config_path_enabled('servicebaz'));
		// Alternate enable key
		$this->assertTrue(config_path_enabled('servicebang', 'otherkey'));
		// nonexistent path
		$this->assertFalse(config_path_enabled('servicebazbang'));
	}

	public function test_config_del_path(): void {
		global $config;
		// Path not in config
		$this->assertNull(config_del_path("foobang/fooband"));
		// Scalar value
		$this->assertEquals('bar', config_del_path('foo'));
		$this->assertArrayNotHasKey('foo', $config);
		// Subarray
		$expect = $config['bar'];
		$val = config_del_path('bar');
		$this->assertSame($expect, $val);
	}

	public function setUp(): void {
		global $config;
		$config = array(
			"foo" => "bar",
			"bar" => array(
				"baz" => "bang",
				"foobar" => "foobaz"
			),
			"emptybar" => null,
			"servicefoo" => array(
				"enable" => true
			),
			"servicebar" => array(
				"enable" => false
			),
			"servicebaz" => array(
				"enable" => null
			),
			"servicebang" => array(
				"otherkey" => true
			)
		);
	}
}
?>
