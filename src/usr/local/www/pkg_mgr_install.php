<?php
/*
	pkg_mgr_install.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2005 Colin Smith
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
##|*IDENT=page-system-packagemanager-installpackage
##|*NAME=System: Package Manager: Install Package
##|*DESCR=Allow access to the 'System: Package Manager: Install Package' page.
##|*MATCH=pkg_mgr_install.php*
##|-PRIV

ini_set('max_execution_time', '0');

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("pkg-utils.inc");

$sendto = "output";
$start_polling = false;
$firmwareupdate = false;
$guitimeout = 90;	// Seconds to wait before reloading the page after reboot
$guiretry = 20;		// Seconds to try again if $guitimeout was not long enough
//---------------------------------------------------------------------------------------------------------------------
// After an installation or removal has been started (mwexec(/usr/local/sbin/pfSense-upgrade-GUI.sh . . . )) AJAX calls
// are made to get status.
// The log file is read and the newest progress record retrieved. The data is formatted
// as JSON before being returned to the AJAX caller (at the bottom of this file)
//
// Arguments received here:
//		logfilename = Passed to installation script to tell it how to name the log file we will parse
//		next_log_line = Send log file entries that come after this line number
//
// JSON items returned
//		log:
//		exitcode:
//		data:{current:, total}
//
// Todo:
//		Respect next_log_line and append log to output window rather than writing it


if ($_REQUEST['ajax']) {
	$response = "";
	$code = 0;

	// Check to see if our process is still running
	$pidfile = $g['varrun_path'] . '/' . $g['product_name'] . '-upgrade.pid';
	$running = "running";

	// When we do a reinstallall, it is technically possible that we might catch the system in-between
	// packages, hence the de-bounce here
	if (!isvalidpid($pidfile)) {
		usleep(100000);
		if (!isvalidpid($pidfile)) {
			$running = "stopped";
			// The log files may not be complete when the process terminates so we need wait until we see the
			// exit status (__RC=x)
			waitfor_string_in_file($_REQUEST['logfilename'] . '.txt', "__RC=", 10);
			filter_configure();
			send_event("service restart packages");
		}
	}

	$pidarray = array('pid' => $running);

	// Process log file -----------------------------------------------------------------------------------------------
	$logfile = @fopen($_REQUEST['logfilename'] . '.txt', "r");

	if ($logfile != FALSE) {
		$resparray = array();
		$statusarray = array();

		// Log file is read a line at a time so that we can detect/modify certain entries
		while (($logline = fgets($logfile)) !== false) {
			// Check for return codes and replace with suitable strings
			if (strpos($logline, "_RC=") != false) {
				$code = str_replace("__RC=", "", $logline);

				if ($code == 0) {
					$logline = gettext("Success") . "\n";
				} else {
					$logline = gettext("Failed") . "\n";
				}

				$response .= $logline;
				$statusarray = array('exitstatus' => $code);
			} else {
				$response .= htmlspecialchars($logline);
			}
		}

		fclose($logfile);
		$resparray['log'] = $response;
	} else {
		$resparray['log'] = "not_ready";
		print(json_encode($resparray));
		exit;
	}

	// Process progress file ------------------------------------------------------------------------------------------
	$progress = "";
	$progarray = array();

	$JSONfile = @fopen($_REQUEST['logfilename'] . '.json', "r");

	if ($JSONfile != FALSE) {
		while (($logline = fgets($JSONfile)) !== false) {
			if (!feof($JSONfile)	 && (strpos($logline, 'INFO_PROGRESS_TICK') !== false)) {
				if (strpos($logline, '}}') !== false) {
					$progress = $logline;
				}
			}
		}

		fclose($JSONfile);

		if (strlen($progress) > 0) {
			$progarray = json_decode($progress, true);
		}
	}

	// Glob all the arrays we have made together, and convert to JSON
	print(json_encode($resparray + $pidarray + $statusarray + $progarray));
	exit;
}

function waitfor_string_in_file($filename, $string, $timeout) {
	$start = $now = time();

	while (($now - $start) < $timeout) {
		$testfile = @fopen($filename, "r");

		if ($testfile != FALSE) {
			while (($line = fgets($testfile)) !== false) {
				if (strpos($line, $string) !== false) {
					fclose($testfile);
					return(true);
				}
			}

			fclose($testfile);
		}

	usleep(100000);
	$now = time();
	}

	return(false);
}

$headline = "<br />";

if ($_POST) {
	if (empty($_POST['id']) && $_POST['mode'] != 'reinstallall') {
		header("Location: pkg_mgr_installed.php");
		return;
	}

	if (isset($_POST['pkgcancel']) || (empty($_POST['id']) && $_POST['mode'] != 'reinstallall')) {
		header("Location: pkg_mgr_installed.php");
		return;
	}
} else if ($_GET && !$_GET['id']) {
	if (empty($_GET['pkg']) && ($_GET['mode'] != 'reinstallall')) {
		header("Location: pkg_mgr_installed.php");
		return;
	}

	switch ($_GET['mode']) {
		case 'reinstallall':
			$headline = gettext("Reinstall all packages");
		case 'reinstallpkg':
			if ($_GET['from'] && $_GET['from']) {
				$headline = gettext("Upgrade package");
			} else {
				$headline = gettext("Reinstall package");
			}

			break;
		case 'delete':
			$headline = gettext("Remove package");
			break;
		default:
			$headline = gettext("Install package");
			break;
	}
}

if ($_GET && $_GET['id'] == "firmware") {
	$firmwareupdate = true;
	$firmwareversion = get_system_pkg_version();
	$headline = gettext("System update") ;
}

$tab_array = array();

if ($firmwareupdate) {
	$pgtitle = array(gettext("System"), gettext("Update"), $headline);
	$tab_array[] = array(gettext("System Update"), true, "");
	$tab_array[] = array(gettext("Update Settings"), false, "system_update_settings.php");
} else {
	$pgtitle = array(gettext("System"), gettext("Package Manager"), $headline);
	$tab_array[] = array(gettext("Available Packages"), false, "pkg_mgr.php");
	$tab_array[] = array(gettext("Installed Packages"), false, "pkg_mgr_installed.php");
	$tab_array[] = array(gettext("Package Installer"), true, "");
}

include("head.inc");
display_top_tabs($tab_array);

if ($input_errors) {
	print_input_errors($input_errors);
}

?>
<form action="pkg_mgr_install.php" method="post" class="form-horizontal">
<!--	<h2><?=$headline?></h2> -->
<?php if (($POST['complete'] != "true")	 && (empty($_GET['mode']) && $_GET['id']) || (!empty($_GET['mode']) && (!empty($_GET['pkg']) || $_GET['mode'] == 'reinstallall'))):
	if (empty($_GET['mode']) && $_GET['id']) {
		$pkgname = str_replace(array("<", ">", ";", "&", "'", '"', '.', '/'), "", htmlspecialchars_decode($_GET['id'], ENT_QUOTES | ENT_HTML401));
		$pkgmode = 'installed';
	} else if (!empty($_GET['mode']) && !empty($_GET['pkg'])) {
		$pkgname = str_replace(array("<", ">", ";", "&", "'", '"', '.', '/'), "", htmlspecialchars_decode($_GET['pkg'], ENT_QUOTES | ENT_HTML401));
		$pkgmode = str_replace(array("<", ">", ";", "&", "'", '"', '.', '/'), "", htmlspecialchars_decode($_GET['mode'], ENT_QUOTES | ENT_HTML401));
	} else if ($_GET['mode'] == 'reinstallall') {
		$pkgmode = 'reinstallall';
	}

	switch ($pkgmode) {
		case 'reinstallpkg':
			$pkgtxt = 'reinstalled';
			break;
		case 'delete':
			$pkgtxt = 'removed';
			break;
		default:
			$pkgtxt = $pkgmode;
			break;
	}
?>
	<br />
	<div class="panel panel-default">
		<div class="panel-heading">
<?php
			if ($pkgmode == 'reinstallall') {
?>
				<?=gettext("All packages will be reinstalled.");?>
<?php
			} else if ($_GET['from'] && $_GET['from']) {
?>
				Package: <b><?=$pkgname;?></b> will be upgraded from <b><?=$_GET['from']?></b> to <b><?=$_GET['to']?></b>.
<?php
			} else if ($firmwareupdate) {
?>
				<?=$g['product_name']?> <?=gettext(" system update")?>
<?php
			} else {
?>
				Package: <b><?=$pkgname;?></b> will be <?=$pkgtxt;?>.
<?php
			}
?>
		</div>
		<div class="panel-body">
		<br />
			<input type="hidden" name="mode" value="<?=$pkgmode;?>" />
<?php
	if ($firmwareupdate) {
?>
		<div class="form-group">
			<label class="col-sm-2 control-label">
				<?=gettext("Current base system")?>
			</label>
			<div class="col-sm-10">
				<?=$firmwareversion['installed_version']?>
			</div>
		</div>

		<div class="form-group">
			<label class="col-sm-2 control-label">
				<?=gettext("Latest base system")?>
			</label>
			<div class="col-sm-10">
				<?=$firmwareversion['version']?>
			</div>
		</div>
<?php
		if ($firmwareversion['version'] != $firmwareversion['installed_version']) {
?>
		<div class="form-group">
			<label class="col-sm-2 control-label">
				<?=gettext("Confirm Upgrade")?>
			</label>
			<div class="col-sm-10">
				<input type="hidden" name="id" value="firmware" />
				<input type="submit" class="btn btn-success" name="pkgconfirm" id="pkgconfirm" value="Confirm"/>
			</div>
		</div>
<?php
		} else {
?>
		<div class="form-group">
			<label class="col-sm-2 control-label">
			</label>
			<div class="col-sm-10">
				<?=($firmwareversion) ? gettext("System is up to date") : ""?>
			</div>
		</div>
<?php
		}
	} else {
?>
			<input type="hidden" name="id" value="<?=$pkgname;?>" />
			<input type="submit" class="btn btn-success" name="pkgconfirm" id="pkgconfirm" value="Confirm"/>
<?php
	}
	?>
		</div>
	</div>
<?php endif;

if ($firmwareupdate && !$firmwareversion) {
	print_info_box(gettext("Unable to retrieve system versions"), danger);
}

if ($_POST['mode'] == 'delete') {
	$modetxt = gettext("removal");
} else if (($_POST['mode'] == 'reinstallpkg') || ($_POST['mode'] == 'reinstallall')) {
	$modetxt = gettext("reinstallation");
} else {
	$modetxt = gettext("installation");
}

if (!empty($_POST['id']) || $_POST['mode'] == "reinstallall"):
	// What if the user navigates away from this page and then come back via his/her "Back" button?
	$pidfile = $g['varrun_path'] . '/' . $g['product_name'] . '-upgrade.pid';

	if (isvalidpid($pidfile)) {
		$start_polling = true;
	}
?>
	<input type="hidden" name="id" value="<?=htmlspecialchars($_POST['id'])?>" />
	<input type="hidden" name="mode" value="<?=htmlspecialchars($_POST['mode'])?>" />
	<input type="hidden" name="completed" value="true" />

	<div id="countdown" class="text-center"></div>

	<div class="progress" style="display: none;">
		<div id="progressbar" class="progress-bar progress-bar-striped" role="progressbar" aria-valuemin="0" aria-valuemax="100" style="width: 1%"></div>
	</div>
	<br />
	<div class="panel panel-default">
		<div class="panel-heading">
<?php if ($firmwareupdate) {
?>
			<h2 class="panel-title" id="status"><?=gettext("Updating system")?></h2>
<?php } else {
?>
			<h2 class="panel-title" id="status"><?=gettext("Package") . " " . $modetxt?></h2>
 <?php } ?>
		</div>

		<div class="panel-body">
			<textarea rows="15" class="form-control" id="output" name="output"><?=$_POST['output']?></textarea>
		</div>
	</div>

	<div id="final" class="alert" role="alert" style=":display: none;"></div>

<?php endif?>
</form>

<?php

ob_flush();

if ($_POST) {
	$pkgid = str_replace(array("<", ">", ";", "&", "'", '"', '.', '/'), "", htmlspecialchars_decode($_POST['id'], ENT_QUOTES | ENT_HTML401));
}

if ($_POST && ($_POST['completed'] != "true")) {
	/* Write out configuration to create a backup prior to pkg install. */
	write_config(gettext("Creating restore point before package installation."));

	$progbar = true;
	$upgrade_script = "/usr/local/sbin/{$g['product_name']}-upgrade -y -l {$g['tmp_path']}/webgui-log.txt -p {$g['tmp_path']}/webgui-log.sock";

	switch ($_POST['mode']) {
		case 'delete':
			mwexec_bg("{$upgrade_script} -r {$pkgid}");
			$start_polling = true;
			break;

		case 'reinstallall':
			if (is_array($config['installedpackages']) && is_array($config['installedpackages']['package'])) {
				$progbar = false; // We don't show the progress bar for reinstallall. It would be far too confusing
				mwexec_bg("{$upgrade_script} -i ALL_PACKAGES -f");
				$start_polling = true;
			}

			break;
		case 'reinstallpkg':
			mwexec_bg("{$upgrade_script} -i {$pkgid} -f");
			$start_polling = true;
			break;

		case 'installed':
		default:
			if ($pkgid == 'firmware') {
				mwexec_bg("{$upgrade_script}");
			} else {
				mwexec_bg("{$upgrade_script} -i {$pkgid}");
			}
			$start_polling = true;
			break;
	}
}

