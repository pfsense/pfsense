<?php
/* $Id$ */
/*
	firewall_nat_out_edit.php
	Copyright (C) 2004 Scott Ullrich
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
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
##|*IDENT=page-firewall-nat-outbound-edit
##|*NAME=Firewall: NAT: Outbound: Edit page
##|*DESCR=Allow access to the 'Firewall: NAT: Outbound: Edit' page.
##|*MATCH=firewall_nat_out_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("filter.inc");
require("shaper.inc");

if (!is_array($config['nat']['outbound']))
	$config['nat']['outbound'] = array();

if (!is_array($config['nat']['outbound']['rule'])) {
	$config['nat']['outbound']['rule'] = array();
}

$a_out = &$config['nat']['outbound']['rule'];

if (!is_array($config['aliases']['alias']))
	$config['aliases']['alias'] = array();
$a_aliases = &$config['aliases']['alias'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (is_numericint($_GET['after']))
	$after = $_GET['after'];
if (isset($_POST['after']) && is_numericint($_GET['after']))
	$after = $_POST['after'];

if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
        $id = $_GET['dup'];
        $after = $_GET['dup'];
}

if (isset($id) && $a_out[$id]) {
	if ( isset($a_out[$id]['created']) && is_array($a_out[$id]['created']) )
		$pconfig['created'] = $a_out[$id]['created'];

	if ( isset($a_out[$id]['updated']) && is_array($a_out[$id]['updated']) )
		$pconfig['updated'] = $a_out[$id]['updated'];

	$pconfig['protocol'] = $a_out[$id]['protocol'];
	list($pconfig['source'],$pconfig['source_subnet']) = explode('/', $a_out[$id]['source']['network']);
	if (!is_numeric($pconfig['source_subnet']))
		$pconfig['source_subnet'] = 32;
	$pconfig['sourceport'] = $a_out[$id]['sourceport'];
	address_to_pconfig($a_out[$id]['destination'], $pconfig['destination'],
		$pconfig['destination_subnet'], $pconfig['destination_not'],
		$none, $none);
	$pconfig['dstport'] = $a_out[$id]['dstport'];
	$pconfig['natport'] = $a_out[$id]['natport'];
	$pconfig['target'] = $a_out[$id]['target'];
	$pconfig['targetip'] = $a_out[$id]['targetip'];
	$pconfig['targetip_subnet'] = $a_out[$id]['targetip_subnet'];
	$pconfig['poolopts'] = $a_out[$id]['poolopts'];
	$pconfig['interface'] = $a_out[$id]['interface'];
	if (!$pconfig['interface']) {
		$pconfig['interface'] = "wan";
	}
	$pconfig['descr'] = $a_out[$id]['descr'];
	$pconfig['nonat'] = $a_out[$id]['nonat'];
	$pconfig['disabled'] = isset($a_out[$id]['disabled']);
	$pconfig['staticnatport'] = isset($a_out[$id]['staticnatport']);
	$pconfig['nosync'] = isset($a_out[$id]['nosync']);
} else {
	$pconfig['source_subnet'] = 24;
	$pconfig['destination'] = "any";
	$pconfig['destination_subnet'] = 24;
	$pconfig['interface'] = "wan";
}

if (isset($_GET['dup']) && is_numericint($_GET['dup']))
	unset($id);

if ($_POST) {
	if ($_POST['destination_type'] == "any") {
		$_POST['destination'] = "any";
		$_POST['destination_subnet'] = 24;
	}
	if ($_POST['source_type'] == "any") {
		$_POST['source'] = "any";
		$_POST['source_subnet'] = 24;
	}

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
	$reqdfields = explode(" ", "interface protocol source source_subnet destination destination_subnet");
	$reqdfieldsn = array(gettext("Interface"),gettext("Protocol"),gettext("Source"),gettext("Source bit count"),gettext("Destination"),gettext("Destination bit count"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	$protocol_uses_ports = in_array($_POST['protocol'], explode(" ", "any tcp udp tcp/udp"));

	if ($_POST['source'])
		$_POST['source'] = trim($_POST['source']);
	if ($_POST['destination'])
		$_POST['destination'] = trim($_POST['destination']);
	if ($_POST['targetip'])
		$_POST['targetip'] = trim($_POST['targetip']);
	if ($_POST['sourceport'])
		$_POST['sourceport'] = trim($_POST['sourceport']);
	if ($_POST['dstport'])
		$_POST['dstport'] = trim($_POST['dstport']);
	if ($_POST['natport'])
		$_POST['natport'] = trim($_POST['natport']);

	if($protocol_uses_ports && $_POST['sourceport'] <> "" && !is_portoralias($_POST['sourceport']))
		$input_errors[] = gettext("You must supply either a valid port or port alias for the source port entry.");

	if($protocol_uses_ports && $_POST['dstport'] <> "" && !is_portoralias($_POST['dstport']))
		$input_errors[] = gettext("You must supply either a valid port or port alias for the destination port entry.");

	if($protocol_uses_ports && $_POST['natport'] <> "" && !is_port($_POST['natport']) && !isset($_POST['nonat']))
		$input_errors[] = gettext("You must supply a valid port for the NAT port entry.");

	if ($_POST['source_type'] != "any") {
		if ($_POST['source'] && !is_ipaddroralias($_POST['source']) && $_POST['source'] <> "any") {
			$input_errors[] = gettext("A valid source must be specified.");
		}
	}
	if ($_POST['source_subnet'] && !is_numericint($_POST['source_subnet'])) {
		$input_errors[] = gettext("A valid source bit count must be specified.");
	}
	if ($_POST['destination_type'] != "any") {
		if ($_POST['destination'] && !is_ipaddroralias($_POST['destination'])) {
			$input_errors[] = gettext("A valid destination must be specified.");
		}
	}
	if ($_POST['destination_subnet'] && !is_numericint($_POST['destination_subnet'])) {
		$input_errors[] = gettext("A valid destination bit count must be specified.");
	}
	if ($_POST['destination_type'] == "any") {
		if ($_POST['destination_not']) {
			$input_errors[] = gettext("Negating destination address of \"any\" is invalid.");
		}
	}

	if ($_POST['target'] && !is_ipaddr($_POST['target']) && !is_subnet($_POST['target']) && !is_alias($_POST['target']) && !isset($_POST['nonat']) && !($_POST['target'] == "other-subnet")) {
		$input_errors[] = gettext("A valid target IP address must be specified.");
	}

	if ($_POST['target'] == "other-subnet") {
		if (!is_ipaddr($_POST['targetip'])) {
			$input_errors[] = gettext("A valid target IP must be specified when using the 'Other Subnet' type.");
		}
		if (!is_numericint($_POST['targetip_subnet'])) {
			$input_errors[] = gettext("A valid target bit count must be specified when using the 'Other Subnet' type.");
		}
	}

	/* Verify Pool Options */
	$poolopts = "";
	if ($_POST['poolopts']) {
		if (is_subnet($_POST['target']) || ($_POST['target'] == "other-subnet"))
			$poolopts = $_POST['poolopts'];
		elseif (is_alias($_POST['target'])) {
			if (substr($_POST['poolopts'], 0, 11) == "round-robin")
				$poolopts = $_POST['poolopts'];
			else
				$input_errors[] = gettext("Only Round Robin pool options may be chosen when selecting an alias.");
		}
	}

	/* if user has selected any as source, set it here */
	if($_POST['source_type'] == "any") {
		$osn = "any";
	} else if(is_alias($_POST['source'])) {
		$osn = $_POST['source'];
	} else {
		$osn = gen_subnet($_POST['source'], $_POST['source_subnet']) . "/" . $_POST['source_subnet'];
	}

	/* check for existing entries */
	if ($_POST['destination_type'] == "any") {
		$ext = "any";
	} else if(is_alias($_POST['destination'])) {
		$ext = $_POST['destination'];
	} else {
		$ext = gen_subnet($_POST['destination'], $_POST['destination_subnet']) . "/" . $_POST['destination_subnet'];
	}

	foreach ($a_out as $natent) {
		if (isset($id) && ($a_out[$id]) && ($a_out[$id] === $natent)) {
			continue;
		}

		if (!$natent['interface']) {
			$natent['interface'] == "wan";
		}
	}

	// Allow extending of the firewall edit page and include custom input validation 
	pfSense_handle_custom_code("/usr/local/pkg/firewall_aon/input_validation");

	if (!$input_errors) {
	        $natent = array();
		$natent['source']['network'] = $osn;
		$natent['sourceport'] = ($protocol_uses_ports) ? $_POST['sourceport'] : "";
		$natent['descr'] = $_POST['descr'];
		$natent['target'] = (!isset($_POST['nonat'])) ? $_POST['target'] : "";
		$natent['targetip'] = (!isset($_POST['nonat'])) ? $_POST['targetip'] : "";
		$natent['targetip_subnet'] = (!isset($_POST['nonat'])) ? $_POST['targetip_subnet'] : "";
		$natent['interface'] = $_POST['interface'];
		$natent['poolopts'] = $poolopts;

		/* static-port */
		if(isset($_POST['staticnatport']) && $protocol_uses_ports && !isset($_POST['nonat'])) {
			$natent['staticnatport'] = true;
		} else {
			unset($natent['staticnatport']);
		}
		
		if(isset($_POST['disabled'])) {
			$natent['disabled'] = true;
		} else {
			unset($natent['disabled']);
		}

		/* if user has selected not nat, set it here */
		if(isset($_POST['nonat'])) {
			$natent['nonat'] = true;
		} else {
			unset($natent['nonat']);
		}

		if ($_POST['protocol'] && $_POST['protocol'] != "any")
			$natent['protocol'] = $_POST['protocol'];
		else
			unset($natent['protocol']);

	        if ($ext == "any") {
			$natent['destination']['any'] = true;
		} else {
			$natent['destination']['address'] = $ext;
		}
		if($_POST['natport'] != "" && $protocol_uses_ports && !isset($_POST['nonat'])) {
	        	$natent['natport'] = $_POST['natport'];
		} else {
			unset($natent['natport']);
		}
		if($_POST['dstport'] != "" && $protocol_uses_ports) {
			$natent['dstport'] = $_POST['dstport'];
		} else {
			unset($natent['dstport']);
		}

		if($_POST['nosync'] == "yes") {
			$natent['nosync'] = true;
		} else {
			unset($natent['nosync']);
		}

		if (isset($_POST['destination_not']) && $ext != "any") {
			$natent['destination']['not'] = true;
		}

		if ( isset($a_out[$id]['created']) && is_array($a_out[$id]['created']) )
			$natent['created'] = $a_out[$id]['created'];

		$natent['updated'] = make_config_revision_entry();

		// Allow extending of the firewall edit page and include custom input validation 
		pfSense_handle_custom_code("/usr/local/pkg/firewall_aon/pre_write_config");

		if (isset($id) && $a_out[$id]) {
			$a_out[$id] = $natent;
		} else {
			$natent['created'] = make_config_revision_entry();
			if (is_numeric($after)) {
				array_splice($a_out, $after+1, 0, array($natent));
			} else {
				$a_out[] = $natent;
			}
		}

		if (write_config())
			mark_subsystem_dirty('natconf');
		header("Location: firewall_nat_out.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"),gettext("NAT"),gettext("Outbound"),gettext("Edit"));
