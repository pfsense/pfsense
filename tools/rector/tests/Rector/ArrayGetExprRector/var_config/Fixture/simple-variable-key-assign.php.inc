<?php
namespace Tools\Rector\Tests\Rector\ConfigGetExprRector\Fixture;

$baz = 'baz';
$foo = $config['bar'][$baz];
?>
-----
<?php
namespace Tools\Rector\Tests\Rector\ConfigGetExprRector\Fixture;

$baz = 'baz';
$foo = config_get_path("bar/{$baz}");
?>
