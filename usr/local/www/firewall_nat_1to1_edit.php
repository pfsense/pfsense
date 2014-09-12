<?php
/* $Id$ */
/*
	firewall_nat_1to1_edit.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
	pfSense_MODULE:	nat
*/

##|+PRIV
##|*IDENT=page-firewall-nat-1-1-edit
##|*NAME=Firewall: NAT: 1:1: Edit page
##|*DESCR=Allow access to the 'Firewall: NAT: 1:1: Edit' page.
##|*MATCH=firewall_nat_1to1_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("interfaces.inc");
require_once("filter.inc");
require("shaper.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_nat_1to1.php');

$specialsrcdst = explode(" ", "any pptp pppoe l2tp openvpn");
$ifdisp = get_configured_interface_with_descr();
foreach ($ifdisp as $kif => $kdescr) {
	$specialsrcdst[] = "{$kif}";
	$specialsrcdst[] = "{$kif}ip";
}

if (!is_array($config['nat']['onetoone']))
	$config['nat']['onetoone'] = array();

$a_1to1 = &$config['nat']['onetoone'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

$after = $_GET['after'];
if (isset($_POST['after']))
	$after = $_POST['after'];

if (isset($_GET['dup']))  {
	$id = $_GET['dup'];
	$after = $_GET['dup'];
}

if (isset($id) && $a_1to1[$id]) {
	$pconfig['disabled'] = isset($a_1to1[$id]['disabled']);

	address_to_pconfig($a_1to1[$id]['source'], $pconfig['src'],
		$pconfig['srcmask'], $pconfig['srcnot'],
		$pconfig['srcbeginport'], $pconfig['srcendport']);

	address_to_pconfig($a_1to1[$id]['destination'], $pconfig['dst'],
		$pconfig['dstmask'], $pconfig['dstnot'],
		$pconfig['dstbeginport'], $pconfig['dstendport']);

	$pconfig['interface'] = $a_1to1[$id]['interface'];
	if (!$pconfig['interface'])
		$pconfig['interface'] = "wan";

	$pconfig['external'] = $a_1to1[$id]['external'];
	$pconfig['descr'] = $a_1to1[$id]['descr'];
	$pconfig['natreflection'] = $a_1to1[$id]['natreflection'];
} else
	$pconfig['interface'] = "wan";

if (isset($_GET['dup']))
	unset($id);

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;
	/*  run through $_POST items encoding HTML entties so that the user
	 *  cannot think he is slick and perform a XSS attack on the unwilling
	 */
	foreach ($_POST as $key => $value) {
		$temp = str_replace(">", "", $value);
		$newpost = htmlentities($temp);
		if($newpost <> $temp)
			$input_errors[] = sprintf(gettext("Invalid characters detected (%s).  Please remove invalid characters and save again."),$temp);
	}

	/* input validation */
	$reqdfields = explode(" ", "interface external");
	$reqdfieldsn = array(gettext("Interface"), gettext("External subnet"));
	if ($_POST['srctype'] == "single" || $_POST['srctype'] == "network") {
		$reqdfields[] = "src";
		$reqdfieldsn[] = gettext("Source address");
	}
	if ($_POST['dsttype'] == "single" || $_POST['dsttype'] == "network") {
		$reqdfields[] = "dst";
		$reqdfieldsn[] = gettext("Destination address");
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['external'])
		$_POST['external'] = trim($_POST['external']);
	if ($_POST['src'])
		$_POST['src'] = trim($_POST['src']);
	if ($_POST['dst'])
		$_POST['dst'] = trim($_POST['dst']);

	if (is_specialnet($_POST['srctype'])) {
		$_POST['src'] = $_POST['srctype'];
		$_POST['srcmask'] = 0;
	} else if ($_POST['srctype'] == "single") {
		$_POST['srcmask'] = 32;
	}
	if (is_specialnet($_POST['dsttype'])) {
		$_POST['dst'] = $_POST['dsttype'];
		$_POST['dstmask'] = 0;
	} else if ($_POST['dsttype'] == "single") {
		$_POST['dstmask'] = 32;
	} else if (is_ipaddr($_POST['dsttype'])) {
		$_POST['dst'] = $_POST['dsttype'];
		$_POST['dstmask'] = 32;
		$_POST['dsttype'] = "single";
	}

	/* For external, user can enter only ip's */
	if (($_POST['external'] && !is_ipaddr($_POST['external'])))
		$input_errors[] = gettext("A valid external subnet must be specified.");

	/* For dst, if user enters an alias and selects "network" then disallow. */
	if ($_POST['dsttype'] == "network" && is_alias($_POST['dst']) )
		$input_errors[] = gettext("You must specify single host or alias for alias entries.");

	/* For src, user can enter only ip's or networks */
	if (!is_specialnet($_POST['srctype'])) {
		if (($_POST['src'] && !is_ipaddr($_POST['src']))) {
			$input_errors[] = sprintf(gettext("%s is not a valid internal IP address."), $_POST['src']);
		}
		if (($_POST['srcmask'] && !is_numericint($_POST['srcmask']))) {
			$input_errors[] = gettext("A valid internal bit count must be specified.");
		}
	}

	/* For dst, user can enter ip's, networks or aliases */
	if (!is_specialnet($_POST['dsttype'])) {
		if (($_POST['dst'] && !is_ipaddroralias($_POST['dst']))) {
			$input_errors[] = sprintf(gettext("%s is not a valid destination IP address or alias."), $_POST['dst']);
		}
		if (($_POST['dstmask'] && !is_numericint($_POST['dstmask']))) {
			$input_errors[] = gettext("A valid destination bit count must be specified.");
		}
	}

	/* check for overlaps with other 1:1 */
	foreach ($a_1to1 as $natent) {
		if (isset($id) && ($a_1to1[$id]) && ($a_1to1[$id] === $natent))
			continue;

		if (check_subnets_overlap($_POST['internal'], $_POST['subnet'], $natent['internal'], $natent['subnet'])) {
			//$input_errors[] = "Another 1:1 rule overlaps with the specified internal subnet.";
			//break;
		}
	}

	if (!$input_errors) {
		$natent = array();

		$natent['disabled'] = isset($_POST['disabled']) ? true:false;
		$natent['external'] = $_POST['external'];
		$natent['descr'] = $_POST['descr'];
		$natent['interface'] = $_POST['interface'];

		pconfig_to_address($natent['source'], $_POST['src'],
			$_POST['srcmask'], $_POST['srcnot']);

		pconfig_to_address($natent['destination'], $_POST['dst'],
			$_POST['dstmask'], $_POST['dstnot']);

		if ($_POST['natreflection'] == "enable" || $_POST['natreflection'] == "disable")
			$natent['natreflection'] = $_POST['natreflection'];
		else
			unset($natent['natreflection']);

		if (isset($id) && $a_1to1[$id])
			$a_1to1[$id] = $natent;
		else {
			if (is_numeric($after))
				array_splice($a_1to1, $after+1, 0, array($natent));
			else
				$a_1to1[] = $natent;
		}

		if (write_config())
			mark_subsystem_dirty('natconf');
		header("Location: firewall_nat_1to1.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"),gettext("NAT"),gettext("1:1"),gettext("Edit"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script type="text/javascript" src="/javascript/suggestions.js"></script>
<script type="text/javascript" src="/javascript/autosuggest.js"></script>
<script type="text/javascript">
//<![CDATA[
function typesel_change() {
	switch (document.iform.srctype.selectedIndex) {
		case 1: /* single */
			document.iform.src.disabled = 0;
			document.iform.srcmask.value = "";
			document.iform.srcmask.disabled = 1;
			break;
		case 2: /* network */
			document.iform.src.disabled = 0;
			document.iform.srcmask.disabled = 0;
			break;
		default:
			document.iform.src.value = "";
			document.iform.src.disabled = 1;
			document.iform.srcmask.value = "";
			document.iform.srcmask.disabled = 1;
			break;
	}
	switch (document.iform.dsttype.selectedIndex) {
		case 1: /* single */
			document.iform.dst.disabled = 0;
			document.iform.dstmask.value = "";
			document.iform.dstmask.disabled = 1;
			break;
		case 2: /* network */
			document.iform.dst.disabled = 0;
			document.iform.dstmask.disabled = 0;
			break;
		default:
			document.iform.dst.value = "";
			document.iform.dst.disabled = 1;
			document.iform.dstmask.value = "";
			document.iform.dstmask.disabled = 1;
			break;
	}
}
//]]>
</script>

<?php
include("fbegin.inc");
if ($input_errors)
	print_input_errors($input_errors);
?>
<form action="firewall_nat_1to1_edit.php" method="post" name="iform" id="iform">
	<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="firewall nat 1to1 edit">
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("Edit NAT 1:1 entry"); ?></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Disabled"); ?></td>
			<td width="78%" class="vtable">
				<input name="disabled" type="checkbox" id="disabled" value="yes" <?php if ($pconfig['disabled']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("Disable this rule"); ?></strong><br />
				<span class="vexpl"><?=gettext("Set this option to disable this rule without removing it from the list."); ?></span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Interface"); ?></td>
			<td width="78%" class="vtable">
				<select name="interface" class="formselect">
<?php
					foreach ($ifdisp as $if => $ifdesc)
						if(have_ruleint_access($if))
							$interfaces[$if] = $ifdesc;

					if ($config['l2tp']['mode'] == "server")
						if(have_ruleint_access("l2tp"))
							$interfaces['l2tp'] = "L2TP VPN";

					if ($config['pptpd']['mode'] == "server")
						if(have_ruleint_access("pptp"))
							$interfaces['pptp'] = "PPTP VPN";

					if (is_pppoe_server_enabled() && have_ruleint_access("pppoe"))
						$interfaces['pppoe'] = "PPPoE VPN";

					/* add ipsec interfaces */
					if (isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable']))
						if(have_ruleint_access("enc0"))
							$interfaces["enc0"] = "IPsec";

					/* add openvpn/tun interfaces */
					if  ($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"])
						$interfaces["openvpn"] = "OpenVPN";

					foreach ($interfaces as $iface => $ifacename):
?>
						<option value="<?=$iface;?>" <?php if ($iface == $pconfig['interface']) echo "selected=\"selected\""; ?>>
							<?=htmlspecialchars($ifacename);?>
						</option>
<?php
					endforeach;
?>
				</select><br />
			  <span class="vexpl"><?=gettext("Choose which interface this rule applies to"); ?>.<br />
			  <?=gettext("Hint: in most cases, you'll want to use WAN here"); ?>.</span></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("External subnet IP"); ?></td>
			<td width="78%" class="vtable">
				<input name="external" type="text" class="formfld" id="external" size="20" value="<?=htmlspecialchars($pconfig['external']);?>" />
				<br />
				<span class="vexpl">
					<?=gettext("Enter the external (usually on a WAN) subnet's starting address for the 1:1 mapping.  " .
						"The subnet mask from the internal address below will be applied to this IP address."); ?><br />
					<?=gettext("Hint: this is generally an address owned by the router itself on the selected interface."); ?>
				</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Internal IP"); ?></td>
			<td width="78%" class="vtable">
				<input name="srcnot" type="checkbox" id="srcnot" value="yes" <?php if ($pconfig['srcnot']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("not"); ?></strong>
				<br />
				<?=gettext("Use this option to invert the sense of the match."); ?>
				<br />
				<br />
				<table border="0" cellspacing="0" cellpadding="0" summary="source">
					<tr>
						<td><?=gettext("Type:"); ?>&nbsp;&nbsp;</td>
						<td>
							<select name="srctype" class="formselect" onchange="typesel_change()">
<?php
							$sel = is_specialnet($pconfig['src']);
?>
								<option value="any"     <?php if ($pconfig['src'] == "any") { echo "selected=\"selected\""; } ?>><?=gettext("any"); ?></option>
								<option value="single"  <?php if ((($pconfig['srcmask'] == 32) || !isset($pconfig['srcmask'])) && !$sel) { echo "selected=\"selected\""; $sel = 1; } ?>>
									<?=gettext("Single host"); ?>
								</option>
								<option value="network" <?php if (!$sel) echo "selected=\"selected\""; ?>><?=gettext("Network"); ?></option>
<?php
							if(have_ruleint_access("pptp")):
?>
								<option value="pptp"    <?php if ($pconfig['src'] == "pptp") { echo "selected=\"selected\""; } ?>><?=gettext("PPTP clients"); ?></option>
<?php
							endif;
							if(have_ruleint_access("pppoe")):
?>
								<option value="pppoe"   <?php if ($pconfig['src'] == "pppoe") { echo "selected=\"selected\""; } ?>><?=gettext("PPPoE clients"); ?></option>
<?php
							endif;
							if(have_ruleint_access("l2tp")):
?>
								<option value="l2tp"   <?php if ($pconfig['src'] == "l2tp") { echo "selected=\"selected\""; } ?>><?=gettext("L2TP clients"); ?></option>
<?php
							endif;
							foreach ($ifdisp as $ifent => $ifdesc):
								if(have_ruleint_access($ifent)):
?>
									<option value="<?=$ifent;?>" <?php if ($pconfig['src'] == $ifent) { echo "selected=\"selected\""; } ?>>
										<?=htmlspecialchars($ifdesc);?> <?=gettext("net"); ?>
									</option>
									<option value="<?=$ifent;?>ip"<?php if ($pconfig['src'] ==  $ifent . "ip") { echo "selected=\"selected\""; } ?>>
										<?=$ifdesc?> <?=gettext("address");?>
									</option>
<?php
								endif;
							endforeach;
?>
							</select>
						</td>
					</tr>
					<tr>
						<td><?=gettext("Address:"); ?>&nbsp;&nbsp;</td>
						<td>
							<input name="src" type="text" class="formfld" id="src" size="20" value="<?php if (!is_specialnet($pconfig['src'])) echo htmlspecialchars($pconfig['src']);?>" /> /
							<select name="srcmask" class="formselect" id="srcmask">
<?php
							for ($i = 31; $i > 0; $i--):
?>
                                                	        <option value="<?=$i;?>" <?php if ($i == $pconfig['srcmask']) echo "selected=\"selected\""; ?>><?=$i;?></option>
<?php
							endfor;
?>
							</select>
						</td>
					</tr>
				</table>
				<br />
				<span class="vexpl"><?=gettext("Enter the internal (LAN) subnet for the 1:1 mapping. The subnet size specified for the internal subnet will be applied to the external subnet."); ?></span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Destination"); ?></td>
			<td width="78%" class="vtable">
				<input name="dstnot" type="checkbox" id="dstnot" value="yes" <?php if ($pconfig['dstnot']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("not"); ?></strong>
				<br />
				<?=gettext("Use this option to invert the sense of the match."); ?>
				<br />
				<br />
				<table border="0" cellspacing="0" cellpadding="0" summary="destination">
					<tr>
						<td><?=gettext("Type:"); ?>&nbsp;&nbsp;</td>
						<td>
							<select name="dsttype" class="formselect" onchange="typesel_change()">
<?php
							$sel = is_specialnet($pconfig['dst']); ?>
								<option value="any" <?php if (empty($pconfig['dst']) || $pconfig['dst'] == "any") { echo "selected=\"selected\""; } ?>><?=gettext("any"); ?></option>
								<option value="single" <?php if (($pconfig['dstmask'] == 32) && !$sel) { echo "selected=\"selected\""; $sel = 1; } ?>>
									<?=gettext("Single host or alias"); ?>
								</option>
								<option value="network" <?php if (!$sel && !empty($pconfig['dst'])) echo "selected=\"selected\""; ?>>
									<?=gettext("Network"); ?>
								</option>
<?php
							if(have_ruleint_access("pptp")):
?>
								<option value="pptp" <?php if ($pconfig['dst'] == "pptp") { echo "selected=\"selected\""; } ?>>
									<?=gettext("PPTP clients"); ?>
								</option>
<?php
							endif;
							if(have_ruleint_access("pppoe")):
?>
								<option value="pppoe" <?php if ($pconfig['dst'] == "pppoe") { echo "selected=\"selected\""; } ?>>
									<?=gettext("PPPoE clients"); ?>
								</option>
<?php
							endif;
							if(have_ruleint_access("l2tp")):
?>
								<option value="l2tp" <?php if ($pconfig['dst'] == "l2tp") { echo "selected=\"selected\""; } ?>>
									<?=gettext("L2TP clients"); ?>
								</option>
<?php
							endif;

							foreach ($ifdisp as $if => $ifdesc):
								if(have_ruleint_access($if)):
?>
									<option value="<?=$if;?>" <?php if ($pconfig['dst'] == $if) { echo "selected=\"selected\""; } ?>><?=htmlspecialchars($ifdesc);?>
										<?=gettext("net"); ?>
									</option>
									<option value="<?=$if;?>ip"<?php if ($pconfig['dst'] == $if . "ip") { echo "selected=\"selected\""; } ?>>
										<?=$ifdesc;?> <?=gettext("address");?>
									</option>
<?php
								endif;
							endforeach;
?>
							</select>
						</td>
					</tr>
					<tr>
						<td><?=gettext("Address:"); ?>&nbsp;&nbsp;</td>
						<td>
							<input name="dst" type="text" autocomplete="off" class="formfldalias" id="dst" size="20" value="<?php if (!is_specialnet($pconfig['dst'])) echo htmlspecialchars($pconfig['dst']);?>" />
							/
							<select name="dstmask" class="formselect" id="dstmask">
<?php
							for ($i = 31; $i > 0; $i--):
?>
								<option value="<?=$i;?>" <?php if ($i == $pconfig['dstmask']) echo "selected=\"selected\""; ?>><?=$i;?></option>
<?php
							endfor;
?>
							</select>
						</td>
					</tr>
				</table>
				<br />
				<span class="vexpl">
					<?=gettext("The 1:1 mapping will only be used for connections to or from the specified destination."); ?><br />
					<?=gettext("Hint: this is usually 'any'."); ?>
				</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
			<td width="78%" class="vtable">
				<input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" />
				<br />
				<span class="vexpl">
					<?=gettext("You may enter a description here for your reference (not parsed)."); ?>
				</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("NAT reflection"); ?></td>
			<td width="78%" class="vtable">
				<select name="natreflection" class="formselect">
					<option value="default" <?php if ($pconfig['natreflection'] != "enable" && $pconfig['natreflection'] != "disable") echo "selected=\"selected\""; ?>>
						<?=gettext("use system default"); ?>
					</option>
					<option value="enable" <?php if ($pconfig['natreflection'] == "enable") echo "selected=\"selected\""; ?>>
						<?=gettext("enable"); ?>
					</option>
					<option value="disable" <?php if ($pconfig['natreflection'] == "disable") echo "selected=\"selected\""; ?>>
						<?=gettext("disable"); ?>
					</option>
				</select>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" />
				<input type="button" class="formbtn" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=$referer;?>'" />
				<?php if (isset($id) && $a_1to1[$id]): ?>
				<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
				<?php endif; ?>
			</td>
		</tr>
	</table>
</form>
<script type="text/javascript">
//<![CDATA[
	typesel_change();
//]]>
</script>
<script type="text/javascript">
//<![CDATA[
	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "openvpn", "urltable"))) ?>;
	var oTextbox1 = new AutoSuggestControl(document.getElementById("dst"), new StateSuggestions(addressarray));
//]]>
</script>
<?php include("fend.inc"); ?>
</body>
</html>
