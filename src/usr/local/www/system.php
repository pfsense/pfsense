<?php
/*
	system.php
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
##|*IDENT=page-system-generalsetup
##|*NAME=System: General Setup
##|*DESCR=Allow access to the 'System: General Setup' page.
##|*MATCH=system.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("system.inc");

$pconfig['hostname'] = $config['system']['hostname'];
$pconfig['domain'] = $config['system']['domain'];
list($pconfig['dns1'], $pconfig['dns2'], $pconfig['dns3'], $pconfig['dns4']) = $config['system']['dnsserver'];

$arr_gateways = return_gateways_array();

// set default colmns to two if unset
if (!isset($config['system']['webgui']['dashboardcolumns'])) {
	$config['system']['webgui']['dashboardcolumns'] = 2;
}

$pconfig['dns1gw'] = $config['system']['dns1gw'];
$pconfig['dns2gw'] = $config['system']['dns2gw'];
$pconfig['dns3gw'] = $config['system']['dns3gw'];
$pconfig['dns4gw'] = $config['system']['dns4gw'];

$pconfig['dnsallowoverride'] = isset($config['system']['dnsallowoverride']);
$pconfig['timezone'] = $config['system']['timezone'];
$pconfig['timeservers'] = $config['system']['timeservers'];
$pconfig['language'] = $config['system']['language'];
$pconfig['webguicss'] = $config['system']['webgui']['webguicss'];
$pconfig['webguifixedmenu'] = $config['system']['webgui']['webguifixedmenu'];
$pconfig['dashboardcolumns'] = $config['system']['webgui']['dashboardcolumns'];
$pconfig['webguileftcolumnhyper'] = isset($config['system']['webgui']['webguileftcolumnhyper']);
$pconfig['dnslocalhost'] = isset($config['system']['dnslocalhost']);

if (!$pconfig['timezone']) {
	if (isset($g['default_timezone']) && !empty($g['default_timezone'])) {
		$pconfig['timezone'] = $g['default_timezone'];
	} else {
		$pconfig['timezone'] = "Etc/UTC";
	}
}

if (!$pconfig['timeservers']) {
	$pconfig['timeservers'] = "pool.ntp.org";
}

$changedesc = gettext("System") . ": ";
$changecount = 0;

function is_timezone($elt) {
	return !preg_match("/\/$/", $elt);
}

if ($pconfig['timezone'] <> $_POST['timezone']) {
	filter_pflog_start(true);
}

$timezonelist = system_get_timezone_list();

$multiwan = false;
$interfaces = get_configured_interface_list();
foreach ($interfaces as $interface) {
	if (interface_has_gateway($interface)) {
		$multiwan = true;
	}
}

if ($_POST) {

	$changecount++;

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "hostname domain");
	$reqdfieldsn = array(gettext("Hostname"), gettext("Domain"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['webguicss']) {
		$config['system']['webgui']['webguicss'] = $_POST['webguicss'];
	} else {
		unset($config['system']['webgui']['webguicss']);
	}

	if ($_POST['webguifixedmenu']) {
		$config['system']['webgui']['webguifixedmenu'] = $_POST['webguifixedmenu'];
	} else {
		unset($config['system']['webgui']['webguifixedmenu']);
	}

	if ($_POST['dashboardcolumns']) {
		$config['system']['webgui']['dashboardcolumns'] = $_POST['dashboardcolumns'];
	} else {
		unset($config['system']['webgui']['dashboardcolumns']);
	}

	if ($_POST['hostname']) {
		if (!is_hostname($_POST['hostname'])) {
			$input_errors[] = gettext("The hostname can only contain the characters A-Z, 0-9 and '-'. It may not start or end with '-'.");
		} else {
			if (!is_unqualified_hostname($_POST['hostname'])) {
				$input_errors[] = gettext("A valid hostname is specified, but the domain name part should be omitted");
			}
		}
	}
	if ($_POST['domain'] && !is_domain($_POST['domain'])) {
		$input_errors[] = gettext("The domain may only contain the characters a-z, 0-9, '-' and '.'.");
	}

	$ignore_posted_dnsgw = array();

	for ($dnscounter=1; $dnscounter<5; $dnscounter++) {
		$dnsname="dns{$dnscounter}";
		$dnsgwname="dns{$dnscounter}gw";
		if (($_POST[$dnsname] && !is_ipaddr($_POST[$dnsname]))) {
			$input_errors[] = gettext("A valid IP address must be specified for DNS server $dnscounter.");
		} else {
			if (($_POST[$dnsgwname] <> "") && ($_POST[$dnsgwname] <> "none")) {
				// A real gateway has been selected.
				if (is_ipaddr($_POST[$dnsname])) {
					if ((is_ipaddrv4($_POST[$dnsname])) && (validate_address_family($_POST[$dnsname], $_POST[$dnsgwname]) === false)) {
						$input_errors[] = gettext("You can not specify IPv6 gateway '{$_POST[$dnsgwname]}' for IPv4 DNS server '{$_POST[$dnsname]}'");
					}
					if ((is_ipaddrv6($_POST[$dnsname])) && (validate_address_family($_POST[$dnsname], $_POST[$dnsgwname]) === false)) {
						$input_errors[] = gettext("You can not specify IPv4 gateway '{$_POST[$dnsgwname]}' for IPv6 DNS server '{$_POST[$dnsname]}'");
					}
				} else {
					// The user selected a gateway but did not provide a DNS address. Be nice and set the gateway back to "none".
					$ignore_posted_dnsgw[$dnsgwname] = true;
				}
			}
		}
	}

	$direct_networks_list = explode(" ", filter_get_direct_networks_list());
	for ($dnscounter=1; $dnscounter<5; $dnscounter++) {
		$dnsitem = "dns{$dnscounter}";
		$dnsgwitem = "dns{$dnscounter}gw";
		if ($_POST[$dnsgwitem]) {
			if (interface_has_gateway($_POST[$dnsgwitem])) {
				foreach ($direct_networks_list as $direct_network) {
					if (ip_in_subnet($_POST[$dnsitem], $direct_network)) {
						$input_errors[] = sprintf(gettext("You can not assign a gateway to DNS '%s' server which is on a directly connected network."), $_POST[$dnsitem]);
					}
				}
			}
		}
	}

	# it's easy to have a little too much whitespace in the field, clean it up for the user before processing.
	$_POST['timeservers'] = preg_replace('/[[:blank:]]+/', ' ', $_POST['timeservers']);
	$_POST['timeservers'] = trim($_POST['timeservers']);
	foreach (explode(' ', $_POST['timeservers']) as $ts) {
		if (!is_domain($ts)) {
			$input_errors[] = gettext("A NTP Time Server name may only contain the characters a-z, 0-9, '-' and '.'.");
		}
	}

	if (!$input_errors) {
		update_if_changed("hostname", $config['system']['hostname'], $_POST['hostname']);
		update_if_changed("domain", $config['system']['domain'], $_POST['domain']);
		update_if_changed("timezone", $config['system']['timezone'], $_POST['timezone']);
		update_if_changed("NTP servers", $config['system']['timeservers'], strtolower($_POST['timeservers']));

		if ($_POST['language'] && $_POST['language'] != $config['system']['language']) {
			$config['system']['language'] = $_POST['language'];
			set_language($config['system']['language']);
		}

		unset($config['system']['webgui']['webguileftcolumnhyper']);
		$config['system']['webgui']['webguileftcolumnhyper'] = $_POST['webguileftcolumnhyper'] ? true : false;

		/* XXX - billm: these still need updating after figuring out how to check if they actually changed */
		$olddnsservers = $config['system']['dnsserver'];
		unset($config['system']['dnsserver']);
		if ($_POST['dns1']) {
			$config['system']['dnsserver'][] = $_POST['dns1'];
		}
		if ($_POST['dns2']) {
			$config['system']['dnsserver'][] = $_POST['dns2'];
		}
		if ($_POST['dns3']) {
			$config['system']['dnsserver'][] = $_POST['dns3'];
		}
		if ($_POST['dns4']) {
			$config['system']['dnsserver'][] = $_POST['dns4'];
		}

		$olddnsallowoverride = $config['system']['dnsallowoverride'];

		unset($config['system']['dnsallowoverride']);
		$config['system']['dnsallowoverride'] = $_POST['dnsallowoverride'] ? true : false;

		if ($_POST['dnslocalhost'] == "yes") {
			$config['system']['dnslocalhost'] = true;
		} else {
			unset($config['system']['dnslocalhost']);
		}

		/* which interface should the dns servers resolve through? */
		$outdnscounter = 0;
		for ($dnscounter=1; $dnscounter<5; $dnscounter++) {
			$dnsname="dns{$dnscounter}";
			$dnsgwname="dns{$dnscounter}gw";
			$olddnsgwname = $config['system'][$dnsgwname];

			if ($ignore_posted_dnsgw[$dnsgwname]) {
				$thisdnsgwname = "none";
			} else {
				$thisdnsgwname = $pconfig[$dnsgwname];
			}

			// "Blank" out the settings for this index, then we set them below using the "outdnscounter" index.
			$config['system'][$dnsgwname] = "none";
			$pconfig[$dnsgwname] = "none";
			$pconfig[$dnsname] = "";

			if ($_POST[$dnsname]) {
				// Only the non-blank DNS servers were put into the config above.
				// So we similarly only add the corresponding gateways sequentially to the config (and to pconfig), as we find non-blank DNS servers.
				// This keeps the DNS server IP and corresponding gateway "lined up" when the user blanks out a DNS server IP in the middle of the list.
				$outdnscounter++;
				$outdnsname="dns{$outdnscounter}";
				$outdnsgwname="dns{$outdnscounter}gw";
				$pconfig[$outdnsname] = $_POST[$dnsname];
				if ($_POST[$dnsgwname]) {
					$config['system'][$outdnsgwname] = $thisdnsgwname;
					$pconfig[$outdnsgwname] = $thisdnsgwname;
				} else {
					// Note: when no DNS GW name is chosen, the entry is set to "none", so actually this case never happens.
					unset($config['system'][$outdnsgwname]);
					$pconfig[$outdnsgwname] = "";
				}
			}
			if (($olddnsgwname != "") && ($olddnsgwname != "none") && (($olddnsgwname != $thisdnsgwname) || ($olddnsservers[$dnscounter-1] != $_POST[$dnsname]))) {
				// A previous DNS GW name was specified. It has now gone or changed, or the DNS server address has changed.
				// Remove the route. Later calls will add the correct new route if needed.
				if (is_ipaddrv4($olddnsservers[$dnscounter-1])) {
					mwexec("/sbin/route delete " . escapeshellarg($olddnsservers[$dnscounter-1]));
				} else if (is_ipaddrv6($olddnsservers[$dnscounter-1])) {
					mwexec("/sbin/route delete -inet6 " . escapeshellarg($olddnsservers[$dnscounter-1]));
				}
			}
		}

		if ($changecount > 0) {
			write_config($changedesc);
		}

		$retval = 0;
		$retval = system_hostname_configure();
		$retval |= system_hosts_generate();
		$retval |= system_resolvconf_generate();
		if (isset($config['dnsmasq']['enable'])) {
			$retval |= services_dnsmasq_configure();
		} elseif (isset($config['unbound']['enable'])) {
			$retval |= services_unbound_configure();
		}
		$retval |= system_timezone_configure();
		$retval |= system_ntp_configure();

		if ($olddnsallowoverride != $config['system']['dnsallowoverride']) {
			$retval |= send_event("service reload dns");
		}

		// Reload the filter - plugins might need to be run.
		$retval |= filter_configure();

		$savemsg = get_std_save_message($retval);
	}

	unset($ignore_posted_dnsgw);
}

