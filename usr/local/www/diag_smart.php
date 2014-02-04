<?php
/*
	Part of pfSense

	Copyright (C) 2006, Eric Friesen
	All rights reserved

	Some modifications:
	Copyright (C) 2010 - Jim Pingle
*/

require("guiconfig.inc");

$pgtitle = array(gettext("Diagnostics"), gettext("S.M.A.R.T. Monitor Tools"));
$smartctl = "/usr/local/sbin/smartctl";
$smartd = "/usr/local/sbin/smartd";
$start_script = "/usr/local/etc/rc.d/smartd.sh";

$valid_test_types = array("offline", "short", "long", "conveyance");
$valid_info_types = array("i", "H", "c", "A", "a");
$valid_log_types = array("error", "selftest");


include("head.inc");
?>

<style>
<!--

input {
	font-family: courier new, courier;
	font-weight: normal;
	font-size: 9pt;
}

pre {
	border: 2px solid #435370;
	background: #F0F0F0;
	padding: 1em;
	font-family: courier new, courier;
	white-space: pre;
	line-height: 10pt;
	font-size: 10pt;
}

.label {
	font-family: tahoma, verdana, arial, helvetica;
	font-size: 11px;
	font-weight: bold;
}

.button {
	font-family: tahoma, verdana, arial, helvetica;
	font-weight: bold;
	font-size: 11px;
}

-->
</style>
</head>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php 
include("fbegin.inc"); 

// Highlates the words "PASSED", "FAILED", and "WARNING".
function add_colors($string)
{
	// To add words keep arrayes matched by numbers
	$patterns[0] = '/PASSED/';
	$patterns[1] = '/FAILED/';
	$patterns[2] = '/Warning/';
	$replacements[0] = '<b><font color="#00ff00">' . gettext("PASSED") . '</font></b>';
	$replacements[1] = '<b><font color="#ff0000">' . gettext("FAILED") . '</font></b>';
	$replacements[2] = '<font color="#ff0000">' . gettext("Warning") . '</font>';
	ksort($patterns);
	ksort($replacements);
	return preg_replace($patterns, $replacements, $string);
}

// Edits smartd.conf file, adds or removes email for failed disk reporting
function update_email($email)
{
	// Did they pass an email?
	if(!empty($email))
	{
		// Put it in the smartd.conf file
		shell_exec("/usr/bin/sed -i old 's/^DEVICESCAN.*/DEVICESCAN -H -m " . escapeshellarg($email) . "/' /usr/local/etc/smartd.conf");
	}
	// Nope
	else
	{
		// Remove email flags in smartd.conf
		shell_exec("/usr/bin/sed -i old 's/^DEVICESCAN.*/DEVICESCAN/' /usr/local/etc/smartd.conf");
	}
}

function smartmonctl($action)
{
	global $start_script;
	shell_exec($start_script . escapeshellarg($action));
}

