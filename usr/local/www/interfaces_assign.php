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

##|+PRIV
##|*IDENT=page-interfaces-assignnetworkports
##|*NAME=Interfaces: Assign network ports page
##|*DESCR=Allow access to the 'Interfaces: Assign network ports' page.
##|*MATCH=interfaces_assign.php*
##|-PRIV

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
	foreach ($config['vlans']['vlan'] as $vlan) {
		$portlist[$vlan['vlanif']] = $vlan;
		$portlist[$vlan['vlanif']]['isvlan'] = true;
	}
}

/* add Bridge interfaces */
if (is_array($config['bridges']['bridged']) && count($config['bridges']['bridged'])) {
        foreach ($config['bridges']['bridged'] as $bridge) {
                $portlist[$bridge['bridgeif']] = $bridge;
                $portlist[$bridge['bridgeif']]['isbridge'] = true;
        }
}

/* add GIF interfaces */
if (is_array($config['gifs']['gif']) && count($config['gifs']['gif'])) {
        foreach ($config['gifs']['gif'] as $gif) {
                $portlist[$gif['gifif']] = $gif;
                $portlist[$gif['gifif']]['isgif'] = true;
        }
}

/* add GRE interfaces */
if (is_array($config['gres']['gre']) && count($config['gres']['gre'])) {
        foreach ($config['gres']['gre'] as $gre) {
                $portlist[$gre['greif']] = $gre;
                $portlist[$gre['greif']]['isgre'] = true;
        }
}

/* add LAGG interfaces */
if (is_array($config['laggs']['lagg']) && count($config['laggs']['lagg'])) {
        foreach ($config['laggs']['lagg'] as $lagg) {
                $portlist[$lagg['laggif']] = $lagg;
                $portlist[$lagg['laggif']]['islagg'] = true;
		/* LAGG members cannot be assigned */
		$lagifs = explode(',', $lagg['members']);
		foreach ($lagifs as $lagif)
			if (isset($portlist[$lagif]))
				unset($portlist[$lagif]);
        }
}


/* add PPP interfaces */
if (is_array($config['ppps']['ppp']) && count($config['ppps']['ppp'])) {
	$i = 0;
	foreach ($config['ppps']['ppp'] as $ppp) {
		$portname = 'ppp_' . basename($ppp['port']);
		$portlist[$portname] = $ppp;
		$portlist[$portname]['isppp'] = true;
		$i++;
	}
}

if ($_POST['apply']) {
	if (file_exists("/var/run/interface_mismatch_reboot_needed"))
		exec("/etc/rc.reboot");
	else {
		write_config();

		$retval = 0;
		$savemsg = get_std_save_message($retval);

		config_lock();
		$retval = filter_configure();
		config_unlock();

		if (stristr($retval, "error") <> true)
			$savemsg = get_std_save_message($retval);
		else
			$savemsg = $retval;

		unlink_if_exists("/tmp/reload_interfaces");
	}

} else if ($_POST) {

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
					if (preg_match('/^ppp_(.+)$/', $ifport, $matches)) {
						$config['interfaces'][$ifname]['pointtopoint'] = true;
						$config['interfaces'][$ifname]['serialport'] = $matches[1];
					}

					/* check for wireless interfaces, set or clear ['wireless'] */
					if (preg_match($g['wireless_regex'], $ifport)) {
						if (!is_array($config['interfaces'][$ifname]['wireless']))
							$config['interfaces'][$ifname]['wireless'] = array();
					} else {
						unset($config['interfaces'][$ifname]['wireless']);
					}
					
					/* make sure there is a descr for all interfaces */
					if (!isset($config['interfaces'][$ifname]['descr']))
						$config['interfaces'][$ifname]['descr'] = strtoupper($ifname);
				}
			}
		}
	
		write_config();
		
		touch("/tmp/reload_interfaces");
	}
}

if ($_GET['act'] == "del") {
	$id = $_GET['id'];

	if (link_int_to_bridge_interface($id))
		$input_errors[] = "The interface is part of a bridge. Please remove it from the bridge to continue";
	else {
		unset($config['interfaces'][$id]['enable']);
		interface_bring_down($id);   /* down the interface */
		
		unset($config['interfaces'][$id]);	/* delete the specified OPTn or LAN*/

		if($id == "lan") {
			unset($config['interfaces']['lan']);
			if (is_array($config['dhcpd']))
				unset($config['dhcpd']['lan']);
				unset($config['shaper']);
				unset($config['ezshaper']);
				unset($config['nat']);
				system("rm /var/dhcpd/var/db/*");
        			services_dhcpd_configure();
		}

		if ($config['filter']['rule'] > 0)
       	 	foreach ($config['filter']['rule'] as $x => $rule) {
                	        if($rule['interface'] == $id)
               		                 unset($config['filter']['rule'][$x]);
        	}
		if ($config['nat']['advancedoutbound']['rule'] > 0)
        	foreach ($config['nat']['advancedoutbound']['rule'] as $x => $rule) {
                	        if($rule['interface'] == $id)
           	    	                 unset($config['nat']['advancedoutbound']['rule'][$x]['interface']);
        	}
        	if (count($config['nat']['rule']) > 0) 
        	foreach ($config['nat']['rule'] as $x => $rule) {
                        	if($rule['interface'] == $id)
                	                unset($config['nat']['rule'][$x]['interface']);
        	}

		write_config();
	
		/* XXX: What is this for?!?! */
		if($config['interfaces']['lan']) {
			unset($config['dhcpd']['wan']);		
		}
	
		$savemsg = "Interface has been deleted.";
	}
}

