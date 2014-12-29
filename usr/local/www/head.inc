<?php
/*
	pfSense_MODULE:	header
*/

$pagetitle = gentitle( $pgtitle );

if (isset($config['system']['webgui']['pagenamefirst']))
	$tabtitle = $pagetitle . " - " . $config['system']['hostname'] . "." . $config['system']['domain'];
else
	$tabtitle = $config['system']['hostname'] . "." . $config['system']['domain'] . " - " . $pagetitle;
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<link rel="stylesheet" href="/bootstrap/css/pfSense.css" />
	<title><?= $tabtitle ?></title>
</head>
<?php
/*  If this page is being remotely managed then do not allow the loading of the contents. */
if ($config['remote_managed_pages']['item']) {
	foreach($config['remote_managed_pages']['item'] as $rmp) {
		if ($rmp == $_SERVER['SCRIPT_NAME']) {
			include("fbegin.inc");
			print_info_box_np("This page is currently being managed by a remote machine.");
			include("fend.inc");
			exit;
		}
	}
}

?>