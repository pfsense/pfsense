<?php
/* $Id$ */
/*
	services_dhcp.php
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

##|+PRIV
##|*IDENT=page-services-dhcpserver
##|*NAME=Services: DHCP server page
##|*DESCR=Allow access to the 'Services: DHCP server' page.
##|*MATCH=services_dhcp.php*
##|-PRIV


require("guiconfig.inc");

$if = $_GET['if'];
if ($_POST['if'])
	$if = $_POST['if'];

/* if OLSRD is enabled, allow WAN to house DHCP. */
if($config['installedpackages']['olsrd']) {
	foreach($config['installedpackages']['olsrd']['config'] as $olsrd) {
			if($olsrd['enable']) {
				$is_olsr_enabled = true;
				break;
			}
	}
}

$ifdescrs = get_configured_interface_with_descr();

foreach ($ifdescrs as $ifname => $ifdesc) {
	$oc = $config['interfaces'][$ifname];

	if (!is_ipaddr($oc['ipaddr']) && !$is_olsr_enabled)
		$singleif_nostaticip = true;
	else if ($oc['if']) 
		$iflist[$ifname] = $ifdesc;
}

/* set the starting interface */
if($config['interfaces']['lan']) {
	if (!$if || !isset($iflist[$if]))
		$if = "lan";
} else
	$if = "wan";

if (is_array($config['dhcpd'][$if])){
	if (is_array($config['dhcpd'][$if]['range'])) {
		$pconfig['range_from'] = $config['dhcpd'][$if]['range']['from'];
		$pconfig['range_to'] = $config['dhcpd'][$if]['range']['to'];
	}
	$pconfig['deftime'] = $config['dhcpd'][$if]['defaultleasetime'];
	$pconfig['maxtime'] = $config['dhcpd'][$if]['maxleasetime'];
	$pconfig['gateway'] = $config['dhcpd'][$if]['gateway'];
	$pconfig['domain'] = $config['dhcpd'][$if]['domain'];
	$pconfig['domainsearchlist'] = $config['dhcpd'][$if]['domainsearchlist'];
	list($pconfig['wins1'],$pconfig['wins2']) = $config['dhcpd'][$if]['winsserver'];
	list($pconfig['dns1'],$pconfig['dns2']) = $config['dhcpd'][$if]['dnsserver'];
	$pconfig['enable'] = isset($config['dhcpd'][$if]['enable']);
	$pconfig['denyunknown'] = isset($config['dhcpd'][$if]['denyunknown']);
	$pconfig['staticarp'] = isset($config['dhcpd'][$if]['staticarp']);
	$pconfig['ddnsdomain'] = $config['dhcpd'][$if]['ddnsdomain'];
	$pconfig['ddnsupdate'] = isset($config['dhcpd'][$if]['ddnsupdate']);
	list($pconfig['ntp1'],$pconfig['ntp2']) = $config['dhcpd'][$if]['ntpserver'];
	$pconfig['tftp'] = $config['dhcpd'][$if]['tftp'];
	$pconfig['ldap'] = $config['dhcpd'][$if]['ldap'];
	$pconfig['netboot'] = isset($config['dhcpd'][$if]['netboot']);
	$pconfig['nextserver'] = $config['dhcpd'][$if]['next-server'];
	$pconfig['filename'] = $config['dhcpd'][$if]['filename'];
	$pconfig['rootpath'] = $config['dhcpd'][$if]['rootpath'];
	$pconfig['failover_peerip'] = $config['dhcpd'][$if]['failover_peerip'];
	$pconfig['netmask'] = $config['dhcpd'][$if]['netmask'];
	if (!is_array($config['dhcpd'][$if]['staticmap'])) 
        	$config['dhcpd'][$if]['staticmap'] = array();
	staticmaps_sort($if);
	$a_maps = &$config['dhcpd'][$if]['staticmap'];
}

$ifcfg = $config['interfaces'][$if];


