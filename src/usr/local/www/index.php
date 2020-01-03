<?php
/*
 * index.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-system-login-logout
##|*NAME=System: Login / Logout / Dashboard
##|*DESCR=Allow access to the 'System: Login / Logout' page and Dashboard.
##|*MATCH=index.php*
##|-PRIV

// Message to display if the session times out and an AJAX call is made
$timeoutmessage = gettext("The dashboard web session has timed out.\\n" .
	"It will not update until you refresh the page and log-in again.");

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

if (isset($_REQUEST['closenotice'])) {
	close_notice($_REQUEST['closenotice']);
	sleep(1);
}

if (($g['disablecrashreporter'] != true) && (system_has_crash_data() || system_has_php_errors())) {
	$savemsg = sprintf(gettext("%s has detected a crash report or programming bug."), $g['product_name']) . " ";
	if (isAllowedPage("/crash_reporter.php")) {
		$savemsg .= sprintf(gettext('Click %1$shere%2$s for more information.'), '<a href="crash_reporter.php">', '</a>');
	} else {
		$savemsg .= sprintf(gettext("Contact a firewall administrator for more information."));
	}
	$class = "warning";
}

## Include each widget php include file.
## These define vars that specify the widget title and title link.

$directory = "/usr/local/www/widgets/include/";
$dirhandle = opendir($directory);
$filename = "";

while (($filename = readdir($dirhandle)) !== false) {
	if (strtolower(substr($filename, -4)) == ".inc" && file_exists($directory . $filename)) {
		include_once($directory . $filename);
	}
}

##build list of widgets
foreach (glob("/usr/local/www/widgets/widgets/*.widget.php") as $file) {
	$basename = basename($file, '.widget.php');
	// Get the widget title that should be in a var defined in the widget's inc file.
	$widgettitle = ${$basename . '_title'};

	if (empty(trim($widgettitle))) {
		// Fall back to constructing a title from the file name of the widget.
		$widgettitle = ucwords(str_replace('_', ' ', $basename));
	}

	$known_widgets[$basename . '-0'] = array(
		'basename' => $basename,
		'title' => $widgettitle,
		'display' => 'none',
		'multicopy' => ${$basename . '_allow_multiple_widget_copies'}
	);
}

##if no config entry found, initialize config entry
if (!is_array($config['widgets'])) {
	$config['widgets'] = array();
}

if (!is_array($user_settings['widgets'])) {
	$user_settings['widgets'] = array();
}

if ($_POST && $_POST['sequence']) {

	// Start with the user's widget settings.
	$widget_settings = $user_settings['widgets'];

	$widget_sep = ',';
	$widget_seq_array = explode($widget_sep, rtrim($_POST['sequence'], $widget_sep));
	$widget_counter_array = array();
	$widget_sep = '';

	// Make a record of the counter of each widget that is in use.
	foreach ($widget_seq_array as $widget_seq_data) {
		list($basename, $col, $display, $widget_counter) = explode(':', $widget_seq_data);

		if ($widget_counter != 'next') {
			if (!is_numeric($widget_counter)) {
				continue;
			}
			$widget_counter_array[$basename][$widget_counter] = true;
			$widget_sequence .= $widget_sep . $widget_seq_data;
			$widget_sep = ',';
		}
	}

	// Find any new entry (and do not assume there is only 1 new entry)
	foreach ($widget_seq_array as $widget_seq_data) {
		list($basename, $col, $display, $widget_counter) = explode(':', $widget_seq_data);

		if ($widget_counter == 'next') {
			// Construct the widget counter of the new widget instance by finding
			// the first non-negative integer that is not in use.
			// The reasoning here is that if you just deleted a widget instance,
			// e.g. had System Information 0,1,2 and deleted 1,
			// then when you add System Information again it will become instance 1,
			// which will bring back whatever filter selections happened to be on
			// the previous instance 1.
			$instance_num = 0;

			while (isset($widget_counter_array[$basename][$instance_num])) {
				$instance_num++;
			}

			$widget_sequence .= $widget_sep . $basename . ':' . $col . ':' . $display . ':' . $instance_num;
			$widget_counter_array[$basename][$instance_num] = true;
			$widget_sep = ',';
		}
	}

	$widget_settings['sequence'] = $widget_sequence;

	foreach ($widget_counter_array as $basename => $instances) {
		foreach ($instances as $instance => $value) {
			$widgetconfigname = $basename . '-' . $instance . '-config';
			if ($_POST[$widgetconfigname]) {
				$widget_settings[$widgetconfigname] = $_POST[$widgetconfigname];
			}
		}
	}

	save_widget_settings($_SESSION['Username'], $widget_settings);
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
					<p><h3><?=sprintf(gettext("Welcome to %s!") . "\n", $g['product_name'])?></h3></p>
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
			or preg_match("/^padlock.: <(.*?)>,/", $dmesgl, $matches)) {
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
if ($user_settings['widgets']['sequence'] != "") {
	$dashboardcolumns = isset($user_settings['webgui']['dashboardcolumns']) ? (int) $user_settings['webgui']['dashboardcolumns'] : 2;
	$pconfig['sequence'] = $user_settings['widgets']['sequence'];
	$widgetsfromconfig = array();

	foreach (explode(',', $pconfig['sequence']) as $line) {
		$line_items = explode(':', $line);
		if (count($line_items) == 3) {
			// There can be multiple copies of a widget on the dashboard.
			// Default the copy number if it is not present (e.g. from old configs)
			$line_items[] = 0;
		}

		list($basename, $col, $display, $copynum) = $line_items;
		if (!is_numeric($copynum)) {
			continue;
		}

		// be backwards compatible
		// If the display column information is missing, we will assign a temporary
		// column here. Next time the user saves the dashboard it will fix itself
		if ($col == "") {
			if ($basename == "system_information") {
				$col = "col1";
			} else {
				$col = "col2";
			}
		}

		// Limit the column to the current dashboard columns.
		if (substr($col, 3) > $dashboardcolumns) {
			$col = "col" . $dashboardcolumns;
		}

		$offset = strpos($basename, '-container');
		if (false !== $offset) {
			$basename = substr($basename, 0, $offset);
		}
		$widgetkey = $basename . '-' . $copynum;

		if (isset($user_settings['widgets'][$widgetkey]['descr'])) {
			$widgettitle = htmlentities($user_settings['widgets'][$widgetkey]['descr']);
		} else {
			// Get the widget title that should be in a var defined in the widget's inc file.
			$widgettitle = ${$basename . '_title'};

			if (empty(trim($widgettitle))) {
				// Fall back to constructing a title from the file name of the widget.
				$widgettitle = ucwords(str_replace('_', ' ', $basename));
			}
		}

		$widgetsfromconfig[$widgetkey] = array(
			'basename' => $basename,
			'title' => $widgettitle,
			'col' => $col,
			'display' => $display,
			'copynum' => $copynum,
			'multicopy' => ${$basename . '_allow_multiple_widget_copies'}
		);

		// Update the known_widgets entry so we know if any copy of the widget is being displayed
		$known_widgets[$basename . '-0']['display'] = $display;
	}

	// add widgets that may not be in the saved configuration, in case they are to be displayed later
	$widgets = $widgetsfromconfig + $known_widgets;

	##find custom configurations of a particular widget and load its info to $pconfig
	foreach ($widgets as $widgetname => $widgetconfig) {
		if ($config['widgets'][$widgetname . '-config']) {
			$pconfig[$widgetname . '-config'] = $config['widgets'][$widgetname . '-config'];
		}
	}
}

## Get the configured options for Show/Hide available widgets panel.
$dashboard_available_widgets_hidden = !$user_settings['webgui']['dashboardavailablewidgetspanel'];

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

// Build the Available Widgets table using a sorted copy of the $known_widgets array
$available = $known_widgets;
uasort($available, function($a, $b){ return strcasecmp($a['title'], $b['title']); });

foreach ($available as $widgetkey => $widgetconfig):
	// If the widget supports multiple copies, or no copies are displayed yet, then it is available to add
	if (($widgetconfig['multicopy']) || ($widgetconfig['display'] == 'none')):
?>
		<div class="col-sm-3"><a href="#" id="btnadd-<?=$widgetconfig['basename']?>"><i class="fa fa-plus"></i> <?=$widgetconfig['title']?></a></div>
	<?php endif; ?>
<?php
endforeach;
?>
			</div>
<p style="text-align:center"><?=sprintf(gettext('Other dashboard settings are available from the <a href="%s">General Setup</a> page.'), '/system.php')?></p>
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
foreach ($widgets as $widgetkey => $widgetconfig) {
	if ($widgetconfig['display'] != 'none' && file_exists("/usr/local/www/widgets/widgets/{$widgetconfig['basename']}.widget.php")) {
		if (!isset($widgetColumns[$widgetconfig['col']])) {
			$widgetColumns[$widgetconfig['col']] = array();
		}
		$widgetColumns[$widgetconfig['col']][$widgetkey] = $widgetconfig;
	}
}
?>

<div class="row">
<?php
	$columnWidth = (int) (12 / $numColumns);

	for ($currentColumnNumber = 1; $currentColumnNumber <= $numColumns; $currentColumnNumber++) {


		//if col$currentColumnNumber exists
		if (isset($widgetColumns['col'.$currentColumnNumber])) {
			echo '<div class="col-md-' . $columnWidth . '" id="widgets-col' . $currentColumnNumber . '">';
			$columnWidgets = $widgetColumns['col'.$currentColumnNumber];

			foreach ($columnWidgets as $widgetkey => $widgetconfig) {
				// Construct some standard names for the ids this widget will use for its commonly-used elements.
				// Included widget.php code can rely on and use these, so the format does not have to be repeated in every widget.php
				$widget_panel_body_id = 'widget-' . $widgetkey . '_panel-body';
				$widget_panel_footer_id = 'widget-' . $widgetkey . '_panel-footer';
				$widget_showallnone_id = 'widget-' . $widgetkey . '_showallnone';

				// Compose the widget title and include the title link if available
				$widgetlink = ${$widgetconfig['basename'] . '_title_link'};

				if ((strlen($widgetlink) > 0)) {
					$wtitle = '<a href="' . $widgetlink . '"> ' . $widgetconfig['title'] . '</a>';
				} else {
					$wtitle = $widgetconfig['title'];
				}
				?>
				<div class="panel panel-default" id="widget-<?=$widgetkey?>">
					<div class="panel-heading">
						<h2 class="panel-title">
							<?=$wtitle?>
							<span class="widget-heading-icon">
								<a data-toggle="collapse" href="#<?=$widget_panel_footer_id?>" class="config hidden">
									<i class="fa fa-wrench"></i>
								</a>
								<a data-toggle="collapse" href="#<?=$widget_panel_body_id?>">
									<!--  actual icon is determined in css based on state of body -->
									<i class="fa fa-plus-circle"></i>
								</a>
								<a data-toggle="close" href="#widget-<?=$widgetkey?>">
									<i class="fa fa-times-circle"></i>
								</a>
							</span>
						</h2>
					</div>
					<div id="<?=$widget_panel_body_id?>" class="panel-body collapse<?=($widgetconfig['display'] == 'close' ? '' : ' in')?>">
						<?php
							// For backward compatibility, included *.widget.php code needs the var $widgetname
							$widgetname = $widgetkey;
							// Determine if this is the first instance of this particular widget.
							// Provide the $widget_first_instance var, to make it easy for the included widget code
							// to be able to know if it is being included for the first time.
							if ($widgets_found[$widgetconfig['basename']]) {
								$widget_first_instance = false;
							} else {
								$widget_first_instance = true;
								$widgets_found[$widgetconfig['basename']] = true;
							}
							include('/usr/local/www/widgets/widgets/' . $widgetconfig['basename'] . '.widget.php');
						?>
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

<?php
/*
 * Import the modal form used to display the copyright/usage information
 * when trigger file exists. Trigger file is created during upgrade process
 * when /etc/version changes
 */
