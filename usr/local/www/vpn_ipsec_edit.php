#!/usr/local/bin/php
<?php
/*
	vpn_ipsec_edit.php
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
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

$pgtitle = array("VPN", "IPsec", "Edit tunnel");
require("guiconfig.inc");

if (!is_array($config['ipsec']['tunnel'])) {
	$config['ipsec']['tunnel'] = array();
}
$a_ipsec = &$config['ipsec']['tunnel'];

$specialsrcdst = explode(" ", "lan");

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];
	
function is_specialnet($net) {
	global $specialsrcdst;
	
	if (in_array($net, $specialsrcdst))
		return true;
	else
		return false;
}

function address_to_pconfig($adr, &$padr, &$pmask) {
		
	if ($adr['network'])
		$padr = $adr['network'];
	else if ($adr['address']) {
		list($padr, $pmask) = explode("/", $adr['address']);
		if (is_null($pmask))
			$pmask = 32;
	}
}

function pconfig_to_address(&$adr, $padr, $pmask) {
	
	$adr = array();
	
	if (is_specialnet($padr))
		$adr['network'] = $padr;
	else {
		$adr['address'] = $padr;
		if ($pmask != 32)
			$adr['address'] .= "/" . $pmask;
	}
}

if (isset($id) && $a_ipsec[$id]) {
	$pconfig['disabled'] = isset($a_ipsec[$id]['disabled']);
	//$pconfig['auto'] = isset($a_ipsec[$id]['auto']);

	if (!isset($a_ipsec[$id]['local-subnet']))
		$pconfig['localnet'] = "lan";
	else
		address_to_pconfig($a_ipsec[$id]['local-subnet'], $pconfig['localnet'], $pconfig['localnetmask']);
		
	if ($a_ipsec[$id]['interface'])
		$pconfig['interface'] = $a_ipsec[$id]['interface'];
	else
		$pconfig['interface'] = "wan";
		
	list($pconfig['remotenet'],$pconfig['remotebits']) = explode("/", $a_ipsec[$id]['remote-subnet']);
	$pconfig['remotegw'] = $a_ipsec[$id]['remote-gateway'];
	$pconfig['p1mode'] = $a_ipsec[$id]['p1']['mode'];
	
	if (isset($a_ipsec[$id]['p1']['myident']['myaddress']))
		$pconfig['p1myidentt'] = 'myaddress';
	else if (isset($a_ipsec[$id]['p1']['myident']['address'])) {
		$pconfig['p1myidentt'] = 'address';
		$pconfig['p1myident'] = $a_ipsec[$id]['p1']['myident']['address'];
	} else if (isset($a_ipsec[$id]['p1']['myident']['fqdn'])) {
		$pconfig['p1myidentt'] = 'fqdn';
		$pconfig['p1myident'] = $a_ipsec[$id]['p1']['myident']['fqdn'];
	} else if (isset($a_ipsec[$id]['p1']['myident']['ufqdn'])) {
		$pconfig['p1myidentt'] = 'user_fqdn';
		$pconfig['p1myident'] = $a_ipsec[$id]['p1']['myident']['ufqdn'];
 	}
	
	$pconfig['p1ealgo'] = $a_ipsec[$id]['p1']['encryption-algorithm'];
	$pconfig['p1halgo'] = $a_ipsec[$id]['p1']['hash-algorithm'];
	$pconfig['p1dhgroup'] = $a_ipsec[$id]['p1']['dhgroup'];
	$pconfig['p1lifetime'] = $a_ipsec[$id]['p1']['lifetime'];
	$pconfig['p1authentication_method'] = $a_ipsec[$id]['p1']['authentication_method'];
	$pconfig['p1pskey'] = $a_ipsec[$id]['p1']['pre-shared-key'];
	$pconfig['p1cert'] = base64_decode($a_ipsec[$id]['p1']['cert']);
	$pconfig['p1peercert'] = base64_decode($a_ipsec[$id]['p1']['peercert']);
	$pconfig['p1privatekey'] = base64_decode($a_ipsec[$id]['p1']['private-key']);
	$pconfig['p2proto'] = $a_ipsec[$id]['p2']['protocol'];
	$pconfig['p2ealgos'] = $a_ipsec[$id]['p2']['encryption-algorithm-option'];
	$pconfig['p2halgos'] = $a_ipsec[$id]['p2']['hash-algorithm-option'];
	$pconfig['p2pfsgroup'] = $a_ipsec[$id]['p2']['pfsgroup'];
	$pconfig['p2lifetime'] = $a_ipsec[$id]['p2']['lifetime'];
	$pconfig['descr'] = $a_ipsec[$id]['descr'];
	
} else {
	/* defaults */
	$pconfig['interface'] = "wan";
	$pconfig['localnet'] = "lan";
	$pconfig['p1mode'] = "aggressive";
	$pconfig['p1myidentt'] = "myaddress";
	$pconfig['p1authentication_method'] = "pre_shared_key";
	$pconfig['p1ealgo'] = "3des";
	$pconfig['p1halgo'] = "sha1";
	$pconfig['p1dhgroup'] = "2";
	$pconfig['p2proto'] = "esp";
	$pconfig['p2ealgos'] = explode(",", "3des,blowfish,cast128,rijndael");
	$pconfig['p2halgos'] = explode(",", "hmac_sha1,hmac_md5");
	$pconfig['p2pfsgroup'] = "0";
	$pconfig['remotebits'] = 32;
}

