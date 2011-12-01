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
/*
	pfSense_BUILDER_BINARIES:	/bin/rm
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-services-dhcpserver
##|*NAME=Services: DHCP server page
##|*DESCR=Allow access to the 'Services: DHCP server' page.
##|*MATCH=services_dhcp.php*
##|-PRIV

require("guiconfig.inc");

if(!$g['services_dhcp_server_enable']) {
	Header("Location: /");
	exit;
}

/* This function will remove entries from dhcpd.leases that would otherwise
 * overlap with static DHCP reservations. If we don't clean these out,
 * then DHCP will print a warning in the logs about a duplicate lease
 */
function dhcp_clean_leases() {
	global $g, $config;
	$leasesfile = "{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases";
	if (!file_exists($leasesfile))
		return;
	/* Build list of static MACs */
	$staticmacs = array();
	foreach($config['interfaces'] as $ifname => $ifarr)
		if (is_array($config['dhcpd'][$ifname]['staticmap']))
			foreach($config['dhcpd'][$ifname]['staticmap'] as $static)
				$staticmacs[] = $static['mac'];
	/* Read existing leases */
	$leases_contents = explode("\n", file_get_contents($leasesfile));
	$newleases_contents = array();
	$i=0;
	while ($i < count($leases_contents)) {
		/* Find a lease definition */
		if (substr($leases_contents[$i], 0, 6) == "lease ") {
			$templease = array();
			$thismac = "";
			/* Read to the end of the lease declaration */
			do {
				if (substr($leases_contents[$i], 0, 20) == "  hardware ethernet ")
					$thismac = substr($leases_contents[$i], 20, 17);
				$templease[] = $leases_contents[$i];
				$i++;
			} while ($leases_contents[$i-1] != "}");
			/* Check for a matching MAC address and if not present, keep it. */
			if (! in_array($thismac, $staticmacs))
				$newleases_contents = array_merge($newleases_contents, $templease);
		} else {
			/* It's a line we want to keep, copy it over. */
			$newleases_contents[] = $leases_contents[$i];
			$i++;
		}
	}
	/* Write out the new leases file */
	$fd = fopen($leasesfile, 'w');
	fwrite($fd, implode("\n", $newleases_contents));
	fclose($fd);
}

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

if (!$_GET['if'])
	$savemsg = "<b>" . gettext("The DHCP Server can only be enabled on interfaces configured with static IP addresses") . ".<p>" . gettext("Only interfaces configured with a static IP will be shown") . ".</p></b>";

$iflist = get_configured_interface_with_descr();

/* set the starting interface */
if (!$if || !isset($iflist[$if])) {
	foreach ($iflist as $ifent => $ifname) {
		$oc = $config['interfaces'][$ifent];
		if ((is_array($config['dhcpd'][$ifent]) && !isset($config['dhcpd'][$ifent]['enable']) && (!is_ipaddrv4($oc['ipaddr']))) ||
			(!is_array($config['dhcpd'][$ifent]) && (!is_ipaddrv4($oc['ipaddr']))))
			continue;
		$if = $ifent;
		break;
	}
}

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
	$pconfig['nextserver'] = $config['dhcpd'][$if]['nextserver'];
	$pconfig['filename'] = $config['dhcpd'][$if]['filename'];
	$pconfig['rootpath'] = $config['dhcpd'][$if]['rootpath'];
	$pconfig['failover_peerip'] = $config['dhcpd'][$if]['failover_peerip'];
	$pconfig['netmask'] = $config['dhcpd'][$if]['netmask'];
	$pconfig['numberoptions'] = $config['dhcpd'][$if]['numberoptions'];
	if (!is_array($config['dhcpd'][$if]['staticmap']))
		$config['dhcpd'][$if]['staticmap'] = array();
	$a_maps = &$config['dhcpd'][$if]['staticmap'];
}

$ifcfgip = $config['interfaces'][$if]['ipaddr'];
$ifcfgsn = $config['interfaces'][$if]['subnet'];

/*   set the enabled flag which will tell us if DHCP relay is enabled
 *   on any interface. We will use this to disable DHCP server since
 *   the two are not compatible with each other.
 */

$dhcrelay_enabled = false;
$dhcrelaycfg = $config['dhcrelay'];

if(is_array($dhcrelaycfg)) {
	foreach ($dhcrelaycfg as $dhcrelayif => $dhcrelayifconf) {
		if (isset($dhcrelayifconf['enable']) && isset($iflist[$dhcrelayif]) &&
			(!link_interface_to_bridge($dhcrelayif)))
			$dhcrelay_enabled = true;
	}
}

