<?php
namespace Tools\Rector\Tests\Rector\ConfigGetExprRector\Fixture;

function get_key(): string {
	return 'baz';
}

$foo = $config['bar'][get_key()]['bang'];
?>
-----
<?php
namespace Tools\Rector\Tests\Rector\ConfigGetExprRector\Fixture;

function get_key(): string {
	return 'baz';
}

$foo = config_get_path('bar/' . get_key() . '/bang');
?>