/*   set the enabled flag which will tell us if DHCP relay is enabled
 *   on any interface.   We will use this to disable DHCP server since
 *   the two are not compatible with each other.
 */

$dhcrelay_enabled = false;
$dhcrelaycfg = $config['dhcrelay'];

if(is_array($dhcrelaycfg)) {
	foreach ($dhcrelaycfg as $dhcrelayif => $dhcrelayifconf) {
		if (isset($dhcrelayifconf['enable']) &&
			(($dhcrelayif == "lan") ||
			(isset($config['interfaces'][$dhcrelayif]['enable']) &&
			$config['interfaces'][$dhcrelayif]['if'] && (!link_int_to_bridge_interface($dhcrelayif)))))
			$dhcrelay_enabled = true;
	}
}

function is_inrange($test, $start, $end) {
	if ( (ip2long($test) < ip2long($end)) && (ip2long($test) > ip2long($start)) )
		return true;
	else
		return false;
}

if ($_POST) {

	unset($input_errors);

	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = explode(" ", "range_from range_to");
		$reqdfieldsn = explode(",", "Range begin,Range end");

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
		
		if (($_POST['range_from'] && !is_ipaddr($_POST['range_from']))) {
			$input_errors[] = "A valid range must be specified.";
		}
		if (($_POST['range_to'] && !is_ipaddr($_POST['range_to']))) {
			$input_errors[] = "A valid range must be specified.";
		}
		if (($_POST['gateway'] && !is_ipaddr($_POST['gateway']))) {
			$input_errors[] = "A valid IP address must be specified for the gateway.";
		}
		if (($_POST['wins1'] && !is_ipaddr($_POST['wins1'])) || ($_POST['wins2'] && !is_ipaddr($_POST['wins2']))) {
			$input_errors[] = "A valid IP address must be specified for the primary/secondary WINS servers.";
		}
		if (($_POST['dns1'] && !is_ipaddr($_POST['dns1'])) || ($_POST['dns2'] && !is_ipaddr($_POST['dns2']))) {
			$input_errors[] = "A valid IP address must be specified for the primary/secondary DNS servers.";
		}
		if ($_POST['deftime'] && (!is_numeric($_POST['deftime']) || ($_POST['deftime'] < 60))) {
			$input_errors[] = "The default lease time must be at least 60 seconds.";
		}
		if ($_POST['maxtime'] && (!is_numeric($_POST['maxtime']) || ($_POST['maxtime'] < 60) || ($_POST['maxtime'] <= $_POST['deftime']))) {
			$input_errors[] = "The maximum lease time must be at least 60 seconds and higher than the default lease time.";
		}
		if (($_POST['ddnsdomain'] && !is_domain($_POST['ddnsdomain']))) {
			$input_errors[] = "A valid domain name must be specified for the dynamic DNS registration.";
		}
		if (($_POST['ntp1'] && !is_ipaddr($_POST['ntp1'])) || ($_POST['ntp2'] && !is_ipaddr($_POST['ntp2']))) {
			$input_errors[] = "A valid IP address must be specified for the primary/secondary NTP servers.";
		}
		if (($_POST['domain'] && !is_domain($_POST['domain']))) {
			$input_errors[] = "A valid domain name must be specified for the DNS domain.";
    }
		if (($_POST['tftp'] && !is_ipaddr($_POST['tftp']))) {
			$input_errors[] = "A valid IP address must be specified for the tftp server.";
		}
		if (($_POST['nextserver'] && !is_ipaddr($_POST['nextserver']))) {
			$input_errors[] = "A valid IP address must be specified for the network boot server.";
		}

		if (!$input_errors) {
			/* make sure the range lies within the current subnet */
			$subnet_start = (ip2long($ifcfg['ipaddr']) & gen_subnet_mask_long($ifcfg['subnet']));
			$subnet_end = (ip2long($ifcfg['ipaddr']) | (~gen_subnet_mask_long($ifcfg['subnet'])));

			if ((ip2long($_POST['range_from']) < $subnet_start) || (ip2long($_POST['range_from']) > $subnet_end) ||
			    (ip2long($_POST['range_to']) < $subnet_start) || (ip2long($_POST['range_to']) > $subnet_end)) {
				$input_errors[] = "The specified range lies outside of the current subnet.";
			}

			if (ip2long($_POST['range_from']) > ip2long($_POST['range_to']))
				$input_errors[] = "The range is invalid (first element higher than second element).";

			/* make sure that the DHCP Relay isn't enabled on this interface */
			if (isset($config['dhcrelay'][$if]['enable']))
				$input_errors[] = "You must disable the DHCP relay on the {$iflist[$if]} interface before enabling the DHCP server.";
		}
	}

	if (!$input_errors) {
		if (!is_array($config['dhcpd'][$if]))
			$config['dhcpd'][$if] = array();
		if (!is_array($config['dhcpd'][$if]['range']))
			$config['dhcpd'][$if]['range'] = array();

		$config['dhcpd'][$if]['range']['from'] = $_POST['range_from'];
		$config['dhcpd'][$if]['range']['to'] = $_POST['range_to'];
		$config['dhcpd'][$if]['defaultleasetime'] = $_POST['deftime'];
		$config['dhcpd'][$if]['maxleasetime'] = $_POST['maxtime'];
		$config['dhcpd'][$if]['netmask'] = $_POST['netmask'];
		$previous = $config['dhcpd'][$if]['failover_peerip'];
		if($previous <> $_POST['failover_peerip']) {
			mwexec("rm -rf /var/dhcpd/var/db/*");
		}
		$config['dhcpd'][$if]['failover_peerip'] = $_POST['failover_peerip'];

		unset($config['dhcpd'][$if]['winsserver']);
		if ($_POST['wins1'])
			$config['dhcpd'][$if]['winsserver'][] = $_POST['wins1'];
		if ($_POST['wins2'])
			$config['dhcpd'][$if]['winsserver'][] = $_POST['wins2'];

		unset($config['dhcpd'][$if]['dnsserver']);
		if ($_POST['dns1'])
			$config['dhcpd'][$if]['dnsserver'][] = $_POST['dns1'];
		if ($_POST['dns2'])
			$config['dhcpd'][$if]['dnsserver'][] = $_POST['dns2'];

		$config['dhcpd'][$if]['gateway'] = $_POST['gateway'];
		$config['dhcpd'][$if]['domain'] = $_POST['domain'];
		$config['dhcpd'][$if]['domainsearchlist'] = $_POST['domainsearchlist'];
		$config['dhcpd'][$if]['denyunknown'] = ($_POST['denyunknown']) ? true : false;
		$config['dhcpd'][$if]['enable'] = ($_POST['enable']) ? true : false;
		$config['dhcpd'][$if]['staticarp'] = ($_POST['staticarp']) ? true : false;
		$config['dhcpd'][$if]['ddnsdomain'] = $_POST['ddnsdomain'];
		$config['dhcpd'][$if]['ddnsupdate'] = ($_POST['ddnsupdate']) ? true : false;

		unset($config['dhcpd'][$if]['ntpserver']);
		if ($_POST['ntp1'])
			$config['dhcpd'][$if]['ntpserver'][] = $_POST['ntp1'];
		if ($_POST['ntp2'])
			$config['dhcpd'][$if]['ntpserver'][] = $_POST['ntp2'];

		$config['dhcpd'][$if]['tftp'] = $_POST['tftp'];
		$config['dhcpd'][$if]['ldap'] = $_POST['ldap'];
		$config['dhcpd'][$if]['netboot'] = ($_POST['netboot']) ? true : false;
		$config['dhcpd'][$if]['next-server'] = $_POST['nextserver'];
		$config['dhcpd'][$if]['filename'] = $_POST['filename'];
		$config['dhcpd'][$if]['rootpath'] = $_POST['rootpath'];

		write_config();

		/* static arp configuration */
		interfaces_staticarp_configure($if);

		$retval = 0;
		$retvaldhcp = 0;
		$retvaldns = 0;
		config_lock();
		/* dnsmasq_configure calls dhcpd_configure */
		/* no need to restart dhcpd twice */
		if (isset($config['dnsmasq']['regdhcpstatic']))	{
			$retvaldns = services_dnsmasq_configure();
			if ($retvaldns == 0) {
				if (file_exists($d_hostsdirty_path))
					unlink($d_hostsdirty_path);
				if (file_exists($d_staticmapsdirty_path))
					unlink($d_staticmapsdirty_path);
			}					
		} else {
			$retvaldhcp = services_dhcpd_configure();	
			if ($retvaldhcp == 0) {
				if (file_exists($d_staticmapsdirty_path))
					unlink($d_staticmapsdirty_path);
			}
		}	
		config_unlock();
		if($retvaldhcp == 1 || $retvaldns == 1)
			$retval = 1;
		$savemsg = get_std_save_message($retval);
	}
}

