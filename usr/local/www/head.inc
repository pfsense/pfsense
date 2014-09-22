<?php
/*
	pfSense_MODULE:	header
*/

$g['theme'] = get_current_theme();

$pagetitle = gentitle( $pgtitle );

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
   "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?=system_get_language_code();?>" xml:lang="<?=system_get_language_code();?>">
<head>
	<title><?php echo($config['system']['hostname'] . "." . $config['system']['domain'] . " - " . $pagetitle); ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=<?=system_get_language_codeset();?>" />
	<link rel="apple-touch-icon" href="/themes/<?php echo $g['theme']; ?>/apple-touch-icon.png"/>
	<link rel="shortcut icon" href="/themes/<?php echo $g['theme']; ?>/images/icons/favicon.ico"/>
		<?php 
		if (file_exists("{$g['www_path']}/themes/{$g['theme']}/table.css")):
			echo "<link type=\"text/css\" rel=\"stylesheet\" href=\"/themes/{$g['theme']}/table.css\" />";
        else:
        	echo "<link type=\"text/css\" rel=\"stylesheet\" href=\"/css/table.css\" media=\"all\" />";
        endif;
		?>

		<?php if (strpos($_SERVER["SCRIPT_FILENAME"], "wizard.php") !== false &&
                  file_exists("{$g['www_path']}/themes/{$g['theme']}/wizard.css")): ?>
					<?php echo "<link type=\"text/css\" rel=\"stylesheet\" href=\"/themes/{$g['theme']}/wizard.css\" />"; ?>
        <?php else: ?>
        	<link type="text/css" rel="stylesheet" href="/themes/<?php echo $g['theme']; ?>/all.css" media="all" />
        <?php endif; ?>
		<link rel="stylesheet" type="text/css" href="/niftycssCode.css" />
		<link rel="stylesheet" type="text/css" href="/niftycssprintCode.css" media="print" />
		<link rel="stylesheet" type="text/css" href="/themes/<?=$g['theme']?>/new_tab_menu.css" media="all" />
		<link rel="stylesheet" type="text/css" href="javascript/jquery/jquery-ui-1.11.1.css" />
		<script type="text/javascript" src="/javascript/niftyjsCode.js"></script>
		<script type="text/javascript" src="/javascript/jquery-1.11.1.min.js"></script>
		<script type="text/javascript" src="/javascript/jquery-migrate-1.2.1.min.js"></script>
		<script type="text/javascript" src="/javascript/jquery/jquery-ui-1.11.1.min.js"></script>
		<script type="text/javascript">
		//<![CDATA[
			var theme = "<?php echo $g['theme']; ?>";

			jQuery.noConflict();
		//]]>
		</script>
		
		<script type="text/javascript" src="/javascript/sorttable.js"></script>
		<script type="text/javascript" src="/javascript/ticker.js"></script>
		<style type="text/css" id="antiClickjack">
		/*<![CDATA[*/
		body{display:none}
		/*]]>*/
		</style>
		<script type="text/javascript">
		//<![CDATA[
		if (self === top) {
			var antiClickjack = document.getElementById("antiClickjack"); antiClickjack.parentNode.removeChild(antiClickjack);
		} else {
			top.location = self.location;
		}
		//]]>
		</script>
		
		<?php echo "\t<script type=\"text/javascript\" src=\"/themes/{$g['theme']}/loader.js\"></script>\n"; ?>
<?php
		if($_GET['enablefirebuglite']) {
			echo "\t<script type=\"text/javascript\" src=\"/javascript/pi.js\"></script>\n";
			echo "\t<script type=\"text/javascript\" src=\"/javascript/firebug-lite.js\"></script>\n";
		}
		echo "\t<script type=\"text/javascript\" src=\"/javascript/scriptaculous/prototype.js\"></script>\n";
		echo "\t<script type=\"text/javascript\" src=\"/javascript/scriptaculous/scriptaculous.js\"></script>\n";
		echo "\t<script type=\"text/javascript\" src=\"/javascript/scriptaculous/effects.js\"></script>\n";
		echo "\t<script type=\"text/javascript\" src=\"/javascript/scriptaculous/dragdrop.js\"></script>\n";
		if(file_exists("{$g['www_path']}/javascript/global.js"))
			echo "\t<script type=\"text/javascript\" src=\"/javascript/global.js\"></script>\n";
	/*
	 *	Find all javascript files that need to be included
	 *	for this page ... from the arrays ... :)
	 *	Coded by: Erik Kristensen
	 */

	$dir  = trim(basename($_SERVER["SCRIPT_FILENAME"], '.php'));
	$path = "{$g['www_path']}/javascript/" . $dir . "/";
	if (is_dir($path)) {
		if ($dh = opendir($path)) {
			while (($file = readdir($dh)) !== false) {
		   		if (is_dir($file)) 
					continue;
				echo "\t<script type=\"text/javascript\" src=\"/javascript/{$dir}/{$file}\"></script>\n";
			}
			closedir($dh);
		}
	}

if (!isset($closehead))
	echo "</head>";

/*  If this page is being remotely managed then do not allow the loading of the contents. */
if($config['remote_managed_pages']['item']) {
	foreach($config['remote_managed_pages']['item'] as $rmp) {
		if($rmp == $_SERVER['SCRIPT_NAME']) {
			include("fbegin.inc");
			print_info_box_np("This page is currently being managed by a remote machine.");
			include("fend.inc");
			exit;
		}
	}	
}

?>
