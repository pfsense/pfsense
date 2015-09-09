<?php
/* $Id$ */
/*
	index.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved. 
 *  Copyright (c)  2004-2012 Scott Ullrich
 *
 *  Redistribution and use in source and binary forms, with or without modification, 
 *  are permitted provided that the following conditions are met: 
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution. 
 *
 *  3. All advertising materials mentioning features or use of this software 
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/). 
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
 */
/*
	pfSense_BUILDER_BINARIES:	/sbin/ifconfig
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-system-login/logout
##|*NAME=System: Login / Logout page / Dashboard
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

if (isset($_GET['closenotice'])) {
	close_notice($_GET['closenotice']);
	echo get_menu_messages();
	exit;
}

if ($g['disablecrashreporter'] != true) {
	// Check to see if we have a crash report
	$x = 0;
	if (file_exists("/tmp/PHP_errors.log")) {
		$total = `/usr/bin/grep -vi warning /tmp/PHP_errors.log | /usr/bin/wc -l | /usr/bin/awk '{ print $1 }'`;
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
			$savemsg = "{$g['product_name']} has detected a crash report or programming bug.  Click <a href='crash_reporter.php'>here</a> for more information.";
		}
	}
}

##build list of widgets
foreach (glob("/usr/local/www/widgets/widgets/*.widget.php") as $file)
{
	$name = basename($file, '.widget.php');
	$widgets[ $name ] = array('name' => ucwords(str_replace('_', ' ', $name)), 'display' => 'none');
}

##insert the system information widget as first, so as to be displayed first
unset($widgets['system_information']);
$widgets = array_merge(array('system_information' => array('name' => 'System Information')), $widgets);

##if no config entry found, initialize config entry
if (!is_array($config['widgets'])) {
	$config['widgets'] = array();
}

if ($_POST && $_POST['sequence']) {
	$config['widgets']['sequence'] = rtrim($_POST['sequence'], ',');

	foreach ($widgets as $widgetname => $widgetconfig) {
		if ($_POST[$widgetname . '-config']) {
			$config['widgets'][$widgetname . '-config'] = $_POST[$name . '-config'];
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
		if ($g['platform'] == "pfSense" || $g['platform'] == "nanobsd") {
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
	<link rel="stylesheet" href="/bootstrap/css/pfSense.css" />
	<title>{$g['product_name']}.localdomain - {$g['product_name']} first time setup</title>
	<meta http-equiv="refresh" content="1;url=wizard.php?xml=setup_wizard.xml" />
</head>
<body id="loading-wizard" class="no-menu">
	<div id="jumbotron">
		<div class="container">
			<div class="col-sm-offset-3 col-sm-6 col-xs-12">
				<p><?=sprintf(gettext("Welcome to %s!\n"), $g['product_name'])?></p>
				<p><?=gettext("One moment while we start the initial setup wizard.")?></p>
				<p><?=gettext("Embedded platform users: Please be patient, the wizard takes a little longer to run than the normal GUI.")?></p>
				<p><?=sprintf(gettext("To bypass the wizard, click on the %s logo on the initial page."), $g['product_name'])?></p>
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
			or preg_match("/^glxsb.: (.*?),/", $dmesgl, $matches)
			or preg_match("/^aesni.: (.*?),/", $dmesgl, $matches)) {
			$hwcrypto = $matches[1];
			break;
		}
	}
	fclose($fd);
}

##build widget saved list information
if ($config['widgets'] && $config['widgets']['sequence'] != "") {
	$pconfig['sequence'] = $config['widgets']['sequence'];
	$widgetsfromconfig = array();

	foreach (explode(',', $pconfig['sequence']) as $line)
	{
		list($file, $col, $display) = explode(':', $line);

		// be backwards compatible
		$offset = strpos($file, '-container');
		if (false !== $offset)
			$file = substr($file, 0, $offset);

		$widgetsfromconfig[ $file ] = array(
			'name' => ucwords(str_replace('_', ' ', $file)),
			'col' => $col,
			'display' => $display,
		);
	}

	##add widgets that may not be in the saved configuration, in case they are to be displayed later
	$widgets = $widgetsfromconfig + $widgets;

	##find custom configurations of a particular widget and load its info to $pconfig
	foreach ($widgets as $widgetname => $widgetconfig) {
		if ($config['widgets'][$name . '-config']) {
			$pconfig[$name . '-config'] = $config['widgets'][$name . '-config'];
		}
	}
}

##build list of php include files
$phpincludefiles = array();
$directory = "/usr/local/www/widgets/include/";
$dirhandle = opendir($directory);
$filename = "";
while (false !== ($filename = readdir($dirhandle))) {
	$phpincludefiles[] = $filename;
}
foreach ($phpincludefiles as $includename) {
	if (!stristr($includename, ".inc")) {
		continue;
	}
	include($directory . $includename);
}

## Set Page Title and Include Header
$pgtitle = array(gettext("Status: Dashboard"));
include("head.inc");

/* Print package server mismatch warning. See https://redmine.pfsense.org/issues/484 */
if (!verify_all_package_servers()) {
	print_info_box(package_server_mismatch_message());
}

