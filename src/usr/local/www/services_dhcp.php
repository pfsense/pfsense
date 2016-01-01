<?php
/*
	services_dhcp.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-services-dhcpserver
##|*NAME=Services: DHCP Server
##|*DESCR=Allow access to the 'Services: DHCP Server' page.
##|*MATCH=services_dhcp.php*
##|-PRIV

require("guiconfig.inc");
require_once("filter.inc");
require_once('rrd.inc');
require_once("shaper.inc");

if (!$g['services_dhcp_server_enable']) {
	header("Location: /");
	exit;
}

$if = $_GET['if'];
if (!empty($_POST['if'])) {
	$if = $_POST['if'];
}

/* if OLSRD is enabled, allow WAN to house DHCP. */
if ($config['installedpackages']['olsrd']) {
	foreach ($config['installedpackages']['olsrd']['config'] as $olsrd) {
		if ($olsrd['enable']) {
			$is_olsr_enabled = true;
			break;
		}
	}
}

$iflist = get_configured_interface_with_descr();

/* set the starting interface */
if (!$if || !isset($iflist[$if])) {
	foreach ($iflist as $ifent => $ifname) {
		$oc = $config['interfaces'][$ifent];
		if ((is_array($config['dhcpd'][$ifent]) && !isset($config['dhcpd'][$ifent]['enable']) && (!is_ipaddrv4($oc['ipaddr']))) ||
		    (!is_array($config['dhcpd'][$ifent]) && (!is_ipaddrv4($oc['ipaddr'])))) {
			continue;
		}

		$if = $ifent;
		break;
	}
}

$act = $_GET['act'];
if (!empty($_POST['act'])) {
	$act = $_POST['act'];
}

$a_pools = array();

if (is_array($config['dhcpd'][$if])) {
	$pool = $_GET['pool'];
	if (is_numeric($_POST['pool'])) {
		$pool = $_POST['pool'];
	}

	// If we have a pool but no interface name, that's not valid. Redirect away.
	if (is_numeric($pool) && empty($if)) {
		header("Location: services_dhcp.php");
		exit;
	}

	if (!is_array($config['dhcpd'][$if]['pool'])) {
		$config['dhcpd'][$if]['pool'] = array();
	}

	$a_pools = &$config['dhcpd'][$if]['pool'];

	if (is_numeric($pool) && $a_pools[$pool]) {
		$dhcpdconf = &$a_pools[$pool];
	} elseif ($act == "newpool") {
		$dhcpdconf = array();
	} else {
		$dhcpdconf = &$config['dhcpd'][$if];
	}

	if (!is_array($config['dhcpd'][$if]['staticmap'])) {
		$dhcpdconf['staticmap'] = array();
	}

	$a_maps = &$config['dhcpd'][$if]['staticmap'];
}
if (is_array($dhcpdconf)) {
	// Global Options
	if (!is_numeric($pool) && !($act == "newpool")) {
		$pconfig['enable'] = isset($dhcpdconf['enable']);
		$pconfig['staticarp'] = isset($dhcpdconf['staticarp']);
		// No reason to specify this per-pool, per the dhcpd.conf man page it needs to be in every
		//	 pool and should be specified in every pool both nodes share, so we'll treat it as global
		$pconfig['failover_peerip'] = $dhcpdconf['failover_peerip'];

		// dhcpleaseinlocaltime is global to all interfaces. So if it is selected on any interface,
		// then show it true/checked.
		foreach ($config['dhcpd'] as $dhcpdifitem) {
			$dhcpleaseinlocaltime = $dhcpdifitem['dhcpleaseinlocaltime'];
			if ($dhcpleaseinlocaltime) {
				break;
			}
		}

		$pconfig['dhcpleaseinlocaltime'] = $dhcpleaseinlocaltime;
	} else {
		// Options that exist only in pools
		$pconfig['descr'] = $dhcpdconf['descr'];
	}

	// Options that can be global or per-pool.
	if (is_array($dhcpdconf['range'])) {
		$pconfig['range_from'] = $dhcpdconf['range']['from'];
		$pconfig['range_to'] = $dhcpdconf['range']['to'];
	}

	$pconfig['deftime'] = $dhcpdconf['defaultleasetime'];
	$pconfig['maxtime'] = $dhcpdconf['maxleasetime'];
	$pconfig['gateway'] = $dhcpdconf['gateway'];
	$pconfig['domain'] = $dhcpdconf['domain'];
	$pconfig['domainsearchlist'] = $dhcpdconf['domainsearchlist'];
	list($pconfig['wins1'], $pconfig['wins2']) = $dhcpdconf['winsserver'];
	list($pconfig['dns1'], $pconfig['dns2'], $pconfig['dns3'], $pconfig['dns4']) = $dhcpdconf['dnsserver'];
	$pconfig['denyunknown'] = isset($dhcpdconf['denyunknown']);
	$pconfig['ddnsdomain'] = $dhcpdconf['ddnsdomain'];
	$pconfig['ddnsdomainprimary'] = $dhcpdconf['ddnsdomainprimary'];
	$pconfig['ddnsdomainkeyname'] = $dhcpdconf['ddnsdomainkeyname'];
	$pconfig['ddnsdomainkey'] = $dhcpdconf['ddnsdomainkey'];
	$pconfig['ddnsupdate'] = isset($dhcpdconf['ddnsupdate']);
	$pconfig['mac_allow'] = $dhcpdconf['mac_allow'];
	$pconfig['mac_deny'] = $dhcpdconf['mac_deny'];
	list($pconfig['ntp1'], $pconfig['ntp2']) = $dhcpdconf['ntpserver'];
	$pconfig['tftp'] = $dhcpdconf['tftp'];
	$pconfig['ldap'] = $dhcpdconf['ldap'];
	$pconfig['netboot'] = isset($dhcpdconf['netboot']);
	$pconfig['nextserver'] = $dhcpdconf['nextserver'];
	$pconfig['filename'] = $dhcpdconf['filename'];
	$pconfig['filename32'] = $dhcpdconf['filename32'];
	$pconfig['filename64'] = $dhcpdconf['filename64'];
	$pconfig['rootpath'] = $dhcpdconf['rootpath'];
	$pconfig['netmask'] = $dhcpdconf['netmask'];
	$pconfig['numberoptions'] = $dhcpdconf['numberoptions'];
	$pconfig['statsgraph'] = $dhcpdconf['statsgraph'];
}

$ifcfgip = $config['interfaces'][$if]['ipaddr'];
$ifcfgsn = $config['interfaces'][$if]['subnet'];

function validate_partial_mac_list($maclist) {
	$macs = explode(',', $maclist);

	// Loop through and look for invalid MACs.
	foreach ($macs as $mac) {
		if (!is_macaddr($mac, true)) {
			return false;
		}
	}

	return true;
}

