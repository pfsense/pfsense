<?php
/* $Id$ */
/*
	firewall_nat_edit.php
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
##|*IDENT=page-firewall-nat-portforward-edit
##|*NAME=Firewall: NAT: Port Forward: Edit page
##|*DESCR=Allow access to the 'Firewall: NAT: Port Forward: Edit' page.
##|*MATCH=firewall_nat_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("itemid.inc");
require("filter.inc");
require("shaper.inc");

if (!is_array($config['nat']['rule'])) {
	$config['nat']['rule'] = array();
}
$a_nat = &$config['nat']['rule'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($_GET['dup'])) {
        $id = $_GET['dup'];
        $after = $_GET['dup'];
}

if (isset($id) && $a_nat[$id]) {
	$pconfig['extaddr'] = $a_nat[$id]['external-address'];
	$pconfig['proto'] = $a_nat[$id]['protocol'];
	list($pconfig['beginport'],$pconfig['endport']) = explode("-", $a_nat[$id]['external-port']);
	if(!$pconfig['endport'])
		$pconfig['endport'] = $pconfig['beginport'];
	$pconfig['localip'] = $a_nat[$id]['target'];
	$pconfig['localbeginport'] = $a_nat[$id]['local-port'];
	$pconfig['descr'] = $a_nat[$id]['descr'];
	$pconfig['interface'] = $a_nat[$id]['interface'];
	$pconfig['associated-rule-id'] = $a_nat[$id]['associated-rule-id'];
	$pconfig['nosync'] = isset($a_nat[$id]['nosync']);
	if (!$pconfig['interface'])
		$pconfig['interface'] = "wan";
} else {
	$pconfig['interface'] = "wan";
}

if (isset($_GET['dup']))
	unset($id);

/*  run through $_POST items encoding HTML entties so that the user
 *  cannot think he is slick and perform a XSS attack on the unwilling 
 */
foreach ($_POST as $key => $value) {
	$temp = $value;
	$newpost = htmlentities($temp);
	if($newpost <> $temp) 
		$input_errors[] = "Invalid characters detected ($temp).  Please remove invalid characters and save again.";		
}

