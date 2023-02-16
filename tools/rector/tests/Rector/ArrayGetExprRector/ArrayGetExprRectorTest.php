<?php
declare(strict_types=1);
namespace Tools\Rector\Tests\Rector\ArrayGetExprRector;

use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use Iterator;
use Symplify\SmartFileSystem\SmartFileInfo;

final class ArrayGetExprRectorTest extends AbstractRectorTestCase {
	/**
	 * @dataProvider provideData()
	 */
	public function test(string $file) : void {
		$this->doTestFile($file);
	}

	/**
	 * @return Iterator<SmartFileInfo>
	 */
	public function provideData(): Iterator {
		return $this->yieldFilesFromDirectory(__DIR__ . '/var_g/Fixture');
	}

	public function provideConfigFilePath(): string {
		return __DIR__ . '/var_g/Config/config.php';
	}
}
?>
