<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;

use Tools\Rector\Rector\Rules;

return static function (RectorConfig $rectorConfig): void {
    // Recursively check these paths...
    $rectorConfig->paths([
        __DIR__ . '/src',
    ]);

    // for files with these extensions...
    $rectorConfig->fileExtensions([
        'php',
        'inc',
    ]);

    // while skipping third-pary code that isn't our concern.
    $rectorConfig->skip([
        __DIR__ . '/src/usr/local/pfSense/include/vendor/*',
    	__DIR__ . '/src/etc/inc/priv.defs.inc',
    ]);

    /*
     * Register Rector rules or rulesets here
     *
     * See https://github.com/rectorphp/rector#running-rector
     *
     * Place custom Rectors in tools/rector/src/Rector named
     * SomeDescriptiveNameRector.php namespaced as "Tools\Rector\Rector".
     * Class should be "final" qualified, extend AbstractRector
     * and the same name as the filename.
     *
     * See https://github.com/rectorphp/rector/blob/main/docs/create_own_rule.md
     */ 

	$rectorConfig->ruleWithConfiguration(Rules\ArrayGetExprRector::class,
										 ['g' => 'g_get',
										  'config' => 'config_get_path']);

};
