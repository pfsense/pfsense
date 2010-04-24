<?php
/* $Id$ */
/*

    firewall_virtual_ip_edit.php
    part of pfSense (http://www.pfsense.com/)

    Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
    All rights reserved.

    Includes code from m0n0wall which is:
    Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
    All rights reserved.

    Includes code from pfSense which is:
    Copyright (C) 2004-2005 Scott Ullrich <geekgod@pfsense.com>.
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

require("guiconfig.inc");
if (!is_array($config['virtualip']['vip'])) {
        $config['virtualip']['vip'] = array();
}
$a_vip = &$config['virtualip']['vip'];

if (isset($_POST['id']))
	$id = $_POST['id'];
else
	$id = $_GET['id'];

function return_first_two_octets($ip) {
	$ip_split = split("\.", $ip);
	return $ip_split[0] . "." . $ip_split[1];
}

if (isset($id) && $a_vip[$id]) {
	$pconfig['mode'] = $a_vip[$id]['mode'];
	$pconfig['vhid'] = $a_vip[$id]['vhid'];
	$pconfig['advskew'] = $a_vip[$id]['advskew'];
	$pconfig['password'] = $a_vip[$id]['password'];
	$pconfig['range'] = $a_vip[$id]['range'];
	$pconfig['subnet'] = $a_vip[$id]['subnet'];
	$pconfig['subnet_bits'] = $a_vip[$id]['subnet_bits'];
	$pconfig['descr'] = $a_vip[$id]['descr'];
	$pconfig['type'] = $a_vip[$id]['type'];
	$pconfig['interface'] = $a_vip[$id]['interface'];
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "mode");
	$reqdfieldsn = explode(",", "Type");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (($_POST['subnet'] && !is_ipaddr($_POST['subnet'])))
		$input_errors[] = "A valid IP address must be specified.";

	if ($_POST['ipaddr'] == $config['interfaces']['wan']['ipaddr'])
		$input_errors[] = "The WAN IP address may not be used in a virtual entry.";

	if ($_POST['ipaddr'] == $config['interfaces']['lan']['ipaddr'])
		$input_errors[] = "The LAN IP address may not be used in a virtual entry.";

	 if($_POST['subnet_bits'] == "32" and $_POST['type'] == "carp")
	 	$input_errors[] = "The /32 subnet mask is invalid for CARP IP's.";

	/* check for overlaps with other virtual IP */
	foreach ($a_vip as $vipent) {
		if (isset($id) && ($a_vip[$id]) && ($a_vip[$id] === $vipent))
			continue;

		if (isset($_POST['subnet']) && $_POST['subnet'] == $vipent['subnet']) {
			$input_errors[] = "There is already a virtual IP entry for the specified IP address.";
			break;
		}
	}

	/* make sure new ip is within the subnet of a valid ip
	 * on one of our interfaces (wan, lan optX)
	 */
	if ($_POST['mode'] == "carp") {
		if(!$id) {
			/* verify against reusage of vhids */
			$idtracker=0;
			foreach($config['virtualip']['vip'] as $vip) {
				if($vip['vhid'] == $_POST['vhid'] and $idtracker <> $id)
					$input_errors[] = "VHID {$_POST['vhid']} is already in use.  Pick a unique number.";
				$idtracker++;
			}
		}
		if($_POST['password'] == "")
			$input_errors[] = "You must specify a CARP password that is shared between the two VHID members.";
		$can_post = true;
		$found = false;
		$subnet_ip = return_first_two_octets($_POST['subnet']);
		$iflist = array("lan", "wan");
		for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++)
			$iflist['opt' . $i] = 'opt' . $i;
		foreach($iflist as $if) {
			$ww_subnet_ip = $config['interfaces'][$if]['ipaddr'];
			$ww_subnet_bits = $config['interfaces'][$if]['subnet'];
			if (ip_in_subnet($_POST['subnet'], gen_subnet($ww_subnet_ip, $ww_subnet_bits) . "/" . $ww_subnet_bits))
				$found = true;
		}
		if($found == false) {
			$cannot_find = $_POST['subnet'] . "/" . $_POST['subnet_bits'] ;
			$can_post = false;
		}
		if($can_post == false)
			$input_errors[] = "Sorry, we could not locate an interface with a matching subnet for {$cannot_find}.  Please add an ip in this subnet on a real interface.";
	}

	if (!$input_errors) {
		$vipent = array();

		$vipent['mode'] = $_POST['mode'];
		$vipent['interface'] = $_POST['interface'];

		/* ProxyARP specific fields */
		if ($_POST['mode'] === "proxyarp") {
			if ($_POST['type'] == "range") {
				$vipent['range']['from'] = $_POST['range_from'];
				$vipent['range']['to'] = $_POST['range_to'];
			}
		}

		/* CARP specific fields */
		if ($_POST['mode'] === "carp") {
			$vipent['vhid'] = $_POST['vhid'];
			$vipent['advskew'] = $_POST['advskew'];
			$vipent['password'] = $_POST['password'];
		}

		/* Common fields */
		$vipent['descr'] = $_POST['descr'];
		if (isset($_POST['type']))
			$vipent['type'] = $_POST['type'];
		else
			$vipent['type'] = "single";

		if ($vipent['type'] == "single" || $vipent['type'] == "network") {
			if (!isset($_POST['subnet_bits'])) {
				$vipent['subnet_bits'] = "32";
			} else {
				$vipent['subnet_bits'] = $_POST['subnet_bits'];
			}
			$vipent['subnet'] = $_POST['subnet'];
		}

		if (isset($id) && $a_vip[$id]) {
			/* modify all virtual IP rules with this address */
			for ($i = 0; isset($config['nat']['rule'][$i]); $i++) {
				if ($config['nat']['rule'][$i]['external-address'] == $a_vip[$id]['subnet'])
					$config['nat']['rule'][$i]['external-address'] = $vipent['subnet'];
			}
			$a_vip[$id] = $vipent;
		} else
			$a_vip[] = $vipent;

		touch($d_vipconfdirty_path);

		write_config();

		header("Location: firewall_virtual_ip.php");
		exit;
	}
}

