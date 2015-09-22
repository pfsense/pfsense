<?php
/*
	vpn_l2tp.php
	part of pfSense

	Copyright (C) 2005 Scott Ullrich (sullrich@gmail.com)
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

##|+PRIV
##|*IDENT=page-vpn-vpnl2tp
##|*NAME=VPN: VPN L2TP page
##|*DESCR=Allow access to the 'VPN: VPN L2TP' page.
##|*MATCH=vpn_l2tp.php*
##|-PRIV

$pgtitle = array(gettext("VPN"), gettext("L2TP"), gettext("L2TP"));
$shortcut_section = "l2tps";

require("guiconfig.inc");
require_once("vpn.inc");

if (!is_array($config['l2tp']['radius'])) {
	$config['l2tp']['radius'] = array();
}
$l2tpcfg = &$config['l2tp'];

$pconfig['remoteip'] = $l2tpcfg['remoteip'];
$pconfig['localip'] = $l2tpcfg['localip'];
$pconfig['l2tp_subnet'] = $l2tpcfg['l2tp_subnet'];
$pconfig['mode'] = $l2tpcfg['mode'];
$pconfig['interface'] = $l2tpcfg['interface'];
$pconfig['l2tp_dns1'] = $l2tpcfg['dns1'];
$pconfig['l2tp_dns2'] = $l2tpcfg['dns2'];
$pconfig['wins'] = $l2tpcfg['wins'];
$pconfig['radiusenable'] = isset($l2tpcfg['radius']['enable']);
$pconfig['radacct_enable'] = isset($l2tpcfg['radius']['accounting']);
$pconfig['radiusserver'] = $l2tpcfg['radius']['server'];
$pconfig['radiussecret'] = $l2tpcfg['radius']['secret'];
$pconfig['radiusissueips'] = $l2tpcfg['radius']['radiusissueips'];
$pconfig['n_l2tp_units'] = $l2tpcfg['n_l2tp_units'];
$pconfig['paporchap'] = $l2tpcfg['paporchap'];
$pconfig['secret'] = $l2tpcfg['secret'];

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['mode'] == "server") {
		$reqdfields = explode(" ", "localip remoteip");
		$reqdfieldsn = array(gettext("Server address"), gettext("Remote start address"));

		if ($_POST['radiusenable']) {
			$reqdfields = array_merge($reqdfields, explode(" ", "radiusserver radiussecret"));
			$reqdfieldsn = array_merge($reqdfieldsn,
				array(gettext("RADIUS server address"), gettext("RADIUS shared secret")));
		}

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		if (($_POST['localip'] && !is_ipaddr($_POST['localip']))) {
			$input_errors[] = gettext("A valid server address must be specified.");
		}
		if (is_ipaddr_configured($_POST['localip'])) {
			$input_errors[] = gettext("'Server address' parameter should NOT be set to any IP address currently in use on this firewall.");
		}
		if (($_POST['l2tp_subnet'] && !is_ipaddr($_POST['remoteip']))) {
			$input_errors[] = gettext("A valid remote start address must be specified.");
		}
		if (($_POST['radiusserver'] && !is_ipaddr($_POST['radiusserver']))) {
			$input_errors[] = gettext("A valid RADIUS server address must be specified.");
		}

		/* if this is an AJAX caller then handle via JSON */
		if (isAjax() && is_array($input_errors)) {
			input_errors2Ajax($input_errors);
			exit;
		}

		if (!$input_errors) {
			$_POST['remoteip'] = $pconfig['remoteip'] = gen_subnet($_POST['remoteip'], $_POST['l2tp_subnet']);
			$subnet_start = ip2ulong($_POST['remoteip']);
			$subnet_end = ip2ulong($_POST['remoteip']) + $_POST['n_l2tp_units'] - 1;

			if ((ip2ulong($_POST['localip']) >= $subnet_start) &&
				(ip2ulong($_POST['localip']) <= $subnet_end)) {
				$input_errors[] = gettext("The specified server address lies in the remote subnet.");
			}
			if ($_POST['localip'] == get_interface_ip("lan")) {
				$input_errors[] = gettext("The specified server address is equal to the LAN interface address.");
			}
		}
	}

	/* if this is an AJAX caller then handle via JSON */
	if (isAjax() && is_array($input_errors)) {
		input_errors2Ajax($input_errors);
		exit;
	}

	if (!$input_errors) {
		$l2tpcfg['remoteip'] = $_POST['remoteip'];
		$l2tpcfg['localip'] = $_POST['localip'];
		$l2tpcfg['l2tp_subnet'] = $_POST['l2tp_subnet'];
		$l2tpcfg['mode'] = $_POST['mode'];
		$l2tpcfg['interface'] = $_POST['interface'];
		$l2tpcfg['n_l2tp_units'] = $_POST['n_l2tp_units'];

		$l2tpcfg['radius']['server'] = $_POST['radiusserver'];
		$l2tpcfg['radius']['secret'] = $_POST['radiussecret'];
		$l2tpcfg['secret'] = $_POST['secret'];

		if ($_POST['wins']) {
			$l2tpcfg['wins'] = $_POST['wins'];
		} else {
			unset($l2tpcfg['wins']);
		}

		$l2tpcfg['paporchap'] = $_POST['paporchap'];


		if ($_POST['l2tp_dns1'] == "") {
			if (isset($l2tpcfg['dns1'])) {
				unset($l2tpcfg['dns1']);
			}
		} else {
			$l2tpcfg['dns1'] = $_POST['l2tp_dns1'];
		}

		if ($_POST['l2tp_dns2'] == "") {
			if (isset($l2tpcfg['dns2'])) {
				unset($l2tpcfg['dns2']);
			}
		} else {
			$l2tpcfg['dns2'] = $_POST['l2tp_dns2'];
		}

		if ($_POST['radiusenable'] == "yes") {
			$l2tpcfg['radius']['enable'] = true;
		} else {
			unset($l2tpcfg['radius']['enable']);
		}

		if ($_POST['radacct_enable'] == "yes") {
			$l2tpcfg['radius']['accounting'] = true;
		} else {
			unset($l2tpcfg['radius']['accounting']);
		}

		if ($_POST['radiusissueips'] == "yes") {
			$l2tpcfg['radius']['radiusissueips'] = true;
		} else {
			unset($l2tpcfg['radius']['radiusissueips']);
		}

		write_config();

		$retval = 0;
		$retval = vpn_l2tp_configure();
		$savemsg = get_std_save_message($retval);

		/* if ajax is calling, give them an update message */
		if (isAjax()) {
			print_info_box_np($savemsg);
		}
	}
}

