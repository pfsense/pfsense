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

function nat1to1cmp($a, $b) {
	return ipcmp($a['external'], $b['external']);
}

function nat_1to1_rules_sort() {
        global $g, $config;

        if (!is_array($config['nat']['onetoone']))
                return;


        usort($config['nat']['onetoone'], "nat1to1cmp");
}

require("guiconfig.inc");
require("filter.inc");
require("shaper.inc");

if (!is_array($config['nat']['onetoone'])) {
	$config['nat']['onetoone'] = array();
}
$a_1to1 = &$config['nat']['onetoone'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_1to1[$id]) {
	$pconfig['external'] = $a_1to1[$id]['external'];
	$pconfig['internal'] = $a_1to1[$id]['internal'];
	$pconfig['interface'] = $a_1to1[$id]['interface'];
	if (!$pconfig['interface'])
		$pconfig['interface'] = "wan";
	if (!$a_1to1[$id]['subnet'])
		$pconfig['subnet'] = 32;
	else
		$pconfig['subnet'] = $a_1to1[$id]['subnet'];
	$pconfig['descr'] = $a_1to1[$id]['descr'];
	$pconfig['natreflection'] = $a_1to1[$id]['natreflection'];
} else {
    $pconfig['subnet'] = 32;
	$pconfig['interface'] = "wan";
}

if ($_POST) {
	
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "interface external internal");
	$reqdfieldsn = array(gettext("Interface"),gettext("External subnet"),gettext("Internal subnet"));
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (($_POST['external'] && !is_ipaddr($_POST['external']))) {
		$input_errors[] = gettext("A valid external subnet must be specified.");
	}
	if (($_POST['internal'] && !is_ipaddr($_POST['internal']))) {
		$input_errors[] = gettext("A valid internal subnet must be specified.");
	}

	/* check for overlaps with other 1:1 */
	foreach ($a_1to1 as $natent) {
		if (isset($id) && ($a_1to1[$id]) && ($a_1to1[$id] === $natent))
			continue;
		
		if (check_subnets_overlap($_POST['external'], $_POST['subnet'], $natent['external'], $natent['subnet'])) {
			//$input_errors[] = "Another 1:1 rule overlaps with the specified external subnet.";
			//break;
		} else if (check_subnets_overlap($_POST['internal'], $_POST['subnet'], $natent['internal'], $natent['subnet'])) {
			//$input_errors[] = "Another 1:1 rule overlaps with the specified internal subnet.";
			//break;
		}
	}

	if (!$input_errors) {
		$natent = array();

		$natent['external'] = $_POST['external'];
		$natent['internal'] = $_POST['internal'];
		$natent['subnet'] = $_POST['subnet'];
		$natent['descr'] = $_POST['descr'];
		$natent['interface'] = $_POST['interface'];

		if ($_POST['natreflection'] == "enable" || $_POST['natreflection'] == "disable")
			$natent['natreflection'] = $_POST['natreflection'];
		else
			unset($natent['natreflection']);

		if (isset($id) && $a_1to1[$id])
			$a_1to1[$id] = $natent;
		else
			$a_1to1[] = $natent;
		nat_1to1_rules_sort();

		mark_subsystem_dirty('natconf');

		write_config();

		header("Location: firewall_nat_1to1.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"),gettext("NAT"),gettext("1:1"),gettext("Edit"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="firewall_nat_1to1_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Edit NAT 1:1 entry"); ?></td>
				</tr>	
				<tr>
				  <td width="22%" valign="top" class="vncellreq">Interface</td>
				  <td width="78%" class="vtable">
					<select name="interface" class="formselect">
						<?php
						$iflist = get_configured_interface_with_descr();
						foreach ($iflist as $if => $ifdesc)
							if(have_ruleint_access($if))
								$interfaces[$if] = $ifdesc;

						if ($config['l2tp']['mode'] == "server")
							if(have_ruleint_access("l2tp"))
								$interfaces['l2tp'] = "L2TP VPN";

						if ($config['pptpd']['mode'] == "server")
							if(have_ruleint_access("pptp"))
								$interfaces['pptp'] = "PPTP VPN";

						if ($config['pppoe']['mode'] == "server")
							if(have_ruleint_access("pppoe"))
								$interfaces['pppoe'] = "PPPoE VPN";

						/* add ipsec interfaces */
						if (isset($config['ipsec']['enable']) || isset($config['ipsec']['mobileclients']['enable']))
							if(have_ruleint_access("enc0"))
								$interfaces["enc0"] = "IPsec";

						/* add openvpn/tun interfaces */
						if  ($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"])
							$interfaces["openvpn"] = "OpenVPN";

						foreach ($interfaces as $iface => $ifacename): 
						?>
						<option value="<?=$iface;?>" <?php if ($iface == $pconfig['interface']) echo "selected"; ?>>
						<?=htmlspecialchars($ifacename);?>
						</option>
						<?php endforeach; ?>
					</select><br>
				  <span class="vexpl"><?=gettext("Choose which interface this rule applies to"); ?>.<br>
				  <?=gettext("Hint: in most cases, you'll want to use WAN here"); ?>.</span></td>
				</tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("External subnet"); ?></td>
                  <td width="78%" class="vtable"> 
                    <input name="external" type="text" class="formfld unknown" id="external" size="20" value="<?=htmlspecialchars($pconfig['external']);?>">
                    <select name="subnet" class="formselect" id="subnet" >
                      <?php for ($i = 32; $i >= 0; $i--): ?>
                      <option value="<?=$i;?>" <?php if ($i == $pconfig['subnet']) echo "selected"; ?>>
                      <?=$i;?>
                      </option>
                      <?php endfor; ?>
                    </select>
                    <br>
                    <span class="vexpl"><?=gettext("Enter the external (WAN) subnet for the 1:1 mapping. You may map single IP addresses by specifying a /32 subnet."); ?></span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Internal subnet"); ?></td>
                  <td width="78%" class="vtable"> 
                    <input name="internal" type="text" class="formfld unknown" id="internal" size="20" value="<?=htmlspecialchars($pconfig['internal']);?>"> 
                    <br>
                     <span class="vexpl"><?=gettext("Enter the internal (LAN) subnet for the 1:1 mapping. The subnet size specified for the external subnet also applies to the internal subnet (they  have to be the same)."); ?></span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
                  <td width="78%" class="vtable"> 
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>"> 
                    <br> <span class="vexpl"><?=gettext("You may enter a description here " .
                    "for your reference (not parsed)."); ?></span></td>
                </tr>
				<tr>
					<td width="22%" valign="top" class="vncell">NAT reflection</td>
					<td width="78%" class="vtable">
						<select name="natreflection" class="formselect">
						<option value="default" <?php if ($pconfig['natreflection'] != "enable" && $pconfig['natreflection'] != "disable") echo "selected"; ?>>use system default</option>
						<option value="enable" <?php if ($pconfig['natreflection'] == "enable") echo "selected"; ?>>enable</option>
						<option value="disable" <?php if ($pconfig['natreflection'] == "disable") echo "selected"; ?>>disable</option>
						</select>
					</td>
				</tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="Save"> <input type="button" class="formbtn" value="<?=gettext("Cancel"); ?>" onclick="history.back()">
                    <?php if (isset($id) && $a_1to1[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>"> 
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