$closehead = false;
include("head.inc");

?>

<script type="text/javascript" src="/javascript/suggestions.js"></script>
<script type="text/javascript" src="/javascript/autosuggest.js"></script>
<script type="text/javascript">
//<![CDATA[
var portsenabled = 1;
function staticportchange() {
	if(document.iform.staticnatport.checked) {
		document.iform.natport.value = "";
		document.iform.natport.disabled = 1;
	} else {
		document.iform.natport.disabled = 0;
	}
}
function typesel_change() {
	switch (document.iform.destination_type.selectedIndex) {
	case 1: // network
		document.iform.destination.disabled = 0;
		document.iform.destination_subnet.disabled = 0;
		break;
	default:
		document.iform.destination.value = "";
		document.iform.destination.disabled = 1;
		document.iform.destination_subnet.value = "24";
		document.iform.destination_subnet.disabled = 1;
		break;
	}
}
function sourcesel_change() {
	switch (document.iform.source_type.selectedIndex) {
	case 1: // network
		document.iform.source.disabled = 0;
		document.iform.source_subnet.disabled = 0;
		break;
	default:
		document.iform.source.value = "";
		document.iform.source.disabled = 1;
		document.iform.source_subnet.value = "24";
		document.iform.source_subnet.disabled = 1;
		break;
	}
}
function nonat_change() {
	if (document.iform.nonat.checked) {
		document.getElementById("transtable").style.display = 'none';
	} else {
		document.getElementById("transtable").style.display = '';
	}
}
function proto_change() {
	if (document.iform.protocol.selectedIndex >= 0 && document.iform.protocol.selectedIndex <= 3) {
		portsenabled = 1;
	} else {
		portsenabled = 0;
	}

	if (portsenabled) {
		document.getElementById("sport_tr").style.display = '';
		document.getElementById("dport_tr").style.display = '';
		document.getElementById("tport_tr").style.display = '';
		document.getElementById("tporttext_tr").style.display = '';
		document.getElementById("tportstatic_tr").style.display = '';
	} else {
		document.getElementById("sport_tr").style.display = 'none';
		document.getElementById("dport_tr").style.display = 'none';
		document.getElementById("tport_tr").style.display = 'none';
		document.getElementById("tporttext_tr").style.display = 'none';
		document.getElementById("tportstatic_tr").style.display = 'none';
	}
}
function poolopts_change() {
	if (jQuery('#target option:selected').text().substring(0,4) == "Host") {
		jQuery('#poolopts_tr').css('display','');
		jQuery('#target_network').css('display','none');
	} else if (jQuery('#target option:selected').text().substring(0,6) == "Subnet") {
		jQuery('#poolopts_tr').css('display','');
		jQuery('#target_network').css('display','none');
	} else if (jQuery('#target option:selected').text().substring(0,5) == "Other") {
		jQuery('#poolopts_tr').css('display','');
		jQuery('#target_network').css('display','');
	} else {
		jQuery('#poolopts').prop('selectedIndex',0);
		jQuery('#poolopts_tr').css('display','none');
		jQuery('#target_network').css('display','none');
		jQuery('#targetip').val('');
		jQuery('#targetip_subnet').val('0');
	}
}
//]]>
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<form action="firewall_nat_out_edit.php" method="post" name="iform" id="iform">
	<table width="100%" border="0" cellpadding="6" cellspacing="1" summary="firewall nat outbound edit">
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("Edit Advanced Outbound NAT entry");?></td>
		</tr>
