#!/usr/local/bin/php
<?php 
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

require("guiconfig.inc");

if (!is_array($config['nat']['rule'])) {
	$config['nat']['rule'] = array();
}
nat_rules_sort();
$a_nat = &$config['nat']['rule'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_nat[$id]) {
	$pconfig['extaddr'] = $a_nat[$id]['external-address'];
	$pconfig['proto'] = $a_nat[$id]['protocol'];
	list($pconfig['beginport'],$pconfig['endport']) = explode("-", $a_nat[$id]['external-port']);
	$pconfig['localip'] = $a_nat[$id]['target'];
	$pconfig['localbeginport'] = $a_nat[$id]['local-port'];
	$pconfig['descr'] = $a_nat[$id]['descr'];
	$pconfig['interface'] = $a_nat[$id]['interface'];
	if (!$pconfig['interface'])
		$pconfig['interface'] = "wan";
} else {
	$pconfig['interface'] = "wan";
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
	
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "interface proto beginport localip localbeginport");
	$reqdfieldsn = explode(",", "Interface,Protocol,Start port,NAT IP,Local port");
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (($_POST['beginport'] && !is_port($_POST['beginport']))) {
		$input_errors[] = "The start port must be an integer between 1 and 65535.";
	}
	if (($_POST['endport'] && !is_port($_POST['endport']))) {
		$input_errors[] = "The end port must be an integer between 1 and 65535.";
	}
	if (($_POST['localbeginport'] && !is_port($_POST['localbeginport']))) {
		$input_errors[] = "The local port must be an integer between 1 and 65535.";
	}
	if (($_POST['localip'] && !is_ipaddroralias($_POST['localip']))) {
		$input_errors[] = "A valid NAT IP address or host alias must be specified.";
	}
	
	if ($_POST['beginport'] > $_POST['endport']) {
		/* swap */
		$tmp = $_POST['endport'];
		$_POST['endport'] = $_POST['beginport'];
		$_POST['beginport'] = $tmp;
	}
	
	if (!$input_errors) {
		if (($_POST['endport'] - $_POST['beginport'] + $_POST['localbeginport']) > 65535)
			$input_errors[] = "The target port range must lie between 1 and 65535.";
	}
	
	/* check for overlaps */
	foreach ($a_nat as $natent) {
		if (isset($id) && ($a_nat[$id]) && ($a_nat[$id] === $natent))
			continue;
		if ($natent['interface'] != $_POST['interface'])
			continue;
		if ($natent['external-address'] != $_POST['extaddr'])
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
		
		if (isset($id) && $a_nat[$id])
			$a_nat[$id] = $natent;
		else
			$a_nat[] = $natent;
		
		touch($d_natconfdirty_path);
		
		if ($_POST['autoadd']) {
			/* auto-generate a matching firewall rule */
			$filterent = array();		
			$filterent['interface'] = $_POST['interface'];
			$filterent['protocol'] = $_POST['proto'];
			$filterent['source']['any'] = "";
			$filterent['destination']['address'] = $_POST['localip'];
			
			$dstpfrom = $_POST['localbeginport'];
			$dstpto = $dstpfrom + $_POST['endport'] - $_POST['beginport'];
			
			if ($dstpfrom == $dstpto)
				$filterent['destination']['port'] = $dstpfrom;
			else
				$filterent['destination']['port'] = $dstpfrom . "-" . $dstpto;
			
			$filterent['descr'] = "NAT " . $_POST['descr'];
			
			$config['filter']['rule'][] = $filterent;
			
			touch($d_filterconfdirty_path);
		}
		
		write_config();
		
		header("Location: firewall_nat.php");
		exit;
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Firewall: NAT: Edit");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
<script language="JavaScript">
<!--
function ext_change() {
	if (document.iform.beginport.selectedIndex == 0) {
		document.iform.beginport_cust.disabled = 0;
	} else {
		document.iform.beginport_cust.value = "";
		document.iform.beginport_cust.disabled = 1;
	}
	if (document.iform.endport.selectedIndex == 0) {
		document.iform.endport_cust.disabled = 0;
	} else {
		document.iform.endport_cust.value = "";
		document.iform.endport_cust.disabled = 1;
	}
	if (document.iform.localbeginport.selectedIndex == 0) {
		document.iform.localbeginport_cust.disabled = 0;
	} else {
		document.iform.localbeginport_cust.value = "";
		document.iform.localbeginport_cust.disabled = 1;
	}
}
function ext_rep_change() {
	document.iform.endport.selectedIndex = document.iform.beginport.selectedIndex;
	document.iform.localbeginport.selectedIndex = document.iform.beginport.selectedIndex;
}
//-->
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Firewall: NAT: Edit</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="firewall_nat_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
			  	<tr>
                  <td width="22%" valign="top" class="vncellreq">Interface</td>
                  <td width="78%" class="vtable">
					<select name="interface" class="formfld">
						<?php
						$interfaces = array('wan' => 'WAN');
						for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
							$interfaces['opt' . $i] = $config['interfaces']['opt' . $i]['descr'];
						}
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
                    <select name="extaddr" class="formfld">
					  <option value="" <?php if (!$pconfig['extaddr']) echo "selected"; ?>>Interface address</option>
                      <?php
					  if (is_array($config['nat']['servernat'])):
						  foreach ($config['nat']['servernat'] as $sn): ?>
                      <option value="<?=$sn['ipaddr'];?>" <?php if ($sn['ipaddr'] == $pconfig['extaddr']) echo "selected"; ?>><?=htmlspecialchars("{$sn['ipaddr']} ({$sn['descr']})");?></option>
                      <?php endforeach; endif; ?>
                    </select><br>
                    <span class="vexpl">
					If you want this rule to apply to another IP address than the IP address of the interface chosen above,
					select it here (you need to define IP addresses on the
					<a href="firewall_nat_server.php">Server NAT</a> page first).</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Protocol</td>
                  <td width="78%" class="vtable"> 
                    <select name="proto" class="formfld">
                      <?php $protocols = explode(" ", "TCP UDP TCP/UDP"); foreach ($protocols as $proto): ?>
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
                        <td><select name="beginport" class="formfld" onChange="ext_rep_change();ext_change()">
                            <option value="">(other)</option>
                            <?php $bfound = 0; foreach ($wkports as $wkport => $wkportdesc): ?>
                            <option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['beginport']) {
																echo "selected";
																$bfound = 1;
															}?>>
							<?=htmlspecialchars($wkportdesc);?>
							</option>
                            <?php endforeach; ?>
                          </select> <input name="beginport_cust" type="text" size="5" value="<?php if (!$bfound) echo $pconfig['beginport']; ?>"></td>
                      </tr>
                      <tr> 
                        <td>to:</td>
                        <td><select name="endport" class="formfld" onChange="ext_change()">
                            <option value="">(other)</option>
                            <?php $bfound = 0; foreach ($wkports as $wkport => $wkportdesc): ?>
                            <option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['endport']) {
																echo "selected";
																$bfound = 1;
															}?>>
							<?=htmlspecialchars($wkportdesc);?>
							</option>
							<?php endforeach; ?>
                          </select> <input name="endport_cust" type="text" size="5" value="<?php if (!$bfound) echo $pconfig['endport']; ?>"></td>
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
                    <input name="localip" type="text" class="formfldalias" id="localip" size="20" value="<?=htmlspecialchars($pconfig['localip']);?>"> 
                    <br> <span class="vexpl">Enter the internal IP address of 
                    the server on which you want to map the ports.<br>
                    e.g. <em>192.168.1.12</em></span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Local port</td>
                  <td width="78%" class="vtable"> 
                    <select name="localbeginport" class="formfld" onChange="ext_change()">
                      <option value="">(other)</option>
                      <?php $bfound = 0; foreach ($wkports as $wkport => $wkportdesc): ?>
                      <option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['localbeginport']) {
																echo "selected";
																$bfound = 1;
															}?>>
					  <?=htmlspecialchars($wkportdesc);?>
					  </option>
                      <?php endforeach; ?>
                    </select> <input name="localbeginport_cust" type="text" size="5" value="<?php if (!$bfound) echo $pconfig['localbeginport']; ?>"> 
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
                    <input name="descr" type="text" class="formfld" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>"> 
                    <br> <span class="vexpl">You may enter a description here 
                    for your reference (not parsed).</span></td>
                </tr><?php if (!(isset($id) && $a_nat[$id])): ?>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="autoadd" type="checkbox" id="autoadd" value="yes">
                    <strong>Auto-add a firewall rule to permit traffic through 
                    this NAT rule</strong></td>
                </tr><?php endif; ?>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="Save"> 
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
<?php include("fend.inc"); ?>
</body>
</html>
