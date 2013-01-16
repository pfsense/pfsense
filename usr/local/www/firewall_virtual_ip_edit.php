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
/*
	pfSense_BUILDER_BINARIES:	/sbin/ifconfig
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-firewall-virtualipaddress-edit
##|*NAME=Firewall: Virtual IP Address: Edit page
##|*DESCR=Allow access to the 'Firewall: Virtual IP Address: Edit' page.
##|*MATCH=firewall_virtual_ip_edit.php*
##|-PRIV

require("guiconfig.inc");
require("filter.inc");
require("shaper.inc");

if (!is_array($config['virtualip']['vip'])) {
        $config['virtualip']['vip'] = array();
}
$a_vip = &$config['virtualip']['vip'];

if (isset($_POST['id']))
	$id = $_POST['id'];
else
	$id = $_GET['id'];

function return_first_two_octets($ip) {
	$ip_split = explode(".", $ip);
	return $ip_split[0] . "." . $ip_split[1];
}

function find_last_used_vhid() {
	global $config, $g;
	$vhid = 0;
	foreach($config['virtualip']['vip'] as $vip) {
		if($vip['vhid'] > $vhid) 
			$vhid = $vip['vhid'];
	}
	return $vhid;
}

if (isset($id) && $a_vip[$id]) {
	$pconfig['mode'] = $a_vip[$id]['mode'];
	$pconfig['vhid'] = $a_vip[$id]['vhid'];
	$pconfig['advskew'] = $a_vip[$id]['advskew'];
	$pconfig['advbase'] = $a_vip[$id]['advbase'];
	$pconfig['password'] = $a_vip[$id]['password'];
	$pconfig['range'] = $a_vip[$id]['range'];
	$pconfig['subnet'] = $a_vip[$id]['subnet'];
	$pconfig['subnet_bits'] = $a_vip[$id]['subnet_bits'];
	$pconfig['noexpand'] = $a_vip[$id]['noexpand'];
	$pconfig['descr'] = $a_vip[$id]['descr'];
	$pconfig['type'] = $a_vip[$id]['type'];
	$pconfig['interface'] = $a_vip[$id]['interface'];
} else {
	$lastvhid = find_last_used_vhid();
	$lastvhid++;
	$pconfig['vhid'] = $lastvhid;
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "mode");
	$reqdfieldsn = array(gettext("Type"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['subnet'])
		$_POST['subnet'] = trim($_POST['subnet']);

	if (($_POST['subnet'] && !is_ipaddr($_POST['subnet'])))
		$input_errors[] = gettext("A valid IP address must be specified.");

	$natiflist = get_configured_interface_with_descr();
	foreach ($natiflist as $natif => $natdescr) {
		if ($_POST['interface'] == $natif && (empty($config['interfaces'][$natif]['ipaddr']) && empty($config['interfaces'][$natif]['ipaddrv6'])))
			$input_errors[] = gettext("The interface chosen for the VIP has no IPv4 or IPv6 address configured so it cannot be used as a parent for the VIP.");
		if ($_POST['subnet'] == get_interface_ip($natif))
			$input_errors[] = sprintf(gettext("The %s IP address may not be used in a virtual entry."),$natdescr);
	}

	if(is_ipaddrv4($_POST['subnet'])) {
		if(($_POST['subnet_bits'] == "31" or $_POST['subnet_bits'] == "32") and $_POST['mode'] == "carp")
		 	$input_errors[] = gettext("The /31 and /32 subnet mask are invalid for CARP IPs.");
	}
	if(is_ipaddrv6($_POST['subnet'])) {
		if(($_POST['subnet_bits'] == "127" or $_POST['subnet_bits'] == "128")  and $_POST['mode'] == "carp")
		 	$input_errors[] = gettext("The /127 and /128 subnet mask are invalid for CARP IPs.");
	}
	/* check for overlaps with other virtual IP */
	foreach ($a_vip as $vipent) {
		if (isset($id) && ($a_vip[$id]) && ($a_vip[$id] === $vipent))
			continue;

		if (isset($_POST['subnet']) && $_POST['subnet'] == $vipent['subnet']) {
			$input_errors[] = gettext("There is already a virtual IP entry for the specified IP address.");
			break;
		}
	}

	/* make sure new ip is within the subnet of a valid ip
	 * on one of our interfaces (wan, lan optX)
	 */
	switch ($_POST['mode']) {
	case "carp":
		/* verify against reusage of vhids */
		$idtracker = 0;
		foreach($config['virtualip']['vip'] as $vip) {
			if($vip['vhid'] == $_POST['vhid'] && $vip['interface'] == $_POST['interface'] && $idtracker <> $id)
				$input_errors[] = sprintf(gettext("VHID %s is already in use on interface %s. Pick a unique number on this interface."),$_POST['vhid'], convert_friendly_interface_to_friendly_descr($_POST['interface']));
			$idtracker++;
		}
		if (empty($_POST['password']))
			$input_errors[] = gettext("You must specify a CARP password that is shared between the two VHID members.");

		if(is_ipaddrv4($_POST['subnet'])) {
			$parent_ip = get_interface_ip($_POST['interface']);
			$parent_sn = get_interface_subnet($_POST['interface']);
			if (!ip_in_subnet($_POST['subnet'], gen_subnet($parent_ip, $parent_sn) . "/" . $parent_sn) && !ip_in_interface_alias_subnet($_POST['interface'], $_POST['subnet'])) {
				$cannot_find = $_POST['subnet'] . "/" . $_POST['subnet_bits'] ;
				$input_errors[] = sprintf(gettext("Sorry, we could not locate an interface with a matching subnet for %s.  Please add an IP alias in this subnet on this interface."),$cannot_find);
			}
		}
		if(is_ipaddrv6($_POST['subnet'])) {
			$parent_ip = get_interface_ipv6($_POST['interface']);
			$parent_sn = get_interface_subnetv6($_POST['interface']);
			$subnet = gen_subnetv6($parent_ip, $parent_sn);
			if (!ip_in_subnet($_POST['subnet'], gen_subnetv6($parent_ip, $parent_sn) . "/" . $parent_sn) && !ip_in_interface_alias_subnet($_POST['interface'], $_POST['subnet'])) {
				$cannot_find = $_POST['subnet'] . "/" . $_POST['subnet_bits'] ;
				$input_errors[] = sprintf(gettext("Sorry, we could not locate an interface with a matching subnet for %s.  Please add an IP alias in this subnet on this interface."),$cannot_find);
			}
		}
		if (strstr($_POST['interface'], "_vip"))
                        $input_errors[] = gettext("For this type of vip a carp parent is not allowed.");
		break;
	case "ipalias":
		if (strstr($_POST['interface'], "_vip")) {
			$parent_ip = get_interface_ip($_POST['interface']);
			$parent_sn = get_interface_subnet($_POST['interface']);
			if (!ip_in_subnet($_POST['subnet'], gen_subnet($parent_ip, $parent_sn) . "/" . $parent_sn) && !ip_in_interface_alias_subnet($_POST['interface'], $_POST['subnet'])) {
				$cannot_find = $_POST['subnet'] . "/" . $_POST['subnet_bits'] ;
				$input_errors[] = sprintf(gettext("Sorry, we could not locate an interface with a matching subnet for %s.  Please add an IP alias in this subnet on this interface."),$cannot_find);
			}
		}
		break;
	default:
		if (strstr($_POST['interface'], "_vip"))
			$input_errors[] = gettext("For this type of VIP, a CARP parent is not allowed.");
		break;
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
			$vipent['noexpand'] = isset($_POST['noexpand']);
		}

		/* CARP specific fields */
		if ($_POST['mode'] === "carp") {
			$vipent['vhid'] = $_POST['vhid'];
			$vipent['advskew'] = $_POST['advskew'];
			$vipent['advbase'] = $_POST['advbase'];
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

		if (!isset($id))
			$id = count($a_vip);
		if (file_exists("{$g['tmp_path']}/.firewall_virtual_ip.apply"))
			$toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.firewall_virtual_ip.apply"));
		else
			$toapplylist = array();

		$toapplylist[$id] = $a_vip[$id];
		if (!empty($a_vip[$id])) {
			/* modify all virtual IP rules with this address */
			for ($i = 0; isset($config['nat']['rule'][$i]); $i++) {
				if ($config['nat']['rule'][$i]['destination']['address'] == $a_vip[$id]['subnet'])
					$config['nat']['rule'][$i]['destination']['address'] = $vipent['subnet'];
			}
		}
		$a_vip[$id] = $vipent;

		if (write_config()) {
			mark_subsystem_dirty('vip');
			file_put_contents("{$g['tmp_path']}/.firewall_virtual_ip.apply", serialize($toapplylist));
		}
		header("Location: firewall_virtual_ip.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"),gettext("Virtual IP Address"),gettext("Edit"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script type="text/javascript" src="/javascript/jquery.ipv4v6ify.js"></script>
<?php include("fbegin.inc"); ?>
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
function set_note(noteMessage){
	var note = document.getElementById("typenote");
	if (note.firstChild != null)
		note.removeChild(note.firstChild);
	if (noteMessage)
		note.appendChild(noteMessage);
}
function enable_change() {
	var carpnote     = document.createTextNode("<?=gettext("This must be the network's subnet mask. It does not specify a CIDR range.");?>");
	var proxyarpnote = document.createTextNode("<?=gettext("This is a CIDR block of proxy ARP addresses.");?>");
	var ipaliasnote  = document.createTextNode("<?=gettext("This must be the network's subnet mask. It does not specify a CIDR range.");?>");
	
	$mode = get_radio_value(document.iform.mode);
	
	document.iform.password.disabled = $mode != "carp";
	document.iform.vhid.disabled     = $mode != "carp";
	document.iform.advskew.disabled  = $mode != "carp";
	document.iform.advbase.disabled  = $mode != "carp";
	document.iform.type.disabled     = $mode in {"carp":1,"ipalias":1};
	
	if ($mode in {"carp":1,"ipalias":1})
		document.iform.type.selectedIndex = 0;// single-adress
	switch($mode)
	{
		case "carp"    : set_note(carpnote);		break;
		case "ipalias" : set_note(ipaliasnote);		break;
		case "proxyarp": set_note(proxyarpnote);	break;
		default: set_note(undefined);
	}
	typesel_change();
}

function typesel_change() {
	switch (document.iform.type.selectedIndex) {
	case 0: // single
		document.iform.subnet.disabled = 0;
		document.iform.subnet_bits.disabled = (get_radio_value(document.iform.mode) == "proxyarp") || (get_radio_value(document.iform.mode) == "other");
		document.iform.noexpand.disabled = 1;
		jQuery('#noexpandrow').css('display','none');
		break;
	case 1: // network
		document.iform.subnet.disabled = 0;
		document.iform.subnet_bits.disabled = 0;
		document.iform.noexpand.disabled = 0;
		jQuery('#noexpandrow').css('display','');
		//document.iform.range_from.disabled = 1;
		//document.iform.range_to.disabled = 1;
		break;
	case 2: // range
		document.iform.subnet.disabled = 1;
		document.iform.subnet_bits.disabled = 1;
		document.iform.noexpand.disabled = 1;
		jQuery('#noexpandrow').css('display','none');
		//document.iform.range_from.disabled = 0;
		//document.iform.range_to.disabled = 0;
		break;
	case 3: // IP alias
		document.iform.subnet.disabled = 1;
		document.iform.subnet_bits.disabled = 0;
		document.iform.noexpand.disabled = 1;
		jQuery('#noexpandrow').css('display','none');
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
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Edit Virtual IP");?></td>
				</tr>	
                <tr>
		  		  <td width="22%" valign="top" class="vncellreq"><?=gettext("Type");?></td>
                  <td width="78%" class="vtable">
					<input name="mode" type="radio" onclick="enable_change()" value="ipalias"
					<?php if ($pconfig['mode'] == "ipalias") echo "checked";?>> <?=gettext("IP Alias");?>
					<input name="mode" type="radio" onclick="enable_change()" value="carp"
					<?php if ($pconfig['mode'] == "carp") echo "checked";?>> <?=gettext("CARP"); ?>
                    <input name="mode" type="radio" onclick="enable_change()" value="proxyarp"
					<?php if ($pconfig['mode'] == "proxyarp") echo "checked";?>> <?=gettext("Proxy ARP"); ?>
					<input name="mode" type="radio" onclick="enable_change()" value="other"
					<?php if ($pconfig['mode'] == "other") echo "checked";?>> <?=gettext("Other");?>
				  </td>
				</tr>
				<tr>
				  <td width="22%" valign="top" class="vncellreq"><?=gettext("Interface");?></td>
				  <td width="78%" class="vtable">
					<select name="interface" class="formselect">
					<?php 
					  $interfaces = get_configured_interface_with_descr(false, true);
					  $carplist = get_configured_carp_interface_list();
                                          foreach ($carplist as $cif => $carpip)
                                          	$interfaces[$cif] = $carpip." (".get_vip_descr($carpip).")";
					  foreach ($interfaces as $iface => $ifacename): ?>
						<option value="<?=$iface;?>" <?php if ($iface == $pconfig['interface']) echo "selected"; ?>>
						<?=htmlspecialchars($ifacename);?>
						</option>
					  <?php endforeach; ?>
					</select>
				  </td>
                </tr>
                <tr>
                  <td valign="top" class="vncellreq"><?=gettext("IP Address(es)");?></td>
                  <td class="vtable">
                    <table border="0" cellspacing="0" cellpadding="0">
                      <tr>
                        <td><?=gettext("Type:");?>&nbsp;&nbsp;</td>
                        <td><select name="type" class="formselect" onChange="typesel_change()">
                            <option value="single" <?php if ((!$pconfig['range'] && $pconfig['subnet_bits'] == 32) || (!isset($pconfig['subnet']))) echo "selected"; ?>>
                            <?=gettext("Single address");?></option>
                            <option value="network" <?php if (!$pconfig['range'] && $pconfig['subnet_bits'] != 32 && isset($pconfig['subnet'])) echo "selected"; ?>>
                            <?=gettext("Network");?></option>
                            <!-- XXX: Billm, don't let anyone choose this until NAT configuration screens are ready for it <option value="range" <?php if ($pconfig['range']) echo "selected"; ?>>
                            Range</option> -->
                          </select></td>
                      </tr>
                      <tr>
                        <td><?=gettext("Address:");?>&nbsp;&nbsp;</td>
                        <td><input name="subnet" type="text" class="formfld unknown ipv4v6" id="subnet" size="28" value="<?=htmlspecialchars($pconfig['subnet']);?>">
                          /<select name="subnet_bits" class="formselect ipv4v6" id="select">
                            <?php for ($i = 128; $i >= 1; $i--): ?>
                            <option value="<?=$i;?>" <?php if ($i == $pconfig['subnet_bits']) echo "selected"; ?>>
                            <?=$i;?>
                      </option>
                            <?php endfor; ?>
                      </select> <i id="typenote"></i>
 						</td>
                      </tr>
                      <tr id="noexpandrow">
                        <td><?=gettext("Expansion:");?>&nbsp;&nbsp;</td>
                        <td><input name="noexpand" type="checkbox" class="formfld unknown" id="noexpand" <?php echo (isset($pconfig['noexpand'])) ? "checked" : "" ; ?>>
                        	Disable expansion of this entry into IPs on NAT lists (e.g. 192.168.1.0/24 expands to 256 entries.)
                        	</td>
                      </tr>
		      <?php
		      /*
                        <tr>
                         <td>Range:&nbsp;&nbsp;</td>
                          <td><input name="range_from" type="text" class="formfld unknown" id="range_from" size="28" value="<?=htmlspecialchars($pconfig['range']['from']);?>">
-
                          <input name="range_to" type="text" class="formfld unknown" id="range_to" size="28" value="<?=htmlspecialchars($pconfig['range']['to']);?>">
                          </td>
			 </tr>
  		       */
			?>
                    </table>
                  </td>
                </tr>
				<tr valign="top">
				  <td width="22%" class="vncellreq"><?=gettext("Virtual IP Password");?></td>
				  <td class="vtable"><input type='password'  name='password' value="<?=htmlspecialchars($pconfig['password']);?>">
					<br><?=gettext("Enter the VHID group password.");?>
				  </td>
				</tr>
				<tr valign="top">
				  <td width="22%" class="vncellreq"><?=gettext("VHID Group");?></td>
				  <td class="vtable"><select id='vhid' name='vhid'>
                            <?php for ($i = 1; $i <= 255; $i++): ?>
                            <option value="<?=$i;?>" <?php if ($i == $pconfig['vhid']) echo "selected"; ?>>
                            <?=$i;?>
                      </option>
                            <?php endfor; ?>
                      </select>
					<br><?=gettext("Enter the VHID group that the machines will share");?>
				  </td>
				</tr>
				<tr valign="top">
				  <td width="22%" class="vncellreq"><?=gettext("Advertising Frequency");?></td>
				  <td class="vtable">
					 Base: <select id='advbase' name='advbase'>
                            <?php for ($i = 1; $i <= 254; $i++): ?>
                            	<option value="<?=$i;?>" <?php if ($i == $pconfig['advbase']) echo "selected"; ?>>
                            <?=$i;?>
                      			</option>
                            <?php endfor; ?>
                      		</select>
					Skew: <select id='advskew' name='advskew'>
                            <?php for ($i = 0; $i <= 254; $i++): ?>
                            	<option value="<?=$i;?>" <?php if ($i == $pconfig['advskew']) echo "selected"; ?>>
                            <?=$i;?>
                      			</option>
                            <?php endfor; ?>
                      		</select>
				<br/><br/>
				<?=gettext("The frequency that this machine will advertise.  0 means usually master. Otherwise the lowest combination of both values in the cluster determines the master.");?>
				  </td>
				</tr>
                <tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
                  <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
                    <br> <span class="vexpl"><?=gettext("You may enter a description here for your reference (not parsed).");?></span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>"> <input type="button" class="formbtn" value="<?=gettext("Cancel"); ?>" onclick="history.back()">
                    <?php if (isset($id) && $a_vip[$id]): ?>
                    <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>">
                    <?php endif; ?>
                  </td>
                </tr>
				<tr>
				  <td colspan="4">
				      <p>
				      	<span class="vexpl">
				      		<span class="red">
							<b><?=gettext("Note:");?><br></b>
				      		</span>&nbsp;&nbsp;
				      		<?=gettext("Proxy ARP and Other type Virtual IPs cannot be bound to by anything running on the firewall, such as IPsec, OpenVPN, etc.  Use a CARP or IP Alias type address for these cases.");?>
				      		<p>&nbsp;&nbsp;&nbsp;<?=gettext("For more information on CARP and the above values, visit the OpenBSD ");?><a href='http://www.openbsd.org/faq/pf/carp.html'> <?=gettext("CARP FAQ"); ?></A>.
						</span>
					  </p>
				  </td>
				</tr>

              </table>
</form>
<script language="JavaScript">
<!--
enable_change();
//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>
