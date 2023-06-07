<?php
namespace Tools\Rector\Tests\Rector\ConfigGetExprRector\Fixture;

$baz = 'baz';
$foo = $config['bar'][$baz]['bang'];
?>
-----
<?php
namespace Tools\Rector\Tests\Rector\ConfigGetExprRector\Fixture;

$baz = 'baz';
$foo = config_get_path("bar/{$baz}/bang");
?>