if (isset($_POST['submit'])) {

	unset($input_errors);

	$pconfig = $_POST;

	$numberoptions = array();
	for ($x = 0; $x < 99; $x++) {
		if (isset($_POST["number{$x}"]) && ctype_digit($_POST["number{$x}"])) {
			$numbervalue = array();
			$numbervalue['number'] = htmlspecialchars($_POST["number{$x}"]);
			$numbervalue['type'] = htmlspecialchars($_POST["itemtype{$x}"]);
			$numbervalue['value'] = str_replace('&quot;', '"', htmlspecialchars($_POST["value{$x}"]));
			$numberoptions['item'][] = $numbervalue;
		}
	}

	// Reload the new pconfig variable that the form uses.
	$pconfig['numberoptions'] = $numberoptions;

	/* input validation */
	if ($_POST['enable'] || is_numeric($pool) || $act == "newpool") {
		$reqdfields = explode(" ", "range_from range_to");
		$reqdfieldsn = array(gettext("Range begin"), gettext("Range end"));

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		if (($_POST['range_from'] && !is_ipaddrv4($_POST['range_from']))) {
			$input_errors[] = gettext("A valid range must be specified.");
		}
		if (($_POST['range_to'] && !is_ipaddrv4($_POST['range_to']))) {
			$input_errors[] = gettext("A valid range must be specified.");
		}
		if (($_POST['gateway'] && $_POST['gateway'] != "none" && !is_ipaddrv4($_POST['gateway']))) {
			$input_errors[] = gettext("A valid IP address must be specified for the gateway.");
		}
		if (($_POST['wins1'] && !is_ipaddrv4($_POST['wins1'])) || ($_POST['wins2'] && !is_ipaddrv4($_POST['wins2']))) {
			$input_errors[] = gettext("A valid IP address must be specified for the primary/secondary WINS servers.");
		}
		$parent_ip = get_interface_ip($_POST['if']);
		if (is_ipaddrv4($parent_ip) && $_POST['gateway'] && $_POST['gateway'] != "none") {
			$parent_sn = get_interface_subnet($_POST['if']);
			if (!ip_in_subnet($_POST['gateway'], gen_subnet($parent_ip, $parent_sn) . "/" . $parent_sn) && !ip_in_interface_alias_subnet($_POST['if'], $_POST['gateway'])) {
				$input_errors[] = sprintf(gettext("The gateway address %s does not lie within the chosen interface's subnet."), $_POST['gateway']);
			}
		}

		if (($_POST['dns1'] && !is_ipaddrv4($_POST['dns1'])) || ($_POST['dns2'] && !is_ipaddrv4($_POST['dns2'])) || ($_POST['dns3'] && !is_ipaddrv4($_POST['dns3'])) || ($_POST['dns4'] && !is_ipaddrv4($_POST['dns4']))) {
			$input_errors[] = gettext("A valid IP address must be specified for each of the DNS servers.");
		}

		if ($_POST['deftime'] && (!is_numeric($_POST['deftime']) || ($_POST['deftime'] < 60))) {
			$input_errors[] = gettext("The default lease time must be at least 60 seconds.");
		}

		if (isset($config['captiveportal']) && is_array($config['captiveportal'])) {
			$deftime = 7200; // Default value if it's empty
			if (is_numeric($_POST['deftime'])) {
				$deftime = $_POST['deftime'];
			}

			foreach ($config['captiveportal'] as $cpZone => $cpdata) {
				if (!isset($cpdata['enable'])) {
					continue;
				}
				if (!isset($cpdata['timeout']) || !is_numeric($cpdata['timeout'])) {
					continue;
				}
				$cp_ifs = explode(',', $cpdata['interface']);
				if (!in_array($if, $cp_ifs)) {
					continue;
				}
				if ($cpdata['timeout'] > $deftime) {
					$input_errors[] = sprintf(gettext(
						"The Captive Portal zone '%s' has Hard Timeout parameter set to a value bigger than Default lease time (%s)."), $cpZone, $deftime);
				}
			}
		}

		if ($_POST['maxtime'] && (!is_numeric($_POST['maxtime']) || ($_POST['maxtime'] < 60) || ($_POST['maxtime'] <= $_POST['deftime']))) {
			$input_errors[] = gettext("The maximum lease time must be at least 60 seconds and higher than the default lease time.");
		}
		if (($_POST['ddnsdomain'] && !is_domain($_POST['ddnsdomain']))) {
			$input_errors[] = gettext("A valid domain name must be specified for the dynamic DNS registration.");
		}
		if (($_POST['ddnsdomain'] && !is_ipaddrv4($_POST['ddnsdomainprimary']))) {
			$input_errors[] = gettext("A valid primary domain name server IP address must be specified for the dynamic domain name.");
		}
		if (($_POST['ddnsdomainkey'] && !$_POST['ddnsdomainkeyname']) ||
		    ($_POST['ddnsdomainkeyname'] && !$_POST['ddnsdomainkey'])) {
			$input_errors[] = gettext("You must specify both a valid domain key and key name.");
		}
		if ($_POST['domainsearchlist']) {
			$domain_array = preg_split("/[ ;]+/", $_POST['domainsearchlist']);
			foreach ($domain_array as $curdomain) {
				if (!is_domain($curdomain)) {
					$input_errors[] = gettext("A valid domain search list must be specified.");
					break;
				}
			}
		}

		// Validate MACs
		if (!empty($_POST['mac_allow']) && !validate_partial_mac_list($_POST['mac_allow'])) {
			$input_errors[] = gettext("If you specify a mac allow list, it must contain only valid partial MAC addresses.");
		}
		if (!empty($_POST['mac_deny']) && !validate_partial_mac_list($_POST['mac_deny'])) {
			$input_errors[] = gettext("If you specify a mac deny list, it must contain only valid partial MAC addresses.");
		}

		if (($_POST['ntp1'] && !is_ipaddrv4($_POST['ntp1'])) || ($_POST['ntp2'] && !is_ipaddrv4($_POST['ntp2']))) {
			$input_errors[] = gettext("A valid IP address must be specified for the primary/secondary NTP servers.");
		}
		if (($_POST['domain'] && !is_domain($_POST['domain']))) {
			$input_errors[] = gettext("A valid domain name must be specified for the DNS domain.");
		}
		if ($_POST['tftp'] && !is_ipaddrv4($_POST['tftp']) && !is_domain($_POST['tftp']) && !is_URL($_POST['tftp'])) {
			$input_errors[] = gettext("A valid IP address or hostname must be specified for the TFTP server.");
		}
		if (($_POST['nextserver'] && !is_ipaddrv4($_POST['nextserver']))) {
			$input_errors[] = gettext("A valid IP address must be specified for the network boot server.");
		}

		if (gen_subnet($ifcfgip, $ifcfgsn) == $_POST['range_from']) {
			$input_errors[] = gettext("You cannot use the network address in the starting subnet range.");
		}
		if (gen_subnet_max($ifcfgip, $ifcfgsn) == $_POST['range_to']) {
			$input_errors[] = gettext("You cannot use the broadcast address in the ending subnet range.");
		}

		// Disallow a range that includes the virtualip
		if (is_array($config['virtualip']['vip'])) {
			foreach ($config['virtualip']['vip'] as $vip) {
				if ($vip['interface'] == $if) {
					if ($vip['subnet'] && is_inrange_v4($vip['subnet'], $_POST['range_from'], $_POST['range_to'])) {
						$input_errors[] = sprintf(gettext("The subnet range cannot overlap with virtual IP address %s."), $vip['subnet']);
					}
				}
			}
		}

		$noip = false;
		if (is_array($a_maps)) {
			foreach ($a_maps as $map) {
				if (empty($map['ipaddr'])) {
					$noip = true;
				}
			}
		}

		if ($_POST['staticarp'] && $noip) {
			$input_errors[] = "Cannot enable static ARP when you have static map entries without IP addresses. Ensure all static maps have IP addresses and try again.";
		}

		if (is_array($pconfig['numberoptions']['item'])) {
			foreach ($pconfig['numberoptions']['item'] as $numberoption) {
				if ($numberoption['type'] == 'text' && strstr($numberoption['value'], '"')) {
					$input_errors[] = gettext("Text type cannot include quotation marks.");
				} else if ($numberoption['type'] == 'string' && !preg_match('/^"[^"]*"$/', $numberoption['value']) && !preg_match('/^[0-9a-f]{2}(?:\:[0-9a-f]{2})*$/i', $numberoption['value'])) {
					$input_errors[] = gettext("String type must be enclosed in quotes like \"this\" or must be a series of octets specified in hexadecimal, separated by colons, like 01:23:45:67:89:ab:cd:ef");
				} else if ($numberoption['type'] == 'boolean' && $numberoption['value'] != 'true' && $numberoption['value'] != 'false' && $numberoption['value'] != 'on' && $numberoption['value'] != 'off') {
					$input_errors[] = gettext("Boolean type must be true, false, on, or off.");
				} else if ($numberoption['type'] == 'unsigned integer 8' && (!is_numeric($numberoption['value']) || $numberoption['value'] < 0 || $numberoption['value'] > 255)) {
					$input_errors[] = gettext("Unsigned 8-bit integer type must be a number in the range 0 to 255.");
				} else if ($numberoption['type'] == 'unsigned integer 16' && (!is_numeric($numberoption['value']) || $numberoption['value'] < 0 || $numberoption['value'] > 65535)) {
					$input_errors[] = gettext("Unsigned 16-bit integer type must be a number in the range 0 to 65535.");
				} else if ($numberoption['type'] == 'unsigned integer 32' && (!is_numeric($numberoption['value']) || $numberoption['value'] < 0 || $numberoption['value'] > 4294967295)) {
					$input_errors[] = gettext("Unsigned 32-bit integer type must be a number in the range 0 to 4294967295.");
				} else if ($numberoption['type'] == 'signed integer 8' && (!is_numeric($numberoption['value']) || $numberoption['value'] < -128 || $numberoption['value'] > 127)) {
					$input_errors[] = gettext("Signed 8-bit integer type must be a number in the range -128 to 127.");
				} else if ($numberoption['type'] == 'signed integer 16' && (!is_numeric($numberoption['value']) || $numberoption['value'] < -32768 || $numberoption['value'] > 32767)) {
					$input_errors[] = gettext("Signed 16-bit integer type must be a number in the range -32768 to 32767.");
				} else if ($numberoption['type'] == 'signed integer 32' && (!is_numeric($numberoption['value']) || $numberoption['value'] < -2147483648 || $numberoption['value'] > 2147483647)) {
					$input_errors[] = gettext("Signed 32-bit integer type must be a number in the range -2147483648 to 2147483647.");
				} else if ($numberoption['type'] == 'ip-address' && !is_ipaddrv4($numberoption['value']) && !is_hostname($numberoption['value'])) {
					$input_errors[] = gettext("IP address or host type must be an IP address or host name.");
				}
			}
		}

		if (!$input_errors) {
			/* make sure the range lies within the current subnet */
			$subnet_start = ip2ulong(long2ip32(ip2long($ifcfgip) & gen_subnet_mask_long($ifcfgsn)));
			$subnet_end = ip2ulong(long2ip32(ip2long($ifcfgip) | (~gen_subnet_mask_long($ifcfgsn))));

			if (ip2ulong($_POST['range_from']) > ip2ulong($_POST['range_to'])) {
				$input_errors[] = gettext("The range is invalid (first element higher than second element).");
			}

			if (ip2ulong($_POST['range_from']) < $subnet_start || ip2ulong($_POST['range_to']) > $subnet_end) {
				$input_errors[] = gettext("The specified range lies outside of the current subnet.");
			}

			if (is_numeric($pool) || ($act == "newpool")) {
				if (!((ip2ulong($_POST['range_from']) > ip2ulong($config['dhcpd'][$if]['range']['to'])) ||
				      (ip2ulong($_POST['range_to']) < ip2ulong($config['dhcpd'][$if]['range']['from'])))) {
					$input_errors[] = gettext("The specified range must not be within the DHCP range for this interface.");
				}
			}

			foreach ($a_pools as $id => $p) {
				if (is_numeric($pool) && ($id == $pool)) {
					continue;
				}

				if (!((ip2ulong($_POST['range_from']) > ip2ulong($p['range']['to'])) ||
				      (ip2ulong($_POST['range_to']) < ip2ulong($p['range']['from'])))) {
					$input_errors[] = gettext("The specified range must not be within the range configured on a DHCP pool for this interface.");
					break;
				}
			}

			/* make sure that the DHCP Relay isn't enabled on this interface */
			if (isset($config['dhcrelay']['enable']) && (stristr($config['dhcrelay']['interface'], $if) !== false)) {
				$input_errors[] = sprintf(gettext("You must disable the DHCP relay on the %s interface before enabling the DHCP server."), $iflist[$if]);
			}

			$dynsubnet_start = ip2ulong($_POST['range_from']);
			$dynsubnet_end = ip2ulong($_POST['range_to']);
			if (is_array($a_maps)) {
				foreach ($a_maps as $map) {
					if (empty($map['ipaddr'])) {
						continue;
					}
					if ((ip2ulong($map['ipaddr']) >= $dynsubnet_start) &&
					    (ip2ulong($map['ipaddr']) <= $dynsubnet_end)) {
						$input_errors[] = sprintf(gettext("The DHCP range cannot overlap any static DHCP mappings."));
						break;
					}
				}
			}
		}
	}

	if (!$input_errors) {
		if (!is_numeric($pool)) {
			if ($act == "newpool") {
				$dhcpdconf = array();
			} else {
				if (!is_array($config['dhcpd'][$if])) {
					$config['dhcpd'][$if] = array();
				}
				$dhcpdconf = $config['dhcpd'][$if];
			}
		} else {
			if (is_array($a_pools[$pool])) {
				$dhcpdconf = $a_pools[$pool];
			} else {
				// Someone specified a pool but it doesn't exist. Punt.
				header("Location: services_dhcp.php");
				exit;
			}
		}
		if (!is_array($dhcpdconf['range'])) {
			$dhcpdconf['range'] = array();
		}

		$dhcpd_enable_changed = false;

		// Global Options
		if (!is_numeric($pool) && !($act == "newpool")) {
			$old_dhcpd_enable = isset($dhcpdconf['enable']);
			$new_dhcpd_enable = ($_POST['enable']) ? true : false;
			if ($old_dhcpd_enable != $new_dhcpd_enable) {
				/* DHCP has been enabled or disabled. The pf ruleset will need to be rebuilt to allow or disallow DHCP. */
				$dhcpd_enable_changed = true;
			}

			$dhcpdconf['enable'] = $new_dhcpd_enable;
			$dhcpdconf['staticarp'] = ($_POST['staticarp']) ? true : false;
			$previous = $dhcpdconf['failover_peerip'];
			if ($previous != $_POST['failover_peerip']) {
				mwexec("/bin/rm -rf /var/dhcpd/var/db/*");
			}

			$dhcpdconf['failover_peerip'] = $_POST['failover_peerip'];
			// dhcpleaseinlocaltime is global to all interfaces. So update the setting on all interfaces.
			foreach ($config['dhcpd'] as &$dhcpdifitem) {
				$dhcpdifitem['dhcpleaseinlocaltime'] = $_POST['dhcpleaseinlocaltime'];
			}
		} else {
			// Options that exist only in pools
			$dhcpdconf['descr'] = $_POST['descr'];
		}

		// Options that can be global or per-pool.
		$dhcpdconf['range']['from'] = $_POST['range_from'];
		$dhcpdconf['range']['to'] = $_POST['range_to'];
		$dhcpdconf['defaultleasetime'] = $_POST['deftime'];
		$dhcpdconf['maxleasetime'] = $_POST['maxtime'];
		$dhcpdconf['netmask'] = $_POST['netmask'];

		unset($dhcpdconf['winsserver']);
		if ($_POST['wins1']) {
			$dhcpdconf['winsserver'][] = $_POST['wins1'];
		}
		if ($_POST['wins2']) {
			$dhcpdconf['winsserver'][] = $_POST['wins2'];
		}

		unset($dhcpdconf['dnsserver']);
		if ($_POST['dns1']) {
			$dhcpdconf['dnsserver'][] = $_POST['dns1'];
		}
		if ($_POST['dns2']) {
			$dhcpdconf['dnsserver'][] = $_POST['dns2'];
		}
		if ($_POST['dns3']) {
			$dhcpdconf['dnsserver'][] = $_POST['dns3'];
		}
		if ($_POST['dns4']) {
			$dhcpdconf['dnsserver'][] = $_POST['dns4'];
		}

		$dhcpdconf['gateway'] = $_POST['gateway'];
		$dhcpdconf['domain'] = $_POST['domain'];
		$dhcpdconf['domainsearchlist'] = $_POST['domainsearchlist'];
		$dhcpdconf['denyunknown'] = ($_POST['denyunknown']) ? true : false;
		$dhcpdconf['ddnsdomain'] = $_POST['ddnsdomain'];
		$dhcpdconf['ddnsdomainprimary'] = $_POST['ddnsdomainprimary'];
		$dhcpdconf['ddnsdomainkeyname'] = $_POST['ddnsdomainkeyname'];
		$dhcpdconf['ddnsdomainkey'] = $_POST['ddnsdomainkey'];
		$dhcpdconf['ddnsupdate'] = ($_POST['ddnsupdate']) ? true : false;
		$dhcpdconf['mac_allow'] = $_POST['mac_allow'];
		$dhcpdconf['mac_deny'] = $_POST['mac_deny'];

		unset($dhcpdconf['ntpserver']);
		if ($_POST['ntp1']) {
			$dhcpdconf['ntpserver'][] = $_POST['ntp1'];
		}
		if ($_POST['ntp2']) {
			$dhcpdconf['ntpserver'][] = $_POST['ntp2'];
		}

		$dhcpdconf['tftp'] = $_POST['tftp'];
		$dhcpdconf['ldap'] = $_POST['ldap'];
		$dhcpdconf['netboot'] = ($_POST['netboot']) ? true : false;
		$dhcpdconf['nextserver'] = $_POST['nextserver'];
		$dhcpdconf['filename'] = $_POST['filename'];
		$dhcpdconf['filename32'] = $_POST['filename32'];
		$dhcpdconf['filename64'] = $_POST['filename64'];
		$dhcpdconf['rootpath'] = $_POST['rootpath'];
		unset($dhcpdconf['statsgraph']);
		if ($_POST['statsgraph']) {
			$dhcpdconf['statsgraph'] = $_POST['statsgraph'];
			enable_rrd_graphing();
		}

		// Handle the custom options rowhelper
		if (isset($dhcpdconf['numberoptions']['item'])) {
			unset($dhcpdconf['numberoptions']['item']);
		}

		$dhcpdconf['numberoptions'] = $numberoptions;

		if (is_numeric($pool) && is_array($a_pools[$pool])) {
			$a_pools[$pool] = $dhcpdconf;
		} elseif ($act == "newpool") {
			$a_pools[] = $dhcpdconf;
		} else {
			$config['dhcpd'][$if] = $dhcpdconf;
		}

		write_config();
	}
}

