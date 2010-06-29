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
require("filter.inc");
require("shaper.inc");

if (!is_array($config['nat']['advancedoutbound']))
        $config['nat']['advancedoutbound'] = array();

if (!is_array($config['nat']['advancedoutbound']['rule'])) {
	$config['nat']['advancedoutbound']['rule'] = array();
}

$a_out = &$config['nat']['advancedoutbound']['rule'];

$id = $_GET['id'];
if (isset($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($_GET['dup']))  {
	$id  =  $_GET['dup'];
	$after  =  $_GET['dup'];
} else
	unset($after);

if (isset($id) && $a_out[$id]) {
	$pconfig['protocol'] = $a_out[$id]['protocol'];
	list($pconfig['source'],$pconfig['source_subnet']) = explode('/', $a_out[$id]['source']['network']);
	$pconfig['sourceport'] = $a_out[$id]['sourceport'];
	address_to_pconfig($a_out[$id]['destination'], $pconfig['destination'],
		$pconfig['destination_subnet'], $pconfig['destination_not'],
		$none, $none);
	$pconfig['dstport'] = $a_out[$id]['dstport'];
	$pconfig['natport'] = $a_out[$id]['natport'];
	$pconfig['target'] = $a_out[$id]['target'];
	$pconfig['interface'] = $a_out[$id]['interface'];
	if (!$pconfig['interface']) {
		$pconfig['interface'] = "wan";
	}
	$pconfig['descr'] = $a_out[$id]['descr'];
	$pconfig['nonat'] = $a_out[$id]['nonat'];
	$pconfig['staticnatport'] = isset($a_out[$id]['staticnatport']);
	$pconfig['nosync'] = isset($a_out[$id]['nosync']);
} else {
	$pconfig['source_subnet'] = 24;
	$pconfig['destination'] = "any";
	$pconfig['destination_subnet'] = 24;
	$pconfig['interface'] = "wan";
}

if (isset($_GET['dup'])) {
        unset($id);
}

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

	/* input validation */
	$reqdfields = explode(" ", gettext("interface protocol source source_subnet destination destination_subnet"));
	$reqdfieldsn = explode(",", gettext("Interface,Protocol,Source,Source bit count,Destination,Destination bit count"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if($_POST['sourceport'] <> "" && !is_port($_POST['sourceport']))
		$input_errors[] = gettext("You must supply either a valid port for the source port entry.");

	if($_POST['dstport'] <> "" and !is_port($_POST['dstport']))
		$input_errors[] = gettext("You must supply either a valid port for the destination port entry.");

	if($_POST['natport'] <> "" and !is_port($_POST['natport']))
		$input_errors[] = gettext("You must supply either a valid port for the nat port entry.");

	if ($_POST['source_type'] != "any") {
		if ($_POST['source'] && !is_ipaddr($_POST['source']) && $_POST['source'] <> "any") {
			$input_errors[] = gettext("A valid source must be specified.");
		}
	}
	if ($_POST['source_subnet'] && !is_numericint($_POST['source_subnet'])) {
		$input_errors[] = gettext("A valid source bit count must be specified.");
	}
	if ($_POST['sourceport'] && !is_numericint($_POST['sourceport'])) {
		$input_errors[] = gettext("A valid source port must be specified.");
	}
	if ($_POST['destination_type'] != "any") {
        	if ($_POST['destination'] && !is_ipaddr($_POST['destination'])) {
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

	if ($_POST['nonat'] && $_POST['staticnatport']) {
		$input_errors[] = gettext("Static port cannot be used with No NAT.");
	}

	if ($_POST['target'] && !is_ipaddr($_POST['target'])) {
		$input_errors[] = gettext("A valid target IP address must be specified.");
	}

	/* if user has selected any as source, set it here */
	if($_POST['source_type'] == "any") {
		$osn = "any";
	} else {
		$osn = gen_subnet($_POST['source'], $_POST['source_subnet']) . "/" . $_POST['source_subnet'];
	}

	/* check for existing entries */
	if ($_POST['destination_type'] == "any") {
		$ext = "any";
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

	if (!$input_errors) {
	        $natent = array();
		$natent['source']['network'] = $osn;
		$natent['sourceport'] = $_POST['sourceport'];
		$natent['descr'] = $_POST['descr'];
		$natent['target'] = $_POST['target'];
		$natent['interface'] = $_POST['interface'];

		/* static-port */
		if(isset($_POST['staticnatport'])) {
			$natent['staticnatport'] = true;
		} else {
			unset($natent['staticnatport']);
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
		if($_POST['natport'] != "") {
	        	$natent['natport'] = $_POST['natport'];
		} else {
			unset($natent['natport']);
		}
		if($_POST['dstport'] != "") {
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

		if (isset($id) && $a_out[$id]) {
			$a_out[$id] = $natent;
		} else {
			if (is_numeric($after)) {
				array_splice($a_out, $after+1, 0, array($natent));
			} else {
				$a_out[] = $natent;
			}
		}

		mark_subsystem_dirty('natconf');
        	write_config();
		header("Location: firewall_nat_out.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall","NAT","Outbound","Edit"));
$closehead = false;
include("head.inc");

?>

<script language="JavaScript">
<!--
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
	    document.iform.sourceport.value = "";
            document.iform.source.disabled = 1;
            document.iform.source_subnet.value = "24";
            document.iform.source_subnet.disabled = 1;
            break;
    }
}
//-->
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="firewall_nat_out_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="1">
				<tr>
					<td colspan="2" valign="top" class="listtopic">gettext("Edit Advanced Outbound NAT entry")</td>
				</tr>
	        <tr>
                  <td width="22%" valign="top" class="vncell">Do not NAT</td>
                  <td width="78%" class="vtable">
			<input type="checkbox" name="nonat"<?php if(isset($pconfig['nonat'])) echo " CHECKED"; ?>>
                     <span class="vexpl">gettext("Enabling this option will disable NAT for traffic matching this rule and stop processing Outbound NAT rules.")
		     <br>gettext("Hint: in most cases, you won't use this option.")</span></td>
                </tr>
	        <tr>
                  <td width="22%" valign="top" class="vncellreq">Interface</td>
                  <td width="78%" class="vtable">
			<select name="interface" class="formselect">
				<?php
				$interfaces = get_configured_interface_with_descr(false, true);
				foreach ($interfaces as $iface => $ifacename): ?>
				<option value="<?=$iface;?>" <?php if ($iface == $pconfig['interface']) echo "selected"; ?>>
				<?=htmlspecialchars($ifacename);?>
				</option>
				<?php endforeach; ?>
			</select><br>
                     <span class="vexpl">gettext("Choose which interface this rule applies to.")<br>
                     gettext("Hint: in most cases, you'll want to use WAN here.")</span></td>
                </tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq">Protocol</td>
			<td width="78%" class="vtable">
				<select name="protocol" class="formselect">
				<?php $protocols = explode(" ", "any TCP UDP TCP/UDP ICMP ESP AH GRE IGMP carp pfsync");
                                foreach ($protocols as $proto): ?>
                                        <option value="<?=strtolower($proto);?>" <?php if (strtolower($proto) == $pconfig['protocol']) echo "selected"; ?>><?=htmlspecialchars($proto);?></option>
				<?php endforeach; ?>
				 </select> <br> <span class="vexpl">gettext("Choose which protocol this rule should match.")<br />
				 gettext("Hint: in most cases, you should specify <em>any</em> &nbsp;here.")</span>
			</td>
		</tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Source</td>
                  <td width="78%" class="vtable">
                    <table border="0" cellspacing="1" cellpadding="1">
                      <tr>
		        <td>Type:&nbsp;&nbsp;</td>
			<td>
			    <select name="source_type" class="formselect" onChange="sourcesel_change()">
                              <option value="any" <?php if ($pconfig['source'] == "any") echo "selected"; ?>>any</option>
                              <option value="network" <?php if ($pconfig['source'] != "any") echo "selected"; ?>>Network</option>
                            </select>
			</td></tr>
                        <td>Address:&nbsp;&nbsp;</td>
                        <td><input name="source" type="text" class="formfld unknown" id="source" size="20" value="<?=htmlspecialchars($pconfig['source']);?>">/<select name="source_subnet" class="formfld" id="source_subnet">
<?php for ($i = 32; $i >= 0; $i--): ?>
                          <option value="<?=$i;?>"<?php if ($i == $pconfig['source_subnet']) echo "selected"; ?>><?=$i;?></option>
<?php endfor; ?>
                          </select></td>
                      </tr>
                      <tr>
                        <td>&nbsp;</td>
                        <td><span class="vexpl">gettext("Enter the source network for the outbound NAT mapping.")</span></td>
                      </tr>
                      <tr>
                        <td>Source port:&nbsp;&nbsp;</td>
                        <td><input name="sourceport" type="text" class="formfld unknown" id="sourceport" size="5" value="<?=htmlspecialchars($pconfig['sourceport']);?>"> (leave 
blank for any)</td>
                      </tr>
                    </table></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">gettext("Destination")</td>
                  <td width="78%" class="vtable">
<input name="destination_not" type="checkbox" id="destination_not" value="yes" <?php if ($pconfig['destination_not']) echo "checked"; ?>>
                    <strong>not</strong><br>
                    gettext("Use this option to invert the sense of the match.")<br>
                    <br>
                    <table border="0" cellspacing="1" cellpadding="1">
                      <tr>
                        <td>Type:&nbsp;&nbsp;</td>
                        <td><select name="destination_type" class="formselect" onChange="typesel_change()">
                            <option value="any"<?php if ($pconfig['destination'] == "any") echo " selected"; ?>>
                            any</option>
                            <option value="network"<?php if ($pconfig['destination'] != "any") echo " selected"; ?>>
                            Network</option>
                          </select></td>
                      </tr>
                      <tr>
                        <td>Address:&nbsp;&nbsp;</td>
                        <td><input name="destination" type="text" class="formfld unknown" id="destination" size="20" value="<?=htmlspecialchars($pconfig['destination']);?>">
                          /
                          <select name="destination_subnet" class="formselect" id="destination_subnet">
<?php for ($i = 32; $i >= 0; $i--): ?>
                            <option value="<?=$i;?>"<?php if ($i == $pconfig['destination_subnet']) echo " selected"; ?>><?=$i;?></option>
<?php endfor; ?>
                          </select> </td>
                      </tr>
                      <tr>
                        <td>&nbsp;</td>
                        <td><span class="vexpl">gettext("Enter the destination network for the outbound NAT mapping.")</span></td>
                      </tr>
                      <tr>
                        <td>gettext("Destination port:")&nbsp;&nbsp;</td>
                        <td><input name="dstport" type="text" class="formfld unknown" id="dstport" size="5" value="<?=htmlspecialchars($pconfig['dstport']);?>"> (leave blank for any)</td>
                      </tr>
                    </table>
		  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">gettext("Translation")</td>
                  <td width="78%" class="vtable">
			<table border="0" cellspacing="1" cellpadding="1">
			<tr>
			  <td>Address:&nbsp;&nbsp;</td>
			  <td><select name="target" class="formselect">
				<option value=""<?php if (!$pconfig['target']) echo " selected"; ?>>gettext("Interface address")</option>
<?php	if (is_array($config['virtualip']['vip'])):
		foreach ($config['virtualip']['vip'] as $sn):
			if ($sn['mode'] == "proxyarp" && $sn['type'] == "network"):
				$start = ip2long32(gen_subnet($sn['subnet'], $sn['subnet_bits']));
				$end = ip2long32(gen_subnet_max($sn['subnet'], $sn['subnet_bits']));
				$len = $end - $start;

				for ($i = 0; $i <= $len; $i++):
					$snip = long2ip32($start+$i);
?>
				<option value="<?=$snip;?>" <?php if ($snip == $pconfig['target']) echo "selected"; ?>><?=htmlspecialchars("{$snip} ({$sn['descr']})");?></option>
				<?php endfor; ?>
			<?php else: ?>
				<option value="<?=$sn['subnet'];?>" <?php if ($sn['subnet'] == $pconfig['target']) echo "selected"; ?>><?=htmlspecialchars("{$sn['subnet']} ({$sn['descr']})");?></option>
<?php 		endif; endforeach;
	endif;
?>
				<option value=""<?php if($pconfig['target'] == "any") echo " selected"; ?>>any</option>
			  </select>
			  </td>
			</tr>
			<tr><td>&nbsp;</td><td>
                     <span class="vexpl">gettext("Packets matching this rule will be mapped to the IP address given here.")<br>
			gettext("If you want this rule to apply to another IP address than the IP address of the interface chosen above, 			select it here") gettext("(you need to define <a href="firewall_virtual_ip.php">Virtual IP</a> addresses on the first)").
			 gettext("Also note that if you are trying to redirect connections on the LAN select the "any" option.")
			</span>
			</td></tr>
			<tr>
                          <td>Port:&nbsp;&nbsp;</td>
                          <td><input name="natport" type="text" class="formfld unknown" id="natport" size="5" value="<?=htmlspecialchars($pconfig['natport']);?>"></td>
			</tr>
			<tr><td>&nbsp;</td><td>
                        <span class="vexpl">gettext("Enter the source port for the outbound NAT mapping.")</span>
			</td></tr>
                        <tr>
                          <td>Static-port:&nbsp;&nbsp;</td>
                          <td><input onChange="staticportchange();" name="staticnatport" type="checkbox" class="formfld" id="staticnatport" size="5"<?php if($pconfig['staticnatport']) echo " CHECKED";?>></td>
			</tr>
			</table>
		  </td>
                </tr>
				<tr>
				  <td width="22%" valign="top" class="vncell">gettext("No XMLRPC Sync")</td>
				  <td width="78%" class="vtable">
					<input value="yes" name="nosync" type="checkbox" class="formfld" id="nosync"<?php if($pconfig['nosync']) echo " CHECKED"; ?>><br>
				   			 gettext("HINT: This prevents the rule from automatically syncing to other CARP members.")
				  </td>
				</tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">gettext("Description")</td>
                  <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
                    <br> <span class="vexpl">gettext("You may enter a description here for your reference (not parsed).")</span></td>
          </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="Save"> <input type="button" class="formbtn" value="Cancel" onclick="history.back()">
                    <?php if (isset($id) && $a_out[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<script language="JavaScript">
<!--
typesel_change();
staticportchange();
//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>
