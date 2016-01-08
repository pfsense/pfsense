<?php
/*
	services_captiveportal_mac.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2004 Dinesh Nair <dinesh@alphaque.com>
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
##|*IDENT=page-services-captiveportal-macaddresses
##|*NAME=Services: Captive portal: Mac Addresses
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
if (isset($_POST['zone'])) {
	$cpzone = $_POST['zone'];
}

if (empty($cpzone) || empty($config['captiveportal'][$cpzone])) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

if (!is_array($config['captiveportal'])) {
	$config['captiveportal'] = array();
}
$a_cp =& $config['captiveportal'];

$pgtitle = array(gettext("Services"), gettext("Captive Portal"), "Zone " . $a_cp[$cpzone]['zone'], gettext("MAC"));
$shortcut_section = "captiveportal";

$actsmbl = array('pass' => '<font color="green" size="4">&#x2714;</font>&nbsp;Pass',
				 'block' => '<font color="red" size="4">&#x2718;</font>&nbsp;Block');

if ($_POST) {
	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;

		if (is_array($a_cp[$cpzone]['passthrumac'])) {
			$cpzoneid = $a_cp[$cpzone]['cpzoneid'];
			$rules = captiveportal_passthrumac_configure();
			if (!empty($rules)) {
				@file_put_contents("{$g['tmp_path']}/passthrumac_gui", $rules);
				mwexec("/sbin/ipfw -x {$cpzoneid} {$g['tmp_path']}/passthrumac_gui");
				@unlink("{$g['tmp_path']}/passthrumac_gui");
			}
			$savemsg = get_std_save_message($retval);
			if ($retval == 0) {
				clear_subsystem_dirty('passthrumac');
			}
		}
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
		if (!is_array($a_cp[$cpzone]['passthrumac'])) {
			$a_cp[$cpzone]['passthrumac'] = array();
		}
		$a_passthrumacs =& $a_cp[$cpzone]['passthrumac'];

		if ($_POST['username']) {
			$mac = captiveportal_passthrumac_findbyname($_POST['username']);
			if (!empty($mac)) {
				$_POST['delmac'] = $mac['mac'];
			} else {
				echo gettext("No entry exists for this username:") . " " . $_POST['username'] . "\n";
			}
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
				echo gettext("The entry was successfully deleted") . "\n";
			} else {
				echo gettext("No entry exists for this mac address:") . " " . $_POST['delmac'] . "\n";
			}
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

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('passthrumac')) {
	print_info_box_np(gettext("The captive portal MAC address configuration has been changed.<br />You must apply the changes in order for them to take effect."));
}

$tab_array = array();
$tab_array[] = array(gettext("Configuration"), false, "services_captiveportal.php?zone={$cpzone}");
$tab_array[] = array(gettext("MAC"), true, "services_captiveportal_mac.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed IP Addresses"), false, "services_captiveportal_ip.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed Hostnames"), false, "services_captiveportal_hostname.php?zone={$cpzone}");
$tab_array[] = array(gettext("Vouchers"), false, "services_captiveportal_vouchers.php?zone={$cpzone}");
$tab_array[] = array(gettext("File Manager"), false, "services_captiveportal_filemanager.php?zone={$cpzone}");
display_top_tabs($tab_array, true);
?>
<div class="table-responsive">
	<table class="table table-hover table-striped table-condensed">
		<thead>
			<tr>
				<th><?=gettext('Action')?></th>
				<th><?=gettext("MAC address")?></th>
				<th><?=gettext("Description")?></th>
				<th><!-- Buttons --></th>
			</tr>
		</thead>

<?php
if (is_array($a_cp[$cpzone]['passthrumac'])): ?>
		<tbody>
<?php
$i = 0;
foreach ($a_cp[$cpzone]['passthrumac'] as $mac): ?>
			<tr>
				<td>
					<?=$actsmbl[$mac['action']]?>
				</td>
				<td>
					<?=$mac['mac']?>
				</td>
				<td >
					<?=htmlspecialchars($mac['descr'])?>
				</td>
				<td>
					<a class="fa fa-pencil"	title="<?=gettext("Edit MAC address"); ?>" href="services_captiveportal_mac_edit.php?zone=<?=$cpzone?>&amp;id=<?=$i?>"></a>
					<a class="fa fa-trash"	title="<?=gettext("Delete MAC address")?>" href="services_captiveportal_mac.php?zone=<?=$cpzone?>&amp;act=del&amp;id=<?=$i?>"></a>
				</td>
			</tr>
<?php
$i++;
endforeach; ?>
		<tbody>
	</table>
<?php
else:
?>
		</tbody>
	</table>
<?php
endif;
?>
</div>

<nav class="action-buttons">
	<a href="services_captiveportal_mac_edit.php?zone=<?=$cpzone?>&amp;act=add" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<div class="infoblock">
	<?=print_info_box(gettext('Adding MAC addresses as "pass" MACs allows them access through the captive portal automatically without being taken to the portal page.'), 'info')?>
</div>
<?php
include("foot.inc");