if ((isset($_POST['submit']) || isset($_POST['apply'])) && (!$input_errors)) {
	$retval = 0;
	$retvaldhcp = 0;
	$retvaldns = 0;
	/* dnsmasq_configure calls dhcpd_configure */
	/* no need to restart dhcpd twice */
	if (isset($config['dnsmasq']['enable']) && isset($config['dnsmasq']['regdhcpstatic']))	{
		$retvaldns = services_dnsmasq_configure();
		if ($retvaldns == 0) {
			clear_subsystem_dirty('hosts');
			clear_subsystem_dirty('staticmaps');
		}
	} else if (isset($config['unbound']['enable']) && isset($config['unbound']['regdhcpstatic'])) {
		$retvaldns = services_unbound_configure();
		if ($retvaldns == 0) {
			clear_subsystem_dirty('unbound');
			clear_subsystem_dirty('hosts');
			clear_subsystem_dirty('staticmaps');
		}
	} else {
		$retvaldhcp = services_dhcpd_configure();
		if ($retvaldhcp == 0) {
			clear_subsystem_dirty('staticmaps');
		}
	}
	if ($dhcpd_enable_changed) {
		$retvalfc = filter_configure();
	}

	if ($retvaldhcp == 1 || $retvaldns == 1 || $retvalfc == 1) {
		$retval = 1;
	}

	$savemsg = get_std_save_message($retval);
}

