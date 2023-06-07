<?php
declare(strict_types=1);
namespace Tools\Rector\Tests\Rector\ArrayGetExprRector;

use Iterator;
use Rector\Testing\Contract\RectorTestInterface;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use Symplify\SmartFileSystem\SmartFileInfo;

final class ArrayGetExprRectorTest extends AbstractRectorTestCase implements RectorTestInterface {
	/**
	 * @test
	 * @dataProvider provideData()
	 */
	public function g(string $file) : void {
		$this->doTestFile($file);
	}

	/**
	 * @test
	 * @dataProvider provideData()
	 */
	public function config(string $file) : void {
		$this->doTestFile($file);
	}

	/**
	 * @return Iterator<SmartFileInfo>
	 */
	public static function provideData(string $test_name): Iterator {
		return AbstractRectorTestCase::yieldFilesFromDirectory(__DIR__ . "/var_{$test_name}/Fixture");
	}

	public function provideConfigFilePath(): string {
		return __DIR__ . "/Config/config.php";
	}
}
?>