if ($_GET['act'] == "del") {
	if ($a_maps[$_GET['id']]) {
		unset($a_maps[$_GET['id']]);
		write_config();
		if(isset($config['dhcpd'][$if]['enable'])) {
			touch($d_staticmapsdirty_path);
			if (isset($config['dnsmasq']['regdhcpstatic']))
				touch($d_hostsdirty_path);
		}
		header("Location: services_dhcp.php?if={$if}");
		exit;
	}
}

$pgtitle = array("Services","DHCP server");
include("head.inc");

?>

<script type="text/javascript" language="JavaScript">

function enable_change(enable_over) {
	var endis;
	endis = !(document.iform.enable.checked || enable_over);
	document.iform.range_from.disabled = endis;
	document.iform.range_to.disabled = endis;
	document.iform.wins1.disabled = endis;
	document.iform.wins2.disabled = endis;
	document.iform.dns1.disabled = endis;
	document.iform.dns2.disabled = endis;
	document.iform.deftime.disabled = endis;
	document.iform.maxtime.disabled = endis;
	document.iform.gateway.disabled = endis;
	document.iform.failover_peerip.disabled = endis;
	document.iform.domain.disabled = endis;
	document.iform.domainsearchlist.disabled = endis;
	document.iform.staticarp.disabled = endis;
	document.iform.ddnsdomain.disabled = endis;
	document.iform.ddnsupdate.disabled = endis;
	document.iform.ntp1.disabled = endis;
	document.iform.ntp2.disabled = endis;
	document.iform.tftp.disabled = endis;
	document.iform.ldap.disabled = endis;
	document.iform.netboot.disabled = endis;
	document.iform.nextserver.disabled = endis;
	document.iform.filename.disabled = endis;
	document.iform.rootpath.disabled = endis;
	document.iform.denyunknown.disabled = endis;
}