include("head.inc");
?>

<script type="text/javascript">
//<![CDATA[
function get_radio_value(obj) {
	for (i = 0; i < obj.length; i++) {
		if (obj[i].checked) {
			return obj[i].value;
		}
	}
	return null;
}

function enable_change(enable_over) {
	if ((get_radio_value(document.iform.mode) == "server") || enable_over) {
		document.iform.remoteip.disabled = 0;
		document.iform.localip.disabled = 0;
		document.iform.l2tp_subnet.disabled = 0;
		document.iform.radiusenable.disabled = 0;
		document.iform.radiusissueips.disabled = 0;
		document.iform.paporchap.disabled = 0;
		document.iform.interface.disabled = 0;
		document.iform.n_l2tp_units.disabled = 0;
		document.iform.secret.disabled = 0;
		document.iform.l2tp_dns1.disabled = 0;
		document.iform.l2tp_dns2.disabled = 0;
		/* fix colors */
		document.iform.remoteip.style.backgroundColor = '#FFFFFF';
		document.iform.localip.style.backgroundColor = '#FFFFFF';
		document.iform.l2tp_subnet.style.backgroundColor = '#FFFFFF';
		document.iform.radiusenable.style.backgroundColor = '#FFFFFF';
		document.iform.radiusissueips.style.backgroundColor = '#FFFFFF';
		document.iform.paporchap.style.backgroundColor = '#FFFFFF';
		document.iform.interface.style.backgroundColor = '#FFFFFF';
		document.iform.n_l2tp_units.style.backgroundColor = '#FFFFFF';
		document.iform.secret.style.backgroundColor = '#FFFFFF';
		if (document.iform.radiusenable.checked || enable_over) {
			document.iform.radacct_enable.disabled = 0;
			document.iform.radiusserver.disabled = 0;
			document.iform.radiussecret.disabled = 0;
			document.iform.radiusissueips.disabled = 0;
			/* fix colors */
			document.iform.radacct_enable.style.backgroundColor = '#FFFFFF';
			document.iform.radiusserver.style.backgroundColor = '#FFFFFF';
			document.iform.radiussecret.style.backgroundColor = '#FFFFFF';
			document.iform.radiusissueips.style.backgroundColor = '#FFFFFF';
		} else {
			document.iform.radacct_enable.disabled = 1;
			document.iform.radiusserver.disabled = 1;
			document.iform.radiussecret.disabled = 1;
			document.iform.radiusissueips.disabled = 1;
			/* fix colors */
			document.iform.radacct_enable.style.backgroundColor = '#D4D0C8';
			document.iform.radiusserver.style.backgroundColor = '#D4D0C8';
			document.iform.radiussecret.style.backgroundColor = '#D4D0C8';
			document.iform.radiusissueips.style.backgroundColor = '#D4D0C8';
		}
	} else {
		document.iform.interface.disabled = 1;
		document.iform.n_l2tp_units.disabled = 1;
		document.iform.l2tp_subnet.disabled = 1;
		document.iform.l2tp_dns1.disabled = 1;
		document.iform.l2tp_dns2.disabled = 1;
		document.iform.paporchap.disabled = 1;
		document.iform.remoteip.disabled = 1;
		document.iform.localip.disabled = 1;
		document.iform.radiusenable.disabled = 1;
		document.iform.radacct_enable.disabled = 1;
		document.iform.radiusserver.disabled = 1;
		document.iform.radiussecret.disabled = 1;
		document.iform.radiusissueips.disabled = 1;
		document.iform.secret.disabled = 1;
		/* fix colors */
		document.iform.interface.style.backgroundColor = '#D4D0C8';
		document.iform.n_l2tp_units.style.backgroundColor = '#D4D0C8';
		document.iform.l2tp_subnet.style.backgroundColor = '#D4D0C8';
		document.iform.paporchap.style.backgroundColor = '#D4D0C8';
		document.iform.remoteip.style.backgroundColor = '#D4D0C8';
		document.iform.localip.style.backgroundColor = '#D4D0C8';
		document.iform.radiusenable.style.backgroundColor = '#D4D0C8';
		document.iform.radacct_enable.style.backgroundColor = '#D4D0C8';
		document.iform.radiusserver.style.backgroundColor = '#D4D0C8';
		document.iform.radiussecret.style.backgroundColor = '#D4D0C8';
		document.iform.radiusissueips.style.backgroundColor = '#D4D0C8';
		document.iform.secret.style.backgroundColor = '#D4D0C8';
	}
}
//]]>
</script>