if ($act == "delpool") {
	if ($a_pools[$_GET['id']]) {
		unset($a_pools[$_GET['id']]);
		write_config();
		header("Location: services_dhcp.php?if={$if}");
		exit;
	}
}

if ($act == "del") {
	if ($a_maps[$_GET['id']]) {
		unset($a_maps[$_GET['id']]);
		write_config();
		if (isset($config['dhcpd'][$if]['enable'])) {
			mark_subsystem_dirty('staticmaps');
			if (isset($config['dnsmasq']['enable']) && isset($config['dnsmasq']['regdhcpstatic'])) {
				mark_subsystem_dirty('hosts');
			}
		}

		header("Location: services_dhcp.php?if={$if}");
		exit;
	}
}

// Build an HTML table that can be inserted into a Form_StaticText element
function build_pooltable() {
	global $a_pools;

	$pooltbl =	'<div class="table-responsive">';
	$pooltbl .=		'<table class="table table-striped table-hover table-condensed">';
	$pooltbl .=			'<thead>';
	$pooltbl .=				'<tr>';
	$pooltbl .=					'<th>' . gettext("Pool Start") . '</th>';
	$pooltbl .=					'<th>' . gettext("Pool End") . '</th>';
	$pooltbl .=					'<th>' . gettext("Description") . '</th>';
	$pooltbl .=					'<th></th>';
	$pooltbl .=				'</tr>';
	$pooltbl .=			'</thead>';
	$pooltbl .=			'<tbody>';

	if (is_array($a_pools)) {
		$i = 0;
		foreach ($a_pools as $poolent) {
			if (!empty($poolent['range']['from']) && !empty($poolent['range']['to'])) {
				$pooltbl .= '<tr>';
				$pooltbl .= '<td ondblclick="document.location=\'services_dhcp.php?if=' . htmlspecialchars($if) . '&pool=' . $i . '\';">' .
							htmlspecialchars($poolent['range']['from']) . '</td>';

				$pooltbl .= '<td ondblclick="document.location=\'services_dhcp.php?if=' . htmlspecialchars($if) . '&pool=' . $i . '\';">' .
							htmlspecialchars($poolent['range']['to']) . '</td>';

				$pooltbl .= '<td ondblclick="document.location=\'services_dhcp.php?if=' . htmlspecialchars($if) . '&pool=' . $i . '\';">' .
							htmlspecialchars($poolent['descr']) . '</td>';

				$pooltbl .= '<td><a class="btn btn-xs btn-info" href="services_dhcp.php?if=' . htmlspecialchars($if) . '&pool=' . $i . '" />' . gettext('Edit') . '</a>';

				$pooltbl .= '<a class="btn btn-xs btn-danger" href="services_dhcp.php?if=' . htmlspecialchars($if) . '&act=delpool&id=' . $i . '" />' . gettext('Delete') . '</a></td>';
				$pooltbl .= '</tr>';
			}
		$i++;
		}
	}

	$pooltbl .=			'</tbody>';
	$pooltbl .=		'</table>';
	$pooltbl .= '</div>';

	return($pooltbl);
}

