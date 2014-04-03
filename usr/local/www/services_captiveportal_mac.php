<?php
/*
	services_captiveportal_mac.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2004 Dinesh Nair <dinesh@alphaque.com>
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
/*
	pfSense_MODULE:	captiveportal
*/

##|+PRIV
##|*IDENT=page-services-captiveportal-macaddresses
##|*NAME=Services: Captive portal: Mac Addresses page
##|*DESCR=Allow access to the 'Services: Captive portal: Mac Addresses' page.
##|*MATCH=services_captiveportal_mac.php*
##|-PRIV

require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

global $cpzone;
global $cpzoneid;

$cpzone = $_GET['zone'];
if (isset($_POST['zone']))
	$cpzone = $_POST['zone'];

if (empty($cpzone) || empty($config['captiveportal'][$cpzone])) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

if (!is_array($config['captiveportal']))
	$config['captiveportal'] = array();
$a_cp =& $config['captiveportal'];

$pgtitle = array(gettext("Services"),gettext("Captive portal"), $a_cp[$cpzone]['zone']);
$shortcut_section = "captiveportal";

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;

		$rules = captiveportal_passthrumac_configure();
		$savemsg = get_std_save_message($retval);
		if ($retval == 0)
			clear_subsystem_dirty('passthrumac');
	}

	if ($_POST['postafterlogin']) {
		if (!is_array($a_passthrumacs)) {
			echo gettext("No entry exists yet!") ."\n";
			exit;
		}
		if (empty($_POST['zone'])) {
			echo gettext("Please set the zone on which the operation should be allowed");
			exit;
		}
		if (!is_array($a_cp[$cpzone]['passthrumac']))
			$a_cp[$cpzone]['passthrumac'] = array();
		$a_passthrumacs =& $a_cp[$cpzone]['passthrumac'];

		if ($_POST['username']) {
			$mac = captiveportal_passthrumac_findbyname($_POST['username']);
			if (!empty($mac))
				$_POST['delmac'] = $mac['mac'];
			else
				echo gettext("No entry exists for this username:") . " " . $_POST['username'] . "\n";
		}
		if ($_POST['delmac']) {
			$found = false;
			foreach ($a_passthrumacs as $idx => $macent) {
				if ($macent['mac'] == $_POST['delmac']) {
					$found = true;
					break;
				}
			}
			if ($found == true) {
				$cpzoneid = $a_cp[$cpzone]['zoneid'];
				$rules = captiveportal_passthrumac_delete_entry($a_passthrumacs[$idx]);
				$uniqid = uniqid("{$cpzone}_mac");
				file_put_contents("{$g['tmp_path']}/{$uniqid}_tmp", $rules);
				mwexec("/sbin/ipfw -x {$cpzoneid} -q {$g['tmp_path']}/{$uniqid}_tmp");
				@unlink("{$g['tmp_path']}/{$uniqid}_tmp");
				unset($a_passthrumacs[$idx]);
				write_config();
				echo gettext("The entry was sucessfully deleted") . "\n";
			} else
				echo gettext("No entry exists for this mac address:") . " " .  $_POST['delmac'] . "\n";
		}
		exit;
	}
}

if ($_GET['act'] == "del") {
	$a_passthrumacs =& $a_cp[$cpzone]['passthrumac'];
	if ($a_passthrumacs[$_GET['id']]) {
		$cpzoneid = $a_cp[$cpzone]['zoneid'];
		$rules = captiveportal_passthrumac_delete_entry($a_passthrumacs[$_GET['id']]);
		$uniqid = uniqid("{$cpzone}_mac");
		file_put_contents("{$g['tmp_path']}/{$uniqid}_tmp", $rules);
		mwexec("/sbin/ipfw -x {$cpzoneid} -q {$g['tmp_path']}/{$uniqid}_tmp");
		@unlink("{$g['tmp_path']}/{$uniqid}_tmp");
		unset($a_passthrumacs[$_GET['id']]);
		write_config();
		header("Location: services_captiveportal_mac.php?zone={$cpzone}");
		exit;
	}
}

