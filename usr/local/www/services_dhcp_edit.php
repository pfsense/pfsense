#!/usr/local/bin/php
<?php 
/*
	services_dhcp_edit.php
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

$if = $_GET['if'];
if ($_POST['if'])
	$if = $_POST['if'];
	
if (!$if) {
	header("Location: services_dhcp.php");
	exit;
}

if (!is_array($config['dhcpd'][$if]['staticmap'])) {
	$config['dhcpd'][$if]['staticmap'] = array();
}
staticmaps_sort($if);
$a_maps = &$config['dhcpd'][$if]['staticmap'];
$ifcfg = &$config['interfaces'][$if];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_maps[$id]) {
	$pconfig['mac'] = $a_maps[$id]['mac'];
	$pconfig['ipaddr'] = $a_maps[$id]['ipaddr'];
	$pconfig['descr'] = $a_maps[$id]['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "mac");
	$reqdfieldsn = explode(",", "MAC address");
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (($_POST['ipaddr'] && !is_ipaddr($_POST['ipaddr']))) {
		$input_errors[] = "A valid IP address must be specified.";
	}
	if (($_POST['mac'] && !is_macaddr($_POST['mac']))) {
		$input_errors[] = "A valid MAC address must be specified.";
	}

	/* check for overlaps */
	foreach ($a_maps as $mapent) {
		if (isset($id) && ($a_maps[$id]) && ($a_maps[$id] === $mapent))
			continue;

		if (($mapent['mac'] == $_POST['mac']) || ($_POST['ipaddr'] && (ip2long($mapent['ipaddr']) == ip2long($_POST['ipaddr'])))) {
			$input_errors[] = "This IP or MAC address already exists.";
			break;
		}
	}
		
	/* make sure it's not within the dynamic subnet */
	if ($_POST['ipaddr']) {
		$dynsubnet_start = ip2long($config['dhcpd'][$if]['range']['from']);
		$dynsubnet_end = ip2long($config['dhcpd'][$if]['range']['to']);
		$lansubnet_start = (ip2long($ifcfg['ipaddr']) & gen_subnet_mask_long($ifcfg['subnet']));
		$lansubnet_end = (ip2long($ifcfg['ipaddr']) | (~gen_subnet_mask_long($ifcfg['subnet'])));
		
		if ((ip2long($_POST['ipaddr']) >= $dynsubnet_start) &&
				(ip2long($_POST['ipaddr']) <= $dynsubnet_end)) {
			$input_errors[] = "Static IP addresses may not lie within the dynamic client range.";
		}
		if ((ip2long($_POST['ipaddr']) < $lansubnet_start) ||
			(ip2long($_POST['ipaddr']) > $lansubnet_end)) {
			$input_errors[] = "The IP address must lie in the {$ifcfg['descr']} subnet.";
		}
	}

	if (!$input_errors) {
		$mapent = array();
		$mapent['mac'] = $_POST['mac'];
		$mapent['ipaddr'] = $_POST['ipaddr'];
		$mapent['descr'] = $_POST['descr'];

		if (isset($id) && $a_maps[$id])
			$a_maps[$id] = $mapent;
		else
			$a_maps[] = $mapent;
		
		touch($d_staticmapsdirty_path);
		
		write_config();
		
		header("Location: services_dhcp.php?if={$if}");
		exit;
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Services: DHCP: Edit static mapping");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Services: DHCP: Edit static mapping</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="services_dhcp_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">MAC address</td>
                  <td width="78%" class="vtable"> 
                    <input name="mac" type="text" class="formfld" id="mac" size="30" value="<?=htmlspecialchars($pconfig['mac']);?>"> 
                    <br>
                    <span class="vexpl">Enter a MAC address in the following format: 
                    xx:xx:xx:xx:xx:xx</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">IP address</td>
                  <td width="78%" class="vtable"> 
                    <input name="ipaddr" type="text" class="formfld" id="ipaddr" size="20" value="<?=htmlspecialchars($pconfig['ipaddr']);?>">
                    <br>
                    If no IP address is given, one will be dynamically allocated  from the pool.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">Description</td>
                  <td width="78%" class="vtable"> 
                    <input name="descr" type="text" class="formfld" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>"> 
                    <br> <span class="vexpl">You may enter a description here 
                    for your reference (not parsed).</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="Save"> 
                    <?php if (isset($id) && $a_maps[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>">
                    <?php endif; ?>
                    <input name="if" type="hidden" value="<?=$if;?>"> 
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
