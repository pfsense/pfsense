<?php
/* $Id$ */
/*
	pkg_mgr_install.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2004, 2005 Scott Ullrich
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
/*
	pfSense_BUILDER_BINARIES:	/bin/rm
	pfSense_MODULE: pkgs
*/

##|+PRIV
##|*IDENT=page-system-packagemanager-installpackage
##|*NAME=System: Package Manager: Install Package page
##|*DESCR=Allow access to the 'System: Package Manager: Install Package' page.
##|*MATCH=pkg_mgr_install.php*
##|-PRIV

ini_set('max_execution_time', '0');

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("pkg-utils.inc");

global $static_output;

$static_output = "";
$static_status = "";
$sendto = "output";
$start_polling = false;

//---------------------------------------------------------------------------------------------------------------------
// After an installation or removal has been started (mwexec(/usr/local/sbin/pfSense-upgrade-GUI.sh . . . )) AJAX calls
// are made to get status.
// The log file is read, the newest progress record retrieved, and the PID status obtained. The data is formatted
// as JSON before being returned to the AJAX caller (at the bottom of this file)
//
// Arguments received here:
//		logfilename = Passed to installation script to tell it how to name the log file we will parse
//		pid = PID of the background install/remove process
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

if ($_POST) {
	if (empty($_POST['id']) && $_POST['mode'] != 'reinstallall') {
		header("Location: pkg_mgr_installed.php");
		return;
	}

	if (isset($_POST['pkgcancel']) || (empty($_POST['id']) && $_POST['mode'] != 'reinstallall')) {
		header("Location: pkg_mgr_installed.php");
		return;
	}
} else if ($_GET) {
	switch ($_GET['mode']) {
		case 'reinstallall':
		case 'showlog':
			break;
		case 'installedinfo':
		case 'reinstallpkg':
		case 'delete':
			if (empty($_GET['pkg'])) {
				header("Location: pkg_mgr_installed.php");
				return;
			}
			break;
		default:
			if (empty($_GET['id'])) {
				header("Location: pkg_mgr_installed.php");
				return;
			}
			break;
	}
}

$pgtitle = array(gettext("System"),gettext("Package Manager"),gettext("Install Package"));
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Available packages"), false, "pkg_mgr.php");
$tab_array[] = array(gettext("Installed packages"), false, "pkg_mgr_installed.php");
$tab_array[] = array(gettext("Package Installer"), true, "");
display_top_tabs($tab_array);

?>
<form action="pkg_mgr_install.php" method="post" class="form-horizontal">
	<h2>Install / remove package</h2>
<?php if ((empty($_GET['mode']) && $_GET['id']) || (!empty($_GET['mode']) && (!empty($_GET['pkg']) || $_GET['mode'] == 'reinstallall') && ($_GET['mode'] != 'installedinfo' && $_GET['mode'] != 'showlog'))):
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
	<div class="panel panel-default">
		<div class="panel-body">
			<div class="content">
<?php
			if ($pkgmode == 'reinstallall') {
?>
				<p><?=gettext("All packages will be reinstalled.");?></p>
<?php
			} else {
?>
				<p>Package: <b><?=$pkgname;?></b> will be <?=$pkgtxt;?>.</p>
<?php
			}
?>
			</div>
		</div>
		<div class="panel-footer">
			<input type="hidden" name="id" value="<?=$pkgname;?>" />
			<input type="hidden" name="mode" value="<?=$pkgmode;?>" />
			<input type="submit" class="btn btn-success" name="pkgconfirm" id="pkgconfirm" value="Confirm"/>
			<input type="submit" class="btn btn-default" name="pkgcancel" id="pkgcancel" value="Cancel"/>
		</div>
	</div>
<?php endif;

if ($_POST['mode'] == 'delete') {
	$modetxt = gettext("removal");
} else if ($_POST['mode'] == 'reinstallpkg') {
	$modetxt = gettext("reinstallation");
} else {
	$modetxt = gettext("installation");
}

if (!empty($_POST['id']) || $_POST['mode'] == "reinstallall" || $_GET['mode'] == 'showlog' || ($_GET['mode'] == 'installedinfo' && !empty($_GET['pkg']))):
	// What if the user navigates away from this page and then come back via his/her "Back" button?
	$pidfile = $g['varrun_path'] . '/' . $g['product_name'] . '-upgrade.pid';

	if (isvalidpid($pidfile)) {
		$start_polling = true;
	}
?>

	<div class="progress" style="display: none;">
		<div id="progressbar" class="progress-bar progress-bar-striped" role="progressbar" aria-valuemin="0" aria-valuemax="100" style="width: 1%"></div>
	</div>
	<br />
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title" id="status"><?=gettext("Package") . " " . $modetxt?></h2>
		</div>

		<div class="panel-body">
			<textarea rows="15" class="form-control" id="output"></textarea>
		</div>
	</div>

	<div id="final" class="alert" role="alert" style=":display: none;"></div>
<?php endif?>
</form>
<?php

ob_flush();