$pgtitle = "Firewall: Virtual IP Address: Edit";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<script language="JavaScript">
<!--
function get_radio_value(obj)
{
        for (i = 0; i < obj.length; i++) {
                if (obj[i].checked)
                        return obj[i].value;
        }
        return null;
}
function enable_change(enable_over) {
	var note = document.getElementById("typenote");
	var carpnote = document.createTextNode("This is the network's subnet mask. It does not specify a CIDR range.");
	var proxyarpnote = document.createTextNode("This is a CIDR block of proxy ARP addresses.");
        if ((get_radio_value(document.iform.mode) == "carp") || enable_over) {
                document.iform.vhid.disabled = 0;
                document.iform.password.disabled = 0;
                document.iform.advskew.disabled = 0;
                document.iform.type.disabled = 1;
                document.iform.subnet_bits.disabled = 0;
		if (note.firstChild == null) {
			note.appendChild(carpnote);
		} else {
			note.removeChild(note.firstChild);
			note.appendChild(carpnote);
		}
        } else {
                document.iform.vhid.disabled = 1;
                document.iform.password.disabled = 1;
                document.iform.advskew.disabled = 1;
                document.iform.type.disabled = 0;
                document.iform.subnet_bits.disabled = 1;
		if (note.firstChild == null) {
			note.appendChild(proxyarpnote);
		} else {
			note.removeChild(note.firstChild);
			note.appendChild(proxyarpnote);
		}
        }
	if (get_radio_value(document.iform.mode) == "other") {
                document.iform.type.disabled = 1;
		if (note.firstChild != null) {
			note.removeChild(note.firstChild);
		}
	}

}
function typesel_change() {
    switch (document.iform.type.selectedIndex) {
        case 0: // single
            document.iform.subnet.disabled = 0;
            if((get_radio_value(document.iform.mode) == "proxyarp")) document.iform.subnet_bits.disabled = 1;
            //document.iform.range_from.disabled = 1;
            //document.iform.range_to.disabled = 1;
            break;
        case 1: // network
            document.iform.subnet.disabled = 0;
            document.iform.subnet_bits.disabled = 0;
            //document.iform.range_from.disabled = 1;
            //document.iform.range_to.disabled = 1;
            break;
        case 2: // range
            document.iform.subnet.disabled = 1;
            document.iform.subnet_bits.disabled = 1;
            //document.iform.range_from.disabled = 0;
            //document.iform.range_to.disabled = 0;
            break;
    }
}
//-->
</script>

