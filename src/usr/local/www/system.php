<?php
/*
 * system.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-system-generalsetup
##|*NAME=System: General Setup
##|*DESCR=Allow access to the 'System: General Setup' page.
##|*MATCH=system.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("system.inc");

$pconfig['hostname'] = $config['system']['hostname'];
$pconfig['domain'] = $config['system']['domain'];
list($pconfig['dns1'], $pconfig['dns2'], $pconfig['dns3'], $pconfig['dns4']) = $config['system']['dnsserver'];

$arr_gateways = return_gateways_array();

// set default columns to two if unset
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
$pconfig['dashboardavailablewidgetspanel'] = isset($config['system']['webgui']['dashboardavailablewidgetspanel']);
$pconfig['systemlogsfilterpanel'] = isset($config['system']['webgui']['systemlogsfilterpanel']);
$pconfig['systemlogsmanagelogpanel'] = isset($config['system']['webgui']['systemlogsmanagelogpanel']);
$pconfig['statusmonitoringsettingspanel'] = isset($config['system']['webgui']['statusmonitoringsettingspanel']);
$pconfig['webguihostnamemenu'] = $config['system']['webgui']['webguihostnamemenu'];
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

	if ($_POST['webguihostnamemenu']) {
		$config['system']['webgui']['webguihostnamemenu'] = $_POST['webguihostnamemenu'];
	} else {
		unset($config['system']['webgui']['webguihostnamemenu']);
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

	$dnslist = $ignore_posted_dnsgw = array();

	for ($dnscounter=1; $dnscounter<5; $dnscounter++) {
		$dnsname="dns{$dnscounter}";
		$dnsgwname="dns{$dnscounter}gw";
		$dnslist[] = $_POST[$dnsname];

		if (($_POST[$dnsname] && !is_ipaddr($_POST[$dnsname]))) {
			$input_errors[] = sprintf(gettext("A valid IP address must be specified for DNS server %s."), $dnscounter);
		} else {
			if (($_POST[$dnsgwname] <> "") && ($_POST[$dnsgwname] <> "none")) {
				// A real gateway has been selected.
				if (is_ipaddr($_POST[$dnsname])) {
					if ((is_ipaddrv4($_POST[$dnsname])) && (validate_address_family($_POST[$dnsname], $_POST[$dnsgwname]) === false)) {
						$input_errors[] = sprintf(gettext('The IPv6 gateway "%1$s" can not be specified for IPv4 DNS server "%2$s".'), $_POST[$dnsgwname], $_POST[$dnsname]);
					}
					if ((is_ipaddrv6($_POST[$dnsname])) && (validate_address_family($_POST[$dnsname], $_POST[$dnsgwname]) === false)) {
						$input_errors[] = sprintf(gettext('The IPv4 gateway "%1$s" can not be specified for IPv6 DNS server "%2$s".'), $_POST[$dnsgwname], $_POST[$dnsname]);
					}
				} else {
					// The user selected a gateway but did not provide a DNS address. Be nice and set the gateway back to "none".
					$ignore_posted_dnsgw[$dnsgwname] = true;
				}
			}
		}
	}

	if (count(array_filter($dnslist)) != count(array_unique(array_filter($dnslist)))) {
		$input_errors[] = gettext('Each configured DNS server must have a unique IP address. Remove the duplicated IP.');
	}

	$direct_networks_list = explode(" ", filter_get_direct_networks_list());
	for ($dnscounter=1; $dnscounter<5; $dnscounter++) {
		$dnsitem = "dns{$dnscounter}";
		$dnsgwitem = "dns{$dnscounter}gw";
		if ($_POST[$dnsgwitem]) {
			if (interface_has_gateway($_POST[$dnsgwitem])) {
				foreach ($direct_networks_list as $direct_network) {
					if (ip_in_subnet($_POST[$dnsitem], $direct_network)) {
						$input_errors[] = sprintf(gettext("A gateway can not be assigned to DNS '%s' server which is on a directly connected network."), $_POST[$dnsitem]);
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
			set_language();
		}

		unset($config['system']['webgui']['webguileftcolumnhyper']);
		$config['system']['webgui']['webguileftcolumnhyper'] = $_POST['webguileftcolumnhyper'] ? true : false;

		unset($config['system']['webgui']['dashboardavailablewidgetspanel']);
		$config['system']['webgui']['dashboardavailablewidgetspanel'] = $_POST['dashboardavailablewidgetspanel'] ? true : false;

		unset($config['system']['webgui']['systemlogsfilterpanel']);
		$config['system']['webgui']['systemlogsfilterpanel'] = $_POST['systemlogsfilterpanel'] ? true : false;

		unset($config['system']['webgui']['systemlogsmanagelogpanel']);
		$config['system']['webgui']['systemlogsmanagelogpanel'] = $_POST['systemlogsmanagelogpanel'] ? true : false;

		unset($config['system']['webgui']['statusmonitoringsettingspanel']);
		$config['system']['webgui']['statusmonitoringsettingspanel'] = $_POST['statusmonitoringsettingspanel'] ? true : false;

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

$section = new Form_Section('DNS Server Settings');

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
			'Gateway',
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
	'DNS Server Override',
	'Allow DNS server list to be overridden by DHCP/PPP on WAN',
	$pconfig['dnsallowoverride']
))->setHelp(sprintf(gettext('If this option is set, %s will use DNS servers '.
	'assigned by a DHCP/PPP server on WAN for its own purposes (including '.
	'the DNS forwarder). However, they will not be assigned to DHCP and PPTP '.
	'VPN clients.'), $g['product_name']));

$section->addInput(new Form_Checkbox(
	'dnslocalhost',
	'Disable DNS Forwarder',
	'Do not use the DNS Forwarder as a DNS server for the firewall',
	$pconfig['dnslocalhost']
))->setHelp('By default localhost (127.0.0.1) will be used as the first DNS '.
	'server where the DNS Forwarder or DNS Resolver is enabled and set to '.
	'listen on Localhost, so system can use the local DNS service to perform '.
	'lookups. Checking this box omits localhost from the list of DNS servers.');

$form->add($section);

$section = new Form_Section('Localization');
$section->addInput(new Form_Select(
	'timezone',
	'Timezone',
	$pconfig['timezone'],
	array_combine($timezonelist, $timezonelist)
))->setHelp('Select the timezone or location within the timezone to be used by this system.');
$section->addInput(new Form_Input(
	'timeservers',
	'Timeservers',
	'text',
	$pconfig['timeservers']
))->setHelp('Use a space to separate multiple hosts (only one required). '.
	'Remember to set up at least one DNS server if a host name is entered here!');
$section->addInput(new Form_Select(
	'language',
	'Language',
	$pconfig['language'],
	get_locale_list()
))->setHelp('Choose a language for the webConfigurator');

$form->add($section);

$section = new Form_Section('webConfigurator');

gen_webguicss_field($section, $pconfig['webguicss']);
gen_webguifixedmenu_field($section, $pconfig['webguifixedmenu']);
gen_webguihostnamemenu_field($section, $pconfig['webguihostnamemenu']);
gen_dashboardcolumns_field($section, $pconfig['dashboardcolumns']);
gen_associatedpanels_fields(
	$section,
	$pconfig['dashboardavailablewidgetspanel'],
	$pconfig['systemlogsfilterpanel'],
	$pconfig['systemlogsmanagelogpanel'],
	$pconfig['statusmonitoringsettingspanel']);
gen_webguileftcolumnhyper_field($section, $pconfig['webguileftcolumnhyper']);

$form->add($section);

print $form;

$csswarning = sprintf(gettext("%sUser-created themes are unsupported, use at your own risk."), "<br />");

?>
</div>

<script>
//<![CDATA[
events.push(function() {

	function setThemeWarning() {
		if ($('#webguicss').val().startsWith("pfSense")) {
			$('#csstxt').html("").addClass("text-default");
		} else {
			$('#csstxt').html("<?=$csswarning?>").addClass("text-danger");
		}
	}

	$('#webguicss').change(function() {
		setThemeWarning();
	});

	setThemeWarning();
});
//]]>
</script>

<?php
include("foot.inc");
?>
