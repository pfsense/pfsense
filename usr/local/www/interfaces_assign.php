#!/usr/local/bin/php
<?php 
/*
	interfaces_assign.php
	part of m0n0wall (http://m0n0.ch/wall)
	Written by Jim McBeath based on existing m0n0wall files
	
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

$pgtitle = array("Interfaces", "Assign network ports");
require("guiconfig.inc");

/*
	In this file, "port" refers to the physical port name,
	while "interface" refers to LAN, WAN, or OPTn.
*/

/* get list without VLAN interfaces */
$portlist = get_interface_list();

/* add VLAN interfaces */
if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {
	$i = 0;
	foreach ($config['vlans']['vlan'] as $vlan) {
		$portlist['vlan' . $i] = $vlan;
		$portlist['vlan' . $i]['isvlan'] = true;
		$i++;
	}
}

if ($_POST) {

	unset($input_errors);

	/* input validation */

	/* Build a list of the port names so we can see how the interfaces map */
	$portifmap = array();
	foreach ($portlist as $portname => $portinfo)
		$portifmap[$portname] = array();

	/* Go through the list of ports selected by the user,
	   build a list of port-to-interface mappings in portifmap */
	foreach ($_POST as $ifname => $ifport) {
		if (($ifname == 'lan') || ($ifname == 'wan') || (substr($ifname, 0, 3) == 'opt'))
			$portifmap[$ifport][] = strtoupper($ifname);
	}

	/* Deliver error message for any port with more than one assignment */
	foreach ($portifmap as $portname => $ifnames) {
		if (count($ifnames) > 1) {
			$errstr = "Port " . $portname .
				" was assigned to " . count($ifnames) .
				" interfaces:";
				
			foreach ($portifmap[$portname] as $ifn)
				$errstr .= " " . $ifn;
			
			$input_errors[] = $errstr;
		}
	}


	if (!$input_errors) {
		/* No errors detected, so update the config */
		foreach ($_POST as $ifname => $ifport) {
		
			if (($ifname == 'lan') || ($ifname == 'wan') ||
				(substr($ifname, 0, 3) == 'opt')) {
				
				if (!is_array($ifport)) {
					$config['interfaces'][$ifname]['if'] = $ifport;
					
					/* check for wireless interfaces, set or clear ['wireless'] */
					if (preg_match($g['wireless_regex'], $ifport)) {
						if (!is_array($config['interfaces'][$ifname]['wireless']))
							$config['interfaces'][$ifname]['wireless'] = array();
					} else {
						unset($config['interfaces'][$ifname]['wireless']);
					}
					
					/* make sure there is a name for OPTn */
					if (substr($ifname, 0, 3) == 'opt') {
						if (!isset($config['interfaces'][$ifname]['descr']))
							$config['interfaces'][$ifname]['descr'] = strtoupper($ifname);
					}
				}
			}
		}
	
		write_config();
		touch($d_sysrebootreqd_path);
	}
}

if ($_GET['act'] == "del") {
	$id = $_GET['id'];
	
	unset($config['interfaces'][$id]);	/* delete the specified OPTn */

	/* shift down other OPTn interfaces to get rid of holes */
	$i = substr($id, 3); /* the number of the OPTn port being deleted */
	$i++;
	
	/* look at the following OPTn ports */
	while (is_array($config['interfaces']['opt' . $i])) {
		$config['interfaces']['opt' . ($i - 1)] =
			$config['interfaces']['opt' . $i];
		
		if ($config['interfaces']['opt' . ($i - 1)]['descr'] == "OPT" . $i)
			$config['interfaces']['opt' . ($i - 1)]['descr'] = "OPT" . ($i - 1);
		
		unset($config['interfaces']['opt' . $i]);
		$i++;
	}

	write_config();
	touch($d_sysrebootreqd_path);
	header("Location: interfaces_assign.php");
	exit;
}

