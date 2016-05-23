<?php
/*
	index.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-system-login-logout
##|*NAME=System: Login / Logout / Dashboard
##|*DESCR=Allow access to the 'System: Login / Logout' page and Dashboard.
##|*MATCH=index.php*
##|-PRIV

// Turn on buffering to speed up rendering
ini_set('output_buffering', 'true');

// Start buffering with a cache size of 100000
ob_start(null, "1000");

## Load Essential Includes
require_once('guiconfig.inc');
require_once('functions.inc');
require_once('notices.inc');
require_once("pkg-utils.inc");

if (isset($_POST['closenotice'])) {
	close_notice($_POST['closenotice']);
	sleep(1);
	exit;
}

if (isset($_GET['closenotice'])) {
	close_notice($_GET['closenotice']);
	sleep(1);
}

if ($g['disablecrashreporter'] != true) {
	// Check to see if we have a crash report
	$x = 0;
	if (file_exists("/tmp/PHP_errors.log")) {
		$total = `/bin/cat /tmp/PHP_errors.log | /usr/bin/wc -l | /usr/bin/awk '{ print $1 }'`;
		if ($total > 0) {
			$x++;
		}
	}

	$crash = glob("/var/crash/*");
	$skip_files = array(".", "..", "minfree", "");

	if (is_array($crash)) {
		foreach ($crash as $c) {
			if (!in_array(basename($c), $skip_files)) {
				$x++;
			}
		}

		if ($x > 0) {
			$savemsg = sprintf(gettext("%s has detected a crash report or programming bug. Click <a href='crash_reporter.php'>here</a> for more information."), $g['product_name']);
			$class = "warning";
		}
	}
}

##build list of php include files
$phpincludefiles = array();
$directory = "/usr/local/www/widgets/include/";
$dirhandle = opendir($directory);
$filename = "";

while (false !== ($filename = readdir($dirhandle))) {
	if (!stristr($filename, ".inc")) {
		continue;
	}
	$phpincludefiles[] = $filename;
}

## Include each widget include file.
## These define vars that specify the widget title and title link.
foreach ($phpincludefiles as $includename) {
	if (file_exists($directory . $includename)) {
		include($directory . $includename);
	}
}

##build list of widgets
foreach (glob("/usr/local/www/widgets/widgets/*.widget.php") as $file) {
	$name = basename($file, '.widget.php');
	// Get the widget title that should be in a var defined in the widget's inc file.
	$widgettitle = ${$name . '_title'};

	if (empty(trim($widgettitle))) {
		// Fall back to constructing a title from the file name of the widget.
		$widgettitle = ucwords(str_replace('_', ' ', $name));
	}

	$widgets[ $name ] = array('name' => $widgettitle, 'display' => 'none');
}

##if no config entry found, initialize config entry
if (!is_array($config['widgets'])) {
	$config['widgets'] = array();
}

if ($_POST && $_POST['sequence']) {

	$config['widgets']['sequence'] = rtrim($_POST['sequence'], ',');

	foreach ($widgets as $widgetname => $widgetconfig) {
		if ($_POST[$widgetname . '-config']) {
			$config['widgets'][$widgetname . '-config'] = $_POST[$widgetname . '-config'];
		}
	}

	write_config(gettext("Widget configuration has been changed."));
	header("Location: /");
	exit;
}

## Load Functions Files
require_once('includes/functions.inc.php');

## Check to see if we have a swap space,
## if true, display, if false, hide it ...
if (file_exists("/usr/sbin/swapinfo")) {
	$swapinfo = `/usr/sbin/swapinfo`;
	if (stristr($swapinfo, '%') == true) $showswap=true;
}

## User recently restored his config.
## If packages are installed lets resync
if (file_exists('/conf/needs_package_sync')) {
	if ($config['installedpackages'] <> '' && is_array($config['installedpackages']['package'])) {
		if ($g['platform'] == $g['product_name'] || $g['platform'] == "nanobsd") {
			## If the user has logged into webGUI quickly while the system is booting then do not redirect them to
			## the package reinstall page. That is about to be done by the boot script anyway.
			## The code in head.inc will put up a notice to the user.
			if (!platform_booting()) {
				header('Location: pkg_mgr_install.php?mode=reinstallall');
				exit;
			}
		}
	} else {
		conf_mount_rw();
		@unlink('/conf/needs_package_sync');
		conf_mount_ro();
	}
}

## If it is the first time webConfigurator has been
## accessed since initial install show this stuff.
if (file_exists('/conf/trigger_initial_wizard')) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<link rel="stylesheet" href="/css/pfSense.css" />
	<title><?=$g['product_name']?>.localdomain - <?=$g['product_name']?> first time setup</title>
	<meta http-equiv="refresh" content="1;url=wizard.php?xml=setup_wizard.xml" />
</head>
<body id="loading-wizard" class="no-menu">
	<div id="jumbotron">
		<div class="container">
			<div class="col-sm-offset-3 col-sm-6 col-xs-12">
				<font color="white">
				<p><h3><?=sprintf(gettext("Welcome to %s!\n"), $g['product_name'])?></h3></p>
				<p><?=gettext("One moment while the initial setup wizard starts.")?></p>
				<p><?=gettext("Embedded platform users: Please be patient, the wizard takes a little longer to run than the normal GUI.")?></p>
				<p><?=sprintf(gettext("To bypass the wizard, click on the %s logo on the initial page."), $g['product_name'])?></p>
				</font>
			</div>
		</div>
	</div>
</body>
</html>
<?php
	exit;
}

## Find out whether there's hardware encryption or not
unset($hwcrypto);
$fd = @fopen("{$g['varlog_path']}/dmesg.boot", "r");
if ($fd) {
	while (!feof($fd)) {
		$dmesgl = fgets($fd);
		if (preg_match("/^hifn.: (.*?),/", $dmesgl, $matches)
			or preg_match("/.*(VIA Padlock)/", $dmesgl, $matches)
			or preg_match("/^safe.: (\w.*)/", $dmesgl, $matches)
			or preg_match("/^ubsec.: (.*?),/", $dmesgl, $matches)
			or preg_match("/^padlock.: <(.*?)>,/", $dmesgl, $matches)
			or preg_match("/^glxsb.: (.*?),/", $dmesgl, $matches)) {
			$hwcrypto = $matches[1];
			break;
		}
	}
	fclose($fd);
	if (!isset($hwcrypto) && get_single_sysctl("dev.aesni.0.%desc")) {
		$hwcrypto = get_single_sysctl("dev.aesni.0.%desc");
	}
}

##build widget saved list information
if ($config['widgets'] && $config['widgets']['sequence'] != "") {
	$dashboardcolumns = isset($config['system']['webgui']['dashboardcolumns']) ? $config['system']['webgui']['dashboardcolumns'] : 2;
	$pconfig['sequence'] = $config['widgets']['sequence'];
	$widgetsfromconfig = array();

	foreach (explode(',', $pconfig['sequence']) as $line) {
		list($file, $col, $display) = explode(':', $line);

		// be backwards compatible
		// If the display column information is missing, we will assign a temporary
		// column here. Next time the user saves the dashboard it will fix itself
		if ($col == "") {
			if ($file == "system_information") {
				$col = "col1";
			} else {
				$col = "col2";
			}
		}

		// Limit the column to the current dashboard columns.
		if (substr($col, 3) > $dashboardcolumns) {
			$col = "col" . $dashboardcolumns;
		}

		$offset = strpos($file, '-container');
		if (false !== $offset) {
			$file = substr($file, 0, $offset);
		}

		// Get the widget title that should be in a var defined in the widget's inc file.
		$widgettitle = ${$file . '_title'};

		if (empty(trim($widgettitle))) {
			// Fall back to constructing a title from the file name of the widget.
			$widgettitle = ucwords(str_replace('_', ' ', $file));
		}

		$widgetsfromconfig[ $file ] = array(
			'name' => $widgettitle,
			'col' => $col,
			'display' => $display,
		);
	}

	// add widgets that may not be in the saved configuration, in case they are to be displayed later
	$widgets = $widgetsfromconfig + $widgets;

	##find custom configurations of a particular widget and load its info to $pconfig
	foreach ($widgets as $widgetname => $widgetconfig) {
		if ($config['widgets'][$widgetname . '-config']) {
			$pconfig[$widgetname . '-config'] = $config['widgets'][$widgetname . '-config'];
		}
	}
}

## Get the configured options for Show/Hide available widgets panel.
$dashboard_available_widgets_hidden = isset($config['system']['webgui']['dashboardavailablewidgetspanel']) ? false : true;

if ($dashboard_available_widgets_hidden) {
	$panel_state = 'out';
	$panel_body_state = 'in';
} else {
	$panel_state = 'in';
	$panel_body_state = 'out';
}

## Set Page Title and Include Header
$pgtitle = array(gettext("Status"), gettext("Dashboard"));
include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, $class);
}

pfSense_handle_custom_code("/usr/local/pkg/dashboard/pre_dashboard");

?>

<div class="panel panel-default collapse <?=$panel_state?>" id="widget-available">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext("Available Widgets"); ?>
			<span class="widget-heading-icon">
				<a data-toggle="collapse" href="#widget-available_panel-body" id="widgets-available">
					<i class="fa fa-plus-circle"></i>
				</a>
			</span>
		</h2>
	</div>
	<div id="widget-available_panel-body" class="panel-body collapse <?=$panel_body_state?>">
		<div class="content">
			<div class="row">
<?php

// Build the Available Widgets table using a sorted copy of the $widgets array
$available = $widgets;
uasort($available, function($a, $b){ return strcasecmp($a['name'], $b['name']); });

foreach ($available as $widgetname => $widgetconfig):
	if ($widgetconfig['display'] == 'none'):
?>
		<div class="col-sm-3"><a href="#" id="btnadd-<?=$widgetname?>"><i class="fa fa-plus"></i> <?=$widgetconfig['name']?></a></div>
	<?php endif; ?>
<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>

<div class="hidden" id="widgetSequence">
	<form action="/" method="post" id="widgetSequence_form" name="widgetForm">
		<input type="hidden" name="sequence" value="" />
	</form>
</div>

<?php
$widgetColumns = array();
foreach ($widgets as $widgetname => $widgetconfig) {
	if ($widgetconfig['display'] == 'none') {
		continue;
	}

	if (!file_exists('/usr/local/www/widgets/widgets/'. $widgetname.'.widget.php')) {
		continue;
	}

	if (!isset($widgetColumns[ $widgetconfig['col'] ])) {
		$widgetColumns[ $widgetconfig['col'] ] = array();
	}

	$widgetColumns[ $widgetconfig['col'] ][ $widgetname ] = $widgetconfig;
}
?>

<div class="row">
<?php
	$columnWidth = 12 / $numColumns;

	for ($currentColumnNumber = 1; $currentColumnNumber <= $numColumns; $currentColumnNumber++) {


		//if col$currentColumnNumber exists
		if (isset($widgetColumns['col'.$currentColumnNumber])) {
			echo '<div class="col-md-' . $columnWidth . '" id="widgets-col' . $currentColumnNumber . '">';
			$columnWidgets = $widgetColumns['col'.$currentColumnNumber];

			foreach ($columnWidgets as $widgetname => $widgetconfig) {
				// Compose the widget title and include the title link if available
				$widgetlink = ${$widgetname . '_title_link'};

				if ((strlen($widgetlink) > 0)) {
					$wtitle = '<a href="' . $widgetlink . '"> ' . $widgetconfig['name'] . '</a>';
				} else {
					$wtitle = $widgetconfig['name'];
				}
				?>
					<div class="panel panel-default" id="widget-<?=$widgetname?>">
					<div class="panel-heading">
						<h2 class="panel-title">
							<?=$wtitle?>
							<span class="widget-heading-icon">
								<a data-toggle="collapse" href="#widget-<?=$widgetname?>_panel-footer" class="config hidden">
									<i class="fa fa-wrench"></i>
								</a>
								<a data-toggle="collapse" href="#widget-<?=$widgetname?>_panel-body">
									<!--  actual icon is determined in css based on state of body -->
									<i class="fa fa-plus-circle"></i>
								</a>
								<a data-toggle="close" href="#widget-<?=$widgetname?>">
									<i class="fa fa-times-circle"></i>
								</a>
							</span>
						</h2>
					</div>
					<div id="widget-<?=$widgetname?>_panel-body" class="panel-body collapse<?=($widgetconfig['display'] == 'close' ? '' : ' in')?>">
						<?php include('/usr/local/www/widgets/widgets/'. $widgetname.'.widget.php'); ?>
					</div>
				</div>
				<?php
			}
			echo "</div>";
		} else {
			echo '<div class="col-md-' . $columnWidth . '" id="widgets-col' . $currentColumnNumber . '"></div>';
		}

	}
?>

</div>

<script type="text/javascript">
//<![CDATA[

dirty = false;
function updateWidgets(newWidget) {
	var sequence = '';

	$('.container .col-md-<?=$columnWidth?>').each(function(idx, col) {
		$('.panel', col).each(function(idx, widget) {
			var isOpen = $('.panel-body', widget).hasClass('in');

			sequence += widget.id.split('-')[1] + ':' + col.id.split('-')[1] + ':' + (isOpen ? 'open' : 'close') + ',';
		});
	});

	if (typeof newWidget !== 'undefined') {
		// The system_information widget is always added to column one. Others go in column two
		if (newWidget == "system_information") {
			sequence += newWidget + ':' + 'col1:open';
		} else {
		sequence += newWidget + ':' + 'col2:open';
		}
	}

	$('input[name=sequence]', $('#widgetSequence_form')).val(sequence);
}

events.push(function() {

	// Make panels destroyable
	$('.container .panel-heading a[data-toggle="close"]').each(function (idx, el) {
		$(el).on('click', function(e) {
			$(el).parents('.panel').remove();
			updateWidgets();
			// Submit the form save/display all selected widgets
			$('[name=widgetForm]').submit();
		})
	});

	// Make panels sortable
	$('.container .col-md-<?=$columnWidth?>').sortable({
		handle: '.panel-heading',
		cursor: 'grabbing',
		connectWith: '.container .col-md-<?=$columnWidth?>',
		update: function(){
			dirty = true;
			$('#btnstore').removeClass('invisible');
		}
	});

	// On clicking a widget to install . .
	$('[id^=btnadd-]').click(function(event) {
		// Add the widget name to the list of displayed widgets
		updateWidgets(this.id.replace('btnadd-', ''));

		// Submit the form save/display all selected widgets
		$('[name=widgetForm]').submit();
	});


	$('#btnstore').click(function() {
		updateWidgets();
		dirty = false;
		$(this).addClass('invisible');
		$('[name=widgetForm]').submit();
	});

	// provide a warning message if the user tries to change page before saving
	$(window).bind('beforeunload', function(){
		if (dirty) {
			return ("<?=gettext('One or more widgets have been moved but have not yet been saved')?>");
		} else {
			return undefined;
		}
	});

	// Show the fa-save icon in the breadcrumb bar if the user opens or closes a panel (In case he/she wants to save the new state)
	// (Sometimes this will cause us to see the icon when we don't need it, but better that than the other way round)
	$('.panel').on('hidden.bs.collapse shown.bs.collapse', function (e) {
	    if (e.currentTarget.id != 'widget-available') {
			$('#btnstore').removeClass("invisible");
		}
	});
});
//]]>
</script>
<?php
//build list of javascript include files
foreach (glob('widgets/javascript/*.js') as $file) {
	echo '<script src="'.$file.'"></script>';
}

include("foot.inc");