if ($_GET['act'] == "add") {
	/* find next free optional interface number */
	if(!$config['interfaces']['lan']) {
		$newifname = "lan";
		$config['interfaces'][$newifname] = array();
		$config['interfaces'][$newifname]['descr'] = $descr;
	} else {
		for ($i = 1; $i <= count($config['interfaces']); $i++) {
			if (!$config['interfaces']["opt{$i}"])
				break;
		}
		$newifname = 'opt' . $i;
		$descr = "OPT{$i}";
		$config['interfaces'][$newifname] = array();
		$config['interfaces'][$newifname]['descr'] = $descr;
		ksort($config['interfaces']);
	}
	
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
	
        /* XXX: Do not remove this. */
        mwexec("rm -f /tmp/config.cache");

	write_config();

	$savemsg = "Interface has been added.";

}

include("head.inc");

if(file_exists("/var/run/interface_mismatch_reboot_needed")) 
	if ($_POST)
		$savemsg = "Reboot is needed. Please apply the settings in order to reboot.";
	else
		$savemsg = "Interface mismatch detected.  Please resolve the mismatch and click Save.  The firewall will reboot afterwards.";

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>

<form action="interfaces_assign.php" method="post" name="iform" id="iform">
<?php if (file_exists("/tmp/reload_interfaces")): ?><p>
<?php print_info_box_np("The interface configuration has been changed.<br>You must apply
 the changes in order for them to take effect.");?><br>
<?php endif; ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[0] = array("Interface assignments", true, "interfaces_assign.php");
	$tab_array[1] = array("VLANs", false, "interfaces_vlan.php");
	$tab_array[2] = array("PPP", false, "interfaces_ppp.php");
        $tab_array[3] = array("GRE", false, "interfaces_gre.php");
        $tab_array[4] = array("GIF", false, "interfaces_gif.php");
	$tab_array[5] = array("Bridges", false, "interfaces_bridge.php");
	$tab_array[6] = array("LAGG", false, "interfaces_lagg.php");
	display_top_tabs($tab_array);
?>  
  </td></tr>
  <tr> 
    <td>
	<div id="mainarea">
        <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
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
		<select name="<?=$ifname;?>" id="<?=$ifname;?>">
		  <?php foreach ($portlist as $portname => $portinfo): ?>
		  <option value="<?=$portname;?>" <?php if ($portname == $iface['if']) echo "selected";?>> 
		  <?php if ($portinfo['isvlan']) {
		  			$descr = "VLAN {$portinfo['tag']} on {$portinfo['if']}";
					if ($portinfo['descr'])
						$descr .= " (" . $portinfo['descr'] . ")";
					echo htmlspecialchars($descr);
				} elseif ($portinfo['isppp']) {
					$descr = "PPP {$portinfo['port']}";
					if ($portinfo['descr'])
						$descr .= " (" . $portinfo['descr'] . ")";
					echo htmlspecialchars($descr);
                                 } elseif ($portinfo['isbridge']) {
                                         $descr = strtoupper($portinfo['bridgeif']);
                                         if ($portinfo['descr'])
                                                 $descr .= " (" . $portinfo['descr'] . ")";
                                        echo htmlspecialchars($descr);
                                 } elseif ($portinfo['isgre']) {
                                         $descr = "GRE {$portinfo['remote-addr']}";
                                         if ($portinfo['descr'])
                                                 $descr .= " (" . $portinfo['descr'] . ")";
                                        echo htmlspecialchars($descr);
                                 } elseif ($portinfo['isgif']) {
                                         $descr = "GRE {$portinfo['remote-addr']}";
                                         if ($portinfo['descr'])
                                                 $descr .= " (" . $portinfo['descr'] . ")";
                                        echo htmlspecialchars($descr);
                                 } elseif ($portinfo['islagg']) {
                                         $descr = strtoupper($portinfo['laggif']);
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
		  <?php if ($ifname != 'wan'): ?>
		  <a href="interfaces_assign.php?act=del&id=<?=$ifname;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" title="delete interface" width="17" height="17" border="0"></a> 
		  <?php endif; ?>
		</td>
  </tr>
  <?php endforeach; ?>
  <?php if (count($config['interfaces']) < count($portlist)): ?>
  <tr>
	<td class="list" colspan="2"></td>
	<td class="list" nowrap>
	<a href="interfaces_assign.php?act=add"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="add interface" width="17" height="17" border="0"></a>
	</td>
  </tr>
  <?php else: ?>
  <tr>
	<td class="list" colspan="3" height="10"></td>
  </tr>
  <?php endif; ?>
</table>
</div>
<br/>
<input name="Submit" type="submit" class="formbtn" value="Save"><br><br>
<p>
</p>
<ul>
  <li><span class="vexpl">change the IP address of your computer</span></li>
  <li><span class="vexpl">renew its DHCP lease</span></li>
  <li><span class="vexpl">access the webConfigurator with the new IP address</span></li>
  <li><span class="vexpl">interfaces that are configured as members of a lagg(4) interface will not be shown.</span></li>
</ul></td>
	</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