$pgtitle = array(gettext("Services"), gettext("DHCP Server"));
$shortcut_section = "dhcp";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (isset($config['dhcrelay']['enable'])) {
	print_info_box(gettext("DHCP Relay is currently enabled. Cannot enable the DHCP Server service while the DHCP Relay is enabled on any interface."));
	include("foot.inc");
	exit;
}

if (is_subsystem_dirty('staticmaps')) {
	print_info_box_np(gettext("The static mapping configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));
}

/* active tabs */
$tab_array = array();
$tabscounter = 0;
$i = 0;

foreach ($iflist as $ifent => $ifname) {
	$oc = $config['interfaces'][$ifent];
	if ((is_array($config['dhcpd'][$ifent]) && !isset($config['dhcpd'][$ifent]['enable']) && (!is_ipaddrv4($oc['ipaddr']))) ||
	    (!is_array($config['dhcpd'][$ifent]) && (!is_ipaddrv4($oc['ipaddr'])))) {
		continue;
	}

	if ($ifent == $if) {
		$active = true;
	} else {
		$active = false;
	}

	$tab_array[] = array($ifname, $active, "services_dhcp.php?if={$ifent}");
	$tabscounter++;
}

if ($tabscounter == 0) {
	print_info_box(gettext("The DHCP Server can only be enabled on interfaces configured with a static IPv4 address. This system has none."));
	include("foot.inc");
	exit;
}

display_top_tabs($tab_array);

// This form uses a non-standard submit button name
$form = new Form(new Form_Button(
	'submit',
	gettext("Save")
));

$section = new Form_Section('General Options');

if (!is_numeric($pool) && !($act == "newpool")) {
	$section->addInput(new Form_Checkbox(
		'enable',
		'Enable',
		sprintf(gettext("Enable DHCP server on %s interface"), htmlspecialchars($iflist[$if])),
		$pconfig['enable']
	));
} else {
	$section->addInput(new Form_StaticText(
		null,
		'<div class="alert alert-info"> Editing Pool-Specific Options. To return to the Interface, click its tab above. </div>'
	));
}

$section->addInput(new Form_Checkbox(
	'denyunknown',
	'Deny unknown clients',
	'Only the clients defined below will get DHCP leases from this server.',
	$pconfig['denyunknown']
));

if (is_numeric($pool) || ($act == "newpool")) {
	$section->addInput(new Form_Input(
		'descr',
		'Pool Description',
		'text',
		$pconfig['descr']
	));
}

$section->addInput(new Form_StaticText(
	'Subnet',
	gen_subnet($ifcfgip, $ifcfgsn)
));

$section->addInput(new Form_StaticText(
	'Subnet mask',
	gen_subnet_mask($ifcfgsn)
));

// Compose a string to display the required address ranges
$range_from = ip2long(gen_subnetv4($ifcfgip, $ifcfgsn));
$range_from++;

$range_to = ip2long(gen_subnetv4_max($ifcfgip, $ifcfgsn));
$range_to--;

$rangestr = long2ip32($range_from) . ' - ' . long2ip32($range_to);

if (is_numeric($pool) || ($act == "newpool")) {
	$rangestr .= '<br />' . 'In-use DHCP Pool Ranges:';
	if (is_array($config['dhcpd'][$if]['range'])) {
		$rangestr .= '<br />' . $config['dhcpd'][$if]['range']['from'] . ' - ' . $config['dhcpd'][$if]['range']['to'];
	}

	foreach ($a_pools as $p) {
		if (is_array($p['range'])) {
			$rangestr .= '<br />' . $p['range']['from'] . ' - ' . $p['range']['to'];
		}
	}
}

$section->addInput(new Form_StaticText(
	'Available range',
	$rangestr
));

if ($is_olsr_enabled) {
	$section->addInput(new Form_Select(
		'netmask',
		'Subnet mask',
		$pconfig['netmask'],
		array_combine(range(32, 1, -1), range(32, 1, -1))
	));
}

$group = new Form_Group('Range');

$group->add(new Form_IpAddress(
	'range_from',
	null,
	$pconfig['range_from']
))->setHelp('From');

$group->add(new Form_IpAddress(
	'range_to',
	null,
	$pconfig['range_to']
))->setHelp('To');

$section->add($group);

$form->add($section);

if (!is_numeric($pool) && !($act == "newpool")) {
	$section = new Form_Section('Additional pools');

	$btnaddpool = new Form_Button(
		'btnaddpool',
		'Add pool',
		'services_dhcp.php?if=' . htmlspecialchars($if) . '&act=newpool'
	);

	$section->addInput(new Form_StaticText(
		'Add',
		$btnaddpool
	))->setHelp('If you need additional pools of addresses inside of this subnet outside the above Range, they may be specified here');

	if (is_array($a_pools)) {
		$section->addInput(new Form_StaticText(
			null,
			build_pooltable()
		));
	}

	$form->add($section);
}

$section = new Form_Section('Servers');

$section->addInput(new Form_IpAddress(
	'wins1',
	'WINS servers',
	$pconfig['wins1']
))->setPattern('[.a-zA-Z0-9_]+')->setAttribute('placeholder', 'WINS Server 1');

$section->addInput(new Form_IpAddress(
	'wins2',
	null,
	$pconfig['wins2']
))->setPattern('[.a-zA-Z0-9_]+')->setAttribute('placeholder', 'WINS Server 2');

for ($idx=1; $idx<=4; $idx++) {
	$section->addInput(new Form_IpAddress(
		'dns' . $idx,
		($idx == 1) ? 'DNS servers':null,
		$pconfig['dns' . $idx]
	))->setPattern('[.a-zA-Z0-9_]+')->setAttribute('placeholder', 'DNS Server ' . $idx)->setHelp(($idx == 4) ? 'Leave blank to use the system default DNS servers, use this interface\'s IP if DNS Forwarder or Resolver is enabled, otherwise use the servers configured on the General page':'');
}

$form->add($section);

$section = new Form_Section('Other options');

$section->addInput(new Form_IpAddress(
	'gateway',
	'Gateway',
	$pconfig['gateway']
))->setPattern('[.a-zA-Z0-9_]+')
  ->setHelp('The default is to use the IP on this interface of the firewall as the gateway. Specify an alternate gateway here if this is not the correct gateway for your network. Type "none" for no gateway assignment');

$section->addInput(new Form_Input(
	'domain',
	'Domain name',
	'text',
	$pconfig['domain']
))->setHelp('The default is to use the domain name of this system as the default domain name provided by DHCP. You may specify an alternate domain name here');

$section->addInput(new Form_Input(
	'domainsearchlist',
	'Domain search list',
	'text',
	$pconfig['domainsearchlist']
))->setHelp('The DHCP server can optionally provide a domain search list. Use the semicolon character as separator');

$section->addInput(new Form_Input(
	'deftime',
	'Default lease time',
	'number',
	$pconfig['deftime']
))->setHelp('This is used for clients that do not ask for a specific expiration time. The default is 7200 seconds');

$section->addInput(new Form_Input(
	'maxtime',
	'Maximum lease time',
	'number',
	$pconfig['maxtime']
))->setHelp('This is the maximum lease time for clients that ask for a specific expiration time. The default is 86400 seconds');

if (!is_numeric($pool) && !($act == "newpool")) {
	$section->addInput(new Form_IpAddress(
		'failover_peerip',
		'Failover peer IP',
		$pconfig['failover_peerip']
	))->setHelp('Leave blank to disable. Enter the interface IP address of the other machine. Machines must be using CARP.' .
				'Interface\'s advskew determines whether the DHCPd process is Primary or Secondary. Ensure one machine\'s advskew < 20 (and the other is > 20).');
}

if (!is_numeric($pool) && !($act == "newpool")) {
	$section->addInput(new Form_Checkbox(
		'staticarp',
		'Static ARP',
		'Enable Static ARP entries',
		$pconfig['staticarp']
	))->setHelp('This option persists even if DHCP server is disabled. Only the machines listed below will be able to communicate with the firewall on this interface.');

	$section->addInput(new Form_Checkbox(
		'dhcpleaseinlocaltime',
		'Time format change',
		'Change DHCP display lease time from UTC to local time',
		$pconfig['dhcpleaseinlocaltime']
	))->setHelp('By default DHCP leases are displayed in UTC time.	By checking this box DHCP lease time will be displayed in local time and set to the time zone selected.' .
				' This will be used for all DHCP interfaces lease time');
	$section->addInput(new Form_Checkbox(
		'statsgraph',
		'Statistics graphs',
		'Enable RRD statistics graphs',
		$pconfig['statsgraph']
	))->setHelp('Enable this to add DHCP leases statistics to the RRD graphs. Disabled by default.');
}

// DDNS
$btnadv = new Form_Button(
	'btnadvdns',
	'Advanced'
);

$btnadv->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'Dynamic DNS',
	$btnadv
));

