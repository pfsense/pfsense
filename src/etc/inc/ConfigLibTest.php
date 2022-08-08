<?php
require_once("config.lib.inc");
use PHPUnit\Framework\TestCase;

class ConfigLibTest extends TestCase {
	public function test_config_get_path(): void {
		// Root element access
		$this->assertEquals("bar", config_get_path("foo", null));
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
		$this->assertNull(config_set_path("barbang/baz", "bang"));
		$this->assertNull(config_get_path("barbang/baz", null));
		// Parent doesn't exist, non-null default return
		$this->assertEquals(-1, config_set_path("barbang/baz", "bang", -1));
		$this->assertNull(config_get_path("barbang/baz", null));

		// Subarray
		$this->assertIsArray(config_set_path("barbang", Array()));
		$this->assertEquals("bang", config_set_path("barbang/baz", "bang"));
		$this->assertEquals("bang", config_get_path("barbang/baz", null));
		// Key exists, replace with array
		$this->assertNotNull(config_get_path("foo", null));
		$this->assertNotNull(config_set_path("foo", ["bar" => "barbaz", "baz" => "barbaz"]));
		$this->assertEquals("barbaz", config_get_path("foo/bar", null));
		$this->assertEquals("barbaz", config_get_path("foo/baz", null));
		// Key in subarray exists
		$this->assertIsArray(config_set_path("bar", Array()));
		$this->assertEquals("barbaz", config_set_path("bar/baz", "barbaz"));
		$this->assertEquals("barbaz", config_get_path("bar/baz", null));
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

	public function setUp(): void {
		global $config;
		$config = array(
			"foo" => "bar",
			"bar" => array(
				"baz" => "bang",
				"foobar" => "foobaz"
			),
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
