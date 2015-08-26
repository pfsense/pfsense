<?php
/*
	vpn_pppoe.php
	Copyright (C) 2010 Ermal Luçi
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
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
	pfSense_MODULE: pppoe
*/

##|+PRIV
##|*IDENT=page-services-pppoeserver
##|*NAME=Services: PPPoE Server page
##|*DESCR=Allow access to the 'Services: PPPoE Server' page.
##|*MATCH=vpn_pppoe.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("filter.inc");
require_once("vpn.inc");

if (!is_array($config['pppoes']['pppoe'])) {
	$config['pppoes']['pppoe'] = array();
}

$a_pppoes = &$config['pppoes']['pppoe'];

if ($_POST) {
	$pconfig = $_POST;

	if ($_POST['apply']) {
		if (file_exists("{$g['tmp_path']}/.vpn_pppoe.apply")) {
			$toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.vpn_pppoe.apply"));
			foreach ($toapplylist as $pppoeid) {
				if (!is_numeric($pppoeid)) {
					continue;
				}
				if (is_array($config['pppoes']['pppoe'])) {
					foreach ($config['pppoes']['pppoe'] as $pppoe) {
						if ($pppoe['pppoeid'] == $pppoeid) {
							vpn_pppoe_configure($pppoe);
							break;
						}
					}
				}
			}
			@unlink("{$g['tmp_path']}/.vpn_pppoe.apply");
		}
		$retval = 0;
		$retval |= filter_configure();
		$savemsg = get_std_save_message($retval);
		clear_subsystem_dirty('vpnpppoe');
	}
}

if ($_GET['act'] == "del") {
	if ($a_pppoes[$_GET['id']]) {
		if ("{$g['varrun_path']}/pppoe" . $a_pppoes[$_GET['id']]['pppoeid'] . "-vpn.pid") {
			killbypid("{$g['varrun_path']}/pppoe" . $a_pppoes[$_GET['id']]['pppoeid'] . "-vpn.pid");
		}
		if (is_dir("{$g['varetc_path']}/pppoe" . $a_pppoes[$_GET['id']]['pppoeid'])) {
			mwexec("/bin/rm -r {$g['varetc_path']}/pppoe" . $a_pppoes[$_GET['id']]['pppoeid']);
		}
		unset($a_pppoes[$_GET['id']]);
		write_config();
		header("Location: vpn_pppoe.php");
		exit;
	}
}

$pgtitle = array(gettext("VPN"), gettext("PPPoE"));
$shortcut_section = "pppoes";
include("head.inc");

if ($savemsg)
	print_info_box($savemsg, 'success');

if (is_subsystem_dirty('vpnpppoe'))
	print_info_box_np(gettext('The PPPoE entry list has been changed') . '.<br />' . gettext('You must apply the changes in order for them to take effect.'));
?>

<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<thead>
			<tr>
				<th><?=gettext("Interface")?></th>
				<th><?=gettext("Local IP")?></th>
				<th><?=gettext("Number of users")?></th>
				<th><?=gettext("Description")?></th>
				<th><!-- Action buttons --></th>
			</tr>
		</thead>
		<tbody>
<?php
$i = 0;
foreach ($a_pppoes as $pppoe):
?>
			<tr>
				<td>
					<?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($pppoe['interface']))?>
				</td>
				<td>
					<?=htmlspecialchars($pppoe['localip'])?>
				</td>
				<td>
					<?=htmlspecialchars($pppoe['n_pppoe_units'])?>
				</td>
				<td>
					<?=htmlspecialchars($pppoe['descr'])?>
				</td>
				<td>
					<a href="vpn_pppoe_edit.php?id=<?=$i?>" class="btn btn-xs btn-info"><?=gettext('Edit')?></a>
					<a href="vpn_pppoe.php?act=del&amp;id=<?=$i?>" class="btn btn-xs btn-danger"><?=gettext('Delete')?></a>
				</td>
			</tr>
<?php
	$i++;
endforeach;
?>
		</tbody>
	</table>
</div>

<nav class="action-buttons">
	<a href="vpn_pppoe_edit.php" class="btn btn-success"><?=gettext("Add")?></a>
</nav>

<?php
include("foot.inc");