$section->addInput(new Form_Checkbox(
	'ddnsupdate',
	null,
	'Enable registration of DHCP client names in DNS',
	$pconfig['ddnsupdate']
));

$section->addInput(new Form_Input(
	'ddnsdomain',
	'DDNS Domain',
	'text',
	$pconfig['ddnsdomain']
))->setHelp('Leave blank to disable dynamic DNS registration.' . '<br />' .
			'Enter the dynamic DNS domain which will be used to register client names in the DNS server.');

$section->addInput(new Form_IpAddress(
	'ddnsdomainprimary',
	'Primary DDNS address',
	$pconfig['ddnsdomainprimary']
))->setHelp('Primary domain name server IP address for the dynamic domain name');

$section->addInput(new Form_Input(
	'ddnsdomainkeyname',
	'DNS Domain key',
	'text',
	$pconfig['ddnsdomainkeyname']
))->setHelp('Dynamic DNS domain key name which will be used to register client names in the DNS server');

$section->addInput(new Form_Input(
	'ddnsdomainkey',
	'DNS Domain key secret',
	'text',
	$pconfig['ddnsdomainkey']
))->setHelp('Dynamic DNS domain key secret which will be used to register client names in the DNS server');

// Advanced MAC
$btnadv = new Form_Button(
	'btnadvmac',
	'Advanced'
);

$btnadv->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'MAC address control',
	$btnadv
));

$section->addInput(new Form_Input(
	'mac_allow',
	'Allow',
	'text',
	$pconfig['mac_allow']
))->setHelp('List of partial MAC addresses to allow, comma separated, no spaces, e.g.: 00:00:00,01:E5:FF');

$section->addInput(new Form_Input(
	'mac_deny',
	'Deny',
	'text',
	$pconfig['mac_deny']
))->setHelp('List of partial MAC addresses to deny access, comma separated, no spaces, e.g.: 00:00:00,01:E5:FF');

// Advanced NTP
$btnadv = new Form_Button(
	'btnadvntp',
	'Advanced'
);

$btnadv->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'NTP servers',
	$btnadv
));