function is_inrange($test, $start, $end) {
	if ( (ip2ulong($test) < ip2ulong($end)) && (ip2ulong($test) > ip2ulong($start)) )
		return true;
	else
		return false;
}

if ($_POST) {

	unset($input_errors);

	$pconfig = $_POST;

	$numberoptions = array();
	for($x=0; $x<99; $x++) {
		if(isset($_POST["number{$x}"]) && ctype_digit($_POST["number{$x}"])) {
			$numbervalue = array();
			$numbervalue['number'] = htmlspecialchars($_POST["number{$x}"]);
			$numbervalue['type'] = htmlspecialchars($_POST["itemtype{$x}"]);
			$numbervalue['value'] = str_replace('&quot;', '"', htmlspecialchars($_POST["value{$x}"]));
			$numberoptions['item'][] = $numbervalue;
		}
	}
	// Reload the new pconfig variable that the forum uses.
	$pconfig['numberoptions'] = $numberoptions;

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = explode(" ", "range_from range_to");
		$reqdfieldsn = array(gettext("Range begin"),gettext("Range end"));

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

		if (($_POST['range_from'] && !is_ipaddrv4($_POST['range_from'])))
			$input_errors[] = gettext("A valid range must be specified.");
		if (($_POST['range_to'] && !is_ipaddrv4($_POST['range_to'])))
			$input_errors[] = gettext("A valid range must be specified.");
		if (($_POST['gateway'] && !is_ipaddrv4($_POST['gateway'])))
			$input_errors[] = gettext("A valid IP address must be specified for the gateway.");
		if (($_POST['wins1'] && !is_ipaddrv4($_POST['wins1'])) || ($_POST['wins2'] && !is_ipaddrv4($_POST['wins2'])))
			$input_errors[] = gettext("A valid IP address must be specified for the primary/secondary WINS servers.");
		$parent_ip = get_interface_ip($_POST['if']);
		if (is_ipaddrv4($parent_ip) && $_POST['gateway']) {
			$parent_sn = get_interface_subnet($_POST['if']);
			if(!ip_in_subnet($_POST['gateway'], gen_subnet($parent_ip, $parent_sn) . "/" . $parent_sn) && !ip_in_interface_alias_subnet($_POST['if'], $_POST['gateway'])) 
				$input_errors[] = sprintf(gettext("The gateway address %s does not lie within the chosen interface's subnet."), $_POST['gateway']);
		}
		if (($_POST['dns1'] && !is_ipaddrv4($_POST['dns1'])) || ($_POST['dns2'] && !is_ipaddrv4($_POST['dns2'])))
			$input_errors[] = gettext("A valid IP address must be specified for the primary/secondary DNS servers.");

		if ($_POST['deftime'] && (!is_numeric($_POST['deftime']) || ($_POST['deftime'] < 60)))
			$input_errors[] = gettext("The default lease time must be at least 60 seconds.");
		if ($_POST['maxtime'] && (!is_numeric($_POST['maxtime']) || ($_POST['maxtime'] < 60) || ($_POST['maxtime'] <= $_POST['deftime'])))
			$input_errors[] = gettext("The maximum lease time must be at least 60 seconds and higher than the default lease time.");
		if (($_POST['ddnsdomain'] && !is_domain($_POST['ddnsdomain'])))
			$input_errors[] = gettext("A valid domain name must be specified for the dynamic DNS registration.");
		if (($_POST['ntp1'] && !is_ipaddrv4($_POST['ntp1'])) || ($_POST['ntp2'] && !is_ipaddrv4($_POST['ntp2'])))
			$input_errors[] = gettext("A valid IP address must be specified for the primary/secondary NTP servers.");
		if (($_POST['domain'] && !is_domain($_POST['domain'])))
			$input_errors[] = gettext("A valid domain name must be specified for the DNS domain.");
		if ($_POST['tftp'] && !is_ipaddrv4($_POST['tftp']) && !is_domain($_POST['tftp']) && !is_URL($_POST['tftp']))
			$input_errors[] = gettext("A valid IP address or hostname must be specified for the TFTP server.");
		if (($_POST['nextserver'] && !is_ipaddrv4($_POST['nextserver'])))
			$input_errors[] = gettext("A valid IP address must be specified for the network boot server.");

		if(gen_subnet($ifcfgip, $ifcfgsn) == $_POST['range_from'])
			$input_errors[] = gettext("You cannot use the network address in the starting subnet range.");
		if(gen_subnet_max($ifcfgip, $ifcfgsn) == $_POST['range_to'])
			$input_errors[] = gettext("You cannot use the broadcast address in the ending subnet range.");

		// Disallow a range that includes the virtualip
		if (is_array($config['virtualip']['vip'])) {
			foreach($config['virtualip']['vip'] as $vip) {
				if($vip['interface'] == $if)
					if($vip['subnet'] && is_inrange($vip['subnet'], $_POST['range_from'], $_POST['range_to']))
						$input_errors[] = sprintf(gettext("The subnet range cannot overlap with virtual IP address %s."),$vip['subnet']);
			}
		}

		$noip = false;
		if(is_array($a_maps))
			foreach ($a_maps as $map)
				if (empty($map['ipaddr']))
					$noip = true;
		if ($_POST['staticarp'] && $noip)
			$input_errors[] = "Cannot enable static ARP when you have static map entries without IP addresses. Ensure all static maps have IP addresses and try again.";

		if(is_array($pconfig['numberoptions']['item'])) {
			foreach ($pconfig['numberoptions']['item'] as $numberoption) {
				if ( $numberoption['type'] == 'text' && strstr($numberoption['value'], '"') )
					$input_errors[] = gettext("Text type cannot include quotation marks.");
				else if ( $numberoption['type'] == 'string' && !preg_match('/^"[^"]*"$/', $numberoption['value']) && !preg_match('/^[0-9a-f]{2}(?:\:[0-9a-f]{2})*$/i', $numberoption['value']) )
					$input_errors[] = gettext("String type must be enclosed in quotes like \"this\" or must be a series of octets specified in hexadecimal, separated by colons, like 01:23:45:67:89:ab:cd:ef");
				else if ( $numberoption['type'] == 'boolean' && $numberoption['value'] != 'true' && $numberoption['value'] != 'false' && $numberoption['value'] != 'on' && $numberoption['value'] != 'off' )
					$input_errors[] = gettext("Boolean type must be true, false, on, or off.");
				else if ( $numberoption['type'] == 'unsigned integer 8' && (!is_numeric($numberoption['value']) || $numberoption['value'] < 0 || $numberoption['value'] > 255) )
					$input_errors[] = gettext("Unsigned 8-bit integer type must be a number in the range 0 to 255.");
				else if ( $numberoption['type'] == 'unsigned integer 16' && (!is_numeric($numberoption['value']) || $numberoption['value'] < 0 || $numberoption['value'] > 65535) )
					$input_errors[] = gettext("Unsigned 16-bit integer type must be a number in the range 0 to 65535.");
				else if ( $numberoption['type'] == 'unsigned integer 32' && (!is_numeric($numberoption['value']) || $numberoption['value'] < 0 || $numberoption['value'] > 4294967295) )
					$input_errors[] = gettext("Unsigned 32-bit integer type must be a number in the range 0 to 4294967295.");
				else if ( $numberoption['type'] == 'signed integer 8' && (!is_numeric($numberoption['value']) || $numberoption['value'] < -128 || $numberoption['value'] > 127) )
					$input_errors[] = gettext("Signed 8-bit integer type must be a number in the range -128 to 127.");
				else if ( $numberoption['type'] == 'signed integer 16' && (!is_numeric($numberoption['value']) || $numberoption['value'] < -32768 || $numberoption['value'] > 32767) )
					$input_errors[] = gettext("Signed 16-bit integer type must be a number in the range -32768 to 32767.");
				else if ( $numberoption['type'] == 'signed integer 32' && (!is_numeric($numberoption['value']) || $numberoption['value'] < -2147483648 || $numberoption['value'] > 2147483647) )
					$input_errors[] = gettext("Signed 32-bit integer type must be a number in the range -2147483648 to 2147483647.");
				else if ( $numberoption['type'] == 'ip-address' && !is_ipaddrv4($numberoption['value']) && !is_hostname($numberoption['value']) )
					$input_errors[] = gettext("IP address or host type must be an IP address or host name.");
			}
		}

		if (!$input_errors) {
			/* make sure the range lies within the current subnet */
			$subnet_start = ip2ulong(long2ip32(ip2long($ifcfgip) & gen_subnet_mask_long($ifcfgsn)));
			$subnet_end = ip2ulong(long2ip32(ip2long($ifcfgip) | (~gen_subnet_mask_long($ifcfgsn))));

			if ((ip2ulong($_POST['range_from']) < $subnet_start) || (ip2ulong($_POST['range_from']) > $subnet_end) ||
			    (ip2ulong($_POST['range_to']) < $subnet_start) || (ip2ulong($_POST['range_to']) > $subnet_end)) {
				$input_errors[] = gettext("The specified range lies outside of the current subnet.");
			}

			if (ip2ulong($_POST['range_from']) > ip2ulong($_POST['range_to']))
				$input_errors[] = gettext("The range is invalid (first element higher than second element).");

			/* make sure that the DHCP Relay isn't enabled on this interface */
			if (isset($config['dhcrelay'][$if]['enable']))
				$input_errors[] = sprintf(gettext("You must disable the DHCP relay on the %s interface before enabling the DHCP server."),$iflist[$if]);

			$dynsubnet_start = ip2ulong($_POST['range_from']);
			$dynsubnet_end = ip2ulong($_POST['range_to']);
			if (is_array($a_maps)) {
				foreach ($a_maps as $map) {
					if (empty($map['ipaddr']))
						continue;
					if ((ip2ulong($map['ipaddr']) > $dynsubnet_start) &&
						(ip2ulong($map['ipaddr']) < $dynsubnet_end)) {
						$input_errors[] = sprintf(gettext("The DHCP range cannot overlap any static DHCP mappings."));
						break;
					}
				}
			}
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
		if($previous <> $_POST['failover_peerip'])
			mwexec("/bin/rm -rf /var/dhcpd/var/db/*");

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
		$config['dhcpd'][$if]['nextserver'] = $_POST['nextserver'];
		$config['dhcpd'][$if]['filename'] = $_POST['filename'];
		$config['dhcpd'][$if]['rootpath'] = $_POST['rootpath'];

		// Handle the custom options rowhelper
		if(isset($config['dhcpd'][$if]['numberoptions']['item']))
			unset($config['dhcpd'][$if]['numberoptions']['item']);

		$config['dhcpd'][$if]['numberoptions'] = $numberoptions;

		write_config();

		$retval = 0;
		$retvaldhcp = 0;
		$retvaldns = 0;
		/* Stop DHCP so we can cleanup leases */
		killbyname("dhcpd");
		dhcp_clean_leases();
		/* dnsmasq_configure calls dhcpd_configure */
		/* no need to restart dhcpd twice */
		if (isset($config['dnsmasq']['regdhcpstatic']))	{
			$retvaldns = services_dnsmasq_configure();
			if ($retvaldns == 0) {
				clear_subsystem_dirty('hosts');
				clear_subsystem_dirty('staticmaps');
			}
		} else {
			$retvaldhcp = services_dhcpd_configure();
			if ($retvaldhcp == 0)
				clear_subsystem_dirty('staticmaps');
		}
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
			mark_subsystem_dirty('staticmaps');
			if (isset($config['dnsmasq']['regdhcpstatic']))
				mark_subsystem_dirty('hosts');
		}
		header("Location: services_dhcp.php?if={$if}");
		exit;
	}
}