include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="services_captiveportal_mac.php" method="post">
<input type="hidden" name="zone" id="zone" value="<?=htmlspecialchars($cpzone);?>" />
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('passthrumac')): ?><p>
<?php print_info_box_np(gettext("The captive portal MAC address configuration has been changed.<br />You must apply the changes in order for them to take effect."));?><br />
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="captiveportal mac">
	<tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Captive portal(s)"), false, "services_captiveportal.php?zone={$cpzone}");
	$tab_array[] = array(gettext("MAC"), true, "services_captiveportal_mac.php?zone={$cpzone}");
	$tab_array[] = array(gettext("Allowed IP addresses"), false, "services_captiveportal_ip.php?zone={$cpzone}");
	$tab_array[] = array(gettext("Allowed Hostnames"), false, "services_captiveportal_hostname.php?zone={$cpzone}");
	$tab_array[] = array(gettext("Vouchers"), false, "services_captiveportal_vouchers.php?zone={$cpzone}");
	$tab_array[] = array(gettext("File Manager"), false, "services_captiveportal_filemanager.php?zone={$cpzone}");
	display_top_tabs($tab_array, true);
?>
	</td></tr>
	<tr>
		<td class="tabcont">
			<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="main">
				<tr>
					<td width="3%"  class="list"></td>
					<td width="37%" class="listhdrr"><?=gettext("MAC address"); ?></td>
					<td width="50%" class="listhdr"><?=gettext("Description"); ?></td>
					<td width="10%" class="list"></td>
				</tr>
<?php
			if (is_array($a_cp[$cpzone]['passthrumac'])):
				$i = 0;
				foreach ($a_cp[$cpzone]['passthrumac'] as $mac):
?>
				<tr ondblclick="document.location='services_captiveportal_mac_edit.php?zone=<?=$cpzone;?>&amp;id=<?=$i;?>'">
					<td valign="middle" class="list nowrap">
						<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_<?=$mac['action'];?>.gif" width="11" height="11" border="0" alt="icon" />
					</td>
					<td class="listlr">
						<?=$mac['mac'];?>
					</td>
					<td class="listbg">
						<?=htmlspecialchars($mac['descr']);?>&nbsp;
					</td>
					<td valign="middle" class="list nowrap">
						<a href="services_captiveportal_mac_edit.php?zone=<?=$cpzone;?>&amp;id=<?=$i;?>">
							<img src="/themes/<?php echo $g['theme']; ?>/images/icons/icon_e.gif" title="<?=gettext("edit host"); ?>" width="17" height="17" border="0" alt="edit" />
						</a>
						&nbsp;
						<a href="services_captiveportal_mac.php?zone=<?=$cpzone;?>&amp;act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this host?"); ?>')">
							<img src="/themes/<?php echo $g['theme']; ?>/images/icons/icon_x.gif" title="<?=gettext("delete host"); ?>" width="17" height="17" border="0" alt="delte" />
						</a>
					</td>
				</tr>
<?php
					$i++;
				endforeach;
			endif;
?>
				<tr>
					<td class="list" colspan="3">&nbsp;</td>
					<td class="list">
						<a href="services_captiveportal_mac_edit.php?zone=<?=$cpzone;?>">
							<img src="/themes/<?php echo $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add host"); ?>" width="17" height="17" border="0" alt="add" />
						</a>
					</td>
				</tr>
				<tr>
					<td colspan="3" class="list">
						<span class="vexpl">
							<span class="red"><strong><?=gettext("Note:"); ?><br /></strong></span>
							<?=gettext("Adding MAC addresses as 'pass' MACs allows them access through the captive portal automatically without being taken to the portal page."); ?>
						</span>
					</td>
					<td class="list">&nbsp;</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>