$section->addInput(new Form_IpAddress(
	'ntp1',
	null,
	$pconfig['ntp1']
))->setAttribute('placeholder', 'NTP Server 1');

$section->addInput(new Form_IpAddress(
	'ntp2',
	null,
	$pconfig['ntp2']
))->setAttribute('placeholder', 'NTP Server 2');

// Advanced TFTP
$btnadv = new Form_Button(
	'btnadvtftp',
	'Advanced'
);

$btnadv->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'TFTP server',
	$btnadv
));

$section->addInput(new Form_IpAddress(
	'tftp',
	null,
	$pconfig['tftp']
))->setHelp('Leave blank to disable.  Enter a full hostname or IP for the TFTP server')->setPattern('[.a-zA-Z0-9_]+');

// Advanced LDAP
$btnadv = new Form_Button(
	'btnadvldap',
	'Advanced'
);

$btnadv->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'LDAP URI',
	$btnadv
));

$section->addInput(new Form_Input(
	'ldap',
	null,
	'text',
	$pconfig['ldap']
))->setHelp('Leave blank to disable. Enter a full URI for the LDAP server in the form ldap://ldap.example.com/dc=example,dc=com ');

// Advanced NETBOOT
$btnadv = new Form_Button(
	'btnadvboot',
	'Advanced'
);

$btnadv->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'Network booting',
	$btnadv
));

$section->addInput(new Form_Checkbox(
	'netboot',
	null,
	'Enables network booting',
	$pconfig['netboot']
));

$section->addInput(new Form_IpAddress(
	'nextserver',
	'Next Server',
	$pconfig['nextserver']
))->setHelp('Enter the IP address of the next server');

$section->addInput(new Form_Input(
	'filename',
	'Default BIOS file name',
	'text',
	$pconfig['filename']
));

$section->addInput(new Form_Input(
	'filename32',
	'UEFI 32 bit file name',
	'text',
	$pconfig['filename32']
));

$section->addInput(new Form_Input(
	'filename64',
	'UEFI 64 bit file name',
	'text',
	$pconfig['filename64']
))->setHelp('You need both a filename and a boot server configured for this to work! ' .
			'You will need all three filenames and a boot server configured for UEFI to work! ');

$section->addInput(new Form_Input(
	'rootpath',
	'Root path',
	'text',
	$pconfig['rootpath']
))->setHelp('string-format: iscsi:(servername):(protocol):(port):(LUN):targetname ');

// Advanced Additional options
$btnadv = new Form_Button(
	'btnadvopts',
	'Advanced'
);

$btnadv->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'Additional BOOTP/DHCP Options',
	$btnadv
));

$form->add($section);

$section = new Form_Section('Additional BOOTP/DHCP Options');
$section->addClass('adnlopts');

$section->addInput(new Form_StaticText(
	null,
	'<div class="alert alert-info"> ' . gettext('Enter the DHCP option number and the value for each item you would like to include in the DHCP lease information. ' .
	'For a list of available options please visit this ') . '<a href="http://www.iana.org/assignments/bootp-dhcp-parameters/" target="_blank">' . gettext("URL") . '</a></div>'
));

if (!$pconfig['numberoptions']) {
	$pconfig['numberoptions']['item']  = array(array('number' => '', 'type' => 'text', 'value' => ''));
}

$customitemtypes = array(
	'text' => gettext('Text'), 'string' => gettext('String'), 'boolean' => gettext('Boolean'),
	'unsigned integer 8' => gettext('Unsigned 8-bit integer'), 'unsigned integer 16' => gettext('Unsigned 16-bit integer'), 'unsigned integer 32' => gettext('Unsigned 32-bit integer'),
	'signed integer 8' => gettext('Signed 8-bit integer'), 'signed integer 16' => gettext('Signed 16-bit integer'), 'signed integer 32' => gettext('Signed 32-bit integer'), 'ip-address' => gettext('IP address or host')
);

$numrows = count($item) -1;
$counter = 0;

$numrows = count($pconfig['numberoptions']['item']) -1;

foreach ($pconfig['numberoptions']['item'] as $item) {
	$number = $item['number'];
	$itemtype = $item['type'];
	$value = $item['value'];

	$group = new Form_Group(($counter == 0) ? 'Option':null);
	$group->addClass('repeatable');

	$group->add(new Form_Input(
		'number' . $counter,
		null,
		'text',
		$number
	))->setHelp($numrows == $counter ? 'Number':null);


	$group->add(new Form_Select(
		'itemtype' . $counter,
		null,
		$itemtype,
		$customitemtypes
	))->setWidth(3)->setHelp($numrows == $counter ? 'Type':null);

	$group->add(new Form_Input(
		'value' . $counter,
		null,
		'text',
		$value
	))->setHelp($numrows == $counter ? 'Value':null);

	$group->add(new Form_Button(
		'deleterow' . $counter,
		'Delete'
	))->removeClass('btn-primary')->addClass('btn-warning');

	$section->add($group);

	$counter++;
}

$section->addInput(new Form_Button(
	'addrow',
	'Add'
))->removeClass('btn-primary')->addClass('btn-success');

$form->add($section);

if ($act == "newpool") {
	$form->addGlobal(new Form_Input(
		'act',
		null,
		'hidden',
		'newpool'
	));
}

if (is_numeric($pool)) {
	$form->addGlobal(new Form_Input(
		'pool',
		null,
		'hidden',
		$pool
	));
}

$form->addGlobal(new Form_Input(
	'if',
	null,
	'hidden',
	$if
));

print($form);

// DHCP Static Mappings table

