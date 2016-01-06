<?php
/*
	vpn_l2tp_users.php
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
##|*IDENT=page-vpn-vpnl2tp-users
##|*NAME=VPN: L2TP: Users
##|*DESCR=Allow access to the 'VPN: L2TP: Users' page.
##|*MATCH=vpn_l2tp_users.php*
##|-PRIV

$pgtitle = array(gettext("VPN"), gettext("L2TP"), gettext("Users"));
$shortcut_section = "l2tps";

require("guiconfig.inc");
require_once("vpn.inc");

if (!is_array($config['l2tp']['user'])) {
	$config['l2tp']['user'] = array();
}
$a_secret = &$config['l2tp']['user'];

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		if (!is_subsystem_dirty('rebootreq')) {
			$retval = vpn_l2tp_configure();
		}
		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			if (is_subsystem_dirty('l2tpusers')) {
				clear_subsystem_dirty('l2tpusers');
			}
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_secret[$_GET['id']]) {
		unset($a_secret[$_GET['id']]);
		write_config();
		mark_subsystem_dirty('l2tpusers');
		pfSenseHeader("vpn_l2tp_users.php");
		exit;
	}
}

include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (isset($config['l2tp']['radius']['enable'])) {
	print_info_box(gettext("Warning: RADIUS is enabled. The local user database will not be used."));
}

if (is_subsystem_dirty('l2tpusers')) {
	print_info_box_np(gettext("The l2tp user list has been modified") . ".<br />" . gettext("You must apply the changes in order for them to take effect") . ".<br /><b>" . gettext("Warning: this will terminate all current l2tp sessions!") . "</b>");
}


$tab_array = array();
$tab_array[] = array(gettext("Configuration"), false, "vpn_l2tp.php");
$tab_array[] = array(gettext("Users"), true, "vpn_l2tp_users.php");
display_top_tabs($tab_array);
?>
<div class="table-responsive">
	<table class="table table-striped table-hover">
		<thead>
			<tr>
				<th><?=gettext("Username")?></th>
				<th><?=gettext("IP address")?></th>
				<th><?=gettext("Actions")?></th>
			</tr>
		</thead>
		<tbody>
<?php $i = 0; foreach ($a_secret as $secretent):?>
			<tr>
				<td>
					<?=htmlspecialchars($secretent['name'])?>
				</td>
				<td>
					<?php if ($secretent['ip'] == "") $secretent['ip'] = "Dynamic"?>
					<?=htmlspecialchars($secretent['ip'])?>&nbsp;
				</td>
				<td>
					<a class="fa fa-pencil"	title="<?=gettext('Edit user')?>"	href="vpn_l2tp_users_edit.php?id=<?=$i?>"></a>
					<a class="fa fa-trash"	title="<?=gettext('Delete user')?>"	href="vpn_l2tp_users.php?act=del&amp;id=<?=$i?>"></a>
				</td>
			</tr>
<?php $i++; endforeach?>
		</tbody>
	</table>
</div>

<nav class="action-buttons">
	<a class="btn btn-success btn-sm" href="vpn_l2tp_users_edit.php">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<?php include("foot.inc");