require_once("copyget.inc");

if (file_exists("{$g['cf_conf_path']}/copynotice_display")) {
	require_once("copynotice.inc");
	@unlink("{$g['cf_conf_path']}/copynotice_display");
}

/*
 * Import the modal form used to display any HTML text a package may want to display
 * on installation or removal
 */
$ui_notice = "/tmp/package_ui_notice";
if (file_exists($ui_notice)) {
	require_once("{$g['www_path']}/upgrnotice.inc");
}
?>

<script type="text/javascript">
//<![CDATA[

dirty = false;
function updateWidgets(newWidget) {
	var sequence = '';

	$('.container .col-md-<?=$columnWidth?>').each(function(idx, col) {
		$('.panel', col).each(function(idx, widget) {
			var isOpen = $('.panel-body', widget).hasClass('in');
			var widget_basename = widget.id.split('-')[1];

			// Only save details for panels that have id's like'widget-*'
			// Some widgets create other panels, so ignore any of those.
			if ((widget.id.split('-')[0] == 'widget') && (typeof widget_basename !== 'undefined')) {
				sequence += widget_basename + ':' + col.id.split('-')[1] + ':' + (isOpen ? 'open' : 'close') + ':' + widget.id.split('-')[2] + ',';
			}
		});
	});

	if (typeof newWidget !== 'undefined') {
		// The system_information widget is always added to column one. Others go in column two
		if (newWidget == "system_information") {
			sequence += newWidget.split('-')[0] + ':' + 'col1:open:next';
		} else {
			sequence += newWidget.split('-')[0] + ':' + 'col2:open:next';
		}
	}

	$('input[name=sequence]', $('#widgetSequence_form')).val(sequence);
}

