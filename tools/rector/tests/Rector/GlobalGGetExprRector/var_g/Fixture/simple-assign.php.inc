<?php
namespace Tools\Rector\Tests\Rector\ConfigGetExprRector\Fixture;

$foo = $g['bar'];
?>
-----
<?php
namespace Tools\Rector\Tests\Rector\ConfigGetExprRector\Fixture;

$foo = g_get('bar');
?>