if ($_GET) {
	$pkgname = str_replace(array("<", ">", ";", "&", "'", '"', '.', '/'), "", htmlspecialchars_decode($_GET['pkg'], ENT_QUOTES | ENT_HTML401));
	switch ($_GET['mode']) {
		case 'showlog':
			if (strpos($pkgname, ".")) {
				update_output_window(gettext("Something is wrong on the request."));
			} else if (file_exists("/tmp/pkg_mgr_{$pkgname}.log")) {
				update_output_window(@file_get_contents("/tmp/pkg_mgr_{$pkgname}.log"));
			} else {
				update_output_window(gettext("Log was not retrievable."));
			}
			break;
		case 'installedinfo':
			if (file_exists("/tmp/{$pkgname}.info")) {
				$status = @file_get_contents("/tmp/{$pkgname}.info");
				update_status("{$pkgname} " . gettext("installation completed."));
				update_output_window($status);
			} else {
				update_output_window(sprintf(gettext("Could not find %s."), $pkgname));
			}
			break;
		default:
			break;
	}
} else if ($_POST) {
	$pkgid = str_replace(array("<", ">", ";", "&", "'", '"', '.', '/'), "", htmlspecialchars_decode($_POST['id'], ENT_QUOTES | ENT_HTML401));

	/* All other cases make changes, so mount rw fs */
	conf_mount_rw();
	/* Write out configuration to create a backup prior to pkg install. */
	write_config(gettext("Creating restore point before package installation."));

	$progbar = true;

	switch ($_POST['mode']) {
		case 'delete':
			mwexec('/usr/local/sbin/pfSense-upgrade -l /tmp/webgui-log.txt -p /tmp/webgui-log.sock -r ' . $pkgid, false, false, true);
			$start_polling = true;
			break;

		case 'reinstallall':
			if (is_array($config['installedpackages']) && is_array($config['installedpackages']['package'])) {
				$progbar = false; // We don't show the progress bar for reinstallall. It would be far too confusing
				mwexec('/usr/local/sbin/pfSense-upgrade -l /tmp/webgui-log.txt -p /tmp/webgui-log.sock -i ' . "ALL_PACKAGES" . ' -f', false, false, true);
				$start_polling = true;
			}

			break;
		case 'reinstallpkg':
			mwexec('/usr/local/sbin/pfSense-upgrade -l /tmp/webgui-log.txt -p /tmp/webgui-log.sock -i ' . $pkgid . ' -f', false, false, true);
			$start_polling = true;
			break;

		case 'installed':
		default:
			mwexec('/usr/local/sbin/pfSense-upgrade -l /tmp/webgui-log.txt -p /tmp/webgui-log.sock -i ' . $pkgid, false, false, true);
			$start_polling = true;
			break;
	}

	// Delete all temporary package tarballs and staging areas.
	unlink_if_exists("/tmp/apkg_*");
	rmdir_recursive("/var/tmp/instmp*");

	// close log
	if ($fd_log) {
		fclose($fd_log);
	}

	/* Restore to read only fs */
	conf_mount_ro();
}

?>

<script>
//<![CDATA[
//	Update the progress indicator
function setProgress(barName, percent) {
	$('.progress').show()
	$('#' + barName).css('width', percent + '%').attr('aria-valuenow', percent);
}

// Display a success banner
function show_success() {
	$('#final').removeClass("alert-info").addClass("alert-success");
	if("<?=$progbar?>")
		$('#final').html("<?=$pkgid?>" + " " + "<?=$modetxt?>" + " " + "<?=gettext(' successfully completed')?>");
	else
		$('#final').html("<?=gettext('Reinstallation of all packages successfully completed')?>");

	$('#final').show();
}

// Display a failure banner
function show_failure() {
	$('#final').addClass("alert-danger");
	$('#final').html("<?=$pkgid?>" + " " + "<?=$modetxt?>" + " " + "<?=gettext(' failed!')?>");
	$('#final').show();
}

// Ask the user to wait a bit
function show_info() {
	$('#final').addClass("alert-info");
	$('#final').html("Please wait while the " + "<?=$modetxt?>" + " of " + "<?=$pkgid?>" + " " + "completes." + "<br />" +
	"<?=gettext("(Some packages may take several minutes!)")?>");
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
					logfilename: "/tmp/webgui-log",
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
			$('#output').scrollTop($('#output')[0].scrollHeight);

			// Update the progress bar
			progress = 0;

			if("<?=$progbar?>") {
				if (json.data) {
					setProgress('progressbar', ((json.data.current * 100) / json.data.total));
					progress = json.data.total - json.data.current
				}
			}
			// Now we need to determine if the installation/removal was successful, and tell the user. Not as easy as it sounds :)
			if ((json.pid == "stopped") && (progress == 0) && (json.exitstatus == 0)) {
				show_success();
				repeat = false;
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

events.push(function(){
	if ("<?=$start_polling?>") {
		setTimeout(getLogsStatus, 1000);
		show_info();
	}
});
//]]>
</script>

<?php
include('foot.inc');
