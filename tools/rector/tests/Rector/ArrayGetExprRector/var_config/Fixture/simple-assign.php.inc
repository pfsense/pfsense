<?php
namespace Tools\Rector\Tests\Rector\ConfigGetExprRector\Fixture;

$foo = $config['bar'];
?>
-----
<?php
namespace Tools\Rector\Tests\Rector\ConfigGetExprRector\Fixture;

$foo = config_get_path('bar');
?>