if ($_POST) {

	if ($_POST['beginport_cust'] && !$_POST['beginport'])
		$_POST['beginport'] = $_POST['beginport_cust'];
	if ($_POST['endport_cust'] && !$_POST['endport'])
		$_POST['endport'] = $_POST['endport_cust'];
	if ($_POST['localbeginport_cust'] && !$_POST['localbeginport'])
		$_POST['localbeginport'] = $_POST['localbeginport_cust'];

	if (!$_POST['endport'])
		$_POST['endport'] = $_POST['beginport'];
        /* Make beginning port end port if not defined and endport is */
        if (!$_POST['beginport'] && $_POST['endport'])
                $_POST['beginport'] = $_POST['endport'];

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if(strtoupper($_POST['proto']) == "TCP" or strtoupper($_POST['proto']) == "UDP" or strtoupper($_POST['proto']) == "TCP/UDP") {
		$reqdfields = explode(" ", "interface proto beginport endport localip localbeginport");
		$reqdfieldsn = explode(",", "Interface,Protocol,External port from,External port to,NAT IP,Local port");
	} else {
		$reqdfields = explode(" ", "interface proto localip");
		$reqdfieldsn = explode(",", "Interface,Protocol,NAT IP");
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (($_POST['localip'] && !is_ipaddroralias($_POST['localip']))) {
		$input_errors[] = "\"{$_POST['localip']}\" is not valid NAT IP address or host alias.";
	}

	/* only validate the ports if the protocol is TCP, UDP or TCP/UDP */
	if(strtoupper($_POST['proto']) == "TCP" or strtoupper($_POST['proto']) == "UDP" or strtoupper($_POST['proto']) == "TCP/UDP") {

		if ($_POST['beginport'] && !is_portoralias($_POST['beginport'])) {
			$input_errors[] = "The start port must be an integer between 1 and 65535.";
		}

		if ($_POST['endport'] && !is_portoralias($_POST['endport'])) {
			$input_errors[] = "The end port must be an integer between 1 and 65535.";
		}

		if ($_POST['localbeginport'] && !is_portoralias($_POST['localbeginport'])) {
			$input_errors[] = "The local port must be an integer between 1 and 65535.";
		}

		if ($_POST['beginport'] > $_POST['endport']) {
			/* swap */
			$tmp = $_POST['endport'];
			$_POST['endport'] = $_POST['beginport'];
			$_POST['beginport'] = $tmp;
		}

		if (!$input_errors) {
			if (($_POST['endport'] - $_POST['beginport'] + $_POST['localbeginport']) > 65535)
				$input_errors[] = "The target port range must be an integer between 1 and 65535.";
		}

	}

	/* check for overlaps */
	foreach ($a_nat as $natent) {
		if (isset($id) && ($a_nat[$id]) && ($a_nat[$id] === $natent))
			continue;
		if ($natent['interface'] != $_POST['interface'])
			continue;
		if ($natent['external-address'] != $_POST['extaddr'])
			continue;
		if (($natent['proto'] != $_POST['proto']) && ($natent['proto'] != "tcp/udp") && ($_POST['proto'] != "tcp/udp"))
			continue;

		list($begp,$endp) = explode("-", $natent['external-port']);
		if (!$endp)
			$endp = $begp;

		if (!(   (($_POST['beginport'] < $begp) && ($_POST['endport'] < $begp))
		      || (($_POST['beginport'] > $endp) && ($_POST['endport'] > $endp)))) {

			$input_errors[] = "The external port range overlaps with an existing entry.";
			break;
		}
	}

	if (!$input_errors) {
		$natent = array();
		if ($_POST['extaddr'])
			$natent['external-address'] = $_POST['extaddr'];
		$natent['protocol'] = $_POST['proto'];

		if ($_POST['beginport'] == $_POST['endport'])
			$natent['external-port'] = $_POST['beginport'];
		else
			$natent['external-port'] = $_POST['beginport'] . "-" . $_POST['endport'];

		$natent['target'] = $_POST['localip'];
		$natent['local-port'] = $_POST['localbeginport'];
		$natent['interface'] = $_POST['interface'];
		$natent['descr'] = $_POST['descr'];
		$natent['associated-rule-id'] = $_POST['associated-rule-id'];
		
		if($_POST['filter-rule-association'] == "pass")
			$natent['associated-rule-id'] = "pass";

		if($_POST['nosync'] == "yes")
			$natent['nosync'] = true;
		else
			unset($natent['nosync']);

		// If we used to have an associated filter rule, but no-longer should have one
		if ($a_nat[$id]>0 && empty($natent['associated-rule-id'])) {
			// Delete the previous rule
			delete_id($a_nat[$id]['associated-rule-id'], $config['filter']['rule']);
			mark_subsystem_dirty('filter');
		}

		$need_filter_rule = false;
		// Updating a rule with a filter rule associated
		if (!empty($natent['associated-rule-id']))
			$need_filter_rule = true;
		// Create a rule or if we want to create a new one
		if( $natent['associated-rule-id']=='new' ) {
			$need_filter_rule = true;
			unset( $natent['associated-rule-id'] );
			$_POST['filter-rule-association']='add-associated';
		}
		// If creating a new rule, where we want to add the filter rule, associated or not
		else if( isset($_POST['filter-rule-association']) &&
			($_POST['filter-rule-association']=='add-associated' ||
			$_POST['filter-rule-association']=='add-unassociated') )
			$need_filter_rule = true;

		// Determine NAT entry ID now, we need it for the firewall rule
		if (isset($id) && $a_nat[$id])
			$a_nat[$id] = $natent;
		else {
			if (is_numeric($after))
				$id = $after + 1;
			else
				$id = count($a_nat);
		}

		if ($need_filter_rule == true) {

			/* auto-generate a matching firewall rule */
			$filterent = array();
			unset($filterentid);
			// If a rule already exists, load it
			if (!empty($natent['associated-rule-id'])) {
				$filterentid = get_id($natent['associated-rule-id'], $config['filter']['rule']);
				if ($filterentid == false) {
					$filterent['source']['any'] = "";
					$filterent['associated-rule-id'] = $natent['associated-rule-id'];
				} else
					$filterent =& $config['filter']['rule'][$filterentid];
			} else
				// Create the default source entry for new filter entries
				$filterent['source']['any'] = "";

			// Update interface, protocol and destination
			$filterent['interface'] = $_POST['interface'];
			$filterent['protocol'] = $_POST['proto'];
			$filterent['destination']['address'] = $_POST['localip'];

			$dstpfrom = $_POST['localbeginport'];
			$dstpto = $dstpfrom + $_POST['endport'] - $_POST['beginport'];

			if ($dstpfrom == $dstpto)
				$filterent['destination']['port'] = $dstpfrom;
			else
				$filterent['destination']['port'] = $dstpfrom . "-" . $dstpto;

			/*
			 * Our firewall filter description may be no longer than
			 * 63 characters, so don't let it be.
			 */
			$filterent['descr'] = substr("NAT " . $_POST['descr'], 0, 62);

			// If this is a new rule, create an ID and add the rule
			if( $_POST['filter-rule-association']=='add-associated' ) {
				$filterent['associated-rule-id'] = $natent['associated-rule-id'] = get_unique_id();
				$config['filter']['rule'][] = $filterent;
			}

			mark_subsystem_dirty('filter');
		}

		// Update the NAT entry now
		if (isset($id) && $a_nat[$id])
			$a_nat[$id] = $natent;
		else {
			if (is_numeric($after))
				array_splice($a_nat, $after+1, 0, array($natent));
			else
				$a_nat[] = $natent;
		}

		mark_subsystem_dirty('natconf');

		write_config();

		header("Location: firewall_nat.php");
		exit;
	}
}

$pgtitle = array("Firewall","NAT","Port Forward: Edit");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="firewall_nat_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
				<tr>
					<td colspan="2" valign="top" class="listtopic">Edit NAT entry</td>
				</tr>	
				<tr>
                  <td width="22%" valign="top" class="vncellreq">Interface</td>
                  <td width="78%" class="vtable">
					<select name="interface" class="formselect">
						<?php
						
						$iflist = get_configured_interface_with_descr(false, true);
						foreach ($iflist as $if => $ifdesc) 
							if(have_ruleint_access($if)) 
								$interfaces[$if] = $ifdesc;
						
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

						foreach ($interfaces as $iface => $ifacename): ?>
						<option value="<?=$iface;?>" <?php if ($iface == $pconfig['interface']) echo "selected"; ?>>
						<?=htmlspecialchars($ifacename);?>
						</option>
						<?php endforeach; ?>
					</select><br>
                     <span class="vexpl">Choose which interface this rule applies to.<br>
                     Hint: in most cases, you'll want to use WAN here.</span></td>
                </tr>
			    <tr>
                  <td width="22%" valign="top" class="vncellreq">External address</td>
                  <td width="78%" class="vtable">
					<select name="extaddr" class="formselect">
						<option value="" <?php if (!$pconfig['extaddr']) echo "selected"; ?>>Interface address</option>
<?php					if (is_array($config['virtualip']['vip'])):
						foreach ($config['virtualip']['vip'] as $sn): 
							if ($sn['mode'] == "proxyarp" && $sn['type'] == "network"):
								$baseip = ip2long($sn['subnet']) & ip2long(gen_subnet_mask($sn['subnet_bits']));
								for ($i = $sn['subnet_bits']; $i <= 32; $i++): 
									$baseip = $baseip + 1;
									$snip = long2ip($baseip);
								?>
						<option value="<?=$snip;?>" <?php if ($snip == $pconfig['extaddr']) echo "selected"; ?>><?=htmlspecialchars("{$snip} ({$sn['descr']})");?></option>
								<?php endfor; 
							else: ?>
						<option value="<?=$sn['subnet'];?>" <?php if ($sn['subnet'] == $pconfig['extaddr']) echo "selected"; ?>><?=htmlspecialchars("{$sn['subnet']} ({$sn['descr']})");?></option>
						<?php endif; ?>
<?php					endforeach;
						endif; ?>
						<option value="any" <?php if($pconfig['extaddr'] == "any") echo "selected"; ?>>any</option>
					</select>
					<br />
                    <span class="vexpl">
					If you want this rule to apply to another IP address than the IP address of the interface chosen above,
					select it here (you need to define <a href="firewall_virtual_ip.php">Virtual IP</a> addresses on the first).  Also note that if you are trying to redirect connections on the LAN select the "any" option.</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Protocol</td>
                  <td width="78%" class="vtable">
                    <select name="proto" class="formselect" onChange="proto_change(); check_for_aliases();">
                      <?php $protocols = explode(" ", "TCP UDP TCP/UDP GRE ESP"); foreach ($protocols as $proto): ?>
                      <option value="<?=strtolower($proto);?>" <?php if (strtolower($proto) == $pconfig['proto']) echo "selected"; ?>><?=htmlspecialchars($proto);?></option>
                      <?php endforeach; ?>
                    </select> <br> <span class="vexpl">Choose which IP protocol
                    this rule should match.<br>
                    Hint: in most cases, you should specify <em>TCP</em> &nbsp;here.</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">External port
                    range </td>
                  <td width="78%" class="vtable">
                    <table border="0" cellspacing="0" cellpadding="0">
                      <tr>
                        <td>from:&nbsp;&nbsp;</td>
                        <td><select name="beginport" class="formselect" onChange="ext_rep_change(); ext_change(); check_for_aliases();">
                            <option value="">(other)</option>
                            <?php $bfound = 0; foreach ($wkports as $wkport => $wkportdesc): ?>
                            <option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['beginport']) {
								echo "selected";
								$bfound = 1;
							}?>>
							<?=htmlspecialchars($wkportdesc);?>
							</option>
                            <?php endforeach; ?>
                          </select> <input onChange="check_for_aliases();" autocomplete='off' class="formfldalias" name="beginport_cust" id="beginport_cust" type="text" size="5" value="<?php if (!$bfound) echo $pconfig['beginport']; ?>"></td>
                      </tr>
                      <tr>
                        <td>to:</td>
                        <td><select name="endport" class="formselect" onChange="ext_change(); check_for_aliases();">
                            <option value="">(other)</option>
                            <?php $bfound = 0; foreach ($wkports as $wkport => $wkportdesc): ?>
                            <option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['endport']) {
								echo "selected";
								$bfound = 1;
							}?>>
							<?=htmlspecialchars($wkportdesc);?>
							</option>
							<?php endforeach; ?>
                          </select> <input onChange="check_for_aliases();" class="formfldalias" autocomplete='off' name="endport_cust" id="endport_cust" type="text" size="5" value="<?php if (!$bfound) echo $pconfig['endport']; ?>"></td>
                      </tr>
                    </table>
                    <br> <span class="vexpl">Specify the port or port range on
                    the firewall's external address for this mapping.<br>
                    Hint: you can leave the <em>'to'</em> field empty if you only
                    want to map a single port</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">NAT IP</td>
                  <td width="78%" class="vtable">
                    <input autocomplete='off' name="localip" type="text" class="formfldalias" id="localip" size="20" value="<?=htmlspecialchars($pconfig['localip']);?>">
                    <br> <span class="vexpl">Enter the internal IP address of
                    the server on which you want to map the ports.<br>
                    e.g. <em>192.168.1.12</em></span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Local port</td>
                  <td width="78%" class="vtable">
                    <select name="localbeginport" class="formselect" onChange="ext_change();check_for_aliases();">
                      <option value="">(other)</option>
                      <?php $bfound = 0; foreach ($wkports as $wkport => $wkportdesc): ?>
                      <option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['localbeginport']) {
							echo "selected";
							$bfound = 1;
						}?>>
					  <?=htmlspecialchars($wkportdesc);?>
					  </option>
                      <?php endforeach; ?>
                    </select> <input onChange="check_for_aliases();" autocomplete='off' class="formfldalias" name="localbeginport_cust" id="localbeginport_cust" type="text" size="5" value="<?php if (!$bfound) echo $pconfig['localbeginport']; ?>">
                    <br>
                    <span class="vexpl">Specify the port on the machine with the
                    IP address entered above. In case of a port range, specify
                    the beginning port of the range (the end port will be calculated
                    automatically).<br>
                    Hint: this is usually identical to the 'from' port above</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">Description</td>
                  <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
                    <br> <span class="vexpl">You may enter a description here
                    for your reference (not parsed).</span></td>
                </tr>
				<tr>
					<td width="22%" valign="top" class="vncell">No XMLRPC Sync</td>
					<td width="78%" class="vtable">
						<input type="checkbox" value="yes" name="nosync"<?php if($pconfig['nosync']) echo " CHECKED"; ?>><br>
						HINT: This prevents the rule from automatically syncing to other CARP members.
					</td>
				</tr>
				<?php if (isset($id) && $a_nat[$id] && !isset($_GET['dup'])): ?>
				<tr>
					<td width="22%" valign="top" class="vncell">Filter rule association</td>
					<td width="78%" class="vtable">
						<select name="associated-rule-id">
							<option value="">None</option>
							<option value="pass" <?php if($pconfig['associated-rule-id'] == "pass") echo " SELECTED"; ?>>Pass</option>
							<?php 
							$linkedrule = "";
							if (is_array($config['filter']['rule'])) {
								$filter_id = 0;
							      foreach ($config['filter']['rule'] as $filter_rule) {
								if (isset($filter_rule['associated-rule-id'])) {
									echo "<option value=\"{$filter_rule['associated-rule-id']}\"";
									if ($filter_rule['associated-rule-id']==$pconfig['associated-rule-id']) {
										echo " SELECTED";
										$linkedrule = "<br /><a href=\"firewall_rules_edit.php?id={$filter_id}\">View the filter rule</a><br/>";
									}
									echo ">". htmlspecialchars('Rule ' . $filter_rule['descr']) . "</option>\n";
									
								}
								if ($filter_rule['interface'] == $pconfig['interface'])
									$filter_id++;
							      }
							}
							if (isset($pconfig['associated-rule-id']))
								echo "<option value=\"new\">Create new associated filter rule</option>\n";
						echo "</select>\n";
						echo $linkedrule;
						?>
					</td>
				</tr>
				<?php endif; ?>
                <?php if ((!(isset($id) && $a_nat[$id])) || (isset($_GET['dup']))): ?>
                <tr>
                  <td width="22%" valign="top" class="vncell">Filter rule association</td>
                  <td width="78%" class="vtable">
                    <select name="filter-rule-association" id="filter-rule-association">
						<option value="">None</option>
						<option value="add-associated" selected="selected">Add associated filter rule</option>
						<option value="add-unassociated">Add unassociated filter rule</option>
						<option value="pass">Pass</option>
					</select>
				  </td>
                </tr><?php endif; ?>
				<tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">&nbsp;</td>
				</tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="Save"> <input type="button" class="formbtn" value="Cancel" onclick="history.back()">
                    <?php if (isset($id) && $a_nat[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<script language="JavaScript">
<!--
	ext_change();
//-->
</script>
<?php
$isfirst = 0;
$aliases = "";
$addrisfirst = 0;
$aliasesaddr = "";
if($config['aliases']['alias'] <> "")
	foreach($config['aliases']['alias'] as $alias_name) {
		switch ($alias_name['type']) {
                        case "port":
                                if($isfirst == 1) $portaliases .= ",";
                                $portaliases .= "'" . $alias_name['name'] . "'";
                                $isfirst = 1;
                                break;
                        case "host":
                        case "network":
                        case "openvpn":
			case "urltable":
                                if($addrisfirst == 1) $aliasesaddr .= ",";
                                $aliasesaddr .= "'" . $alias_name['name'] . "'";
                                $addrisfirst = 1;
                                break;
                        default:
                                break;
		}
	}
?>
<script language="JavaScript">
<!--
	var addressarray=new Array(<?php echo $aliasesaddr; ?>);
	var customarray=new Array(<?php echo $portaliases; ?>);

	var oTextbox1 = new AutoSuggestControl(document.getElementById("localip"), new StateSuggestions(addressarray));
        var oTextbox2 = new AutoSuggestControl(document.getElementById("beginport_cust"), new StateSuggestions(customarray));
        var oTextbox3 = new AutoSuggestControl(document.getElementById("endport_cust"), new StateSuggestions(customarray));
        var oTextbox4 = new AutoSuggestControl(document.getElementById("localbeginport_cust"), new StateSuggestions(customarray));
//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>
