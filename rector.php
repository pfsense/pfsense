<?php
declare(strict_types=1);

use Utils\Rector\Rector\MyFirstRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    // skip third-party code
    $rectorConfig->skip([
	__DIR__ . '/src/usr/local/pfSense/include/vendor/*',
    ]);

    $rectorConfig->paths([
        __DIR__ . '/src',
    ]);

    $rectorConfig->rule(MyFirstRector::class);

};