<form class="form-horizontal" action="vpn_l2tp.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors)?>
<?php if ($savemsg) print_info_box($savemsg)?>

<?php
$tab_array = array();
$tab_array[0] = array(gettext("Configuration"), true, "vpn_l2tp.php");
$tab_array[1] = array(gettext("Users"), false, "vpn_l2tp_users.php");
display_top_tabs($tab_array);
?>

	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext('Enable L2TP'); ?></h2>
		</div>

		<div class="panel-body">
			<div class="form-group">
				<div class="col-sm-10">
					<label>
						<input name="mode" type="radio" onclick="enable_change(false)" value="off" <?php if (($pconfig['mode'] != "server") && ($pconfig['mode'] != "redir")) echo "checked=\"checked\""?> />
						<?=gettext("Off")?>
					</label>
					<label>
						<input type="radio" name="mode" value="server" onclick="enable_change(false)" <?php if ($pconfig['mode'] == "server") echo "checked=\"checked\""?> />
						<?=gettext("Enable L2TP server")?>
					</label>
				</div>
			</div>
		</div>
	</div>

	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext('Configuration'); ?></h2>
		</div>

		<div class="panel-body">
			<div class="form-group">
				<label for="interface" class="col-sm-2 control-label"><?=gettext("Interface")?></label>
				<div class="col-sm-2">
					<select class="form-control" name="interface" class="formselect" id="interface">
<?php
$interfaces = get_configured_interface_with_descr();
foreach ($interfaces as $iface => $ifacename): ?>
						<option value="<?=$iface?>" <?php if ($iface == $pconfig['interface']) echo "selected=\"selected\""?>>
							<?=htmlspecialchars($ifacename)?>
						</option>