if ($_POST) {
	if (is_specialnet($_POST['localnettype'])) {
		$_POST['localnet'] = $_POST['localnettype'];
		$_POST['localnetmask'] = 0;
	} else if ($_POST['localnettype'] == "single") {
		$_POST['localnetmask'] = 32;
	}
	
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['p1authentication_method'] == "pre_shared_key") {
		$reqdfields = explode(" ", "localnet remotenet remotebits remotegw p1pskey p2ealgos p2halgos");
		$reqdfieldsn = explode(",", "Local network,Remote network,Remote network bits,Remote gateway,Pre-Shared Key,P2 Encryption Algorithms,P2 Hash Algorithms");
	}
	else {
		$reqdfields = explode(" ", "localnet remotenet remotebits remotegw p2ealgos p2halgos");
		$reqdfieldsn = explode(",", "Local network,Remote network,Remote network bits,Remote gateway,P2 Encryption Algorithms,P2 Hash Algorithms");	
		if (!strstr($_POST['p1cert'], "BEGIN CERTIFICATE") || !strstr($_POST['p1cert'], "END CERTIFICATE"))
			$input_errors[] = "This certificate does not appear to be valid.";
		if (!strstr($_POST['p1privatekey'], "BEGIN RSA PRIVATE KEY") || !strstr($_POST['p1privatekey'], "END RSA PRIVATE KEY"))
			$input_errors[] = "This key does not appear to be valid.";	
		if ($_POST['p1peercert']!="" && (!strstr($_POST['p1peercert'], "BEGIN CERTIFICATE") || !strstr($_POST['p1peercert'], "END CERTIFICATE")))
			$input_errors[] = "This peer certificate does not appear to be valid.";	
	}
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (!is_specialnet($_POST['localnettype'])) {
		if (($_POST['localnet'] && !is_ipaddr($_POST['localnet']))) {
			$input_errors[] = "A valid local network IP address must be specified.";
		}
		if (($_POST['localnetmask'] && !is_numeric($_POST['localnetmask']))) {
			$input_errors[] = "A valid local network bit count must be specified.";
		}
	}
	if (($_POST['p1lifetime'] && !is_numeric($_POST['p1lifetime']))) {
		$input_errors[] = "The P1 lifetime must be an integer.";
	}
	if (($_POST['p2lifetime'] && !is_numeric($_POST['p2lifetime']))) {
		$input_errors[] = "The P2 lifetime must be an integer.";
	}
	if ($_POST['remotebits'] && (!is_numeric($_POST['remotebits']) || ($_POST['remotebits'] < 0) || ($_POST['remotebits'] > 32))) {
		$input_errors[] = "The remote network bits are invalid.";
	}
	if (($_POST['remotenet'] && !is_ipaddr($_POST['remotenet']))) {
		$input_errors[] = "A valid remote network address must be specified.";
	}
	if (($_POST['remotegw'] && !is_ipaddr($_POST['remotegw']))) {
		$input_errors[] = "A valid remote gateway address must be specified.";
	}
	if ((($_POST['p1myidentt'] == "address") && !is_ipaddr($_POST['p1myident']))) {
		$input_errors[] = "A valid IP address for 'My identifier' must be specified.";
	}
	if ((($_POST['p1myidentt'] == "fqdn") && !is_domain($_POST['p1myident']))) {
		$input_errors[] = "A valid domain name for 'My identifier' must be specified.";
	}
	if ($_POST['p1myidentt'] == "user_fqdn") {
		$ufqdn = explode("@",$_POST['p1myident']);
		if (!is_domain($ufqdn[1])) 
			$input_errors[] = "A valid User FQDN in the form of user@my.domain.com for 'My identifier' must be specified.";
	}
	
	if ($_POST['p1myidentt'] == "myaddress")
		$_POST['p1myident'] = "";

	if (!$input_errors) {
		$ipsecent['disabled'] = $_POST['disabled'] ? true : false;
		//$ipsecent['auto'] = $_POST['auto'] ? true : false;
		$ipsecent['interface'] = $pconfig['interface'];
		pconfig_to_address($ipsecent['local-subnet'], $_POST['localnet'], $_POST['localnetmask']);
		$ipsecent['remote-subnet'] = $_POST['remotenet'] . "/" . $_POST['remotebits'];
		$ipsecent['remote-gateway'] = $_POST['remotegw'];
		$ipsecent['p1']['mode'] = $_POST['p1mode'];
		
		$ipsecent['p1']['myident'] = array();
		switch ($_POST['p1myidentt']) {
			case 'myaddress':
				$ipsecent['p1']['myident']['myaddress'] = true;
				break;
			case 'address':
				$ipsecent['p1']['myident']['address'] = $_POST['p1myident'];
				break;
			case 'fqdn':
				$ipsecent['p1']['myident']['fqdn'] = $_POST['p1myident'];
				break;
			case 'user_fqdn':
				$ipsecent['p1']['myident']['ufqdn'] = $_POST['p1myident'];
				break;
		}
		
		$ipsecent['p1']['encryption-algorithm'] = $_POST['p1ealgo'];
		$ipsecent['p1']['hash-algorithm'] = $_POST['p1halgo'];
		$ipsecent['p1']['dhgroup'] = $_POST['p1dhgroup'];
		$ipsecent['p1']['lifetime'] = $_POST['p1lifetime'];
		$ipsecent['p1']['pre-shared-key'] = $_POST['p1pskey'];
		$ipsecent['p1']['private-key'] = base64_encode($_POST['p1privatekey']);
		$ipsecent['p1']['cert'] = base64_encode($_POST['p1cert']);
		$ipsecent['p1']['peercert'] = base64_encode($_POST['p1peercert']);
		$ipsecent['p1']['authentication_method'] = $_POST['p1authentication_method'];
		$ipsecent['p2']['protocol'] = $_POST['p2proto'];
		$ipsecent['p2']['encryption-algorithm-option'] = $_POST['p2ealgos'];
		$ipsecent['p2']['hash-algorithm-option'] = $_POST['p2halgos'];
		$ipsecent['p2']['pfsgroup'] = $_POST['p2pfsgroup'];
		$ipsecent['p2']['lifetime'] = $_POST['p2lifetime'];
		$ipsecent['descr'] = $_POST['descr'];
		
		if (isset($id) && $a_ipsec[$id])
			$a_ipsec[$id] = $ipsecent;
		else
			$a_ipsec[] = $ipsecent;
		
		write_config();
		touch($d_ipsecconfdirty_path);
		
		header("Location: vpn_ipsec.php");
		exit;
	}
}
?>
<?php include("fbegin.inc"); ?>
<script language="JavaScript">
<!--
function typesel_change() {
	switch (document.iform.localnettype.selectedIndex) {
		case 0:	/* single */
			document.iform.localnet.disabled = 0;
			document.iform.localnetmask.value = "";
			document.iform.localnetmask.disabled = 1;
			break;
		case 1:	/* network */
			document.iform.localnet.disabled = 0;
			document.iform.localnetmask.disabled = 0;
			break;
		default:
			document.iform.localnet.value = "";
			document.iform.localnet.disabled = 1;
			document.iform.localnetmask.value = "";
			document.iform.localnetmask.disabled = 1;
			break;
	}
}
function methodsel_change() {
	switch (document.iform.p1authentication_method.selectedIndex) {
		case 1:	/* rsa */
			document.iform.p1pskey.disabled = 1;
			document.iform.p1privatekey.disabled = 0;
			document.iform.p1cert.disabled = 0;
			document.iform.p1peercert.disabled = 0;
			break;
		default: /* pre-shared */
			document.iform.p1pskey.disabled = 0;
			document.iform.p1privatekey.disabled = 1;
			document.iform.p1cert.disabled = 1;
			document.iform.p1peercert.disabled = 1;
			break;
	}
}
//-->
</script>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="vpn_ipsec_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Mode</td>
                  <td width="78%" class="vtable"> Tunnel</td>
                </tr>
				<tr> 
                  <td width="22%" valign="top" class="vncellreq">Disabled</td>
                  <td width="78%" class="vtable"> 
                    <input name="disabled" type="checkbox" id="disabled" value="yes" <?php if ($pconfig['disabled']) echo "checked"; ?>>
                    <strong>Disable this tunnel</strong><br>
                    <span class="vexpl">Set this option to disable this tunnel without
					removing it from the list.</span></td>
                </tr>
				<!-- <tr> 
				  <td width="22%" valign="top" class="vncellreq">Auto-establish</td>
				  <td width="78%" class="vtable"> 
					<input name="auto" type="checkbox" id="auto" value="yes" <?php if ($pconfig['auto']) echo "checked"; ?>>
					<strong>Automatically establish this tunnel</strong><br>
					<span class="vexpl">Set this option to automatically re-establish this tunnel after reboots/reconfigures. If this is not set, the tunnel is established on demand.</span></td>
				</tr> -->
				<tr> 
                  <td width="22%" valign="top" class="vncellreq">Interface</td>
                  <td width="78%" class="vtable"><select name="interface" class="formfld">
                      <?php $interfaces = array('wan' => 'WAN', 'lan' => 'LAN');
					  for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
					  	$interfaces['opt' . $i] = $config['interfaces']['opt' . $i]['descr'];
					  }
					  foreach ($interfaces as $iface => $ifacename): ?>
                      <option value="<?=$iface;?>" <?php if ($iface == $pconfig['interface']) echo "selected"; ?>> 
                      <?=htmlspecialchars($ifacename);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br>
                    <span class="vexpl">Select the interface for the local endpoint of this tunnel.</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Local subnet</td>
                  <td width="78%" class="vtable"> 
                    <table border="0" cellspacing="0" cellpadding="0">
                      <tr> 
                        <td>Type:&nbsp;&nbsp;</td>
						<td></td>
                        <td><select name="localnettype" class="formfld" onChange="typesel_change()">
                            <?php $sel = is_specialnet($pconfig['localnet']); ?>
                            <option value="single" <?php if (($pconfig['localnetmask'] == 32) && !$sel) { echo "selected"; $sel = 1; } ?>> 
                            Single host</option>
                            <option value="network" <?php if (!$sel) echo "selected"; ?>> 
                            Network</option>
                            <option value="lan" <?php if ($pconfig['localnet'] == "lan") { echo "selected"; } ?>> 
                            LAN subnet</option>
                          </select></td>
                      </tr>
                      <tr> 
                        <td>Address:&nbsp;&nbsp;</td>
						<td><?=$mandfldhtmlspc;?></td>
                        <td><input name="localnet" type="text" class="formfld" id="localnet" size="20" value="<?php if (!is_specialnet($pconfig['localnet'])) echo htmlspecialchars($pconfig['localnet']);?>">
                          / 
                          <select name="localnetmask" class="formfld" id="localnetmask">
                            <?php for ($i = 31; $i >= 0; $i--): ?>
                            <option value="<?=$i;?>" <?php if ($i == $pconfig['localnetmask']) echo "selected"; ?>>
                            <?=$i;?>
                            </option>
                            <?php endfor; ?>
                          </select> </td>
                      </tr>
                    </table></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Remote subnet</td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="remotenet" type="text" class="formfld" id="remotenet" size="20" value="<?=$pconfig['remotenet'];?>">
                    / 
                    <select name="remotebits" class="formfld" id="remotebits">
                      <?php for ($i = 32; $i >= 0; $i--): ?>
                      <option value="<?=$i;?>" <?php if ($i == $pconfig['remotebits']) echo "selected"; ?>> 
                      <?=$i;?>
                      </option>
                      <?php endfor; ?>
                    </select></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Remote gateway</td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="remotegw" type="text" class="formfld" id="remotegw" size="20" value="<?=$pconfig['remotegw'];?>"> 
                    <br>
                    Enter the public IP address of the remote gateway</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">Description</td>
                  <td width="78%" class="vtable"> 
                    <input name="descr" type="text" class="formfld" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>"> 
                    <br> <span class="vexpl">You may enter a description here 
                    for your reference (not parsed).</span></td>
                </tr>
                <tr> 
                  <td colspan="2" class="list" height="12"></td>
                </tr>
                <tr> 
                  <td colspan="2" valign="top" class="listtopic">Phase 1 proposal 
                    (Authentication)</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Negotiation mode</td>
                  <td width="78%" class="vtable">
					<select name="p1mode" class="formfld">
                      <?php $modes = explode(" ", "main aggressive"); foreach ($modes as $mode): ?>
                      <option value="<?=$mode;?>" <?php if ($mode == $pconfig['p1mode']) echo "selected"; ?>> 
                      <?=htmlspecialchars($mode);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br> <span class="vexpl">Aggressive is faster, but 
                    less secure.</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">My identifier</td>
                  <td width="78%" class="vtable">
					<select name="p1myidentt" class="formfld">
                      <?php foreach ($my_identifier_list as $mode => $modename): ?>
                      <option value="<?=$mode;?>" <?php if ($mode == $pconfig['p1myidentt']) echo "selected"; ?>> 
                      <?=htmlspecialchars($modename);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <input name="p1myident" type="text" class="formfld" id="p1myident" size="30" value="<?=$pconfig['p1myident'];?>"> 
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Encryption algorithm</td>
                  <td width="78%" class="vtable">
					<select name="p1ealgo" class="formfld">
                      <?php foreach ($p1_ealgos as $algo => $algoname): ?>
                      <option value="<?=$algo;?>" <?php if ($algo == $pconfig['p1ealgo']) echo "selected"; ?>> 
                      <?=htmlspecialchars($algoname);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br> <span class="vexpl">Must match the setting 
                    chosen on the remote side. </span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Hash algorithm</td>
                  <td width="78%" class="vtable">
					<select name="p1halgo" class="formfld">
                      <?php foreach ($p1_halgos as $algo => $algoname): ?>
                      <option value="<?=$algo;?>" <?php if ($algo == $pconfig['p1halgo']) echo "selected"; ?>> 
                      <?=htmlspecialchars($algoname);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br> <span class="vexpl">Must match the setting 
                    chosen on the remote side. </span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">DH key group</td>
                  <td width="78%" class="vtable">
					<select name="p1dhgroup" class="formfld">
                      <?php $keygroups = explode(" ", "1 2 5"); foreach ($keygroups as $keygroup): ?>
                      <option value="<?=$keygroup;?>" <?php if ($keygroup == $pconfig['p1dhgroup']) echo "selected"; ?>> 
                      <?=htmlspecialchars($keygroup);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br> <span class="vexpl"><em>1 = 768 bit, 2 = 1024 
                    bit, 5 = 1536 bit</em><br>
                    Must match the setting chosen on the remote side. </span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">Lifetime</td>
                  <td width="78%" class="vtable"> 
                    <input name="p1lifetime" type="text" class="formfld" id="p1lifetime" size="20" value="<?=$pconfig['p1lifetime'];?>">
                    seconds</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Authentication method</td>
                  <td width="78%" class="vtable">
					<select name="p1authentication_method" class="formfld" onChange="methodsel_change()">
                      <?php foreach ($p1_authentication_methods as $method => $methodname): ?>
                      <option value="<?=$method;?>" <?php if ($method == $pconfig['p1authentication_method']) echo "selected"; ?>> 
                      <?=htmlspecialchars($methodname);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br> <span class="vexpl">Must match the setting 
                    chosen on the remote side.</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Pre-Shared Key</td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="p1pskey" type="text" class="formfld" id="p1pskey" size="40" value="<?=htmlspecialchars($pconfig['p1pskey']);?>"> 
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Certificate</td>
                  <td width="78%" class="vtable"> 
                    <textarea name="p1cert" cols="65" rows="7" id="p1cert" class="formpre"><?=htmlspecialchars($pconfig['p1cert']);?></textarea>
                    <br> 
                    Paste a certificate in X.509 PEM format here.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Key</td>
                  <td width="78%" class="vtable"> 
                    <textarea name="p1privatekey" cols="65" rows="7" id="p1privatekey" class="formpre"><?=htmlspecialchars($pconfig['p1privatekey']);?></textarea>
                    <br> 
                    Paste an RSA private key in PEM format here.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">Peer certificate</td>
                  <td width="78%" class="vtable"> 
                    <textarea name="p1peercert" cols="65" rows="7" id="p1peercert" class="formpre"><?=htmlspecialchars($pconfig['p1peercert']);?></textarea>
                    <br> 
                    Paste the peer X.509 certificate in PEM format here.<br>
                    Leave this blank if you want to use a CA certificate for identity validation.</td>
                </tr>
                <tr> 
                  <td colspan="2" class="list" height="12"></td>
                </tr>
                <tr> 
                  <td colspan="2" valign="top" class="listtopic">Phase 2 proposal 
                    (SA/Key Exchange)</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Protocol</td>
                  <td width="78%" class="vtable">
					<select name="p2proto" class="formfld">
                      <?php foreach ($p2_protos as $proto => $protoname): ?>
                      <option value="<?=$proto;?>" <?php if ($proto == $pconfig['p2proto']) echo "selected"; ?>> 
                      <?=htmlspecialchars($protoname);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br> <span class="vexpl">ESP is encryption, AH is 
                    authentication only </span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Encryption algorithms</td>
                  <td width="78%" class="vtable"> 
                    <?php foreach ($p2_ealgos as $algo => $algoname): ?>
                    <input type="checkbox" name="p2ealgos[]" value="<?=$algo;?>" <?php if (in_array($algo, $pconfig['p2ealgos'])) echo "checked"; ?>> 
                    <?=htmlspecialchars($algoname);?>
                    <br> 
                    <?php endforeach; ?>
                    <br>
                    Hint: use 3DES for best compatibility or if you have a hardware 
                    crypto accelerator card. Blowfish is usually the fastest in 
                    software encryption. </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Hash algorithms</td>
                  <td width="78%" class="vtable"> 
                    <?php foreach ($p2_halgos as $algo => $algoname): ?>
                    <input type="checkbox" name="p2halgos[]" value="<?=$algo;?>" <?php if (in_array($algo, $pconfig['p2halgos'])) echo "checked"; ?>> 
                    <?=htmlspecialchars($algoname);?>
                    <br> 
                    <?php endforeach; ?>
				  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">PFS key group</td>
                  <td width="78%" class="vtable">
					<select name="p2pfsgroup" class="formfld">
                      <?php foreach ($p2_pfskeygroups as $keygroup => $keygroupname): ?>
                      <option value="<?=$keygroup;?>" <?php if ($keygroup == $pconfig['p2pfsgroup']) echo "selected"; ?>> 
                      <?=htmlspecialchars($keygroupname);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br> <span class="vexpl"><em>1 = 768 bit, 2 = 1024 
                    bit, 5 = 1536 bit</em></span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">Lifetime</td>
                  <td width="78%" class="vtable"> 
                    <input name="p2lifetime" type="text" class="formfld" id="p2lifetime" size="20" value="<?=$pconfig['p2lifetime'];?>">
                    seconds</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="Save"> 
                    <?php if (isset($id) && $a_ipsec[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>"> 
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<script language="JavaScript">
<!--
typesel_change();
methodsel_change();
//-->
</script>
<?php include("fend.inc"); ?>
