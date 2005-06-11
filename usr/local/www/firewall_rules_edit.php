#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	firewall_rules_edit.php
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

require("guiconfig.inc");

$specialsrcdst = explode(" ", "any lan pptp");

if (!is_array($config['filter']['rule'])) {
	$config['filter']['rule'] = array();
}
filter_rules_sort();
$a_filter = &$config['filter']['rule'];

$id = $_GET['id'];
if (is_numeric($_POST['id']))
	$id = $_POST['id'];

$after = $_GET['after'];

if (isset($_POST['after']))
	$after = $_POST['after'];

if (isset($_GET['dup'])) {
	$id = $_GET['dup'];
	$after = $_GET['dup'];
}

if (isset($id) && $a_filter[$id]) {
	$pconfig['interface'] = $a_filter[$id]['interface'];

	if (!isset($a_filter[$id]['type']))
		$pconfig['type'] = "pass";
	else
		$pconfig['type'] = $a_filter[$id]['type'];

	if (isset($a_filter[$id]['protocol']))
		$pconfig['proto'] = $a_filter[$id]['protocol'];
	else
		$pconfig['proto'] = "any";

	if ($a_filter[$id]['protocol'] == "icmp")
		$pconfig['icmptype'] = $a_filter[$id]['icmptype'];

	address_to_pconfig($a_filter[$id]['source'], $pconfig['src'],
		$pconfig['srcmask'], $pconfig['srcnot'],
		$pconfig['srcbeginport'], $pconfig['srcendport']);

	if($a_filter[$id]['os'] <> "")
		$pconfig['os'] = $a_filter[$id]['os'];

	address_to_pconfig($a_filter[$id]['destination'], $pconfig['dst'],
		$pconfig['dstmask'], $pconfig['dstnot'],
		$pconfig['dstbeginport'], $pconfig['dstendport']);

	$pconfig['disabled'] = isset($a_filter[$id]['disabled']);
	$pconfig['log'] = isset($a_filter[$id]['log']);
	$pconfig['descr'] = $a_filter[$id]['descr'];
	
	/* advanced */
        $pconfig['max-src-nodes'] = $a_filter[$id]['max-src-nodes'];
        $pconfig['max-src-states'] = $a_filter[$id]['max-src-states'];
        $pconfig['statetype'] = $a_filter[$id]['statetype'];
	$pconfig['statetimeout'] = $a_filter[$id]['statetimeout'];
	
	/* advanced - new connection per second banning*/
	$pconfig['max-src-conn-rate'] = $a_filter[$id]['max-src-conn-rate'];
	$pconfig['max-src-conn-rates'] = $a_filter[$id]['max-src-conn-rates'];

} else {
	/* defaults */
	if ($_GET['if'])
		$pconfig['interface'] = $_GET['if'];
	$pconfig['type'] = "pass";
	$pconfig['src'] = "any";
	$pconfig['dst'] = "any";
}

if (isset($_GET['dup']))
	unset($id);