// What page, aka. action is being wanted
// If they "get" a page but don't pass all arguments, smartctl will throw an error
$action = (isset($_POST['action']) ? $_POST['action'] : $_GET['action']);
$targetdev = basename($_POST['device']);
if (!file_exists('/dev/' . $targetdev)) {
	echo "Device does not exist, bailing.";
	return;
}
switch($action) {
	// Testing devices
	case 'test':
	{
		$test = $_POST['testType'];
		if (!in_array($test, $valid_test_types)) {
			echo "Invalid test type, bailing.";
			return;
		}
		$output = add_colors(shell_exec($smartctl . " -t " . escapeshellarg($test) . " /dev/" . escapeshellarg($targetdev)));
		echo '<pre>' . $output . '
		<form action="diag_smart.php" method="post" name="abort">
		<input type="hidden" name="device" value="' . $targetdev . '" />
		<input type="hidden" name="action" value="abort" />
		<input type="submit" name="submit" value="' . gettext("Abort") . '" />
		</form>
		</pre>';
		break;
	}

	// Info on devices
	case 'info':
	{
		$type = $_POST['type'];
		if (!in_array($type, $valid_info_types)) {
			echo "Invalid info type, bailing.";
			return;
		}
		$output = add_colors(shell_exec($smartctl . " -" . escapeshellarg($type) . " /dev/" . escapeshellarg($targetdev)));
		echo "<pre>$output</pre>";
		break;
	}

	// View logs
	case 'logs':
	{
		$type = $_POST['type'];
		if (!in_array($type, $valid_log_types)) {
			echo "Invalid log type, bailing.";
			return;
		}
		$output = add_colors(shell_exec($smartctl . " -l " . escapeshellarg($type) . " /dev/" . escapeshellarg($targetdev)));
		echo "<pre>$output</pre>";
		break;
	}

	// Abort tests
	case 'abort':
	{
		$output = shell_exec($smartctl . " -X /dev/" . escapeshellarg($targetdev));
		echo "<pre>$output</pre>";
		break;
	}

	// Config changes, users email in xml config and write changes to smartd.conf
	case 'config':
	{
		if(isset($_POST['submit']))
		{
			// DOES NOT WORK YET...
			if($_POST['testemail'])
			{
// FIXME				shell_exec($smartd . " -M test -m " . $config['system']['smartmonemail']);
				$savemsg = sprintf(gettext("Email sent to %s"), $config['system']['smartmonemail']);
				smartmonctl("stop");
				smartmonctl("start");
			}
			else
			{
				$config['system']['smartmonemail'] = $_POST['smartmonemail'];
				write_config();

				// Don't know what all this means, but it addes the config changed header when config is saved
				$retval = 0;
				config_lock();
				if(stristr($retval, "error") <> true)
					$savemsg = get_std_save_message($retval);
				else
					$savemsg = $retval;
				config_unlock();

				if($_POST['email'])
				{
					// Write the changes to the smartd.conf file
					update_email($_POST['smartmonemail']);
				}

				// Send sig HUP to smartd, rereads the config file
				shell_exec("/usr/bin/killall -HUP smartd");
			}
		}
		// Was the config changed? if so , print the message
		if ($savemsg) print_info_box($savemsg);
		// Get users email from the xml file
		$pconfig['smartmonemail'] = $config['system']['smartmonemail'];

		?>
		<!-- Print the tabs across the top -->
		<table width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr>
				<td>
					<?php
					$tab_array = array();
					$tab_array[0] = array(gettext("Information/Tests"), false, $_SERVER['PHP_SELF'] . "?action=default");
					$tab_array[1] = array(gettext("Config"), true, $_SERVER['PHP_SELF'] . "?action=config");
					display_top_tabs($tab_array);
				?>
				</td>
			</tr>
		</table>
<!-- user email address -->
		<form action="<?= $_SERVER['PHP_SELF']?>" method="post" name="config">
		<table width="100%" border="0" cellpadding="6" cellspacing="0">
			<tbody>
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Config"); ?></td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell"><?=gettext("Email Address"); ?></td>
					<td width="78%" class="vtable">
						<input type="text" name="smartmonemail" value="<?=htmlspecialchars($pconfig['smartmonemail'])?>"/>
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top">&nbsp;</td>
					<td width="78%">
						<input type="hidden" name="action" value="config" />
						<input type="hidden" name="email" value="true" />
						<input type="submit" name="submit" value="<?=gettext("Save"); ?>" class="formbtn" />
					</td>
				</tr>
			</tbody>
		</table>
		</form>

<!-- test email -->
		<form action="<?= $_SERVER['PHP_SELF']?>" method="post" name="config">
		<table width="100%" border="0" cellpadding="6" cellspacing="0">
			<tbody>
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Test email"); ?></td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell">&nbsp;</td>
					<td width="78%" class="vtable">
						<?php printf(gettext("Send test email to %s"), $config['system']['smartmonemail']); ?>
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top">&nbsp;</td>
					<td width="78%">
						<input type="hidden" name="action" value="config" />
						<input type="hidden" name="testemail" value="true" />
						<input type="submit" name="submit" value="<?=gettext("Send"); ?>" class="formbtn" />
					</td>
				</tr>
			</tbody>
		</table>
		</form>

		<?php
		break;
	}

	// Default page, prints the forms to view info, test, etc...
	default:
	{
		// Get all AD* and DA* (IDE and SCSI) devices currently installed and stores them in the $devs array
		exec("ls /dev | grep '^\(ad\|da\|ada\)[0-9]\{1,2\}$'", $devs);
		?>
		<table width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr>
				<td>
					<?php
					$tab_array = array();
					$tab_array[0] = array(gettext("Information/Tests"), true, $_SERVER['PHP_SELF']);
					//$tab_array[1] = array("Config", false, $_SERVER['PHP_SELF'] . "?action=config");
					display_top_tabs($tab_array);
				?>
				</td>
			</tr>
		</table>
<!--INFO-->
		<form action="<?= $_SERVER['PHP_SELF']?>" method="post" name="info">
		<table width="100%" border="0" cellpadding="6" cellspacing="0">
			<tbody>
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Info"); ?></td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell"><?=gettext("Info type"); ?></td>
					<td width="78%" class="vtable">
						<input type="radio" name="type" value="i" /><?=gettext("Info"); ?><br />
						<input type="radio" name="type" value="H" checked /><?=gettext("Health"); ?><br />
						<input type="radio" name="type" value="c" /><?=gettext("SMART Capabilities"); ?><br />
						<input type="radio" name="type" value="A" /><?=gettext("Attributes"); ?><br />
						<input type="radio" name="type" value="a" /><?=gettext("All"); ?><br />
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell"><?=gettext("Device: /dev/"); ?></td>
					<td width="78%" class="vtable">
						<select name="device">
						<?php
						foreach($devs as $dev)
						{
							echo "<option value=" . $dev . ">" . $dev . "</option>";
						}
						?>
						</select>
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top">&nbsp;</td>
					<td width="78%">
						<input type="hidden" name="action" value="info" />
						<input type="submit" name="submit" value="<?=gettext("View"); ?>" class="formbtn" />
					</td>
				</tr>
			</tbody>
		</table>
		</form>
<!--TESTS-->
		<form action="<?= $_SERVER['PHP_SELF']?>" method="post" name="tests">
		<table width="100%" border="0" cellpadding="6" cellspacing="0">
			<tbody>
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Perform Self-tests"); ?></td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell"><?=gettext("Test type"); ?></td>
					<td width="78%" class="vtable">
						<input type="radio" name="testType" value="offline" /><?=gettext("Offline"); ?><br />
						<input type="radio" name="testType" value="short" checked /><?=gettext("Short"); ?><br />
						<input type="radio" name="testType" value="long" /><?=gettext("Long"); ?><br />
						<input type="radio" name="testType" value="conveyance" /><?=gettext("Conveyance (ATA Disks Only)"); ?><br />
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell"><?=gettext("Device: /dev/"); ?></td>
					<td width="78%" class="vtable">
						<select name="device">
						<?php
						foreach($devs as $dev)
						{
							echo "<option value=" . $dev . ">" . $dev;
						}
						?>
						</select>
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top">&nbsp;</td>
					<td width="78%">
						<input type="hidden" name="action" value="test" />
						<input type="submit" name="submit" value="<?=gettext("Test"); ?>" class="formbtn" />
					</td>
				</tr>
			</tbody>
		</table>
		</form>
<!--LOGS-->
		<form action="<?= $_SERVER['PHP_SELF']?>" method="post" name="logs">
		<table width="100%" border="0" cellpadding="6" cellspacing="0">
			<tbody>
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("View Logs"); ?></td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell"><?=gettext("Log type"); ?></td>
					<td width="78%" class="vtable">
						<input type="radio" name="type" value="error" checked /><?=gettext("Error"); ?><br />
						<input type="radio" name="type" value="selftest" /><?=gettext("Self-test"); ?><br />
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell"><?=gettext("Device: /dev/"); ?></td>
					<td width="78%" class="vtable">
						<select name="device">
						<?php
						foreach($devs as $dev)
						{
							echo "<option value=" . $dev . ">" . $dev;
						}
						?>
						</select>
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top">&nbsp;</td>
					<td width="78%">
						<input type="hidden" name="action" value="logs" />
						<input type="submit" name="submit" value="<?=gettext("View"); ?>" class="formbtn" />
					</td>
				</tr>
			</tbody>
		</table>
		</form>
<!--ABORT-->
		<form action="<?= $_SERVER['PHP_SELF']?>" method="post" name="abort">
		<table width="100%" border="0" cellpadding="6" cellspacing="0">
			<tbody>
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Abort tests"); ?></td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell"><?=gettext("Device: /dev/"); ?></td>
					<td width="78%" class="vtable">
						<select name="device">
						<?php
						foreach($devs as $dev)
						{
							echo "<option value=" . $dev . ">" . $dev;
						}
						?>
						</select>
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top">&nbsp;</td>
					<td width="78%">
						<input type="hidden" name="action" value="abort" />
						<input type="submit" name="submit" value="<?=gettext("Abort"); ?>" class="formbtn" onclick="return confirm('<?=gettext("Do you really want to abort the test?"); ?>')" />
					</td>
				</tr>
			</tbody>
		</table>
		</form>

		<?php
		break;
	}
}

// print back button on pages
if(isset($_POST['submit']) && $_POST['submit'] != "Save")
{
	echo '<br /><a href="' . $_SERVER['PHP_SELF'] . '">' . gettext("Back") . '</a>';
}
?>
<br />
<?php if ($ulmsg) echo "<p><strong>" . $ulmsg . "</strong></p>\n"; ?>

<?php include("fend.inc"); ?>
</body>
</html>