if ($_GET['act'] == "add") {
	/* find next free optional interface number */
	$i = 1;
	while (is_array($config['interfaces']['opt' . $i]))
		$i++;
	
	$newifname = 'opt' . $i;
	$config['interfaces'][$newifname] = array();
	$config['interfaces'][$newifname]['descr'] = "OPT" . $i;
	
	/* Find an unused port for this interface */
	foreach ($portlist as $portname => $portinfo) {
		$portused = false;
		foreach ($config['interfaces'] as $ifname => $ifdata) {
			if ($ifdata['if'] == $portname) {
				$portused = true;
				break;
			}
		}
		if (!$portused) {
			$config['interfaces'][$newifname]['if'] = $portname;
			if (preg_match($g['wireless_regex'], $portname))
				$config['interfaces'][$newifname]['wireless'] = array();
			break;
		}
	}
	
	write_config();
	touch($d_sysrebootreqd_path);
	header("Location: interfaces_assign.php");
	exit;
}

?>
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if (file_exists($d_sysrebootreqd_path)) print_info_box(get_std_save_message(0)); ?>
<form action="interfaces_assign.php" method="post" name="iform" id="iform">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
  <ul id="tabnav">
    <li class="tabact">Interface assignments</li>
    <li class="tabinact"><a href="interfaces_vlan.php">VLANs</a></li>
  </ul>
  </td></tr>
  <tr> 
    <td class="tabcont">
                    <table border="0" cellpadding="0" cellspacing="0">
                      <tr> 
	<td class="listhdrr">Interface</td>
	<td class="listhdr">Network port</td>
	<td class="list">&nbsp;</td>
  </tr>
  <?php foreach ($config['interfaces'] as $ifname => $iface):
  	if ($iface['descr'])
		$ifdescr = $iface['descr'];
	else
		$ifdescr = strtoupper($ifname);
	?>
  <tr> 
	<td class="listlr" valign="middle"><strong><?=$ifdescr;?></strong></td>
	  <td valign="middle" class="listr">
		<select name="<?=$ifname;?>" class="formfld" id="<?=$ifname;?>">
		  <?php foreach ($portlist as $portname => $portinfo): ?>
		  <option value="<?=$portname;?>" <?php if ($portname == $iface['if']) echo "selected";?>> 
		  <?php if ($portinfo['isvlan']) {
		  			$descr = "VLAN {$portinfo['tag']} on {$portinfo['if']}";
					if ($portinfo['descr'])
						$descr .= " (" . $portinfo['descr'] . ")";
					echo htmlspecialchars($descr);
				  } else
					echo htmlspecialchars($portname . " (" . $portinfo['mac'] . ")");
		  ?>
		  </option>
		  <?php endforeach; ?>
		</select>
		</td>
		<td valign="middle" class="list"> 
		  <?php if (($ifname != 'lan') && ($ifname != 'wan')): ?>
		  <a href="interfaces_assign.php?act=del&id=<?=$ifname;?>"><img src="x.gif" title="delete interface" width="17" height="17" border="0"></a> 
		  <?php endif; ?>
		</td>
  </tr>
  <?php endforeach; ?>
  <?php if (count($config['interfaces']) < count($portlist)): ?>
  <tr>
	<td class="list" colspan="2"></td>
	<td class="list" nowrap>
	<a href="interfaces_assign.php?act=add"><img src="plus.gif" title="add interface" width="17" height="17" border="0"></a>
	</td>
  </tr>
  <?php else: ?>
  <tr>
	<td class="list" colspan="3" height="10"></td>
  </tr>
  <?php endif; ?>
</table>
  <input name="Submit" type="submit" class="formbtn" value="Save"><br><br>
<p><span class="vexpl"><strong><span class="red">Warning:</span><br>
</strong>After you click &quot;Save&quot;, you must reboot the firewall to make the changes take effect. You may also have to do one or more of the following steps before you can access your firewall again: </span></p>
<ul>
  <li><span class="vexpl">change the IP address of your computer</span></li>
  <li><span class="vexpl">renew its DHCP lease</span></li>
  <li><span class="vexpl">access the webGUI with the new IP address</span></li>
</ul></td>
	</tr>
</table>
</form>
<?php include("fend.inc"); ?>
