<?php
/*
	vpn_ipsec_mobile.php
	
	Copyright (C) 2008 Shrew Soft Inc
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

##|+PRIV
##|*IDENT=page-vpn-ipsec-mobile
##|*NAME=VPN: IPsec: Mobile page
##|*DESCR=Allow access to the 'VPN: IPsec: Mobile' page.
##|*MATCH=vpn_ipsec_mobile.php*
##|-PRIV

require("functions.inc");
require("guiconfig.inc");
require_once("ipsec.inc");
require_once("vpn.inc");

if (!is_array($config['ipsec']['phase1']))
	$config['ipsec']['phase1'] = array();

$a_phase1 = &$config['ipsec']['phase1'];

if (!is_array($config['ipsec']['client']))
	$config['ipsec']['client'] = array();

$a_client = &$config['ipsec']['client'];

if (count($a_client)) {

	$pconfig['enable'] = $a_client['enable'];

	$pconfig['user_source'] = $a_client['user_source'];
	$pconfig['group_source'] = $a_client['group_source'];

	$pconfig['pool_address'] = $a_client['pool_address'];
	$pconfig['pool_netbits'] = $a_client['pool_netbits'];
	$pconfig['net_list'] = $a_client['net_list'];
	$pconfig['save_passwd'] = $a_client['save_passwd'];
	$pconfig['dns_domain'] = $a_client['dns_domain'];
	$pconfig['dns_split'] = $a_client['dns_split'];
	$pconfig['dns_server1'] = $a_client['dns_server1'];
	$pconfig['dns_server2'] = $a_client['dns_server2'];
	$pconfig['dns_server3'] = $a_client['dns_server3'];
	$pconfig['dns_server4'] = $a_client['dns_server4'];
	$pconfig['wins_server1'] = $a_client['wins_server1'];
	$pconfig['wins_server2'] = $a_client['wins_server2'];
	$pconfig['pfs_group'] = $a_client['pfs_group'];
	$pconfig['login_banner'] = $a_client['login_banner'];

	if (isset($pconfig['enable']))
		$pconfig['enable'] = true;

	if ($pconfig['pool_address']&&$pconfig['pool_netbits'])
		$pconfig['pool_enable'] = true;
	else
		$pconfig['pool_netbits'] = 24;

	if (isset($pconfig['net_list']))
		$pconfig['net_list_enable'] = true;

	if (isset($pconfig['save_passwd']))
		$pconfig['save_passwd_enable'] = true;

	if ($pconfig['dns_domain'])
		$pconfig['dns_domain_enable'] = true;

	if ($pconfig['dns_split'])
		$pconfig['dns_split_enable'] = true;

	if ($pconfig['dns_server1']||$pconfig['dns_server2']||$pconfig['dns_server3']||$pconfig['dns_server4'])
		$pconfig['dns_server_enable'] = true;

	if ($pconfig['wins_server1']||$pconfig['wins_server2'])
		$pconfig['wins_server_enable'] = true;

	if (isset($pconfig['pfs_group']))
		$pconfig['pfs_group_enable'] = true;

	if ($pconfig['login_banner'])
		$pconfig['login_banner_enable'] = true;
}

if ($_POST['create']) {
	header("Location: vpn_ipsec_phase1.php?mobile=true");
}

if ($_POST['apply']) {
	$retval = 0;
	$retval = vpn_ipsec_configure();
	$savemsg = get_std_save_message($retval);
	if ($retval >= 0)
		if (is_subsystem_dirty('ipsec'))
			clear_subsystem_dirty('ipsec');
}

if ($_POST['submit']) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input consolidation */

	

	/* input validation */

	$reqdfields = explode(" ", "user_source group_source");
	$reqdfieldsn =  array(gettext("User Authentication Source"),gettext("Group Authentication Source"));

    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($pconfig['pool_enable'])
		if (!is_ipaddr($pconfig['pool_address']))
			$input_errors[] = gettext("A valid IP address for 'Virtual Address Pool Network' must be specified.");

	if ($pconfig['dns_domain_enable'])
		if (!is_domain($pconfig['dns_domain']))
			$input_errors[] = gettext("A valid value for 'DNS Default Domain' must be specified.");

	if ($pconfig['dns_split_enable']) {
		if (!empty($pconfig['dns_split'])) {
			$domain_array=preg_split("/[ ,]+/",$pconfig['dns_split']);
			foreach ($domain_array as $curdomain) {
				if (!is_domain($curdomain)) {
					$input_errors[] = gettext("A valid split DNS domain list must be specified.");
					break;
				}
			}
		}
	}

	if ($pconfig['dns_server_enable']) {
		if (!$pconfig['dns_server1'] && !$pconfig['dns_server2'] &&
			!$pconfig['dns_server3'] && !$pconfig['dns_server4'] )
			$input_errors[] = gettext("At least one DNS server must be specified to enable the DNS Server option.");
		if ($pconfig['dns_server1'] && !is_ipaddr($pconfig['dns_server1']))
			$input_errors[] = gettext("A valid IP address for 'DNS Server #1' must be specified.");
		if ($pconfig['dns_server2'] && !is_ipaddr($pconfig['dns_server2']))
			$input_errors[] = gettext("A valid IP address for 'DNS Server #2' must be specified.");
		if ($pconfig['dns_server3'] && !is_ipaddr($pconfig['dns_server3']))
			$input_errors[] = gettext("A valid IP address for 'DNS Server #3' must be specified.");
		if ($pconfig['dns_server4'] && !is_ipaddr($pconfig['dns_server4']))
			$input_errors[] = gettext("A valid IP address for 'DNS Server #4' must be specified.");
	}

	if ($pconfig['wins_server_enable']) {
		if (!$pconfig['wins_server1'] && !$pconfig['wins_server2'])
			$input_errors[] = gettext("At least one WINS server must be specified to enable the DNS Server option.");
		if ($pconfig['wins_server1'] && !is_ipaddr($pconfig['wins_server1']))
			$input_errors[] = gettext("A valid IP address for 'WINS Server #1' must be specified.");
		if ($pconfig['wins_server2'] && !is_ipaddr($pconfig['wins_server2']))
			$input_errors[] = gettext("A valid IP address for 'WINS Server #2' must be specified.");
	}

	if ($pconfig['login_banner_enable'])
		if (!strlen($pconfig['login_banner']))
			$input_errors[] = gettext("A valid value for 'Login Banner' must be specified.");

	if (!$input_errors) {
		$client = array();
		
		if ($pconfig['enable'])
			$client['enable'] = true;

		if (!empty($pconfig['user_source']))
			$client['user_source'] = implode(",", $pconfig['user_source']);
		$client['group_source'] = $pconfig['group_source'];

		if ($pconfig['pool_enable']) {
			$client['pool_address'] = $pconfig['pool_address'];
			$client['pool_netbits'] = $pconfig['pool_netbits'];
		}

		if ($pconfig['net_list_enable'])
			$client['net_list'] = true;

		if ($pconfig['save_passwd_enable'])
			$client['save_passwd'] = true;

		if ($pconfig['dns_domain_enable'])
			$client['dns_domain'] = $pconfig['dns_domain'];

		if ($pconfig['dns_split_enable'])
			$client['dns_split'] = $pconfig['dns_split'];

		if ($pconfig['dns_server_enable']) {
			$client['dns_server1'] = $pconfig['dns_server1'];
			$client['dns_server2'] = $pconfig['dns_server2'];
			$client['dns_server3'] = $pconfig['dns_server3'];
			$client['dns_server4'] = $pconfig['dns_server4'];
		}

		if ($pconfig['wins_server_enable']) {
			$client['wins_server1'] = $pconfig['wins_server1'];
			$client['wins_server2'] = $pconfig['wins_server2'];
		}

		if ($pconfig['pfs_group_enable'])
			$client['pfs_group'] = $pconfig['pfs_group'];

		if ($pconfig['login_banner_enable'])
			$client['login_banner'] = $pconfig['login_banner'];

//		$echo "login banner = {$pconfig['login_banner']}";

		$a_client = $client;
		
		write_config();
		mark_subsystem_dirty('ipsec');
		
		header("Location: vpn_ipsec_mobile.php");
		exit;
	}
}