if ($savemsg) {
	print_info_box($savemsg);
}

pfSense_handle_custom_code("/usr/local/pkg/dashboard/pre_dashboard");

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Available Widgets"); ?></h2></div>
	<div class="panel-body">
<?php foreach ($widgets as $widgetname => $widgetconfig): ?>
	<?php if ($widgetconfig['display'] == 'none'): ?>
		<div class="col-sm-3"><a href="#"><i class="icon icon-plus"></i> <?=$widgetconfig['name']?></a></div>
	<?php endif; ?>
<?php endforeach; ?>
	</div>
</div>

<div class="modal fade">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title"><?=gettext("Welcome to the Dashboard page"); ?>!</h4>
			</div>
			<div class="modal-body">
				<p>
					<?=gettext("This page allows you to customize the information you want to be displayed!");?>
					<?=gettext("To get started click the");?> FIXME <?=gettext("icon to add widgets.");?><br />
					<br />
					<?=gettext("You can move any widget around by clicking and dragging the title.");?>
				</p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default btn-primary" data-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<div class="hidden" id="widgetSequence">
	<form action="/" method="post" id="widgetSequence">
		<input type="hidden" name="sequence" value="" />

		<button type="submit" class="btn btn-primary">Store widget configuration</button>
	</form>
</div>

<?php
$widgetColumns = array();
foreach ($widgets as $widgetname => $widgetconfig)
{
	if ($widgetconfig['display'] == 'none')
		continue;

	if (!isset($widgetColumns[ $widgetconfig['col'] ]))
		$widgetColumns[ $widgetconfig['col'] ] = array();

	$widgetColumns[ $widgetconfig['col'] ][ $widgetname ] = $widgetconfig;
}
?>

<div class="row">
<?php foreach ($widgetColumns as $column => $columnWidgets):?>
	<div class="col-md-6" id="widgets-<?=$column?>">
<?php foreach ($columnWidgets as $widgetname => $widgetconfig):?>
		<div class="panel panel-default" id="widget-<?=$widgetname?>">
			<div class="panel-heading">
				<?=$widgetconfig['name']?>
				<span class="icons">
					<a data-toggle="collapse" href="#widget-<?=$widgetname?> .panel-footer" class="config hidden">
						<i class="icon icon-wrench"></i>
					</a>
					<a data-toggle="collapse" href="#widget-<?=$widgetname?> .panel-body">
						<!--  actual icon is determined in css based on state of body -->
						<i class="icon icon-plus-sign"></i>
					</a>
					<a data-toggle="close" href="#widget-<?=$widgetname?>">
						<i class="icon icon-remove-sign"></i>
					</a>
				</span>
			</div>
			<div class="panel-body collapse<?=($widgetconfig['display']=='close' ? '' : ' in')?>">
				<?php include('/usr/local/www/widgets/widgets/'. $widgetname.'.widget.php'); ?>
			</div>
		</div>
<?php endforeach; ?>
	</div>
<?php endforeach; ?>
</div>

<script>
function updateWidgets()
{
	var sequence = '';

	$('.container .col-md-6').each(function(idx, col){
		$('.panel', col).each(function(idx, widget){
			var isOpen = $('.panel-body', widget).hasClass('in');

			sequence += widget.id.split('-')[1] +':'+ col.id.split('-')[1] +':'+ (isOpen ? 'open' : 'close') +',';
		});
	});

	$('#widgetSequence').removeClass('hidden');
	$('input[name=sequence]', $('#widgetSequence')).val(sequence);
}

events.push(function() {
	// Hide configuration button for panels without configuration
	$('.container .panel-heading a.config').each(function (idx, el){
		var config = $(el).parents('.panel').children('.panel-footer');
		if (config.length == 1)
			$(el).removeClass('hidden');
	});

	// Initial state & toggle icons of collapsed panel
	$('.container .panel-heading a[data-toggle="collapse"]').each(function (idx, el){
		var body = $(el).parents('.panel').children('.panel-body'), isOpen = body.hasClass('in');
		$(el).toggleClass('icon-plus-sign', !isOpen);
		$(el).toggleClass('icon-minus-sign', isOpen);

		body.on('shown.bs.collapse', function(){
			$(el).toggleClass('icon-minus-sign', true);
			$(el).toggleClass('icon-plus-sign', false);

			updateWidgets();
		});

		body.on('hidden.bs.collapse', function(){
			$(el).toggleClass('icon-minus-sign', false);
			$(el).toggleClass('icon-plus-sign', true);

			updateWidgets();
		});
	});

	// Make panels destroyable
	$('.container .panel-heading a[data-toggle="close"]').each(function (idx, el){
		$(el).on('click', function(e){
			$(el).parents('.panel').remove();
			updateWidgets();
		})
	});

	// Make panels sortable
	$('.container .col-md-6').sortable({
		handle: '.panel-heading',
		cursor: 'grabbing',
		connectWith: '.container .col-md-6',
		update: updateWidgets
	});
});
</script>
<?php
//build list of javascript include files
foreach (glob('widgets/javascript/*.js') as $file)
	echo '<script src="'.$file.'"></script>';

include("foot.inc");