// $_POST['completed'] just means that we are refreshing the page to update any new menu items
// that were installed
if ($_POST && $_POST['completed'] == "true"):
	if ($pkgid == 'firmware'):
?>
<script>
//<![CDATA[
events.push(function() {
	time = "<?=$guitimeout?>";
	startCountdown();
});
//]]>
</script>
<?php
	endif;
endif;

?>

<script>
//<![CDATA[
// Update the progress indicator
// transition = true allows the bar to move at default speed, false = instantaneous
function setProgress(barName, percent, transition) {
	$('.progress').show()
	if (!transition) {
		$('#' + barName).css('transition', 'width 0s ease-in-out');
	}

	$('#' + barName).css('width', percent + '%').attr('aria-valuenow', percent);
}

// Display a success banner
function show_success() {
	$('#final').removeClass("alert-info").addClass("alert-success");
	if ("<?=$_POST['mode']?>" != "reinstallall") {
		if ("<?=$pkgid?>" == "firmware") {
			$('#final').html("<b>" + "System update" + " " + "<?=gettext(' successfully completed')?>");
		} else {
			$('#final').html("<b>" + "<?=$pkgid?>" + " </b>" + "<?=$modetxt?>" + " " + "<?=gettext(' successfully completed')?>");
		}
	} else {
		$('#final').html("<?=gettext('Reinstallation of all packages successfully completed')?>");
	}

	$('#final').show();
}

