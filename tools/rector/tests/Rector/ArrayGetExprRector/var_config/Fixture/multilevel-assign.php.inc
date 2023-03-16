<?php
namespace Tools\Rector\Tests\Rector\ConfigGetExprRector\Fixture;

$foo = $config['bar']['baz'];
?>
-----
<?php
namespace Tools\Rector\Tests\Rector\ConfigGetExprRector\Fixture;

$foo = config_get_path('bar/baz');
?>