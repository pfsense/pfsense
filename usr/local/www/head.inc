<?php
/* 
 *   if user has selected a custom template, use it.
 *   otherwise default to pfsense tempalte
 */
if($config['theme'] <> "")
	$g['theme'] = $config['theme'];
else
	$g['theme'] = "pfsense";

$pagetitle = gentitle( $pgtitle );

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title><?=$pagetitle;?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
        <?php if (strpos($_SERVER["SCRIPT_FILENAME"], "wizard.php") !== false &&
                  file_exists("{$g['www_path']}/themes/{$g['theme']}/wizard.css")): ?>
					<?php outputCSSFileInline("themes/{$g['theme']}/wizard.css"); ?>
        <?php else: ?>
        	<link rel="stylesheet" href="/themes/<?php echo $g['theme']; ?>/all.css" media="all" />
        <?php endif; ?>
		<?php if(file_exists("niftycssCode.css")) outputCSSFileInline("niftycssCode.css"); ?>	
		<script type="text/javascript" src="niftycssprintCode.css"></script>
		<?php outputJavaScriptFileInline("niftyjsCode.js"); ?>
		<script type="text/javascript">
			var theme = "<?php echo $g['theme']; ?>";
		</script>
		<?php outputJavaScriptFileInline("themes/{$g['theme']}/loader.js"); ?>
<?php
		if($_GET['enablefirebuglite']) {
			outputJavaScriptFileInline("javascript/pi.js");
			outputJavaScriptFileInline("javascript/firebug-lite.js");
		}

	/*
	 *	Find all javascript files that need to be included
	 *	for this page ... from the arrays ... :)
	 *	Coded by: Erik Kristensen
	 */

	$dir  = trim(basename($_SERVER["SCRIPT_FILENAME"]), '.php');
	$path = "/usr/local/www/javascript/" . $dir . "/";
	if (is_dir($path)) {
		if ($dh = opendir($path)) {
			while (($file = readdir($dh)) !== false) {
		   		if (is_dir($file)) continue;
				//echo "\t".'<script type="text/javascript" src="/javascript/'.$dir.'/'.$file.'"></script>'."\n";
				outputJavaScriptFileInline("javascript/{$dir}/{$file}"); 
			}
			closedir($dh);
		}
	}

	if ($oSajax):
?>
	<script language="javascript" type="text/javascript">
		<?php $oSajax->sajax_show_javascript(); ?>
	</script>

<?	endif; ?>

<?php if (!isset($closehead))
	echo "</head>";
?>