$pgtitle = array(gettext("Services"),gettext("DHCP server"));
$statusurl = "status_dhcp_leases.php";
$logurl = "diag_logs_dhcp.php";

include("head.inc");

?>

<script type="text/javascript" src="/javascript/row_helper.js">
</script>

<script type="text/javascript">
	function itemtype_field(fieldname, fieldsize, n) {
		return '<select name="' + fieldname + n + '" class="formselect" id="' + fieldname + n + '"><?php
			$customitemtypes = array('text' => gettext('Text'), 'string' => gettext('String'), 'boolean' => gettext('Boolean'),
				'unsigned integer 8' => gettext('Unsigned 8-bit integer'), 'unsigned integer 16' => gettext('Unsigned 16-bit integer'), 'unsigned integer 32' => gettext('Unsigned 32-bit integer'),
				'signed integer 8' => gettext('Signed 8-bit integer'), 'signed integer 16' => gettext('Signed 16-bit integer'), 'signed integer 32' => gettext('Signed 32-bit integer'), 'ip-address' => gettext('IP address or host'));
			foreach ($customitemtypes as $typename => $typedescr) {
				echo "<option value=\"{$typename}\">{$typedescr}</option>";
			}
		?></select>';
	}

	rowname[0] = "number";
	rowtype[0] = "textbox";
	rowsize[0] = "10";
	rowname[1] = "itemtype";
	rowtype[1] = itemtype_field;
	rowname[2] = "value";
	rowtype[2] = "textbox";
	rowsize[2] = "40";
