<?php
namespace Tools\Rector\Tests\Rector\ConfigGetExprRector\Fixture;

$baz = 'baz';
$foo = $config['bar'][$baz]['bang'][$foobar];
?>
-----
<?php
namespace Tools\Rector\Tests\Rector\ConfigGetExprRector\Fixture;

$baz = 'baz';
$foo = config_get_path("bar/{$baz}/bang/{$foobar}");
?>