if ($_POST) {

	if (($_POST['proto'] != "tcp") && ($_POST['proto'] != "udp") && ($_POST['proto'] != "tcp/udp")) {
		$_POST['srcbeginport'] = 0;
		$_POST['srcendport'] = 0;
		$_POST['dstbeginport'] = 0;
		$_POST['dstendport'] = 0;
	} else {

		if ($_POST['srcbeginport_cust'] && !$_POST['srcbeginport'])
			$_POST['srcbeginport'] = $_POST['srcbeginport_cust'];
		if ($_POST['srcendport_cust'] && !$_POST['srcendport'])
			$_POST['srcendport'] = $_POST['srcendport_cust'];

		if ($_POST['srcbeginport'] == "any") {
			$_POST['srcbeginport'] = 0;
			$_POST['srcendport'] = 0;
		} else {
			if (!$_POST['srcendport'])
				$_POST['srcendport'] = $_POST['srcbeginport'];
		}
		if ($_POST['srcendport'] == "any")
			$_POST['srcendport'] = $_POST['srcbeginport'];

		if ($_POST['dstbeginport_cust'] && !$_POST['dstbeginport'])
			$_POST['dstbeginport'] = $_POST['dstbeginport_cust'];
		if ($_POST['dstendport_cust'] && !$_POST['dstendport'])
			$_POST['dstendport'] = $_POST['dstendport_cust'];

		if ($_POST['dstbeginport'] == "any") {
			$_POST['dstbeginport'] = 0;
			$_POST['dstendport'] = 0;
		} else {
			if (!$_POST['dstendport'])
				$_POST['dstendport'] = $_POST['dstbeginport'];
		}
		if ($_POST['dstendport'] == "any")
			$_POST['dstendport'] = $_POST['dstbeginport'];
	}

	if (is_specialnet($_POST['srctype'])) {
		$_POST['src'] = $_POST['srctype'];
		$_POST['srcmask'] = 0;
	} else if ($_POST['srctype'] == "single") {
		$_POST['srcmask'] = 32;
	}
	if (is_specialnet($_POST['dsttype'])) {
		$_POST['dst'] = $_POST['dsttype'];
		$_POST['dstmask'] = 0;
	}  else if ($_POST['dsttype'] == "single") {
		$_POST['dstmask'] = 32;
	}

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "type interface proto src dst");
	$reqdfieldsn = explode(",", "Type,Interface,Protocol,Source,Destination");


	if($_POST['statetype'] == "modulate state" or $_POST['statetype'] == "synproxy state")
		if( $_POST['proto'] != "tcp" )
			$input_errors[] = "{$_POST['statetype']} is only valid with protocol tcp.";


	if (!(is_specialnet($_POST['srctype']) || ($_POST['srctype'] == "single"))) {
		$reqdfields[] = "srcmask";
		$reqdfieldsn[] = "Source bit count";
	}
	if (!(is_specialnet($_POST['dsttype']) || ($_POST['dsttype'] == "single"))) {
		$reqdfields[] = "dstmask";
		$reqdfieldsn[] = "Destination bit count";
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (!$_POST['srcbeginport']) {
		$_POST['srcbeginport'] = 0;
		$_POST['srcendport'] = 0;
	}
	if (!$_POST['dstbeginport']) {
		$_POST['dstbeginport'] = 0;
		$_POST['dstendport'] = 0;
	}

	if (($_POST['srcbeginport'] && !alias_expand($_POST['srcbeginport']) && !is_port($_POST['srcbeginport']))) {
		$input_errors[] = "The start source port must be an alias or integer between 1 and 65535.";
	}
	if (($_POST['srcendport'] && !alias_expand($_POST['srcendport']) && !is_port($_POST['srcendport']))) {
		$input_errors[] = "The end source port must be an alias or integer between 1 and 65535.";
	}
	if (($_POST['dstbeginport'] && !alias_expand($_POST['dstbeginport']) && !is_port($_POST['dstbeginport']))) {
		$input_errors[] = "The start destination port must be an alias or integer between 1 and 65535.";
	}
	if (($_POST['dstendport'] && !alias_expand($_POST['dstbeginport']) && !is_port($_POST['dstendport']))) {
		$input_errors[] = "The end destination port must be an alias or integer between 1 and 65535.";
	}

	if (!is_specialnet($_POST['srctype'])) {
		if (($_POST['src'] && !is_ipaddroranyalias($_POST['src']))) {
			$input_errors[] = "A valid source IP address or alias must be specified.";
		}
		if (($_POST['srcmask'] && !is_numericint($_POST['srcmask']))) {
			$input_errors[] = "A valid source bit count must be specified.";
		}
	}
	if (!is_specialnet($_POST['dsttype'])) {
		if (($_POST['dst'] && !is_ipaddroranyalias($_POST['dst']))) {
			$input_errors[] = "A valid destination IP address or alias must be specified.";
		}
		if (($_POST['dstmask'] && !is_numericint($_POST['dstmask']))) {
			$input_errors[] = "A valid destination bit count must be specified.";
		}
	}

	if ($_POST['srcbeginport'] > $_POST['srcendport']) {
		/* swap */
		$tmp = $_POST['srcendport'];
		$_POST['srcendport'] = $_POST['srcbeginport'];
		$_POST['srcbeginport'] = $tmp;
	}
	if ($_POST['dstbeginport'] > $_POST['dstendport']) {
		/* swap */
		$tmp = $_POST['dstendport'];
		$_POST['dstendport'] = $_POST['dstbeginport'];
		$_POST['dstbeginport'] = $tmp;
	}
	if ($_POST['os'])
		if( $_POST['proto'] != "tcp" )
			$input_errors[] = "OS detection is only valid with protocol tcp.";

	if (!$input_errors) {
		$filterent = array();
		$filterent['type'] = $_POST['type'];
		$filterent['interface'] = $_POST['interface'];

		/* Advanced options */
		$filterent['max-src-nodes'] = $_POST['max-src-nodes'];
		$filterent['max-src-states'] = $_POST['max-src-states'];
		$filterent['statetimeout'] = $_POST['statetimeout'];
		$filterent['statetype'] = $_POST['statetype'];
		$filterent['os'] = $_POST['os'];
		$filterent['max-src-conn-rate'] = $_POST['max-src-conn-rate'];
		$filterent['max-src-conn-rates'] = $_POST['max-src-conn-rates'];

		if ($_POST['proto'] != "any")
			$filterent['protocol'] = $_POST['proto'];
		else
			unset($filterent['protocol']);

		if ($_POST['proto'] == "icmp" && $_POST['icmptype'])
			$filterent['icmptype'] = $_POST['icmptype'];
		else
			unset($filterent['icmptype']);

		pconfig_to_address($filterent['source'], $_POST['src'],
			$_POST['srcmask'], $_POST['srcnot'],
			$_POST['srcbeginport'], $_POST['srcendport']);

		pconfig_to_address($filterent['destination'], $_POST['dst'],
			$_POST['dstmask'], $_POST['dstnot'],
			$_POST['dstbeginport'], $_POST['dstendport']);

                if ($_POST['disabled'])
                        $filterent['disabled'] = true;
                else
                        unset($filterent['disabled']);
                if ($_POST['log'])
                        $filterent['log'] = true;
                else
                        unset($filterent['log']);
		$filterent['descr'] = $_POST['descr'];

		if (isset($id) && $a_filter[$id])
			$a_filter[$id] = $filterent;
		else {
			if (is_numeric($after))
				array_splice($a_filter, $after+1, 0, array($filterent));
			else
				$a_filter[] = $filterent;
		}

		write_config();
		touch($d_filterconfdirty_path);

		header("Location: firewall_rules.php?if=" . $_POST['interface']);
		exit;
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Firewall: Rules: Edit");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
<script language="JavaScript">
<!--
var portsenabled = 1;

function ext_change() {
	if ((document.iform.srcbeginport.selectedIndex == 0) && portsenabled) {
		document.iform.srcbeginport_cust.disabled = 0;
	} else {
		document.iform.srcbeginport_cust.value = "";
		document.iform.srcbeginport_cust.disabled = 1;
	}
	if ((document.iform.srcendport.selectedIndex == 0) && portsenabled) {
		document.iform.srcendport_cust.disabled = 0;
	} else {
		document.iform.srcendport_cust.value = "";
		document.iform.srcendport_cust.disabled = 1;
	}
	if ((document.iform.dstbeginport.selectedIndex == 0) && portsenabled) {
		document.iform.dstbeginport_cust.disabled = 0;
	} else {
		document.iform.dstbeginport_cust.value = "";
		document.iform.dstbeginport_cust.disabled = 1;
	}
	if ((document.iform.dstendport.selectedIndex == 0) && portsenabled) {
		document.iform.dstendport_cust.disabled = 0;
	} else {
		document.iform.dstendport_cust.value = "";
		document.iform.dstendport_cust.disabled = 1;
	}

	if (!portsenabled) {
		document.iform.srcbeginport.disabled = 1;
		document.iform.srcendport.disabled = 1;
		document.iform.dstbeginport.disabled = 1;
		document.iform.dstendport.disabled = 1;
	} else {
		document.iform.srcbeginport.disabled = 0;
		document.iform.srcendport.disabled = 0;
		document.iform.dstbeginport.disabled = 0;
		document.iform.dstendport.disabled = 0;
	}
}

function typesel_change() {
	switch (document.iform.srctype.selectedIndex) {
		case 1:	/* single */
			document.iform.src.disabled = 0;
			document.iform.srcmask.value = "";
			document.iform.srcmask.disabled = 1;
			break;
		case 2:	/* network */
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
		case 1:	/* single */
			document.iform.dst.disabled = 0;
			document.iform.dstmask.value = "";
			document.iform.dstmask.disabled = 1;
			break;
		case 2:	/* network */
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

function proto_change() {
	if (document.iform.proto.selectedIndex < 3) {
		portsenabled = 1;
	} else {
		portsenabled = 0;
	}

	/* Disable OS knob if the proto is not TCP. */
	if (document.iform.proto.selectedIndex < 1) {
		document.forms[0].os.disabled = 0;
	} else {
		document.forms[0].os.disabled = 1;
	}

	if (document.iform.proto.selectedIndex == 3) {
		document.iform.icmptype.disabled = 0;
	} else {
		document.iform.icmptype.disabled = 1;
	}

	ext_change();
}

function src_rep_change() {
	document.iform.srcendport.selectedIndex = document.iform.srcbeginport.selectedIndex;
}
function dst_rep_change() {
	document.iform.dstendport.selectedIndex = document.iform.dstbeginport.selectedIndex;
}
//-->
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Firewall: Rules: Edit</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="firewall_rules_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Action</td>
                  <td width="78%" class="vtable">
<select name="type" class="formfld">
                      <?php $types = explode(" ", "Pass Block Reject"); foreach ($types as $type): ?>
                      <option value="<?=strtolower($type);?>" <?php if (strtolower($type) == strtolower($pconfig['type'])) echo "selected"; ?>>
                      <?=htmlspecialchars($type);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br>
                    <span class="vexpl">Choose what to do with packets that match
					the criteria specified below.<br>
Hint: the difference between block and reject is that with reject, a packet (TCP RST or ICMP port unreachable for UDP) is returned to the sender, whereas with block the packet is dropped silently. In either case, the original packet is discarded. Reject only works when the protocol is set to either TCP or UDP (but not &quot;TCP/UDP&quot;) below.</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Disabled</td>
                  <td width="78%" class="vtable">
                    <input name="disabled" type="checkbox" id="disabled" value="yes" <?php if ($pconfig['disabled']) echo "checked"; ?>>
                    <strong>Disable this rule</strong><br>
                    <span class="vexpl">Set this option to disable this rule without
					removing it from the list.</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Interface</td>
                  <td width="78%" class="vtable">
<select name="interface" class="formfld">
                      <?php $interfaces = array('wan' => 'WAN', 'lan' => 'LAN', 'pptp' => 'PPTP');
					  for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
					  	$interfaces['opt' . $i] = $config['interfaces']['opt' . $i]['descr'];
					  }
					  foreach ($interfaces as $iface => $ifacename): ?>
                      <option value="<?=$iface;?>" <?php if ($iface == $pconfig['interface']) echo "selected"; ?>>
                      <?=htmlspecialchars($ifacename);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br>
                    <span class="vexpl">Choose on which interface packets must
                    come in to match this rule.</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Protocol</td>
                  <td width="78%" class="vtable">
<select name="proto" class="formfld" onchange="proto_change()">
                      <?php $protocols = explode(" ", "TCP UDP TCP/UDP ICMP ICMP6 ESP AH GRE IPv6 IGMP any carp pfsync"); foreach ($protocols as $proto): ?>
                      <option value="<?=strtolower($proto);?>" <?php if (strtolower($proto) == $pconfig['proto']) echo "selected"; ?>>
                      <?=htmlspecialchars($proto);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br>
                    <span class="vexpl">Choose which IP protocol this rule should
                    match.<br>
                    Hint: in most cases, you should specify <em>TCP</em> &nbsp;here.</span></td>
                </tr>
                <tr>
                  <td valign="top" class="vncell">ICMP type</td>
                  <td class="vtable">
                    <select name="icmptype" class="formfld">
                      <?php

					  $icmptypes = array(
					  	"" => "any",
						"echorep" => "Echo reply",
					  	"unreach" => "Destination unreachable",
						"squench" => "Source quench",
						"redir" => "Redirect",
						"althost" => "Alternate Host",
						"echoreq" => "Echo",
						"routeradv" => "Router advertisement",
						"routersol" => "Router solicitation",
						"timex" => "Time exceeded",
						"paramprob" => "Invalid IP header",
						"timereq" => "Timestamp",
						"timerep" => "Timestamp reply",
						"inforeq" => "Information request",
						"inforep" => "Information reply",
						"maskreq" => "Address mask request",
						"maskrep" => "Address mask reply"
					  );

					  foreach ($icmptypes as $icmptype => $descr): ?>
                      <option value="<?=$icmptype;?>" <?php if ($icmptype == $pconfig['icmptype']) echo "selected"; ?>>
                      <?=htmlspecialchars($descr);?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                    <br>
                    <span class="vexpl">If you selected ICMP for the protocol above, you may specify an ICMP type here.</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Source</td>
                  <td width="78%" class="vtable">
<input name="srcnot" type="checkbox" id="srcnot" value="yes" <?php if ($pconfig['srcnot']) echo "checked"; ?>>
                    <strong>not</strong><br>
                    Use this option to invert the sense of the match.<br>
                    <br>
                    <table border="0" cellspacing="0" cellpadding="0">
                      <tr>
                        <td>Type:&nbsp;&nbsp;</td>
                        <td><select name="srctype" class="formfld" onChange="typesel_change()">
							<?php $sel = is_specialnet($pconfig['src']); ?>
                            <option value="any" <?php if ($pconfig['src'] == "any") { echo "selected"; } ?>>
                            any</option>
                            <option value="single" <?php if (($pconfig['srcmask'] == 32) && !$sel) { echo "selected"; $sel = 1; } ?>>
                            Single host or alias</option>
                            <option value="network" <?php if (!$sel) echo "selected"; ?>>
                            Network</option>
                            <option value="lan" <?php if ($pconfig['src'] == "lan") { echo "selected"; } ?>>
                            LAN subnet</option>
                            <option value="pptp" <?php if ($pconfig['src'] == "pptp") { echo "selected"; } ?>>
                            PPTP clients</option>
							<?php for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++): ?>
                            <option value="opt<?=$i;?>" <?php if ($pconfig['src'] == "opt" . $i) { echo "selected"; } ?>>
                            <?=htmlspecialchars($config['interfaces']['opt' . $i]['descr']);?> subnet</option>
							<?php endfor; ?>
                          </select></td>
                      </tr>
                      <tr>
                        <td>Address:&nbsp;&nbsp;</td>
                        <td><input autocomplete='off' onblur='actb_removedisp()' onkeypress='return (event.keyCode!=13);' onkeydown='actb_checkkey(event, this)' onkeyup='actb_tocomplete(this,event,addressarray);' name="src" type="text" class="formfldalias" id="src" size="20" value="<?php if (!is_specialnet($pconfig['src'])) echo htmlspecialchars($pconfig['src']);?>">
                        /
						<select name="srcmask" class="formfld" id="srcmask">
						<?php for ($i = 31; $i > 0; $i--): ?>
						<option value="<?=$i;?>" <?php if ($i == $pconfig['srcmask']) echo "selected"; ?>><?=$i;?></option>
						<?php endfor; ?>
						</select>
						</td>
					  </tr>
                    </table></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Source port range
                  </td>
                  <td width="78%" class="vtable">
                    <table border="0" cellspacing="0" cellpadding="0">
                      <tr>
                        <td>from:&nbsp;&nbsp;</td>
                        <td><select name="srcbeginport" class="formfld" onchange="src_rep_change();ext_change()">
                            <option value="">(other)</option>
                            <option value="any" <?php $bfound = 0; if ($pconfig['srcbeginport'] == "any") { echo "selected"; $bfound = 1; } ?>>any</option>
                            <?php foreach ($wkports as $wkport => $wkportdesc): ?>
                            <option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['srcbeginport']) {
																echo "selected";
																$bfound = 1;
															}?>>
                            <?=htmlspecialchars($wkportdesc);?>
                            </option>
                            <?php endforeach; ?>
                          </select> <input autocomplete='off' onblur='actb_removedisp()' onkeypress='return (event.keyCode!=13);'  onkeydown='actb_checkkey(event, this);' onkeyup='actb_tocomplete(this,event,customarray)' class="formfldalias" name="srcbeginport_cust" type="text" size="5" value="<?php if (!$bfound && $pconfig['srcbeginport']) echo $pconfig['srcbeginport']; ?>"></td>
                      </tr>
                      <tr>
                        <td>to:</td>
                        <td><select name="srcendport" class="formfld" onchange="ext_change()">
                            <option value="">(other)</option>
                            <option value="any" <?php $bfound = 0; if ($pconfig['srcendport'] == "any") { echo "selected"; $bfound = 1; } ?>>any</option>
                            <?php foreach ($wkports as $wkport => $wkportdesc): ?>
                            <option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['srcendport']) {
																echo "selected";
																$bfound = 1;
															}?>>
                            <?=htmlspecialchars($wkportdesc);?>
                            </option>
                            <?php endforeach; ?>
                          </select> <input autocomplete='off' onblur='actb_removedisp()' onkeypress='return (event.keyCode!=13);'  onkeydown='actb_checkkey(event, this);' onkeyup='actb_tocomplete(this,event,customarray)' class="formfldalias" name="srcendport_cust" type="text" size="5" value="<?php if (!$bfound && $pconfig['srcendport']) echo $pconfig['srcendport']; ?>"></td>
                      </tr>
                    </table>
                    <br>
                    <span class="vexpl">Specify the port or port range for
                    the source of the packet for this rule. This is usually not equal to the destination port range (and is often &quot;any&quot;). <br>
                    Hint: you can leave the <em>'to'</em> field empty if you only
                    want to filter a single port</span></td>

                <tr>
                  <td width="22%" valign="top" class="vncellreq">Source OS</td>
                  <td width="78%" class="vtable">OS Type:&nbsp;
                    <select name="os" id="os" class="formfld">
                      <?php
                                          $ostypes = array(
						"" => "any",
                                                "AIX" => "AIX",
                                                "Linux" => "Linux",
                                                "FreeBSD" => "FreeBSD",
                                                "NetBSD" => "NetBSD",
                                                "OpenBSD" => "OpenBSD",
                                                "Solaris" => "Solaris",
                                                "MacOS" => "MacOS",
                                                "Windows" => "Windows",
                                                "Novell" => "Novell"
                                          );

                                          foreach ($ostypes as $ostype => $descr): ?>
                      <option value="<?=$ostype;?>" <?php if ($ostype == $pconfig['os']) echo "selected"; ?>>
                      <?=htmlspecialchars($descr);?>
                      </option>
                      <?php endforeach; ?>
                    </select><br>
                    Note: this only works for TCP rules</td>
		</tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Destination</td>
                  <td width="78%" class="vtable">
                    <input name="dstnot" type="checkbox" id="dstnot" value="yes" <?php if ($pconfig['dstnot']) echo "checked"; ?>>
                    <strong>not</strong><br>
                    Use this option to invert the sense of the match.<br>
                    <br>
                    <table border="0" cellspacing="0" cellpadding="0">
                      <tr>
                        <td>Type:&nbsp;&nbsp;</td>
                        <td><select name="dsttype" class="formfld" onChange="typesel_change()">
                            <?php $sel = is_specialnet($pconfig['dst']); ?>
                            <option value="any" <?php if ($pconfig['dst'] == "any") { echo "selected"; } ?>>
                            any</option>
                            <option value="single" <?php if (($pconfig['dstmask'] == 32) && !$sel) { echo "selected"; $sel = 1; } ?>>
                            Single host or alias</option>
                            <option value="network" <?php if (!$sel) echo "selected"; ?>>
                            Network</option>
                            <option value="lan" <?php if ($pconfig['dst'] == "lan") { echo "selected"; } ?>>
                            LAN subnet</option>
                            <option value="pptp" <?php if ($pconfig['dst'] == "pptp") { echo "selected"; } ?>>
                            PPTP clients</option>
							<?php for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++): ?>
                            <option value="opt<?=$i;?>" <?php if ($pconfig['dst'] == "opt" . $i) { echo "selected"; } ?>>
                            <?=htmlspecialchars($config['interfaces']['opt' . $i]['descr']);?> subnet</option>
							<?php endfor; ?>
                          </select></td>
                      </tr>
                      <tr>
                        <td>Address:&nbsp;&nbsp;</td>
                        <td><input name="dst" autocomplete='off' onblur='actb_removedisp()' onkeypress='return (event.keyCode!=13);'  onkeydown='actb_checkkey(event, this);' onkeyup='actb_tocomplete(this,event,addressarray)' type="text" class="formfldalias" id="dst" size="20" value="<?php if (!is_specialnet($pconfig['dst'])) echo htmlspecialchars($pconfig['dst']);?>">
                          /
                          <select name="dstmask" class="formfld" id="dstmask">
						<?php for ($i = 31; $i > 0; $i--): ?>
						<option value="<?=$i;?>" <?php if ($i == $pconfig['dstmask']) echo "selected"; ?>><?=$i;?></option>
						<?php endfor; ?>
						</select></td>
                      </tr>
                    </table></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Destination port
                    range </td>
                  <td width="78%" class="vtable">
                    <table border="0" cellspacing="0" cellpadding="0">
                      <tr>
                        <td>from:&nbsp;&nbsp;</td>
                        <td><select name="dstbeginport" class="formfld" onchange="dst_rep_change();ext_change()">
                            <option value="">(other)</option>
                            <option value="any" <?php $bfound = 0; if ($pconfig['dstbeginport'] == "any") { echo "selected"; $bfound = 1; } ?>>any</option>
                            <?php foreach ($wkports as $wkport => $wkportdesc): ?>
                            <option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['dstbeginport']) {
																echo "selected";
																$bfound = 1;
															}?>>
                            <?=htmlspecialchars($wkportdesc);?>
                            </option>
                            <?php endforeach; ?>
                          </select> <input autocomplete='off' onblur='actb_removedisp()' onkeypress='return (event.keyCode!=13);' onkeydown='actb_checkkey(event, this);' onkeyup='actb_tocomplete(this,event,customarray)' class="formfldalias" name="dstbeginport_cust" type="text" size="5" value="<?php if (!$bfound && $pconfig['dstbeginport']) echo $pconfig['dstbeginport']; ?>"></td>
                      </tr>
                      <tr>
                        <td>to:</td>
                        <td><select name="dstendport" class="formfld" onchange="ext_change()">
                            <option value="">(other)</option>
                            <option value="any" <?php $bfound = 0; if ($pconfig['dstendport'] == "any") { echo "selected"; $bfound = 1; } ?>>any</option>
                            <?php foreach ($wkports as $wkport => $wkportdesc): ?>
                            <option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['dstendport']) {
																echo "selected";
																$bfound = 1;
															}?>>
                            <?=htmlspecialchars($wkportdesc);?>
                            </option>
                            <?php endforeach; ?>
                          </select> <input autocomplete='off' onblur='actb_removedisp()' onkeypress='return (event.keyCode!=13);' onkeydown='actb_checkkey(event, this);' onkeyup='actb_tocomplete(this,event,customarray)' class="formfldalias" name="dstendport_cust" type="text" size="5" value="<?php if (!$bfound && $pconfig['dstendport']) echo $pconfig['dstendport']; ?>"></td>
                      </tr>
                    </table>
                    <br> <span class="vexpl">Specify the port or port range for
                    the destination of the packet for this rule.<br>
                    Hint: you can leave the <em>'to'</em> field empty if you only
                    want to filter a single port</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Log</td>
                  <td width="78%" class="vtable">
                    <input name="log" type="checkbox" id="log" value="yes" <?php if ($pconfig['log']) echo "checked"; ?>>
                    <strong>Log packets that are handled by this rule</strong><br>
                    <span class="vexpl">Hint: the firewall has limited local log
                    space. Don't turn on logging for everything. If you want to
                    do a lot of logging, consider using a remote syslog server
                    (see the <a href="diag_logs_settings.php">Diagnostics: System
                    logs: Settings</a> page).</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">Description</td>
                  <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
                    <br> <span class="vexpl">You may enter a description here
                    for your reference (not parsed).</span></td>
                </tr>
               <tr>
                  <td width="22%" valign="top" class="vncell">Advanced Options</td>
                  <td width="78%" class="vtable">
			<input name="max-src-nodes" id="max-src-nodes" value="<?php echo $pconfig['max-src-nodes'] ?>"><br> Simultaneous client connection limit<p>
			<input name="max-src-states" id="max-src-states" value="<?php echo $pconfig['max-src-states'] ?>"><br> Maximum state entries per host<p>
			<input name="max-src-conn-rate" id="max-src-conn-rate" value="<?php echo $pconfig['max-src-conn-rate'] ?>"> /
			<select name="max-src-conn-rates" id="max-src-conn-rates">
			 <option value=""<?php if(intval($pconfig['max-src-conn-rates']) < 1) echo " selected"; ?>></option>
			 <?php
			   for($x=1; $x<255; $x++) {
				if($x == $pconfig['max-src-conn-rates'])
					$selected = " selected";
				else 
					$selected = "";
				echo "<option value=\"{$x}\"{$selected}>{$x}</option>\n";
			   }
			 ?>
			 </select>
			<br>
			Maximum new connections / per second
			<p><strong>NOTE: Leave these fields blank to disable this feature.</strong>
		    </td>
                </tr>
               <tr>
                  <td width="22%" valign="top" class="vncell">State Type</td>
                  <td width="78%" class="vtable">
			<select name="statetype">
			<option value="keep state" <?php if(!isset($pconfig['statetype']) or $pconfig['statetype'] == "keep state") echo "selected"; ?>>keep state</option>
			<option value="modulate state" <?php if($pconfig['statetype'] == "modulate state")  echo "selected"; ?>>modulate state</option>
			<option value="synproxy state"<?php if($pconfig['statetype'] == "synproxy state")  echo "selected"; ?>>synproxy state</option>
			<option value="none"<?php if($pconfig['statetype'] == "none") echo "selected"; ?>>none</option>
			</select><br>HINT: Select which type of state tracking mechanism you would like to use.  If in doubt, use keep state.
			<p><strong>
			<table>
			<tr><td width="25%"><li>keep state</li></td><td>works with TCP, UDP, and ICMP.</td></tr>
			<tr><td width="25%"><li>modulate state</li></td><td>works only with TCP. pfSense will generate strong Initial Sequence Numbers (ISNs) for packets matching this rule.</li></td></tr>
			<tr><td width="25%"><li>synproxy state</li></td><td>proxies incoming TCP connections to help protect servers from spoofed TCP SYN floods. This option includes the functionality of keep state and modulate state combined.</td></tr>
			<tr><td width="25%"><li>none</li></td><td>do not use state mechanisms to keep track.  this is only useful if your doing advanced queueing in certain situations.  please check the faq.</td></tr>
			</table>
			</strong>
		    </td>
                </tr>

		<tr>
                  <td width="22%" valign="top" class="vncell">State Timeout</td>
                  <td width="78%" class="vtable">
			<input name="statetimeout" value="<?php echo $pconfig['frags'] ?>">
			<p><strong>Leave blank for default.  Amount is in seconds.
			</strong>
		    </td>
		</tr>

		<?php
			/* build a list of gateways */
			$gateways = array();
			foreach($config['interfaces'] as $int) {
				if($int['gateway'] <> "")
					$gateways[]=$int['gateway'];
			}
		?>
		<tr>
                  <td width="22%" valign="top" class="vncell">Gateway</td>
                  <td width="78%" class="vtable">
			<select name='gateway'>
			<?php
				foreach($gateways as $gw) {
					if($gw == $pconfig['gateway')
						$selected = " SELECTED";
					else
						$selected = "";
					if($gw <> "")
						echo "<option value=\"{$gw}\" {$selected}>{$gw}</option>\n";
				}
			?>
			</select>
			<input name="statetimeout" value="<?php echo $pconfig['frags'] ?>">
			<p><strong>Leave blank for default.
			</strong>
		    </td>
		</tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="Save">  <input type="button" class="formbtn" value="Cancel" onclick="history.back()">
                    <?php if (isset($id) && $a_filter[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>">
                    <?php endif; ?>
                    <input name="after" type="hidden" value="<?=$after;?>">
                  </td>
                </tr>
              </table>
</form>
<script language="JavaScript">
<!--
ext_change();
typesel_change();
proto_change();

<?php
$isfirst = 0;
$aliases = "";
$addrisfirst = 0;
$aliasesaddr = "";
if($config['aliases']['alias'] <> "" and is_array($config['aliases']['alias']))
	foreach($config['aliases']['alias'] as $alias_name) {
		if(!stristr($alias_name['address'], ".")) {
			if($isfirst == 1) $aliases .= ",";
			$aliases .= "'" . $alias_name['name'] . "'";
			$isfirst = 1;
		} else {
			if($addrisfirst == 1) $aliasesaddr .= ",";
			$aliasesaddr .= "'" . $alias_name['name'] . "'";
			$addrisfirst = 1;
		}
	}
?>

var addressarray=new Array(<?php echo $aliasesaddr; ?>);
var customarray=new Array(<?php echo $aliases; ?>);

//-->
</script>
<script type="text/javascript" language="javascript" src="auto_complete_helper.js">
</script>
<?php include("fend.inc"); ?>
</body>
</html>