// Determine if all the checkboxes are checked
function are_all_checked(checkbox_panel_ref) {
	var allBoxesChecked = true;
	$(checkbox_panel_ref).each(function() {
		if ((this.type == 'checkbox') && !this.checked) {
			allBoxesChecked = false;
		}
	});
	return allBoxesChecked;
}

// If the checkboxes are all checked, then clear them all.
// Otherwise set them all.
function set_clear_checkboxes(checkbox_panel_ref) {
	checkTheBoxes = !are_all_checked(checkbox_panel_ref);

	$(checkbox_panel_ref).each(function() {
		$(this).prop("checked", checkTheBoxes);
	});
}

// Set the given id to All or None button depending if the checkboxes are all checked.
function set_all_none_button(checkbox_panel_ref, all_none_button_id) {
	if (are_all_checked(checkbox_panel_ref)) {
		text = "<?=gettext('None')?>";
	} else {
		text = "<?=gettext('All')?>";
	}

	$("#" + all_none_button_id).html('<i class="fa fa-undo icon-embed-btn"></i>' + text);
}

// Setup the necessary events to manage the All/None button and included checkboxes
// used for selecting the items to show on a widget.
function set_widget_checkbox_events(checkbox_panel_ref, all_none_button_id) {
		set_all_none_button(checkbox_panel_ref, all_none_button_id);

		$(checkbox_panel_ref).change(function() {
			set_all_none_button(checkbox_panel_ref, all_none_button_id);
		});

		$("#" + all_none_button_id).click(function() {
			set_clear_checkboxes(checkbox_panel_ref);
			set_all_none_button(checkbox_panel_ref, all_none_button_id);
		});
}