<?php endforeach?>
					</select>
				</div>
			</div>
			<div class="form-group">
				<label for="localip" class="col-sm-2 control-label"><?=gettext("Server Address")?></label>
				<div class="col-sm-10">
					<?=$mandfldhtml?><input name="localip" type="text" class="form-control formfld unknown" id="localip" size="20" value="<?=htmlspecialchars($pconfig['localip'])?>" />

					<span class="help-block">
						<?=gettext("Enter the IP address the L2TP server should give to clients for use as their \"gateway\"")?>.
						<br />
						<?=gettext("Typically this is set to an unused IP just outside of the client range")?>.
						<br />
						<br />
						<?=gettext("NOTE: This should NOT be set to any IP address currently in use on this firewall")?>.
					</span>
				</div>
			</div>

			<div class="form-group">
				<label for="remoteip" class="col-sm-2 control-label"><?=gettext("Remote Address Range")?></label>
				<div class="col-sm-10">
					<?=$mandfldhtml?><input name="remoteip" type="text" class="form-control formfld unknown" id="remoteip" size="20" value="<?=htmlspecialchars($pconfig['remoteip'])?>" />
					<span class="help-block">
						<?=gettext("Specify the starting address for the client IP address subnet.")?>
					</span>
				</div>
			</div>

			<div class="form-group">
				<label for="l2tp_subnet" class="col-sm-2 control-label"><?=gettext("Subnet Mask")?></label>
				<div class="col-sm-2">
					<select id="l2tp_subnet" name="l2tp_subnet" class="form-control">
<?php
					 for($x=0; $x<33; $x++) {
						if($x == $pconfig['l2tp_subnet'])
								$SELECTED = " selected=\"selected\"";
						else
								$SELECTED = "";
						echo "<option value=\"{$x}\"{$SELECTED}>{$x}</option>\n";
					 }
?>
					</select>
					<span class="help-block">
						<?=gettext("Hint:")?> 24 <?=gettext("is")?> 255.255.255.0
					</span>
				</div>
			</div>

			<div class="form-group">
				<label for="n_l2tp_units" class="col-sm-2 control-label"><?=gettext("Number of L2TP users")?></label>
				<div class="col-sm-2">
					<select id="n_l2tp_units" name="n_l2tp_units" class="form-control">
<?php
					 for($x=0; $x<255; $x++) {
						if($x == $pconfig['n_l2tp_units'])
								$SELECTED = " selected=\"selected\"";
						else
								$SELECTED = "";
						echo "<option value=\"{$x}\"{$SELECTED}>{$x}</option>\n";
					 }
