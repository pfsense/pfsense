<?php 
/* $Id$ */
/*
	firewall_nat_npt_edit.php
	part of pfSense (https://www.pfsense.org)
	
	Copyright (C) 2011 Seth Mos <seth.mos@dds.nl>.
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
##|*IDENT=page-firewall-nat-npt-edit
##|*NAME=Firewall: NAT: NPt: Edit page
##|*DESCR=Allow access to the 'Firewall: NAT: NPt: Edit' page.
##|*MATCH=firewall_nat_npt_edit.php*
##|-PRIV

function natnptcmp($a, $b) {
	return ipcmp($a['external'], $b['external']);
}

function nat_npt_rules_sort() {
        global $g, $config;

        if (!is_array($config['nat']['npt']))
                return;


        usort($config['nat']['npt'], "natnptcmp");
}

require("guiconfig.inc");
require_once("interfaces.inc");
require_once("filter.inc");
require("shaper.inc");

$ifdisp = get_configured_interface_with_descr();
foreach ($ifdisp as $kif => $kdescr) {
        $specialsrcdst[] = "{$kif}";
        $specialsrcdst[] = "{$kif}ip";
}

if (!is_array($config['nat']['npt'])) {
	$config['nat']['npt'] = array();
}
$a_npt = &$config['nat']['npt'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_npt[$id]) {
	$pconfig['disabled'] = isset($a_npt[$id]['disabled']);

	address_to_pconfig($a_npt[$id]['source'], $pconfig['src'],
                $pconfig['srcmask'], $pconfig['srcnot'],
		$pconfig['srcbeginport'], $pconfig['srcendport']);

        address_to_pconfig($a_npt[$id]['destination'], $pconfig['dst'],
                $pconfig['dstmask'], $pconfig['dstnot'],
		$pconfig['dstbeginport'], $pconfig['dstendport']);

	$pconfig['interface'] = $a_npt[$id]['interface'];
	if (!$pconfig['interface'])
		$pconfig['interface'] = "wan";

	$pconfig['external'] = $a_npt[$id]['external'];
	$pconfig['descr'] = $a_npt[$id]['descr'];
} else
	$pconfig['interface'] = "wan";


if ($_POST) {
	
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "interface");
	$reqdfieldsn = array(gettext("Interface"));
        $reqdfields[] = "src";
        $reqdfieldsn[] = gettext("Source prefix");
        $reqdfields[] = "dst";
        $reqdfieldsn[] = gettext("Destination prefix");
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (!$input_errors) {
		$natent = array();

		$natent['disabled'] = isset($_POST['disabled']) ? true:false;
		$natent['descr'] = $_POST['descr'];
		$natent['interface'] = $_POST['interface'];

	if ($_POST['src'])
		$_POST['src'] = trim($_POST['src']);
	if ($_POST['dst'])
		$_POST['dst'] = trim($_POST['dst']);

		pconfig_to_address($natent['source'], $_POST['src'],
                        $_POST['srcmask'], $_POST['srcnot']);

                pconfig_to_address($natent['destination'], $_POST['dst'],
                        $_POST['dstmask'], $_POST['dstnot']);

		if (isset($id) && $a_npt[$id])
			$a_npt[$id] = $natent;
		else
			$a_npt[] = $natent;
		nat_npt_rules_sort();

		if (write_config())
			mark_subsystem_dirty('natconf');

		header("Location: firewall_nat_npt.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"),gettext("NAT"),gettext("NPt"),gettext("Edit"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script type="text/javascript" src="/javascript/suggestions.js"></script>
<script type="text/javascript" src="/javascript/autosuggest.js"></script>

<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="firewall_nat_npt_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0" summary="firewall nat npt edit">
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Edit NAT NPt entry"); ?></td>
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
					<option value="<?=$iface;?>" <?php if ($iface == $pconfig['interface']) echo " selected=\"selected\""; ?>>
					<?=htmlspecialchars($ifacename);?>
					</option>
					<?php endforeach; ?>
				</select><br/>
			  <span class="vexpl"><?=gettext("Choose which interface this rule applies to"); ?>.<br/>
			  <?=gettext("Hint: in most cases, you'll want to use WAN here"); ?>.</span></td>
		</tr>
		<tr>
                        <td width="22%" valign="top" class="vncellreq"><?=gettext("Internal IPv6 Prefix"); ?></td>
                        <td width="78%" class="vtable">
                                <input name="srcnot" type="checkbox" id="srcnot" value="yes" <?php if ($pconfig['srcnot']) echo "checked=\"checked\""; ?> />
                                <strong><?=gettext("not"); ?></strong>
                                <br />
                                <?=gettext("Use this option to invert the sense of the match."); ?>
                                <br />
                                <br />
                                <table border="0" cellspacing="0" cellpadding="0" summary="internal">
                                        <tr>
                                                <td><?=gettext("Address:"); ?>&nbsp;&nbsp;</td>
                                                <td>
                                                        <input name="src" type="text" class="formfldalias" id="src" size="20" value="<?php if (!is_specialnet($pconfig['src'])) echo htmlspecialchars($pconfig['src']);?>" /> /
                                                        <select name="srcmask" class="formselect" id="srcmask">
<?php                                           for ($i = 128; $i > 0; $i--): ?>
                                                        <option value="<?=$i;?>" <?php if ($i == $pconfig['srcmask']) echo " selected=\"selected\""; ?>><?=$i;?></option>
<?php                                           endfor; ?>
                                                        </select>
                                                </td>
                                        </tr>
                                </table>
			<br/>
                     <span class="vexpl"><?=gettext("Enter the internal (LAN) ULA IPv6 Prefix for the Network Prefix translation. The prefix size specified for the internal IPv6 prefix will be applied to the 
external prefix."); 
?></span>
			</td>
                </tr>
		<tr>
                        <td width="22%" valign="top" class="vncellreq"><?=gettext("Destination IPv6 Prefix"); ?></td>
                        <td width="78%" class="vtable">
                                <input name="dstnot" type="checkbox" id="dstnot" value="yes" <?php if ($pconfig['dstnot']) echo "checked=\"checked\""; ?> />
                                <strong><?=gettext("not"); ?></strong>
                                        <br />
                                <?=gettext("Use this option to invert the sense of the match."); ?>
                                        <br />
                                        <br />
                                <table border="0" cellspacing="0" cellpadding="0" summary="destination">
                                        <tr>
                                                <td><?=gettext("Address:"); ?>&nbsp;&nbsp;</td>
                                                <td>
                                                        <input name="dst" type="text" class="formfldalias" id="dst" size="20" value="<?php if (!is_specialnet($pconfig['dst'])) echo htmlspecialchars($pconfig['dst']);?>" />
                                                        /
                                                        <select name="dstmask" class="formselect" id="dstmask">
<?php
                                                        for ($i = 128; $i > 0; $i--): ?>
                                                                <option value="<?=$i;?>" <?php if ($i == $pconfig['dstmask']) echo " selected=\"selected\""; ?>><?=$i;?></option>
<?php                                           endfor; ?>
                                                        </select>
                                                </td>
                                        </tr>
                                </table>
			<br/>
                     <span class="vexpl"><?=gettext("Enter the Global Unicast routable IPv6 prefix here"); ?><br/></span>
                     </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
                  <td width="78%" class="vtable"> 
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" />
                    <br/> <span class="vexpl"><?=gettext("You may enter a description here " .
                    "for your reference (not parsed)."); ?></span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" /> <input type="button" class="formbtn" value="<?=gettext("Cancel"); ?>" onclick="history.back()" />
                    <?php if (isset($id) && $a_npt[$id]): ?>
                    <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