// ---------------------Centralized widget refresh system -------------------------------------------
// These need to live outside of the events.push() function to enable the widgets to see them
var ajaxspecs = new Array();	// Array to hold widget refresh specifications (objects )
var ajaxidx = 0;
var ajaxmutex = false;
var ajaxcntr = 0;

// Add a widget refresh object to the array list
function register_ajax(ws) {
  ajaxspecs.push(ws);
}
// ---------------------------------------------------------------------------------------------------

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

	// --------------------- Centralized widget refresh system ------------------------------
	ajaxtimeout = false;

	function make_ajax_call(wd) {
		ajaxmutex = true;

		$.ajax({
			type: 'POST',
			url: wd.url,
			dataType: 'html',
			data: wd.parms,

			success: function(data){
				if (data.length > 0 ) {
					// If the session has timed out, display a pop-up
					if (data.indexOf("SESSION_TIMEOUT") === -1) {
						wd.callback(data);
					} else {
						if (ajaxtimeout === false) {
							ajaxtimeout = true;
							alert("<?=$timeoutmessage?>");
						}
					}
				}

				ajaxmutex = false;
			},

			error: function(e){
//				alert("Error: " + e);
				ajaxmutex = false;
			}
		});
	}

	// Loop through each AJAX widget refresh object, make the AJAX call and pass the
	// results back to the widget's callback function
	function executewidget() {
		if (ajaxspecs.length > 0) {
			var freq = ajaxspecs[ajaxidx].freq;	// widget can specify it should be called freq times around the loop

			if (!ajaxmutex) {
				if (((ajaxcntr % freq) === 0) && (typeof ajaxspecs[ajaxidx].callback === "function" )) {
				    make_ajax_call(ajaxspecs[ajaxidx]);
				}

			    if (++ajaxidx >= ajaxspecs.length) {
					ajaxidx = 0;

					if (++ajaxcntr >= 4096) {
						ajaxcntr = 0;
					}
			    }
			}

		    setTimeout(function() { executewidget(); }, 1000);
	  	}
	}

	// Kick it off
	executewidget();

	//----------------------------------------------------------------------------------------------------
});
//]]>
</script>

<?php
//build list of javascript include files
foreach (glob('widgets/javascript/*.js') as $file) {
	$mtime = filemtime("/usr/local/www/{$file}");
	echo '<script src="'.$file.'?v='.$mtime.'"></script>';
}

include("foot.inc");