function show_ddns_config() {
	document.getElementById("showddnsbox").innerHTML='';
	aodiv = document.getElementById('showddns');
	aodiv.style.display = "block";
}

function show_ntp_config() {
	document.getElementById("showntpbox").innerHTML='';
	aodiv = document.getElementById('showntp');
	aodiv.style.display = "block";
}

function show_tftp_config() {
	document.getElementById("showtftpbox").innerHTML='';
	aodiv = document.getElementById('showtftp');
	aodiv.style.display = "block";
}

function show_ldap_config() {
	document.getElementById("showldapbox").innerHTML='';
	aodiv = document.getElementById('showldap');
	aodiv.style.display = "block";
}

function show_netboot_config() {
	document.getElementById("shownetbootbox").innerHTML='';
	aodiv = document.getElementById('shownetboot');
	aodiv.style.display = "block";
}

</script>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="services_dhcp.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php 
	if ($dhcrelay_enabled) {
		echo "DHCP Relay is currently enabled.  Cannot enable the DHCP Server service while the DHCP Relay is enabled on any interface.";
		include("fend.inc"); 
		echo "</body>";
		echo "</html>";
		exit;
	}
	if ($singleif_nostaticip) {
		echo "<b>The DHCP Server can only be enabled on interfaces configured with static IP addresses. Your interface is not configured with a static IP.</b>";
		include("fend.inc"); 
		echo "</body>";
		echo "</html>";
		exit;		
	}
