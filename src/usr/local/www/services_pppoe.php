<?php
/*
	services_pppoe.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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
##|*IDENT=page-services-pppoeserver
##|*NAME=Services: PPPoE Server
##|*DESCR=Allow access to the 'Services: PPPoE Server' page.
##|*MATCH=services_pppoe.php*
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
		header("Location: services_pppoe.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("PPPoE Server"));
$shortcut_section = "pppoes";
include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('vpnpppoe')) {
	print_info_box_np(gettext('The PPPoE entry list has been changed') . '.<br />' . gettext('You must apply the changes in order for them to take effect.'));
}
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('PPPoE Server')?></h2></div>
	<div class="panel-body">
	
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
					<a class="fa fa-pencil"	title="<?=gettext('Edit PPPoE instance')?>"	href="services_pppoe_edit.php?id=<?=$i?>"></a>
					<a class="fa fa-trash" title="<?=gettext('Delete PPPoE instance')?>" href="services_pppoe.php?act=del&amp;id=<?=$i?>"></a>
				</td>
			</tr>
<?php
	$i++;
endforeach;
?>
		</tbody>
	</table>
</div>

	</div>
</div>

<nav class="action-buttons">
	<a href="services_pppoe_edit.php" class="btn btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<?php
include("foot.inc");