?>
					</select>
					<span class="help-block">
						<?=gettext("Hint:")?> 10 <?=gettext("is ten L2TP clients")?>
					</span>
				</div>
			</div>

			<div class="form-group">
				<label for="secret" class="col-sm-2 control-label"><?=gettext("Secret")?></label>
				<div class="col-sm-10">
					<input type="password" name="secret" id="secret" class="formfld pwd form-control" value="<?=htmlspecialchars($pconfig['secret'])?>" />
					<span class="help-block">
						<?=gettext("Specify optional secret shared between peers. Required on some devices/setups.")?>
					</span>
				</div>
			</div>

			<div class="form-group">
				<label for="paporchap" class="col-sm-2 control-label"><?=gettext("Authentication Type")?></label>
				<div class="col-sm-2">
					<?=$mandfldhtml?><select name="paporchap" id="paporchap" class="form-control">
						<option value='chap'<?php if($pconfig['paporchap'] == "chap") echo " selected=\"selected\""?>><?=gettext("CHAP")?></option>
						<option value='pap'<?php if($pconfig['paporchap'] == "pap") echo " selected=\"selected\""?>><?=gettext("PAP")?></option>
					</select>
					<span class="help-block">
						<?=gettext("Specifies which protocol to use for authentication.")?>
					</span>
				</div>
			</div>

			<div class="form-group">
				<label for="l2tp_dns1" class="col-sm-2 control-label"><?=gettext("L2TP DNS Servers")?></label>
				<div class="col-sm-10">
					<?=$mandfldhtml?><input name="l2tp_dns1" type="text" class="formfld unknown form-control" id="l2tp_dns1" size="20" value="<?=htmlspecialchars($pconfig['l2tp_dns1'])?>" />
		    		<input name="l2tp_dns2" type="text" class="formfld unknown form-control" id="l2tp_dns2" size="20" value="<?=htmlspecialchars($pconfig['l2tp_dns2'])?>" />
					<span class="help-block">
			            <?=gettext("primary and secondary DNS servers assigned to L2TP clients")?>
					</span>
			    </div>
			</div>

			<div class="form-group">
				<label for="wins" class="col-sm-2 control-label"><?=gettext("WINS Server")?></label>
				<div class="col-sm-10">
					<input name="wins" class="formfld unknown form-control" id="wins" size="20" value="<?=htmlspecialchars($pconfig['wins'])?>" />
		        </div>
			</div>
		</div>
	</div>

	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext('RADIUS'); ?></h2>
		</div>

		<div class="panel-body">
			<div class="form-group">
				<label for="radiusenable" class="col-sm-2 control-label"><?=gettext('RADIUS Authentication')?></label>
				<div class="col-sm-10 checkbox">
					<label>
						<input name="radiusenable" type="checkbox" id="radiusenable" onclick="enable_change(false)" value="yes" <?php if ($pconfig['radiusenable']) echo "checked=\"checked\""?> />
						<?=gettext("Use a RADIUS server for authentication")?>
					</label>
					<span class="help-block">
					  <?=gettext("When set, all users will be authenticated using the RADIUS server specified below. The local user database will not be used.")?>
					</span>
				</div>
			</div>
			<div class="form-group">
				<label for="radacct_enable" class="col-sm-2 control-label"><?=gettext('RADIUS Accounting')?></label>
				<div class="col-sm-10 checkbox">
					<label>
						<input name="radacct_enable" type="checkbox" id="radacct_enable" onclick="enable_change(false)" value="yes" <?php if ($pconfig['radacct_enable']) echo "checked=\"checked\""?> />
						<?=gettext("Enable RADIUS accounting")?>
					</label>
					<span class="help-block">
						<?=gettext("Sends accounting packets to the RADIUS server.")?>
					</span>
				</div>
			</div>

			<div class="form-group">
				<label for="radiusserver" class="col-sm-2 control-label"><?=gettext("RADIUS Server")?></label>
				<div class="col-sm-10">
					<input name="radiusserver" type="text" class="formfld unknown form-control" id="radiusserver" size="20" value="<?=htmlspecialchars($pconfig['radiusserver'])?>" />
					<span class="help-block">
						<?=gettext("Enter the IP address of the RADIUS server.")?>
					</span>
				</div>
			</div>
			<div class="form-group">
				<label for="radiussecret" class="col-sm-2 control-label"><?=gettext("RADIUS Shared Secret")?></label>
				<div class="col-sm-10">
					<input name="radiussecret" type="password" class="formfld pwd form-control" id="radiussecret" size="20" value="<?=htmlspecialchars($pconfig['radiussecret'])?>" />
					<span class="help-block">
						<?=gettext("Enter the shared secret that will be used to authenticate to the RADIUS server.")?>
					</span>
				</div>
			</div>

			<div class="form-group">
				<label for="radiusissueips" class="col-sm-2 control-label"><?=gettext("RADIUS Issued IPs")?></label>
				<div class="col-sm-10 checkbox">
					<label>
						<input name="radiusissueips" value="yes" type="checkbox" class="formfld" id="radiusissueips"<?php if(isset($pconfig['radiusissueips'])) echo " checked=\"checked\""?> />
						<?=gettext("Issue IP Addresses via RADIUS server.")?>
					</label>
				</div>
			</div>
		</div>
	</div>

<?php
	// TODO: Is it possible to detect available rules and only show warning if there are no (relevant) rules set?
?>
	<div class="alert alert-danger">
		<strong><?=gettext("Note:")?></strong> <?=gettext("Don't forget to add a firewall rule to permit traffic from L2TP clients!")?>
	</div>

	<div class="col-sm-10 col-sm-offset-2">
		<input id="submit" name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save")?>" onclick="enable_change(true)" />
	</div>
</form>

<script type="text/javascript">
//<![CDATA[
	enable_change(false);
//]]>
</script>

<?php include("foot.inc")?>