// Display a failure banner
function show_failure() {
	$('#final').addClass("alert-danger");
	if ("<?=$_POST['mode']?>" != "reinstallall") {
		$('#final').html("<?=$pkgid?>" + " " + "<?=$modetxt?>" + " " + "<?=gettext(' failed!')?>");
	} else {
		$('#final').html("<?=gettext('Reinstallation of all packages failed')?>");
	}
	$('#final').show();
}

// Ask the user to wait a bit
function show_info() {
	$('#final').addClass("alert-info");
	if ("<?=$_POST['mode']?>" != "reinstallall") {
		$('#final').html("Please wait while the " + "<?=$modetxt?>" + " of " + "<?=$pkgid?>" + " " + "completes." + "<br />" +
			"<?=gettext("(Some packages may take several minutes!)")?>");
	} else {
		$('#final').html("Please wait while the reinstallation of all packages completes." + "<br />" +
			"<?=gettext("(Some packages may take several minutes!)")?>");
	}
	$('#final').show();
}

function getLogsStatus() {
	var ajaxRequest;
	var repeat;
	var progress;

	repeat = true;

	ajaxRequest = $.ajax({
			url: "pkg_mgr_install.php",
			type: "post",
			data: { ajax: "ajax",
					logfilename: "<?=$g['tmp_path'];?>/webgui-log",
					next_log_line: "0"
				  }
		});

	// Deal with the results of the above ajax call
	ajaxRequest.done(function (response, textStatus, jqXHR) {
		var json = new Object;

		json = jQuery.parseJSON(response);

		if (json.log != "not ready") {
			// Write the log file to the "output" textarea
			$('#output').html(json.log);
			scrollToBottom();

			// Update the progress bar
			progress = 0;

			if ("<?=$progbar?>") {
				if (json.data) {
					/*
					 * XXX: There appears to be a bug in pkg that can cause "total"
					 * to be reported as zero
					 *
					 * https://github.com/freebsd/pkg/issues/1336
					 */
					if (json.data.total > 0) {
						setProgress('progressbar', ((json.data.current * 100) / json.data.total), true);
					}

					progress = json.data.total - json.data.current
					if (progress < 0) {
						progress = 0;
					}

				}
			}
			// Now we need to determine if the installation/removal was successful, and tell the user. Not as easy as it sounds :)
			if ((json.pid == "stopped") && (progress == 0) && (json.exitstatus == 0)) {
				show_success();
				repeat = false;
				$('form').submit();
			}

			if ((json.pid == "stopped") && ((progress != 0) || (json.exitstatus != 0))) {
				show_failure();
				repeat = false;
			}
			// ToDo: There are more end conditions we need to catch
		}

		// And maybe do it again
		if (repeat)
			setTimeout(getLogsStatus, 500);
	});
}

function scrollToBottom() {
	$('#output').scrollTop($('#output')[0].scrollHeight);
}

var timeoutmsg = '<h4>Rebooting<br />Page will automatically reload in ';
var time = 0;

function checkonline() {
	$.ajax({
		url	 : "/index.php", // or other resource
		type : "HEAD"
	})
	.done(function() {
		window.location="/index.php";
	});
}

function startCountdown() {
	setInterval(function() {
		if (time > 0) {
			$('#countdown').html(timeoutmsg + time + ' seconds.</h4>');
			time--;
		} else {
			time = "<?=$guiretry?>";
			timeoutmsg = '<h4>Not yet ready<br />Retrying in another ';
			checkonline();
		}
	}, 1000);
}

events.push(function() {
	if ("<?=$start_polling?>") {
		setTimeout(getLogsStatus, 1000);
		show_info();
	}

	// If we are just re-drawing the page after a successful install/remove/reinstall,
	// we only meed to re-populate the progress indicator and the status banner
	if ("<?=$_POST['completed']?>" == "true") {
		setProgress('progressbar', 100, false);
		show_success();
		setTimeout(scrollToBottom, 200);
	}

});
//]]>
</script>

<?php
include('foot.inc');