$pgtitle = array(gettext("VPN"),gettext("IPsec"),gettext("Mobile"));
$shortcut_section = "ipsec";

include("head.inc");
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>

<script type="text/javascript">
<!--

function pool_change() {

	if (document.iform.pool_enable.checked) {
		document.iform.pool_address.disabled = 0;
		document.iform.pool_netbits.disabled = 0;
	} else {
		document.iform.pool_address.disabled = 1;
		document.iform.pool_netbits.disabled = 1;
	}
}

function dns_domain_change() {

	if (document.iform.dns_domain_enable.checked)
		document.iform.dns_domain.disabled = 0;
	else
		document.iform.dns_domain.disabled = 1;
}

function dns_split_change() {

	if (document.iform.dns_split_enable.checked)
		document.iform.dns_split.disabled = 0;
	else
		document.iform.dns_split.disabled = 1;
}

function dns_server_change() {

	if (document.iform.dns_server_enable.checked) {
		document.iform.dns_server1.disabled = 0;
		document.iform.dns_server2.disabled = 0;
		document.iform.dns_server3.disabled = 0;
		document.iform.dns_server4.disabled = 0;
	} else {
		document.iform.dns_server1.disabled = 1;
		document.iform.dns_server2.disabled = 1;
		document.iform.dns_server3.disabled = 1;
		document.iform.dns_server4.disabled = 1;
	}
}