?>
<?php if (file_exists($d_staticmapsdirty_path)): ?><p>
<?php print_info_box_np("The static mapping configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
  <?php
	/* active tabs */
	$tab_array = array();
	$tabscounter = 0;
	$i = 0;
	foreach ($iflist as $ifent => $ifname) {
		if ($ifent == $if)
			$active = true;
		else
			$active = false;
		$tab_array[] = array($ifname, $active, "services_dhcp.php?if={$ifent}");
	}
	display_top_tabs($tab_array);
  ?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
                      <tr>
                        <td width="22%" valign="top" class="vtable">&nbsp;</td>
                        <td width="78%" class="vtable">
			  <input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable']) echo "checked"; ?> onClick="enable_change(false)">
                          <strong>Enable DHCP server on
                          <?=htmlspecialchars($iflist[$if]);?>
                          interface</strong></td>
                      </tr>
				  <tr>
	              <td width="22%" valign="top" class="vtable">&nbsp;</td>
                      <td width="78%" class="vtable">
			<input name="denyunknown" id="denyunknown" type="checkbox" value="yes" <?php if ($pconfig['denyunknown']) echo "checked"; ?>>
                      <strong>Deny unknown clients</strong><br>
                      If this is checked, only the clients defined below will get DHCP leases from this server. </td>
		      		  </tr>
                      <tr>
                        <td width="22%" valign="top" class="vncellreq">Subnet</td>
                        <td width="78%" class="vtable">
                          <?=gen_subnet($ifcfg['ipaddr'], $ifcfg['subnet']);?>
                        </td>
                      </tr>
                      <tr>
                        <td width="22%" valign="top" class="vncellreq">Subnet
                          mask</td>
                        <td width="78%" class="vtable">
                          <?=gen_subnet_mask($ifcfg['subnet']);?>
                        </td>
                      </tr>
                      <tr>
                        <td width="22%" valign="top" class="vncellreq">Available
                          range</td>
                        <td width="78%" class="vtable">
                          <?=long2ip(ip2long($ifcfg['ipaddr']) & gen_subnet_mask_long($ifcfg['subnet']));?>
                          -
                          <?=long2ip(ip2long($ifcfg['ipaddr']) | (~gen_subnet_mask_long($ifcfg['subnet']))); ?>
                        </td>
                      </tr>
					  <?php if($is_olsr_enabled): ?>
                      <tr>
                        <td width="22%" valign="top" class="vncellreq">Subnet Mask</td>
                        <td width="78%" class="vtable">
	                        <select name="netmask" class="formselect" id="netmask">
							<?php
							for ($i = 32; $i > 0; $i--) {
								if($i <> 31) {
									echo "<option value=\"{$i}\" ";
									if ($i == $pconfig['netmask']) echo "selected";
									echo ">" . $i . "</option>";
								}
							}
							?>
							</select>
                        </td>
                      </tr>
                      <?php endif; ?>
                      <tr>
                        <td width="22%" valign="top" class="vncellreq">Range</td>
                        <td width="78%" class="vtable">
                          <input name="range_from" type="text" class="formfld unknown" id="range_from" size="20" value="<?=htmlspecialchars($pconfig['range_from']);?>">
                          &nbsp;to&nbsp; <input name="range_to" type="text" class="formfld unknown" id="range_to" size="20" value="<?=htmlspecialchars($pconfig['range_to']);?>">
			</td>
                      </tr>
                      <tr>
                        <td width="22%" valign="top" class="vncell">WINS servers</td>
                        <td width="78%" class="vtable">
                          <input name="wins1" type="text" class="formfld unknown" id="wins1" size="20" value="<?=htmlspecialchars($pconfig['wins1']);?>"><br>
                          <input name="wins2" type="text" class="formfld unknown" id="wins2" size="20" value="<?=htmlspecialchars($pconfig['wins2']);?>">
			</td>
                      </tr>
                      <tr>
                        <td width="22%" valign="top" class="vncell">DNS servers</td>
                        <td width="78%" class="vtable">
                          <input name="dns1" type="text" class="formfld unknown" id="dns1" size="20" value="<?=htmlspecialchars($pconfig['dns1']);?>"><br>
                          <input name="dns2" type="text" class="formfld unknown" id="dns2" size="20" value="<?=htmlspecialchars($pconfig['dns2']);?>"><br>
			  NOTE: leave blank to use the system default DNS servers - this interface's IP if DNS forwarder is enabled, otherwise the servers configured on the General page.  
			</td>
                      </tr>
                     <tr>
                       <td width="22%" valign="top" class="vncell">Gateway</td>
                       <td width="78%" class="vtable">
                         <input name="gateway" type="text" class="formfld host" id="gateway" size="20" value="<?=htmlspecialchars($pconfig['gateway']);?>"><br>
			 The default is to use the IP on this interface of the firewall as the gateway.  Specify an alternate gateway here if this is not the correct gateway for your network.
			</td>
                     </tr>
                      <tr>
                       <td width="22%" valign="top" class="vncell">Domain-Name</td>
                       <td width="78%" class="vtable">
                         <input name="domain" type="text" class="formfld unknown" id="domain" size="20" value="<?=htmlspecialchars($pconfig['domain']);?>"><br>
			 The default is to use the domainname of the router as DNS-Search string that is served via DHCP. Specify an alternate DNS-Search string here.
			</td>
                     </tr>
                      <tr>
                       <td width="22%" valign="top" class="vncell">Domain-Searchlist</td>
                       <td width="78%" class="vtable">
                         <input name="domainsearchlist" type="text" class="formfld unknown" id="domainsearchlist" size="20" value="<?=htmlspecialchars($pconfig['domainsearchlist']);?>"><br>
			 DNS-Searchlist: the DHCP server can serve a list of domains to be searched.
			</td>
                     </tr>                     
                      <tr>
                        <td width="22%" valign="top" class="vncell">Default lease time</td>
                        <td width="78%" class="vtable">
                          <input name="deftime" type="text" class="formfld unknown" id="deftime" size="10" value="<?=htmlspecialchars($pconfig['deftime']);?>">
                          seconds<br>
                          This is used for clients that do not ask for a specific
                          expiration time.<br>
                          The default is 7200 seconds.
			</td>
                      </tr>
                      <tr>
                        <td width="22%" valign="top" class="vncell">Maximum lease time</td>
                        <td width="78%" class="vtable">
                          <input name="maxtime" type="text" class="formfld unknown" id="maxtime" size="10" value="<?=htmlspecialchars($pconfig['maxtime']);?>">
                          seconds<br>
                          This is the maximum lease time for clients that ask
                          for a specific expiration time.<br>
                          The default is 86400 seconds.
			</td>
                      </tr>
                      <tr>
                        <td width="22%" valign="top" class="vncell">Failover peer IP:</td>
                        <td width="78%" class="vtable">
				<input name="failover_peerip" type="text" class="formfld host" id="failover_peerip" size="20" value="<?=htmlspecialchars($pconfig['failover_peerip']);?>"><br>
				Leave blank to disable.  Enter the REAL address of the other machine.  Machines must be using CARP.
			</td>
		      </tr>
                      <tr>
                        <td width="22%" valign="top" class="vncell">Static ARP</td>
                        <td width="78%" class="vtable">
				<table>
					<tr>
						<td>
							<input valign="middle" type="checkbox" value="yes" name="staticarp" id="staticarp" <?php if($pconfig['staticarp']) echo " checked"; ?>>&nbsp;
						</td>
						<td>
							<b>Enable Static ARP entries</b>
						</td>
					</tr>
					<tr>
						<td>
							&nbsp;
						</td>
						<td>
							<span class="red"><strong>Note:</strong></span> Only the machines listed below will be able to communicate with the firewall on this NIC.
						</td>
					</tr>
				</table>
			</td>
                      </tr>
                      <tr>
                        <td width="22%" valign="top" class="vncell">Dynamic DNS</td>
                        <td width="78%" class="vtable">
				<div id="showddnsbox">
					<input type="button" onClick="show_ddns_config()" value="Advanced"></input> - Show Dynamic DNS</a>
				</div>
				<div id="showddns" style="display:none">
					<input valign="middle" type="checkbox" value="yes" name="ddnsupdate" id="ddnsupdate" <?php if($pconfig['ddnsupdate']) echo " checked"; ?>>&nbsp;
					<b>Enable registration of DHCP client names in DNS.</b><br />
					<p>
					<input name="ddnsdomain" type="text" class="formfld unknown" id="ddnsdomain" size="20" value="<?=htmlspecialchars($pconfig['ddnsdomain']);?>"><br />
					Note: Leave blank to disable dynamic DNS registration.<br />
					Enter the dynamic DNS domain which will be used to register client names in the DNS server.
				</div>
			</td>
		      </tr>
                      <tr>
                        <td width="22%" valign="top" class="vncell">NTP servers</td>
                        <td width="78%" class="vtable">
				<div id="showntpbox">
					<input type="button" onClick="show_ntp_config()" value="Advanced"></input> - Show NTP configuration</a>
				</div>
				<div id="showntp" style="display:none">
					<input name="ntp1" type="text" class="formfld unknown" id="ntp1" size="20" value="<?=htmlspecialchars($pconfig['ntp1']);?>"><br>
					<input name="ntp2" type="text" class="formfld unknown" id="ntp2" size="20" value="<?=htmlspecialchars($pconfig['ntp2']);?>">
				</div>
			</td>
                      </tr>
                      <tr>
                        <td width="22%" valign="top" class="vncell">TFTP server</td>
                        <td width="78%" class="vtable">
				<div id="showtftpbox">
					<input type="button" onClick="show_tftp_config()" value="Advanced"></input> - Show TFTP configuration</a>
				</div>
				<div id="showtftp" style="display:none">
					<input name="tftp" type="text" class="formfld unknown" id="tftp" size="50" value="<?=htmlspecialchars($pconfig['tftp']);?>"><br>
					Leave blank to disable.  Enter a full hostname or IP for the TFTP server.
				</div>
			</td>
                      </tr>
                      <tr>
                        <td width="22%" valign="top" class="vncell">LDAP URI</td>
                        <td width="78%" class="vtable">
				<div id="showldapbox">
					<input type="button" onClick="show_ldap_config()" value="Advanced"></input> - Show LDAP configuration</a>
				</div>
				<div id="showldap" style="display:none">
					<input name="ldap" type="text" class="formfld unknown" id="ldap" size="80" value="<?=htmlspecialchars($pconfig['ldap']);?>"><br>
					Leave blank to disable.  Enter a full URI for the LDAP server in the form ldap://ldap.example.com/dc=example,dc=com
				</div>
			</td>
                      </tr>
                      <tr>
                        <td width="22%" valign="top" class="vncell">Enable Network booting</td>
                        <td width="78%" class="vtable">
				<div id="shownetbootbox">
					<input type="button" onClick="show_netboot_config()" value="Advanced"></input> - Show Network booting</a>
				</div>
				<div id="shownetboot" style="display:none">
					<input valign="middle" type="checkbox" value="yes" name="netboot" id="netboot" <?php if($pconfig['netboot']) echo " checked"; ?>>&nbsp;
					<b>Enables network booting.</b>
					<p>
					Enter the IP of the <b>next-server</b>
					<input name="nextserver" type="text" class="formfld unknown" id="nextserver" size="20" value="<?=htmlspecialchars($pconfig['nextserver']);?>">
					and the filename					
					<input name="filename" type="text" class="formfld unknown" id="filename" size="20" value="<?=htmlspecialchars($pconfig['filename']);?>"><br>
					Note: You need both a filename and a boot server configured for this to work!
				  <p>
					Enter the <b>root-path</b>-string
          <input name="rootpath" type="text" class="formfld unknown" id="rootpath" size="90" value="<?=htmlspecialchars($pconfig['rootpath']);?>"><br>
          Note: string-format: iscsi:(servername):(protocol):(port):(LUN):targetname
        </div>
			</td>
		                  </tr>
                      <tr>
                        <td width="22%" valign="top">&nbsp;</td>
                        <td width="78%">
                          <input name="if" type="hidden" value="<?=$if;?>">
                          <input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)">
                        </td>
                      </tr>
                      <tr>
                        <td width="22%" valign="top">&nbsp;</td>
                        <td width="78%"> <p><span class="vexpl"><span class="red"><strong>Note:<br>
                            </strong></span>The DNS servers entered in <a href="system.php">System:
                            General setup</a> (or the <a href="services_dnsmasq.php">DNS
                            forwarder</a>, if enabled) </span><span class="vexpl">will
                            be assigned to clients by the DHCP server.<br>
                            <br>
                            The DHCP lease table can be viewed on the <a href="diag_dhcp_leases.php">Status:
                            DHCP leases</a> page.<br>
                            </span></p>
			</td>
                      </tr>
                    </table>
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="25%" class="listhdrr">MAC address</td>
                  <td width="15%" class="listhdrr">IP address</td>
				  <td width="20%" class="listhdrr">Hostname</td>
                  <td width="30%" class="listhdr">Description</td>
                  <td width="10%" class="list">
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
			<td valign="middle" width="17"></td>
                        <td valign="middle"><a href="services_dhcp_edit.php?if=<?=$if;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
		  </td>
		</tr>
			  <?php if(is_array($a_maps)): ?>
			  <?php $i = 0; foreach ($a_maps as $mapent): ?>
			  <?php if($mapent['mac'] <> "" or $mapent['ipaddr'] <> ""): ?>
                <tr>
                  <td class="listlr" ondblclick="document.location='services_dhcp_edit.php?if=<?=$if;?>&id=<?=$i;?>';">
                    <?=htmlspecialchars($mapent['mac']);?>
                  </td>
                  <td class="listr" ondblclick="document.location='services_dhcp_edit.php?if=<?=$if;?>&id=<?=$i;?>';">
                    <?=htmlspecialchars($mapent['ipaddr']);?>&nbsp;
                  </td>
                  <td class="listr" ondblclick="document.location='services_dhcp_edit.php?if=<?=$if;?>&id=<?=$i;?>';">
                    <?=htmlspecialchars($mapent['hostname']);?>&nbsp;
                  </td>	
                  <td class="listbg" ondblclick="document.location='services_dhcp_edit.php?if=<?=$if;?>&id=<?=$i;?>';">
                    <font color="#FFFFFF"><?=htmlspecialchars($mapent['descr']);?>&nbsp;</font>
                  </td>
                  <td valign="middle" nowrap class="list">
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
                        <td valign="middle"><a href="services_dhcp_edit.php?if=<?=$if;?>&id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a></td>
                        <td valign="middle"><a href="services_dhcp.php?if=<?=$if;?>&act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this mapping?')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
                  </td>
                </tr>
		<?php endif; ?>
		<?php $i++; endforeach; ?>
		<?php endif; ?>
                <tr>
                  <td class="list" colspan="4"></td>
                  <td class="list">
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
			<td valign="middle" width="17"></td>
                        <td valign="middle"><a href="services_dhcp_edit.php?if=<?=$if;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
	</div>
    </td>
  </tr>
</table>
</form>
<script language="JavaScript">
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>