<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="firewall_virtual_ip_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
		  		  <td width="22%" valign="top" class="vncellreq">Type</td>
                  <td width="78%" class="vtable">
                    <input name="mode" type="radio" onclick="enable_change(false)" value="proxyarp"
					<?php if ($pconfig['mode'] == "proxyarp" || $pconfig['type'] != "carp") echo "checked";?>> Proxy ARP
					<input name="mode" type="radio" onclick="enable_change(false)" value="carp"
					<?php if ($pconfig['mode'] == "carp") echo "checked";?>> CARP
					<input name="mode" type="radio" onclick="enable_change(false)" value="other"
					<?php if ($pconfig['mode'] == "other") echo "checked";?>> Other
				  </td>
				</tr>
				<tr>
				  <td width="22%" valign="top" class="vncellreq">Interface</td>
				  <td width="78%" class="vtable">
					<select name="interface" class="formfld">
					<?php $interfaces = array('wan' => 'WAN', 'lan' => 'LAN');
					  for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
						$interfaces['opt' . $i] = $config['interfaces']['opt' . $i]['descr'];
					  }
					  foreach ($interfaces as $iface => $ifacename): ?>
						<option value="<?=$iface;?>" <?php if ($iface == $pconfig['interface']) echo "selected"; ?>>
						<?=htmlspecialchars($ifacename);?>
						</option>
					  <?php endforeach; ?>
					</select>
				  </td>
                </tr>
                <tr>
                  <td valign="top" class="vncellreq">IP Address(es)</td>
                  <td class="vtable">
                    <table border="0" cellspacing="0" cellpadding="0">
                      <tr>
                        <td>Type:&nbsp;&nbsp;</td>
                        <td><select name="type" class="formfld" onChange="typesel_change()">
                            <option value="single" <?php if ((!$pconfig['range'] && $pconfig['subnet_bits'] == 32) || (!isset($pconfig['ipaddr']))) echo "selected"; ?>>
                            Single address</option>
                            <option value="network" <?php if (!$pconfig['range'] && $pconfig['subnet_bits'] != 32 && isset($pconfig['ipaddr'])) echo "selected"; ?>>
                            Network</option>
                            <!-- XXX: Billm, don't let anyone choose this until NAT configuration screens are ready for it <option value="range" <?php if ($pconfig['range']) echo "selected"; ?>>
                            Range</option> -->
                          </select></td>
                      </tr>
                      <tr>
                        <td>Address:&nbsp;&nbsp;</td>
                        <td><input name="subnet" type="text" class="formfld" id="subnet" size="20" value="<?=htmlspecialchars($pconfig['subnet']);?>">
/
                          <select name="subnet_bits" class="formfld" id="select">
                            <?php for ($i = 32; $i >= 1; $i--): ?>
                            <option value="<?=$i;?>" <?php if (($i == $pconfig['subnet_bits']) || (!isset($pconfig['ipaddr']) && $i == 32)) echo "selected"; ?>>
                            <?=$i;?>
                      </option>
                            <?php endfor; ?>
                      </select> <i id="typenote"></i>
 						</td>
                      </tr>
		      <?php
		      /*
                        <tr>
                         <td>Range:&nbsp;&nbsp;</td>
                          <td><input name="range_from" type="text" class="formfld" id="range_from" size="20" value="<?=htmlspecialchars($pconfig['range']['from']);?>">
-
                          <input name="range_to" type="text" class="formfld" id="range_to" size="20" value="<?=htmlspecialchars($pconfig['range']['to']);?>">
                          </td>
			 </tr>
  		       */
			?>
                    </table>
                  </td>
                </tr>
				<tr valign="top">
				  <td width="22%" class="vncellreq">Virtual IP Password</td>
				  <td class="vtable"><input type='password'  name='password' value="<?=htmlspecialchars($pconfig['password']);?>">
					<br>Enter the VHID group password.
				  </td>
				</tr>
				<tr valign="top">
				  <td width="22%" class="vncellreq">VHID Group</td>
				  <td class="vtable"><select id='vhid' name='vhid'>
                            <?php for ($i = 1; $i <= 254; $i++): ?>
                            <option value="<?=$i;?>" <?php if ($i == $pconfig['vhid']) echo "selected"; ?>>
                            <?=$i;?>
                      </option>
                            <?php endfor; ?>
                      </select>
					<br>Enter the VHID group that the machines will share
				  </td>
				</tr>
				<tr valign="top">
				  <td width="22%" class="vncellreq">Advertising Frequency</td>
				  <td class="vtable"><select id='advskew' name='advskew'>
                            <?php for ($i = 0; $i <= 254; $i++): ?>
                            <option value="<?=$i;?>" <?php if ($i == $pconfig['advskew']) echo "selected"; ?>>
                            <?=$i;?>
                      </option>
                            <?php endfor; ?>
                      </select>
					<br>The frequency that this machine will advertise.  0 = master.   Anything above 0 designates a backup.
				  </td>
				</tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">Description</td>
                  <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
                    <br> <span class="vexpl">You may enter a description here for your reference (not parsed).</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="Save"> <input type="button" class="formbtn" value="Cancel" onclick="history.back()">
                    <?php if (isset($id) && $a_vip[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>">
                    <?php endif; ?>
                  </td>
                </tr>
				<tr>
				  <td colspan="4">
				      <p>
				      	<span class="vexpl">
				      		<span class="red">
				      			<strong>Note:<br></strong>
				      		</span>&nbsp;&nbsp;
				      		ProxyARP type IP addresses *DO NOT* work with the FTP Helper and addon packages such as squid.  Use a CARP type address in this case.
				      		<p>&nbsp;&nbsp;&nbsp;For more information on CARP and the above values, visit the OpenBSD <a href='http://www.openbsd.org/faq/pf/carp.html'>CARP faq</A>.
						</span>
					  </p>
				  </td>
				</tr>

              </table>
</form>
<script language="JavaScript">
<!--
enable_change(false);
typesel_change();
//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>
