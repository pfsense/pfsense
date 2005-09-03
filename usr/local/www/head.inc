<?php
/* 
 *   if user has selected a custom template, use it.
 *   otherwise default to pfsense tempalte
 */
if($config['theme'] <> "")
	$g['theme'] = $config['theme'];
else
	$g['theme'] = "pfsense";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title><?=gentitle($pgtitle);?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
	<link rel="stylesheet" href="/themes/<?php echo $g['theme']; ?>/all.css" media="all" />
	<link rel="stylesheet" type="text/css" href="/niftycssprintCode.css" media="print">
	<script type="text/javascript" src="/themes/<?php echo $g['theme']; ?>/loader.js"></script>
	<script type="text/javascript">var theme = "<?php echo $g['theme']; ?>"</script>

<?
	/*
	 *	Find all javascript files that need to be included
	 *	for this page ... from the arrays ... :)
	 *	Coded by: Erik Kristensen
	 */

	## Make Sure $page_filename has been set ##
	## if it hasn't been don't bother ... :P ##
	/*
	if ($page_filename) {
		include('includes/javascript.inc.php');

		foreach ($top_javascript_files["$page_filename"] as $file) {
		 	echo '<script type="text/javascript" src="/javascript/'.$file.'"></script>';
		}
	}
	*/
	$dir  = trim(basename($_SERVER["PHP_SELF"]), '.php');
	$path = "/usr/local/www/javascript/" . $dir . "/";
	if (is_dir($path)) {
		if ($dh = opendir($path)) {
			while (($file = readdir($dh)) !== false) {
				"here<br>";
		   		if (is_dir($file)) continue;
				echo "\t".'<script type="text/javascript" src="/javascript/'.$dir.'/'.$file.'"></script>'."\n";
			}
			closedir($dh);
		}
	}
?>

<?php if (!isset($closehead)){ ?>
</head>
<?php } ?>