if (!is_numeric($pool) && !($act == "newpool")) {
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("DHCP Static Mappings for this interface")?></h2></div>
	<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><?=gettext("Static ARP")?></th>
						<th><?=gettext("MAC address")?></th>
						<th><?=gettext("IP address")?></th>
						<th><?=gettext("Hostname")?></th>
						<th><?=gettext("Description")?></th>
						<th></th>
					</tr>
				</thead>
<?php
	if (is_array($a_maps)) {
		$i = 0;
?>
				<tbody>
<?php
		foreach ($a_maps as $mapent) {
?>
					<tr>
						<td align="center" ondblclick="document.location='services_dhcp_edit.php?if=<?=htmlspecialchars($if)?>&amp;id=<?=$i?>';">
							<?php if (isset($mapent['arp_table_static_entry'])): ?>
								<i class="fa fa-check"></i>
							<?php endif; ?>
						</td>
						<td ondblclick="document.location='services_dhcp_edit.php?if=<?=htmlspecialchars($if)?>&amp;id=<?=$i?>';">
							<?=htmlspecialchars($mapent['mac'])?>
						</td>
						<td ondblclick="document.location='services_dhcp_edit.php?if=<?=htmlspecialchars($if)?>&amp;id=<?=$i?>';">
							<?=htmlspecialchars($mapent['ipaddr'])?>
						</td>
						<td ondblclick="document.location='services_dhcp_edit.php?if=<?=htmlspecialchars($if)?>&amp;id=<?=$i?>';">
							<?=htmlspecialchars($mapent['hostname'])?>
						</td>
						<td ondblclick="document.location='services_dhcp_edit.php?if=<?=htmlspecialchars($if)?>&amp;id=<?=$i?>';">
							<?=htmlspecialchars($mapent['descr'])?>
						</td>
						<td>
							<a class="fa fa-pencil"	title="<?=gettext('Edit static mapping')?>"	href="services_dhcp_edit.php?if=<?=htmlspecialchars($if)?>&amp;id=<?=$i?>"></a>
							<a class="fa fa-trash"	title="<?=gettext('Delete static mapping')?>"	href="services_dhcp.php?if=<?=htmlspecialchars($if)?>&amp;act=del&amp;id=<?=$i?>"></a>
						</td>
					</tr>
<?php
		$i++;
		}
?>
				</tbody>
<?php
	}
?>
		</table>
	</div>
</div>

<nav class="action-buttons">
	<a href="services_dhcp_edit.php?if=<?=htmlspecialchars($if)?>" class="btn btn-sm btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>
<?php
}
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Show advanced DNS options ======================================================================================
	var showadvdns = false;

	function show_advdns() {
<?php
		if (!$pconfig['ddnsupdate'] && empty($pconfig['ddnsdomain']) && empty($pconfig['ddnsdomainprimary']) &&
		    empty($pconfig['ddnsdomainkeyname']) && empty($pconfig['ddnsdomainkey'])) {
			$hide = false;
		} else {
			$hide = true;
		}
?>
		var hide = <?php if ($hide) {echo 'true';} else {echo 'false';} ?>;

		hideCheckbox('ddnsupdate', !showadvdns && !hide);
		hideInput('ddnsdomain', !showadvdns && !hide);
		hideInput('ddnsdomainprimary', !showadvdns && !hide);
		hideInput('ddnsdomainkeyname', !showadvdns && !hide);
		hideInput('ddnsdomainkey', !showadvdns && !hide);
		hideInput('btnadvdns', hide);
		showadvdns = !showadvdns;
	}

	$('#btnadvdns').prop('type', 'button');

	$('#btnadvdns').click(function(event) {
		show_advdns();
	});

 // Show advanced MAC options ======================================================================================
	var showadvmac = false;

	function show_advmac() {
<?php
		if (empty($pconfig['mac_allow']) && empty($pconfig['mac_deny'])) {
			$hide = false;
		} else {
			$hide = true;
		}
?>
		var hide = <?php if ($hide) {echo 'true';} else {echo 'false';} ?>;

		hideInput('mac_allow', !showadvmac && !hide);
		hideInput('mac_deny', !showadvmac && !hide);
		hideInput('btnadvmac', hide);

		showadvmac = !showadvmac;
	}

	$('#btnadvmac').prop('type', 'button');

	$('#btnadvmac').click(function(event) {
		show_advmac();
	});

  // Show advanced NTP options ======================================================================================
	var showadvntp = false;

	function show_advntp() {
<?php
		if (empty($pconfig['ntp1']) && empty($pconfig['ntp2'])) {
			$hide = false;
		} else {
			$hide = true;
		}
?>
		var hide = <?php if ($hide) {echo 'true';} else {echo 'false';} ?>;

		hideInput('ntp1', !showadvntp && !hide);
		hideInput('ntp2', !showadvntp && !hide);
		hideInput('btnadvntp', hide);

		showadvntp = !showadvntp;
	}

	$('#btnadvntp').prop('type', 'button');

	$('#btnadvntp').click(function(event) {
		show_advntp();
	});

   // Show advanced TFTP options ======================================================================================
	var showadvtftp = false;

	function show_advtftp() {
<?php
		if (empty($pconfig['tftp'])) {
			$hide = false;
		} else {
			$hide = true;
		}
?>
		var hide = <?php if ($hide) {echo 'true';} else {echo 'false';} ?>;

		hideInput('tftp', !showadvtftp && !hide);
		hideInput('btnadvtftp', hide);

		showadvtftp = !showadvtftp;
	}

	$('#btnadvtftp').prop('type', 'button');

	$('#btnadvtftp').click(function(event) {
		show_advtftp();
	});

   // Show advanced LDAP options ======================================================================================
	var showadvldap = false;

	function show_advldap() {
<?php
		if (empty($pconfig['ldap'])) {
			$hide = false;
		} else {
			$hide = true;
		}
?>
		var hide = <?php if ($hide) {echo 'true';} else {echo 'false';} ?>;

		hideInput('ldap', !showadvldap && !hide);
		hideInput('btnadvldap', hide);

		showadvldap = !showadvldap;
	}

	$('#btnadvldap').prop('type', 'button');

	$('#btnadvldap').click(function(event) {
		show_advldap();
	});

   // Show advanced NETBOOT options ===================================================================================
	var showadvboot = false;

	function show_advboot() {
<?php
		if (!$pconfig['netboot'] && empty($pconfig['nextserver']) && empty($pconfig['filename']) && empty($pconfig['filename32']) &&
		    empty($pconfig['filename64']) && empty($pconfig['rootpath'])) {
			$hide = false;
		} else {
			$hide = true;
		}
?>
		var hide = <?php if ($hide) {echo 'true';} else {echo 'false';} ?>;

		hideCheckbox('netboot', !showadvboot && !hide);
		hideInput('nextserver', !showadvboot && !hide);
		hideInput('filename', !showadvboot && !hide);
		hideInput('filename32', !showadvboot && !hide);
		hideInput('filename64', !showadvboot && !hide);
		hideInput('rootpath', !showadvboot && !hide);
		hideInput('btnadvboot', hide);

		showadvboot = !showadvboot;
	}

	$('#btnadvboot').prop('type', 'button');

	$('#btnadvboot').click(function(event) {
		show_advboot();
	});

	// Show advanced additional opts options ===========================================================================
	var showadvopts = false;

	function show_advopts() {
<?php
		if (empty($pconfig['numberoptions']) ||
		    (empty($pconfig['numberoptions']['item'][0]['number']) && (empty($pconfig['numberoptions']['item'][0]['value'])))) {
			$hide = false;
		} else {
			$hide = true;
		}
?>
		var hide = <?php if ($hide) {echo 'true';} else {echo 'false';} ?>;

		hideClass('adnlopts', !showadvopts && !hide);
		hideInput('btnadvopts', hide);

		showadvopts = !showadvopts;
	}

	$('#btnadvopts').prop('type', 'button');

	$('#btnadvopts').click(function(event) {
		show_advopts();
	});

	// ---------- On initial page load ------------------------------------------------------------

	show_advdns();
	show_advmac();
	show_advntp();
	show_advtftp();
	show_advldap();
	show_advboot();
	show_advopts();

	// Suppress "Delete row" button if there are fewer than two rows
	checkLastRow();
});
//]]>
</script>

<?php include("foot.inc");
