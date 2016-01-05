<?php
/*
	vpn_ipsec_keys.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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
##|*IDENT=page-vpn-ipsec-listkeys
##|*NAME=VPN: IPsec: Pre-Shared Keys List
##|*DESCR=Allow access to the 'VPN: IPsec: Pre-Shared Keys List' page.
##|*MATCH=vpn_ipsec_keys.php*
##|-PRIV

require("functions.inc");
require("guiconfig.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("filter.inc");

if (!is_array($config['ipsec']['mobilekey'])) {
	$config['ipsec']['mobilekey'] = array();
}
ipsec_mobilekey_sort();
$a_secret = &$config['ipsec']['mobilekey'];

$userkeys = array();
foreach ($config['system']['user'] as $id => $user) {
	if (!empty($user['ipsecpsk'])) {
		$userkeys[] = array('ident' => $user['name'], 'type' => 'PSK', 'pre-shared-key' => $user['ipsecpsk'], 'id' => $id);;
	}
}

if (isset($_POST['apply'])) {
	$retval = vpn_ipsec_configure();
	/* reload the filter in the background */
	filter_configure();
	$savemsg = get_std_save_message($retval);
	if (is_subsystem_dirty('ipsec')) {
		clear_subsystem_dirty('ipsec');
	}
}

if ($_GET['act'] == "del") {
	if ($a_secret[$_GET['id']]) {
		unset($a_secret[$_GET['id']]);
		write_config(gettext("Deleted IPsec Pre-Shared Key"));
		mark_subsystem_dirty('ipsec');
		header("Location: vpn_ipsec_keys.php");
		exit;
	}
}

$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("Pre-Shared Keys"));
$shortcut_section = "ipsec";

include("head.inc");

?>

<?php
if ($savemsg) {
	print_info_box($savemsg);
}

if (is_subsystem_dirty('ipsec')) {
	print_info_box_np(gettext("The IPsec tunnel configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));
}
?>

<?php
	$tab_array = array();
	$tab_array[0] = array(gettext("Tunnels"), false, "vpn_ipsec.php");
	$tab_array[1] = array(gettext("Mobile Clients"), false, "vpn_ipsec_mobile.php");
	$tab_array[2] = array(gettext("Pre-Shared Keys"), true, "vpn_ipsec_keys.php");
	$tab_array[3] = array(gettext("Advanced Settings"), false, "vpn_ipsec_settings.php");
	display_top_tabs($tab_array);
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Pre-Shared Keys')?></h2></div>
	<div class="panel-body">

<div class="table-responsive">
	<table class="table table-striped table-hover">
		<thead>
			<tr>
				<th><?=gettext("Identifier"); ?></th>
				<th><?=gettext("Type"); ?></th>
				<th><?=gettext("Pre-Shared Key"); ?></th>
				<th></th>
			</tr>
		</thead>

		<tbody>
<?php $i = 0; foreach ($userkeys as $secretent): ?>
			<tr>
				<td>
					<?php
					if ($secretent['ident'] == 'allusers') {
						echo gettext("ANY USER");
					} else {
						echo htmlspecialchars($secretent['ident']);
					}
					?>
				</td>
				<td>
					<?php
					if (empty($secretent['type'])) {
						echo 'PSK';
					} else {
						echo htmlspecialchars($secretent['type']);
					}
					?>
				</td>
				<td>
					<?=htmlspecialchars($secretent['pre-shared-key'])?>
				</td>
				<td>
					<a class="fa fa-pencil"	title="<?=gettext('Edit user')?>"	href="system_usermanager.php?act=edit&amp;userid=<?=$secretent['id']?>"></a>
				</td>
			</tr>
<?php $i++; endforeach; ?>

<?php $i = 0; foreach ($a_secret as $secretent): ?>
			<tr>
				<td>
					<?=htmlspecialchars($secretent['ident'])?>
				</td>
				<td>
					<?php
					if (empty($secretent['type'])) {
						echo 'PSK';
					} else {
						echo htmlspecialchars($secretent['type']);
					}
					?>
				</td>
				<td>
					<?=htmlspecialchars($secretent['pre-shared-key'])?>
				</td>
				<td>
					<a class="fa fa-pencil"	title="<?=gettext('Edit key')?>" href="vpn_ipsec_keys_edit.php?id=<?=$i?>"></a>
					<a class="fa fa-trash"	title="<?=gettext('Delete key')?>" href="vpn_ipsec_keys.php?act=del&amp;id=<?=$i?>"></a>
				</td>
			</tr>
<?php $i++; endforeach; ?>
		</tbody>
	</table>
</div>

	</div>
</div>

<nav class="action-buttons">
	<a class="btn btn-success btn-sm" href="vpn_ipsec_keys_edit.php">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<div class="alert alert-info">
	<strong><?=gettext("Note"); ?>:</strong>
	<?=gettext(" PSK for any user can be set by using an identifier of any")?>
</div>

<?php include("foot.inc"); ?>