<?php
		// Allow extending of the firewall edit page and include custom input validation 
		pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/htmlphpearly");
?>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Disabled");?></td>
			<td width="78%" class="vtable">
				<input name="disabled" type="checkbox" id="disabled" value="yes" <?php if ($pconfig['disabled']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("Disable this rule");?></strong><br />
				<span class="vexpl"><?=gettext("Set this option to disable this rule without removing it from the list.");?></span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Do not NAT");?></td>
			<td width="78%" class="vtable">
				<input type="checkbox" name="nonat" id="nonat" onclick="nonat_change();" <?php if(isset($pconfig['nonat'])) echo " checked=\"checked\""; ?> />
				<span class="vexpl"><?=gettext("Enabling this option will disable NAT for traffic matching this rule and stop processing Outbound NAT rules.");?>
				<br /><?=gettext("Hint: in most cases, you won't use this option.");?></span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Interface");?></td>
			<td width="78%" class="vtable">
				<select name="interface" class="formselect">
<?php
					$iflist = get_configured_interface_with_descr(false, true);
					foreach ($iflist as $if => $ifdesc)
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
				<span class="vexpl"><?=gettext("Choose which interface this rule applies to.");?><br />
					<?=gettext("Hint: in most cases, you'll want to use WAN here.");?>
				</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Protocol");?></td>
			<td width="78%" class="vtable">
				<select name="protocol" class="formselect" onchange="proto_change();">
<?php
				$protocols = explode(" ", "any TCP UDP TCP/UDP ICMP ESP AH GRE IPV6 IGMP carp pfsync");
				foreach ($protocols as $proto):
?>
					<option value="<?=strtolower($proto);?>" <?php if (strtolower($proto) == $pconfig['protocol']) echo "selected=\"selected\""; ?>><?=htmlspecialchars($proto);?></option>
<?php
				endforeach;
?>
				</select><br />
				<span class="vexpl"><?=gettext("Choose which protocol this rule should match.");?><br />
<?php
					printf(gettext("Hint: in most cases, you should specify %s any %s here."),"<em>","</em>&nbsp;");
?>
				</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Source");?></td>
			<td width="78%" class="vtable">
				<table border="0" cellspacing="1" cellpadding="1" summary="source">
					<tr>
						<td><?=gettext("Type:");?>&nbsp;&nbsp;</td>
						<td>
							<select name="source_type" class="formselect" onchange="sourcesel_change()">
								<option value="any" <?php if ($pconfig['source'] == "any") echo "selected=\"selected\""; ?>><?=gettext("any");?></option>
								<option value="network" <?php if ($pconfig['source'] != "any") echo "selected=\"selected\""; ?>><?=gettext("Network");?></option>
							</select>
						</td>
					</tr>
					<tr>
						<td><?=gettext("Address:");?>&nbsp;&nbsp;</td>
						<td>
							<input name="source" type="text" autocomplete="off" class="formfldalias" id="source" size="20" value="<?=htmlspecialchars($pconfig['source']);?>" />/
							<select name="source_subnet" class="formfld" id="source_subnet">
<?php
							for ($i = 32; $i >= 0; $i--):
?>
								<option value="<?=$i;?>"<?php if ($i == $pconfig['source_subnet']) echo " selected=\"selected\""; ?>><?=$i;?></option>
<?php
							endfor;
?>
							</select>
						</td>
					</tr>
					<tr>
						<td>&nbsp;</td>
						<td>
							<span class="vexpl"><?=gettext("Enter the source network for the outbound NAT mapping.");?></span>
						</td>
					</tr>
					<tr name="sport_tr" id="sport_tr">
						<td><?=gettext("Source port:");?>&nbsp;&nbsp;</td>
						<td>
							<input name="sourceport" type="text" autocomplete="off" class="formfldalias" id="sourceport" size="5" value="<?=htmlspecialchars($pconfig['sourceport']);?>" />
							<?=gettext("(leave blank for any)");?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Destination");?></td>
			<td width="78%" class="vtable">
				<input name="destination_not" type="checkbox" id="destination_not" value="yes" <?php if ($pconfig['destination_not']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("not");?></strong><br />
				<?=gettext("Use this option to invert the sense of the match.");?><br />
				<br />
				<table border="0" cellspacing="1" cellpadding="1" summary="destination">
					<tr>
						<td><?=gettext("Type:");?>&nbsp;&nbsp;</td>
						<td>
							<select name="destination_type" class="formselect" onchange="typesel_change()">
								<option value="any"<?php if ($pconfig['destination'] == "any") echo " selected=\"selected\""; ?>>
									<?=gettext("any");?>
								</option>
								<option value="network"<?php if ($pconfig['destination'] != "any") echo " selected=\"selected\""; ?>>
									<?=gettext("Network");?>
								</option>
							</select>
						</td>
					</tr>
					<tr>
						<td><?=gettext("Address:");?>&nbsp;&nbsp;</td>
						<td>
							<input name="destination" type="text" autocomplete="off" class="formfldalias" id="destination" size="20" value="<?=htmlspecialchars($pconfig['destination']);?>" />/
							<select name="destination_subnet" class="formselect" id="destination_subnet">
<?php
							for ($i = 32; $i >= 0; $i--):
?>
								<option value="<?=$i;?>"<?php if ($i == $pconfig['destination_subnet']) echo " selected=\"selected\""; ?>><?=$i;?></option>
<?php
							endfor;
?>
							</select>
						</td>
					</tr>
					<tr>
						<td>&nbsp;</td>
						<td>
							<span class="vexpl"><?=gettext("Enter the destination network for the outbound NAT mapping.");?></span>
						</td>
					</tr>
					<tr name="dport_tr" id="dport_tr">
						<td><?=gettext("Destination port:");?>&nbsp;&nbsp;</td>
						<td>
							<input name="dstport" type="text" autocomplete="off" class="formfldalias" id="dstport" size="5" value="<?=htmlspecialchars($pconfig['dstport']);?>" />
							<?=gettext("(leave blank for any)");?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr name="transtable" id="transtable">
			<td width="22%" valign="top" class="vncell"><?=gettext("Translation");?></td>
			<td width="78%" class="vtable">
				<table border="0" cellspacing="1" cellpadding="1" summary="translation">
					<tr>
						<td><?=gettext("Address:");?>&nbsp;&nbsp;</td>
						<td>
							<select name="target" class="formselect" id="target" onchange="poolopts_change();">
								<option value=""<?php if (!$pconfig['target']) echo " selected=\"selected\""; ?>>
									<?=gettext("Interface address");?>
								</option>
<?php
								if (is_array($config['virtualip']['vip'])):
									foreach ($config['virtualip']['vip'] as $sn):
										if (isset($sn['noexpand']))
											continue;
										if ($sn['mode'] == "proxyarp" && $sn['type'] == "network"):
											$start = ip2long32(gen_subnet($sn['subnet'], $sn['subnet_bits']));
											$end = ip2long32(gen_subnet_max($sn['subnet'], $sn['subnet_bits']));
											$len = $end - $start;
?>
								<option value="<?=$sn['subnet'].'/'.$sn['subnet_bits'];?>" <?php if ($sn['subnet'].'/'.$sn['subnet_bits'] == $pconfig['target']) echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars("Subnet: {$sn['subnet']}/{$sn['subnet_bits']} ({$sn['descr']})");?>
								</option>
<?php
											for ($i = 0; $i <= $len; $i++):
												$snip = long2ip32($start+$i);
?>
								<option value="<?=$snip;?>" <?php if ($snip == $pconfig['target']) echo "selected"; ?>>
									<?=htmlspecialchars("{$snip} ({$sn['descr']})");?>
								</option>
<?php
											endfor;
?>
<?php
										else:
?>
								<option value="<?=$sn['subnet'];?>" <?php if ($sn['subnet'] == $pconfig['target']) echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars("{$sn['subnet']} ({$sn['descr']})");?>
								</option>
<?php
										endif;
									endforeach;
								endif;
								foreach ($a_aliases as $alias):
									if ($alias['type'] != "host")
										continue;
?>
								<option value="<?=$alias['name'];?>" <?php if ($alias['name'] == $pconfig['target']) echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars("Host Alias: {$alias['name']} ({$alias['descr']})");?>
								</option>
<?php
								endforeach;
?>
								<option value="other-subnet"<?php if($pconfig['target'] == "other-subnet") echo " selected=\"selected\""; ?>>
									<?=gettext("Other Subnet (Enter Below)");?>
								</option>
							</select>
						</td>
					</tr>

					<tr id="target_network">
						<td><?=gettext("Other Subnet:");?>&nbsp;&nbsp;</td>
						<td>
							<input name="targetip" type="text" class="formfld unknown" id="targetip" size="20" value="<?=htmlspecialchars($pconfig['targetip']);?>" />/
							<select name="targetip_subnet" class="formfld" id="targetip_subnet">
<?php
							for ($i = 32; $i >= 0; $i--):
?>
								<option value="<?=$i;?>"<?php if ($i == $pconfig['targetip_subnet']) echo " selected=\"selected\""; ?>><?=$i;?></option>
<?php
							endfor;
?>
							</select>
						</td>
					</tr>

					<tr>
						<td>&nbsp;</td>
						<td>
							<span class="vexpl"><?=gettext("Packets matching this rule will be mapped to the IP address given here.");?><br />
								<?=gettext("If you want this rule to apply to another IP address rather than the IP address of the interface chosen above, ".
								"select it here (you will need to define ");?>
								<a href="firewall_virtual_ip.php"><?=gettext("Virtual IP");?></a>
								<?=gettext("addresses on the interface first).");?>
							</span><br />
						</td>
					</tr>
					<tr id="poolopts_tr">
						<td valign="top">Pool Options</td>
						<td>
							<select name="poolopts" id="poolopts">
								<option value="" <?php if ($pconfig['poolopts'] == "") echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars("Default");?>
								</option>
								<option value="round-robin" <?php if ($pconfig['poolopts'] == "round-robin") echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars("Round Robin");?>
								</option>
								<option value="round-robin sticky-address" <?php if ($pconfig['poolopts'] == "round-robin sticky-address") echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars("Round Robin with Sticky Address");?>
								</option>
								<option value="random" <?php if ($pconfig['poolopts'] == "random") echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars("Random");?>
								</option>
								<option value="random sticky-address" <?php if ($pconfig['poolopts'] == "random sticky-address") echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars("Random with Sticky Address");?>
								</option>
								<option value="source-hash" <?php if ($pconfig['poolopts'] == "source-hash") echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars("Source Hash");?>
								</option>
								<option value="bitmask" <?php if ($pconfig['poolopts'] == "bitmask") echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars("Bitmask");?>
								</option>
							</select>
							<br />
							<span class="vexpl">
								<?=gettext("Only Round Robin types work with Host Aliases. Any type can be used with a Subnet.");?><br />
								* <?=gettext("Round Robin: Loops through the translation addresses.");?><br />
								* <?=gettext("Random: Selects an address from the translation address pool at random.");?><br />
								* <?=gettext("Source Hash: Uses a hash of the source address to determine the translation address, ensuring that the redirection address is always the same for a given source.");?><br />
								* <?=gettext("Bitmask: Applies the subnet mask and keeps the last portion identical; 10.0.1.50 -&gt; x.x.x.50.");?><br />
								* <?=gettext("Sticky Address: The Sticky Address option can be used with the Random and Round Robin pool types to ensure that a particular source address is always mapped to the same translation address.");?><br />
							</span><br />
						</td>
					</tr>
					<tr name="tport_tr" id="tport_tr">
						<td><?=gettext("Port:");?>&nbsp;&nbsp;</td>
						<td>
							<input name="natport" type="text" class="formfld unknown" id="natport" size="5" value="<?=htmlspecialchars($pconfig['natport']);?>" />
						</td>
					</tr>
					<tr name="tporttext_tr" id="tporttext_tr">
						<td>&nbsp;</td>
						<td>
							<span class="vexpl"><?=gettext("Enter the source port for the outbound NAT mapping.");?></span>
						</td>
					</tr>
					<tr name="tportstatic_tr" id="tportstatic_tr">
						<td><?=gettext("Static-port:");?>&nbsp;&nbsp;</td>
						<td><input onchange="staticportchange();" name="staticnatport" type="checkbox" class="formfld" id="staticnatport" size="5"<?php if($pconfig['staticnatport']) echo " checked=\"checked\"";?> /></td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("No XMLRPC Sync");?></td>
			<td width="78%" class="vtable">
				<input value="yes" name="nosync" type="checkbox" class="formfld" id="nosync"<?php if($pconfig['nosync']) echo " checked=\"checked\""; ?> /><br />
				<?=gettext("Hint: This prevents the rule on Master from automatically syncing to other CARP members. This does NOT prevent the rule from being overwritten on Slave.");?>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
			<td width="78%" class="vtable">
				<input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" />
				<br />
				<span class="vexpl"><?=gettext("You may enter a description here for your reference (not parsed).");?></span>
			</td>
		</tr>
<?php
	$has_created_time = (isset($a_out[$id]['created']) && is_array($a_out[$id]['created']));
	$has_updated_time = (isset($a_out[$id]['updated']) && is_array($a_out[$id]['updated']));
	if ($has_created_time || $has_updated_time):
?>
		<tr>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("Rule Information");?></td>
		</tr>
<?php
		if ($has_created_time):
?>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Created");?></td>
			<td width="78%" class="vtable">
				<?= date(gettext("n/j/y H:i:s"), $a_out[$id]['created']['time']) ?> <?= gettext("by") ?> <strong><?= $a_out[$id]['created']['username'] ?></strong>
			</td>
		</tr>
<?php
		endif;

		if ($has_updated_time):
?>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Updated");?></td>
			<td width="78%" class="vtable">
				<?= date(gettext("n/j/y H:i:s"), $a_out[$id]['updated']['time']) ?> <?= gettext("by") ?> <strong><?= $a_out[$id]['updated']['username'] ?></strong>
			</td>
		</tr>
<?php
		endif;
	endif;
	// Allow extending of the firewall edit page and include custom input validation 
	pfSense_handle_custom_code("/usr/local/pkg/firewall_aon/htmlphplate");
?>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" /> <input type="button" class="formbtn" value="<?=gettext("Cancel");?>" onclick="history.back()" />
<?php
			if (isset($id) && $a_out[$id]):
?>
				<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
<?php
			endif;
?>
				<input name="after" type="hidden" value="<?=htmlspecialchars($after);?>" />
			</td>
		</tr>
	</table>
</form>
<script type="text/javascript">
//<![CDATA[
	sourcesel_change();
	typesel_change();
	staticportchange();
	nonat_change();
	proto_change();
	poolopts_change();

	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "openvpn", "urltable"))) ?>;
	var customarray  = <?= json_encode(get_alias_list(array("port", "url_ports", "urltable_ports"))) ?>;

	var oTextbox1 = new AutoSuggestControl(document.getElementById("source"), new StateSuggestions(addressarray));
	var oTextbox2 = new AutoSuggestControl(document.getElementById("sourceport"), new StateSuggestions(customarray));
	var oTextbox3 = new AutoSuggestControl(document.getElementById("destination"), new StateSuggestions(addressarray));
	var oTextbox4 = new AutoSuggestControl(document.getElementById("dstport"), new StateSuggestions(customarray));
//]]>
</script>
<?php include("fend.inc"); ?>
</body>
</html>
