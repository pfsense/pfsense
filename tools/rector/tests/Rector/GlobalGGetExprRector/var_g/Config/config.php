<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Tools\Rector\Rector\Rules\GlobalGGetExprRector;

return static function (RectorConfig $rectorConfig): void {
	$rectorConfig->ruleWithConfiguration(GlobalGGetExprRector::class,
										 ['var' => 'g', 'func' => 'g_get']);
};
?>