function wins_server_change() {

	if (document.iform.wins_server_enable.checked) {
		document.iform.wins_server1.disabled = 0;
		document.iform.wins_server2.disabled = 0;
	} else {
		document.iform.wins_server1.disabled = 1;
		document.iform.wins_server2.disabled = 1;
	}
}

function pfs_group_change() {

	if (document.iform.pfs_group_enable.checked)
		document.iform.pfs_group.disabled = 0;
	else
		document.iform.pfs_group.disabled = 1;
}

function login_banner_change() {

	if (document.iform.login_banner_enable.checked)
		document.iform.login_banner.disabled = 0;
	else
		document.iform.login_banner.disabled = 1;
}

//-->
</script>

<form action="vpn_ipsec_mobile.php" method="post" name="iform" id="iform">

<?php
	if ($savemsg)
		print_info_box($savemsg);
	if (isset($config['ipsec']['enable']) && is_subsystem_dirty('ipsec'))
		print_info_box_np(gettext("The IPsec tunnel configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));
	foreach ($a_phase1 as $ph1ent)
		if (isset($ph1ent['mobile']))
			$ph1found = true;
	if ($pconfig['enable'] && !$ph1found)
		print_info_box_np(gettext("Support for IPsec Mobile clients is enabled but a Phase1 definition was not found") . ".<br />" . gettext("Please click Create to define one."),gettext("create"),gettext("Create Phase1"));
	if ($input_errors)
		print_input_errors($input_errors);
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td class="tabnavtbl">
			<?php
				$tab_array = array();
				$tab_array[0] = array(gettext("Tunnels"), false, "vpn_ipsec.php");
				$tab_array[1] = array(gettext("Mobile clients"), true, "vpn_ipsec_mobile.php");
				$tab_array[2] = array(gettext("Pre-Shared Key"), false, "vpn_ipsec_keys.php");
				display_top_tabs($tab_array);
			?>
		</td>
	</tr>
	<tr> 
		<td id="mainarea">
			<div class="tabcont">
				<table width="100%" border="0" cellpadding="6" cellspacing="0">
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("IKE Extensions"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellspacing="2" cellpadding="0">
								<tr>
									<td>
										<?php set_checked($pconfig['enable'],$chk); ?>
										<input name="enable" type="checkbox" id="enable" value="yes" <?=$chk;?>>
									</td>
									<td>
										<strong><?=gettext("Enable IPsec Mobile Client Support"); ?></strong>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic">
							<?=gettext("Extended Authentication (Xauth)"); ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("User Authentication"); ?></td>
						<td width="78%" class="vtable">
							<?=gettext("Source"); ?>:&nbsp;&nbsp;
							<select name="user_source[]" class="formselect" id="user_source"  multiple="true" size="3">
							<?php
								$authmodes = explode(",", $pconfig['user_source']);
								$auth_servers = auth_get_authserver_list();
								foreach ($auth_servers as $auth_server) {
									$selected = "";
									if (in_array($auth_server['name'], $authmodes))
										$selected = "selected";
									echo "<option value='{$auth_server['name']}' {$selected}>{$auth_server['name']}</option>\n";
								}
							?>
							</select>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Group Authentication"); ?></td>
						<td width="78%" class="vtable">
							<?=gettext("Source"); ?>:&nbsp;&nbsp;
							<select name="group_source" class="formselect" id="group_source">
								<option value="none"><?=gettext("none"); ?></option>
								<option value="system" <?php if ($pconfig['group_source'] == "system") echo "selected"; ?> ><?=gettext("system"); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr> 
						<td colspan="2" valign="top" class="listtopic">
							<?=gettext("Client Configuration (mode-cfg)"); ?>
						</td>
					</tr>
					<tr> 
						<td width="22%" valign="top" class="vncell"><?=gettext("Virtual Address Pool"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellspacing="2" cellpadding="0">
								<tr>
									<td>
										<?php set_checked($pconfig['pool_enable'],$chk); ?>
										<input name="pool_enable" type="checkbox" id="pool_enable" value="yes" <?=$chk;?> onClick="pool_change()">
									</td>
									<td>
										<?=gettext("Provide a virtual IP address to clients"); ?><br />
									</td>
								</tr>
							</table>
							<table border="0" cellspacing="2" cellpadding="0">
								<tr>
									<td>
										<?=gettext("Network"); ?>:&nbsp;
										<input name="pool_address" type="text" class="formfld unknown" id="pool_address" size="20" value="<?=htmlspecialchars($pconfig['pool_address']);?>">
										/
										<select name="pool_netbits" class="formselect" id="pool_netbits">
											<?php for ($i = 32; $i >= 0; $i--): ?>
											<option value="<?=$i;?>" <?php if ($i == $pconfig['pool_netbits']) echo "selected"; ?>>
												<?=$i;?>
											</option>
											<?php endfor; ?>
										</select>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Network List"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellspacing="2" cellpadding="0">
								<tr>
									<td>
										<?php set_checked($pconfig['net_list_enable'],$chk); ?>
										<input name="net_list_enable" type="checkbox" id="net_list_enable" value="yes" <?=$chk;?>>
									</td>
									<td>
										<?=gettext("Provide a list of accessible networks to clients"); ?><br />
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Save Xauth Password"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellspacing="2" cellpadding="0">
								<tr>
									<td>
										<?php set_checked($pconfig['save_passwd_enable'],$chk); ?>
										<input name="save_passwd_enable" type="checkbox" id="save_passwd_enable" value="yes" <?=$chk;?>>
									</td>
									<td>
										<?=gettext("Allow clients to save Xauth passwords (Cisco VPN client only)."); ?><br />
										<?=gettext("NOTE: With iPhone clients, this does not work when deployed via the iPhone configuration utility, only by manual entry."); ?><br />
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr> 
						<td width="22%" valign="top" class="vncell"><?=gettext("DNS Default Domain"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellspacing="2" cellpadding="0">
								<tr>
									<td>
										<?php set_checked($pconfig['dns_domain_enable'],$chk); ?>
										<input name="dns_domain_enable" type="checkbox" id="dns_domain_enable" value="yes" <?=$chk;?> onClick="dns_domain_change()">
									</td>
									<td>
										<?=gettext("Provide a default domain name to clients"); ?><br />
									</td>
								</tr>
							</table>
							<table border="0" cellspacing="2" cellpadding="0">
								<tr>
									<td>
										<input name="dns_domain" type="text" class="formfld unknown" id="dns_domain" size="30" value="<?=htmlspecialchars($pconfig['dns_domain']);?>">
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Split DNS"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellspacing="2" cellpadding="0">
								<tr>
									<td>
										<?php set_checked($pconfig['dns_split_enable'],$chk); ?>
										<input name="dns_split_enable" type="checkbox" id="dns_split_enable" value="yes" <?=$chk;?> onClick="dns_split_change()">
									</td>
									<td>
										<?=gettext("Provide a list of split DNS domain names to clients. Enter a comma separated list."); ?><br />
										<?=gettext("NOTE: If left blank, and a default domain is set, it will be used for this value."); ?>
									</td>
								</tr>
							</table>
							<table border="0" cellspacing="2" cellpadding="0">
								<tr>
									<td>
										<input name="dns_split" type="text" class="formfld unknown" id="dns_split" size="30" value="<?=htmlspecialchars($pconfig['dns_split']);?>">
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr> 
						<td width="22%" valign="top" class="vncell"><?=gettext("DNS Servers"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellspacing="2" cellpadding="0">
								<tr>
									<td>
										<?php set_checked($pconfig['dns_server_enable'],$chk); ?>
										<input name="dns_server_enable" type="checkbox" id="dns_server_enable" value="yes" <?=$chk;?> onClick="dns_server_change()">
									</td>
									<td>
										<?=gettext("Provide a DNS server list to clients"); ?><br />
									</td>
								</tr>
							</table>
							<table border="0" cellspacing="2" cellpadding="0">
								<tr>
									<td>
										<?=gettext("Server"); ?> #1:&nbsp;
										<input name="dns_server1" type="text" class="formfld unknown" id="dns_server1" size="20" value="<?=htmlspecialchars($pconfig['dns_server1']);?>">
									</td>
								</tr>
								<tr>
									<td>
										<?=gettext("Server"); ?> #2:&nbsp;
										<input name="dns_server2" type="text" class="formfld unknown" id="dns_server2" size="20" value="<?=htmlspecialchars($pconfig['dns_server2']);?>">
									</td>
								</tr>
								<tr>
									<td>
										<?=gettext("Server"); ?> #3:&nbsp;
										<input name="dns_server3" type="text" class="formfld unknown" id="dns_server3" size="20" value="<?=htmlspecialchars($pconfig['dns_server3']);?>">
									</td>
								</tr>
								<tr>
									<td>
										<?=gettext("Server"); ?> #4:&nbsp;
										<input name="dns_server4" type="text" class="formfld unknown" id="dns_server4" size="20" value="<?=htmlspecialchars($pconfig['dns_server4']);?>">
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr> 
						<td width="22%" valign="top" class="vncell"><?=gettext("WINS Servers"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellspacing="2" cellpadding="0">
								<tr>
									<td>
										<?php set_checked($pconfig['wins_server_enable'],$chk); ?>
										<input name="wins_server_enable" type="checkbox" id="wins_server_enable" value="yes" <?=$chk;?> onClick="wins_server_change()">
									</td>
									<td>
										<?=gettext("Provide a WINS server list to clients"); ?><br />
									</td>
								</tr>
							</table>
							<table border="0" cellspacing="2" cellpadding="0">
								<tr>
									<td>
										<?=gettext("Server"); ?> #1:&nbsp;
										<input name="wins_server1" type="text" class="formfld unknown" id="wins_server1" size="20" value="<?=htmlspecialchars($pconfig['wins_server1']);?>">
									</td>
								</tr>
								<tr>
									<td>
										<?=gettext("Server"); ?> #2:&nbsp;
										<input name="wins_server2" type="text" class="formfld unknown" id="wins_server2" size="20" value="<?=htmlspecialchars($pconfig['wins_server2']);?>">
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Phase2 PFS Group"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellspacing="2" cellpadding="0">
								<tr>
									<td>
										<?php set_checked($pconfig['pfs_group_enable'],$chk); ?>
										<input name="pfs_group_enable" type="checkbox" id="pfs_group_enable" value="yes" <?=$chk;?> onClick="pfs_group_change()">
									</td>
									<td>
										<?=gettext("Provide the Phase2 PFS group to clients ( overrides all mobile phase2 settings )"); ?><br />
									</td>
								</tr>
							</table>
							<table border="0" cellspacing="2" cellpadding="0">
								<tr>
									<td>
										<?=gettext("Group"); ?>:&nbsp;&nbsp;
										<select name="pfs_group" class="formselect" id="pfs_group">
										<?php foreach ($p2_pfskeygroups as $keygroup => $keygroupname): ?>
											<option value="<?=$keygroup;?>" <?php if ($pconfig['pfs_group'] == $keygroup) echo "selected"; ?>>
												<?=htmlspecialchars($keygroupname);?>
											</option>
										<?php endforeach; ?>
										</select>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr> 
						<td width="22%" valign="top" class="vncell"><?=gettext("Login Banner"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellspacing="2" cellpadding="0">
								<tr>
									<td>
										<?php set_checked($pconfig['login_banner_enable'],$chk); ?>
										<input name="login_banner_enable" type="checkbox" id="login_banner_enable" value="yes" <?=$chk;?> onClick="login_banner_change()">
									</td>
									<td>
										<?=gettext("Provide a login banner to clients"); ?><br />
									</td>
								</tr>
							</table>
							<table border="0" cellspacing="2" cellpadding="0">
								<tr>
									<td>
										<?php $banner = htmlspecialchars($pconfig['login_banner']); ?>
										<textarea name="login_banner" cols="65" rows="7" id="login_banner" class="formpre"><?=htmlspecialchars($banner);?></textarea>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top">&nbsp;</td>
						<td width="78%">
							<input name="submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>">
						</td>
					</tr>
				</table>
			</div>
		</td>
	</tr>
</table>
</form>
<script type="text/javascript">
pool_change();
dns_domain_change();
dns_split_change();
dns_server_change();
wins_server_change();
pfs_group_change();
login_banner_change();
//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>

<?php

/* local utility functions */

function set_checked($var,& $chk) {
	if($var)
		$chk = 'checked';
	else
		$chk = '';
}

?>

