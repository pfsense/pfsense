<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    // skip third-party code
    $rectorConfig->skip([
	__DIR__ . '/src/usr/local/pfSense/include/vendor/*',
    ]);

    $rectorConfig->paths([
        __DIR__ . '/src',
    ]);

    $rectorConfig->rule(Rector\DeadCode\Rector\Stmt\RemoveUnreachableStatementRector::class);

    // $rectorConfig->rule(Rector\DeadCode\Rector\Cast\RecastingRemovalRector::class);

    // $rectorConfig->rule(Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector::class);
};
