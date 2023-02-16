<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Tools\Rector\Rector\Rules\ArrayGetExprRector;

return static function (RectorConfig $rectorConfig): void {
	$rectorConfig->ruleWithConfiguration(ArrayGetExprRector::class,
										 ['var' => 'g', 'func' => 'g_get']);
};
?>