</script>

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

	function show_shownumbervalue() {
		document.getElementById("shownumbervaluebox").innerHTML='';
		aodiv = document.getElementById('shownumbervalue');
		aodiv.style.display = "block";
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
		echo gettext("DHCP Relay is currently enabled. Cannot enable the DHCP Server service while the DHCP Relay is enabled on any interface.");
		include("fend.inc");
		echo "</body>";
		echo "</html>";
		exit;
	}
?>
<?php if (is_subsystem_dirty('staticmaps')): ?><p>
<?php print_info_box_np(gettext("The static mapping configuration has been changed") . ".<br>" . gettext("You must apply the changes in order for them to take effect."));?><br>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<?php
	/* active tabs */
	$tab_array = array();
	$tabscounter = 0;
	$i = 0;
	foreach ($iflist as $ifent => $ifname) {
		$oc = $config['interfaces'][$ifent];
		if ((is_array($config['dhcpd'][$ifent]) && !isset($config['dhcpd'][$ifent]['enable']) && (!is_ipaddrv4($oc['ipaddr']))) ||
			(!is_array($config['dhcpd'][$ifent]) && (!is_ipaddrv4($oc['ipaddr']))))
			continue;
		if ($ifent == $if)
			$active = true;
		else
			$active = false;
		$tab_array[] = array($ifname, $active, "services_dhcp.php?if={$ifent}");
		$tabscounter++;
	}
	if ($tabscounter == 0) {
		echo "</td></tr></table></form>";
		include("fend.inc");
		echo "</body>";
		echo "</html>";
		exit;
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
			<strong><?php printf(gettext("Enable DHCP server on " .
			"%s " .
			"interface"),htmlspecialchars($iflist[$if]));?></strong></td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vtable">&nbsp;</td>
			<td width="78%" class="vtable">
				<input name="denyunknown" id="denyunknown" type="checkbox" value="yes" <?php if ($pconfig['denyunknown']) echo "checked"; ?>>
				<strong><?=gettext("Deny unknown clients");?></strong><br>
				<?=gettext("If this is checked, only the clients defined below will get DHCP leases from this server. ");?></td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Subnet");?></td>
			<td width="78%" class="vtable">
				<?=gen_subnet($ifcfgip, $ifcfgsn);?>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Subnet mask");?></td>
			<td width="78%" class="vtable">
				<?=gen_subnet_mask($ifcfgsn);?>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Available range");?></td>
			<td width="78%" class="vtable">
			<?php
				$range_from = ip2long(long2ip32(ip2long($ifcfgip) & gen_subnet_mask_long($ifcfgsn)));
				$range_from++;
				echo long2ip32($range_from);
			?>
			-
			<?php
				$range_to = ip2long(long2ip32(ip2long($ifcfgip) | (~gen_subnet_mask_long($ifcfgsn))));
				$range_to--;
				echo long2ip32($range_to);
			?>
			</td>
			</tr>
			<?php if($is_olsr_enabled): ?>
			<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Subnet Mask");?></td>
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
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Range");?></td>
			<td width="78%" class="vtable">
				<input name="range_from" type="text" class="formfld unknown" id="range_from" size="20" value="<?=htmlspecialchars($pconfig['range_from']);?>">
				&nbsp;<?=gettext("to"); ?>&nbsp; <input name="range_to" type="text" class="formfld unknown" id="range_to" size="20" value="<?=htmlspecialchars($pconfig['range_to']);?>">
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("WINS servers");?></td>
			<td width="78%" class="vtable">
				<input name="wins1" type="text" class="formfld unknown" id="wins1" size="20" value="<?=htmlspecialchars($pconfig['wins1']);?>"><br>
				<input name="wins2" type="text" class="formfld unknown" id="wins2" size="20" value="<?=htmlspecialchars($pconfig['wins2']);?>">
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("DNS servers");?></td>
			<td width="78%" class="vtable">
				<input name="dns1" type="text" class="formfld unknown" id="dns1" size="20" value="<?=htmlspecialchars($pconfig['dns1']);?>"><br>
				<input name="dns2" type="text" class="formfld unknown" id="dns2" size="20" value="<?=htmlspecialchars($pconfig['dns2']);?>"><br>
				<?=gettext("NOTE: leave blank to use the system default DNS servers - this interface's IP if DNS forwarder is enabled, otherwise the servers configured on the General page.");?>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Gateway");?></td>
			<td width="78%" class="vtable">
				<input name="gateway" type="text" class="formfld host" id="gateway" size="20" value="<?=htmlspecialchars($pconfig['gateway']);?>"><br>
			 	 <?=gettext("The default is to use the IP on this interface of the firewall as the gateway. Specify an alternate gateway here if this is not the correct gateway for your network.");?>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Domain name");?></td>
			<td width="78%" class="vtable">
				<input name="domain" type="text" class="formfld unknown" id="domain" size="20" value="<?=htmlspecialchars($pconfig['domain']);?>"><br>
				 <?=gettext("The default is to use the domain name of this system as the default domain name provided by DHCP. You may specify an alternate domain name here.");?>
			 </td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Domain search list");?></td>
			<td width="78%" class="vtable">
				<input name="domainsearchlist" type="text" class="formfld unknown" id="domainsearchlist" size="20" value="<?=htmlspecialchars($pconfig['domainsearchlist']);?>"><br>
				<?=gettext("The DHCP server can optionally provide a domain search list.");?>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Default lease time");?></td>
			<td width="78%" class="vtable">
				<input name="deftime" type="text" class="formfld unknown" id="deftime" size="10" value="<?=htmlspecialchars($pconfig['deftime']);?>">
				<?=gettext("seconds");?><br>
				<?=gettext("This is used for clients that do not ask for a specific " .
				"expiration time."); ?><br>
				<?=gettext("The default is 7200 seconds.");?>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Maximum lease time");?></td>
			<td width="78%" class="vtable">
				<input name="maxtime" type="text" class="formfld unknown" id="maxtime" size="10" value="<?=htmlspecialchars($pconfig['maxtime']);?>">
				<?=gettext("seconds");?><br>
				<?=gettext("This is the maximum lease time for clients that ask".
				" for a specific expiration time."); ?><br>
				<?=gettext("The default is 86400 seconds.");?>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Failover peer IP:");?></td>
			<td width="78%" class="vtable">
				<input name="failover_peerip" type="text" class="formfld host" id="failover_peerip" size="20" value="<?=htmlspecialchars($pconfig['failover_peerip']);?>"><br>
				<?=gettext("Leave blank to disable.  Enter the interface IP address of the other machine.  Machines must be using CARP.");?>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Static ARP");?></td>
			<td width="78%" class="vtable">
				<table>
					<tr>
					<td>
						<input valign="middle" type="checkbox" value="yes" name="staticarp" id="staticarp" <?php if($pconfig['staticarp']) echo " checked"; ?>>&nbsp;
					</td>
					<td><b><?=gettext("Enable Static ARP entries");?></b></td>
					</tr>
					<tr>
					<td>&nbsp;</td>
					<td>
						<span class="red"><strong><?=gettext("Note:");?></strong></span> <?=gettext("Only the machines listed below will be able to communicate with the firewall on this NIC.");?>
					</td>
					</tr>
				</table>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Dynamic DNS");?></td>
			<td width="78%" class="vtable">
				<div id="showddnsbox">
					<input type="button" onClick="show_ddns_config()" value="<?=gettext("Advanced");?>"></input> - <?=gettext("Show Dynamic DNS");?></a>
				</div>
				<div id="showddns" style="display:none">
					<input valign="middle" type="checkbox" value="yes" name="ddnsupdate" id="ddnsupdate" <?php if($pconfig['ddnsupdate']) echo " checked"; ?>>&nbsp;
					<b><?=gettext("Enable registration of DHCP client names in DNS.");?></b><br />
					<p>
					<input name="ddnsdomain" type="text" class="formfld unknown" id="ddnsdomain" size="20" value="<?=htmlspecialchars($pconfig['ddnsdomain']);?>"><br />
					<?=gettext("Note: Leave blank to disable dynamic DNS registration.");?><br />
					<?=gettext("Enter the dynamic DNS domain which will be used to register client names in the DNS server.");?>
				</div>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("NTP servers");?></td>
			<td width="78%" class="vtable">
				<div id="showntpbox">
					<input type="button" onClick="show_ntp_config()" value="<?=gettext("Advanced");?>"></input> - <?=gettext("Show NTP configuration");?></a>
				</div>
				<div id="showntp" style="display:none">
					<input name="ntp1" type="text" class="formfld unknown" id="ntp1" size="20" value="<?=htmlspecialchars($pconfig['ntp1']);?>"><br>
					<input name="ntp2" type="text" class="formfld unknown" id="ntp2" size="20" value="<?=htmlspecialchars($pconfig['ntp2']);?>">
				</div>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("TFTP server");?></td>
			<td width="78%" class="vtable">
			<div id="showtftpbox">
				<input type="button" onClick="show_tftp_config()" value="<?=gettext("Advanced");?>"></input> - <?=gettext("Show TFTP configuration");?></a>
			</div>
			<div id="showtftp" style="display:none">
				<input name="tftp" type="text" class="formfld unknown" id="tftp" size="50" value="<?=htmlspecialchars($pconfig['tftp']);?>"><br>
				<?=gettext("Leave blank to disable.  Enter a full hostname or IP for the TFTP server.");?>
			</div>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("LDAP URI");?></td>
			<td width="78%" class="vtable">
				<div id="showldapbox">
					<input type="button" onClick="show_ldap_config()" value="<?=gettext("Advanced");?>"></input> - <?=gettext("Show LDAP configuration");?></a>
				</div>
				<div id="showldap" style="display:none">
					<input name="ldap" type="text" class="formfld unknown" id="ldap" size="80" value="<?=htmlspecialchars($pconfig['ldap']);?>"><br>
					<?=gettext("Leave blank to disable.  Enter a full URI for the LDAP server in the form ldap://ldap.example.com/dc=example,dc=com");?>
				</div>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Enable network booting");?></td>
			<td width="78%" class="vtable">
				<div id="shownetbootbox">
					<input type="button" onClick="show_netboot_config()" value="<?=gettext("Advanced");?>"></input> - <?=gettext("Show Network booting");?></a>
				</div>
				<div id="shownetboot" style="display:none">
					<input valign="middle" type="checkbox" value="yes" name="netboot" id="netboot" <?php if($pconfig['netboot']) echo " checked"; ?>>&nbsp;
					<b><?=gettext("Enables network booting.");?></b>
					<p>
					<?=gettext("Enter the IP of the"); ?> <b><?=gettext("next-server"); ?></b>
					<input name="nextserver" type="text" class="formfld unknown" id="nextserver" size="20" value="<?=htmlspecialchars($pconfig['nextserver']);?>">
					<?=gettext("and the filename");?>
					<input name="filename" type="text" class="formfld unknown" id="filename" size="20" value="<?=htmlspecialchars($pconfig['filename']);?>"><br>
					<?=gettext("Note: You need both a filename and a boot server configured for this to work!");?>
					<p>
					<?=gettext("Enter the"); ?> <b><?=gettext("root-path"); ?></b>-<?=gettext("string");?>
					<input name="rootpath" type="text" class="formfld unknown" id="rootpath" size="90" value="<?=htmlspecialchars($pconfig['rootpath']);?>"><br>
					<?=gettext("Note: string-format: iscsi:(servername):(protocol):(port):(LUN):targetname");?>
				</div>
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Additional BOOTP/DHCP Options");?></td>
			<td width="78%" class="vtable">
				<div id="shownumbervaluebox">
					<input type="button" onClick="show_shownumbervalue()" value="<?=gettext("Advanced");?>"></input> - <?=gettext("Show Additional BOOTP/DHCP Options");?></a>
				</div>
				<div id="shownumbervalue" style="display:none">
				<table id="maintable">
				<tbody>
				<tr>
				<td colspan="3">
					<div style="padding:5px; margin-top: 16px; margin-bottom: 16px; border:1px dashed #000066; background-color: #ffffff; color: #000000; font-size: 8pt;" id="itemhelp">
					<?=gettext("Enter the DHCP option number and the value for each item you would like to include in the DHCP lease information.  For a list of available options please visit this"); ?> <a href="http://www.iana.org/assignments/bootp-dhcp-parameters/" target="_new"><?=gettext("URL"); ?></a>
					</div>
				</td>
				</tr>
				<tr>
				<td><div id="onecolumn"><?=gettext("Number");?></div></td>
				<td><div id="twocolumn"><?=gettext("Type");?></div></td>
				<td><div id="threecolumn"><?=gettext("Value");?></div></td>
				</tr>
				<?php $counter = 0; ?>
				<?php
					if($pconfig['numberoptions'])
						foreach($pconfig['numberoptions']['item'] as $item):
				?>
					<?php
						$number = $item['number'];
						$itemtype = $item['type'];
						$value = $item['value'];
					?>
				<tr>
				<td>
					<input autocomplete="off" name="number<?php echo $counter; ?>" type="text" class="formfld unknown" id="number<?php echo $counter; ?>" size="10" value="<?=htmlspecialchars($number);?>" />
				</td>
				<td>
					<select name="itemtype<?php echo $counter; ?>" class="formselect" id="itemtype<?php echo $counter; ?>">
					<?php
					foreach ($customitemtypes as $typename => $typedescr) {
						echo "<option value=\"{$typename}\" ";
						if ($itemtype == $typename) echo "selected";
						echo ">" . $typedescr . "</option>";
					}
					?>
					</select>
				</td>
				<td>
					<input autocomplete="off" name="value<?php echo $counter; ?>" type="text" class="formfld unknown" id="value<?php echo $counter; ?>" size="40" value="<?=htmlspecialchars($value);?>" />
				</td>
				<td>
					<a onclick="removeRow(this); return false;" href="#"><img border="0" src="/themes/<?echo $g['theme'];?>/images/icons/icon_x.gif" /></a>
				</td>
				</tr>
				<?php $counter++; ?>
				<?php endforeach; ?>
				</tbody>
				<tfoot>
				</tfoot>
				</table>
				<a onclick="javascript:addRowTo('maintable', 'formfldalias'); return false;" href="#">
					<img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" alt="" title="<?=gettext("add another entry");?>" />
				</a>
				<script type="text/javascript">
					field_counter_js = 3;
					rows = 1;
					totalrows = <?php echo $counter; ?>;
					loaded = <?php echo $counter; ?>;
				</script>
				</div>

				</td>
			</tr>
			<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input name="if" type="hidden" value="<?=htmlspecialchars($if);?>">
				<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" onclick="enable_change(true)">
			</td>
			</tr>
			<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%"> <p><span class="vexpl"><span class="red"><strong><?=gettext("Note:");?><br>
				</strong></span><?=gettext("The DNS servers entered in"); ?> <a href="system.php"><?=gettext("System: " .
				"General setup"); ?></a> <?=gettext("(or the"); ?> <a href="services_dnsmasq.php"><?=gettext("DNS " .
				"forwarder"); ?></a>, <?=gettext("if enabled)"); ?> </span><span class="vexpl"><?=gettext("will " .
				"be assigned to clients by the DHCP server."); ?><br>
				<br>
				<?=gettext("The DHCP lease table can be viewed on the"); ?> <a href="status_dhcp_leases.php"><?=gettext("Status: " .
				"DHCP leases"); ?></a> <?=gettext("page."); ?><br>
				</span></p>
			</td>
			</tr>
		</table>
		<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<td width="25%" class="listhdrr"><?=gettext("MAC address");?></td>
			<td width="15%" class="listhdrr"><?=gettext("IP address");?></td>
			<td width="20%" class="listhdrr"><?=gettext("Hostname");?></td>
			<td width="30%" class="listhdr"><?=gettext("Description");?></td>
			<td width="10%" class="list">
			<table border="0" cellspacing="0" cellpadding="1">
			<tr>
			<td valign="middle" width="17"></td>
			<td valign="middle"><a href="services_dhcp_edit.php?if=<?=htmlspecialchars($if);?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
			</tr>
			</table>
			</td>
		</tr>
			<?php if(is_array($a_maps)): ?>
			<?php $i = 0; foreach ($a_maps as $mapent): ?>
			<?php if($mapent['mac'] <> "" or $mapent['ipaddr'] <> ""): ?>
		<tr>
		<td class="listlr" ondblclick="document.location='services_dhcp_edit.php?if=<?=htmlspecialchars($if);?>&id=<?=$i;?>';">
			<?=htmlspecialchars($mapent['mac']);?>
		</td>
		<td class="listr" ondblclick="document.location='services_dhcp_edit.php?if=<?=htmlspecialchars($if);?>&id=<?=$i;?>';">
			<?=htmlspecialchars($mapent['ipaddr']);?>&nbsp;
		</td>
		<td class="listr" ondblclick="document.location='services_dhcp_edit.php?if=<?=htmlspecialchars($if);?>&id=<?=$i;?>';">
			<?=htmlspecialchars($mapent['hostname']);?>&nbsp;
		</td>
		<td class="listbg" ondblclick="document.location='services_dhcp_edit.php?if=<?=htmlspecialchars($if);?>&id=<?=$i;?>';">
			<?=htmlspecialchars($mapent['descr']);?>&nbsp;
		</td>
		<td valign="middle" nowrap class="list">
			<table border="0" cellspacing="0" cellpadding="1">
			<tr>
			<td valign="middle"><a href="services_dhcp_edit.php?if=<?=htmlspecialchars($if);?>&id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a></td>
			<td valign="middle"><a href="services_dhcp.php?if=<?=htmlspecialchars($if);?>&act=del&id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this mapping?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a></td>
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
			<td valign="middle"><a href="services_dhcp_edit.php?if=<?=htmlspecialchars($if);?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
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