$pgtitle = array(gettext("System"), gettext("General Setup"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}
?>
<div id="container">
<?php

$form = new Form;
$section = new Form_Section('System');
$section->addInput(new Form_Input(
	'hostname',
	'Hostname',
	'text',
	$pconfig['hostname'],
	['placeholder' => 'pfSense']
))->setHelp('Name of the firewall host, without domain part');
$section->addInput(new Form_Input(
	'domain',
	'Domain',
	'text',
	$pconfig['domain'],
	['placeholder' => 'mycorp.com, home, office, private, etc.']
))->setHelp('Do not use \'local\' as a domain name. It will cause local '.
	'hosts running mDNS (avahi, bonjour, etc.) to be unable to resolve '.
	'local hosts not running mDNS.');
$form->add($section);

$section = new Form_Section('DNS server settings');

for ($i=1; $i<5; $i++) {
//	if (!isset($pconfig['dns'.$i]))
//		continue;

	$group = new Form_Group('DNS Server ' . $i);

	$group->add(new Form_Input(
		'dns' . $i,
		'DNS Server',
		'text',
		$pconfig['dns'. $i]
	))->setHelp(($i == 4) ? 'Address':null);

	$help = "Enter IP addresses to be used by the system for DNS resolution. " .
		"These are also used for the DHCP service, DNS forwarder and for PPTP VPN clients.";

	if ($multiwan)	{
		$options = array('none' => 'none');

		foreach ($arr_gateways as $gwname => $gwitem) {
			if ((is_ipaddrv4(lookup_gateway_ip_by_name($pconfig[$dnsgw])) && (is_ipaddrv6($gwitem['gateway'])))) {
				continue;
			}

			if ((is_ipaddrv6(lookup_gateway_ip_by_name($pconfig[$dnsgw])) && (is_ipaddrv4($gwitem['gateway'])))) {
				continue;
			}

			$options[$gwname] = $gwname.' - '.$gwitem['friendlyiface'].' - '.$gwitem['gateway'];
		}

		$group->add(new Form_Select(
			'dns' . $i . 'gw',
			null,
			$pconfig['dns' . $i . 'gw'],
			$options
		))->setHelp(($i == 4) ? 'Gateway':null);;

		$help .= '<br/>'. "In addition, optionally select the gateway for each DNS server. " .
			"When using multiple WAN connections there should be at least one unique DNS server per gateway.";
	}

	if ($i == 4) {
		$group->setHelp($help);
	}

	$section->add($group);
}

$section->addInput(new Form_Checkbox(
	'dnsallowoverride',
	'DNS server override',
	'Allow DNS server list to be overridden by DHCP/PPP on WAN',
	$pconfig['dnsallowoverride']
))->setHelp(sprintf(gettext('If this option is set, %s will use DNS servers'.
	'assigned by a DHCP/PPP server on WAN for its own purposes (including '.
	'the DNS forwarder). However, they will not be assigned to DHCP and PPTP '.
	'VPN clients.'), $g['product_name']));

$section->addInput(new Form_Checkbox(
	'dnslocalhost',
	'Disable DNS forwarder',
	'Do not use the DNS Forwarder as a DNS server for the firewall',
	$pconfig['dnslocalhost']
))->setHelp('By default localhost (127.0.0.1) will be used as the first DNS'.
	'server where the DNS Forwarder or DNS Resolver is enabled and set to '.
	'listen on Localhost, so system can use the local DNS service to perform'.
	'lookups. Checking this box omits localhost from the list of DNS servers.');

$form->add($section);

$section = new Form_Section('Localization');
$section->addInput(new Form_Select(
	'timezone',
	'Timezone',
	$pconfig['timezone'],
	array_combine($timezonelist, $timezonelist)
))->setHelp('Select the location closest to you');
$section->addInput(new Form_Input(
	'timeservers',
	'Timeservers',
	'text',
	$pconfig['timeservers']
))->setHelp('Use a space to separate multiple hosts (only one required). '.
	'Remember to set up at least one DNS server if you enter a host name here!');
$section->addInput(new Form_Select(
	'language',
	'Language',
	$pconfig['language'],
	get_locale_list()
))->setHelp('Choose a language for the webConfigurator');

$form->add($section);

$csslist = array();
$css = glob("/usr/local/www/bootstrap/css/*.css");
foreach ($css as $file) {
	$file = basename($file);
	if (substr($file, 0, 9) !== 'bootstrap') {
		$csslist[$file] = pathinfo($file, PATHINFO_FILENAME);
	}
}

asort($csslist);

if (!isset($pconfig['webguicss']) || !isset($csslist[$pconfig['webguicss']])) {
	$pconfig['webguicss'] = "pfSense.css";
}

$section = new Form_Section('Web Configurator');

$section->addInput(new Form_Select(
	'webguicss',
	'Theme',
	$pconfig['webguicss'],
	$csslist
))->setHelp('Choose an alternative css file (if installed) to change the appearance of the Web configurator. css files are located in /usr/local/www/bootstrap/css');

$section->addInput(new Form_Select(
	'webguifixedmenu',
	'Top Navigation',
	$pconfig['webguifixedmenu'],
	["" => "Scrolls with page", "fixed" => "Fixed (Remains visible at top of page)"]
))->setHelp("The fixed option is intended for large screens only.");

$section->addInput(new Form_Input(
	'dashboardcolumns',
	'Dashboard Columns',
	'number',
	$pconfig['dashboardcolumns'],
	[min => 1, max => 4]
))->setHelp('<span class="badge" title="This feature is in BETA">BETA</span>');

$section->addInput(new Form_Checkbox(
	'webguileftcolumnhyper',
	'Left Column Labels',
	'Active',
	$pconfig['webguileftcolumnhyper']
))->setHelp('If selected, clicking a label in the left column will select/toggle the first item of the group.<br /><span class="badge" title="This feature is in BETA">BETA</span>');

$form->add($section);

print $form;
?>
</div>
<?php
include("foot.inc");
?>
