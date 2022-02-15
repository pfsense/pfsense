<?php
/*
 * services_unbound.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2014 Warren Baker (warren@pfsense.org)
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
##|*IDENT=page-services-dnsresolver
##|*NAME=Services: DNS Resolver
##|*DESCR=Allow access to the 'Services: DNS Resolver' page.
##|*MATCH=services_unbound.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("unbound.inc");
require_once("pfsense-utils.inc");
require_once("system.inc");

init_config_arr(array('unbound', 'hosts'));
init_config_arr(array('unbound', 'domainoverrides'));
$a_unboundcfg = &$config['unbound'];
$a_hosts = &$a_unboundcfg['hosts'];
$a_domainOverrides = &$a_unboundcfg['domainoverrides'];

if (isset($a_unboundcfg['enable'])) {
	$pconfig['enable'] = true;
}
if (isset($a_unboundcfg['enablessl'])) {
	$pconfig['enablessl'] = true;
}
if (isset($a_unboundcfg['strictout'])) {
	$pconfig['strictout'] = true;
}
if (isset($a_unboundcfg['dnssec'])) {
	$pconfig['dnssec'] = true;
}
if (isset($a_unboundcfg['python'])) {
	$pconfig['python'] = true;
}
if (isset($a_unboundcfg['forwarding'])) {
	$pconfig['forwarding'] = true;
}
if (isset($a_unboundcfg['forward_tls_upstream'])) {
	$pconfig['forward_tls_upstream'] = true;
}
if (isset($a_unboundcfg['regdhcp'])) {
	$pconfig['regdhcp'] = true;
}
if (isset($a_unboundcfg['regdhcpstatic'])) {
	$pconfig['regdhcpstatic'] = true;
}
if (isset($a_unboundcfg['regovpnclients'])) {
	$pconfig['regovpnclients'] = true;
}

$pconfig['python_order'] = $a_unboundcfg['python_order'];
$pconfig['python_script'] = $a_unboundcfg['python_script'];
$pconfig['port'] = $a_unboundcfg['port'];
$pconfig['tlsport'] = $a_unboundcfg['tlsport'];
$pconfig['sslcertref'] = $a_unboundcfg['sslcertref'];
$pconfig['custom_options'] = base64_decode($a_unboundcfg['custom_options']);

if (empty($a_unboundcfg['active_interface'])) {
	$pconfig['active_interface'] = array();
} else {
	$pconfig['active_interface'] = explode(",", $a_unboundcfg['active_interface']);
}

if (empty($a_unboundcfg['outgoing_interface'])) {
	$pconfig['outgoing_interface'] = array();
} else {
	$pconfig['outgoing_interface'] = explode(",", $a_unboundcfg['outgoing_interface']);
}

if (empty($a_unboundcfg['system_domain_local_zone_type'])) {
	$pconfig['system_domain_local_zone_type'] = "transparent";
} else {
	$pconfig['system_domain_local_zone_type'] = $a_unboundcfg['system_domain_local_zone_type'];
}

init_config_arr(array('cert'));
$a_cert = &$config['cert'];
$certs_available = false;

if (is_array($a_cert) && count($a_cert)) {
	$certs_available = true;
} else {
	$a_cert = array();
}

if ($_POST['apply']) {
	$retval = 0;
	$retval |= services_unbound_configure();
	if ($retval == 0) {
		clear_subsystem_dirty('unbound');
	}
	/* Update resolv.conf in case the interface bindings exclude localhost. */
	system_resolvconf_generate();
	/* Start or restart dhcpleases when it's necessary */
	system_dhcpleases_configure();
}

if ($_POST['save']) {
	$pconfig = $_POST;
	unset($input_errors);

	if (isset($pconfig['enable']) && isset($config['dnsmasq']['enable'])) {
		if ($pconfig['port'] == $config['dnsmasq']['port']) {
			$input_errors[] = gettext("The DNS Forwarder is enabled using this port. Choose a non-conflicting port, or disable the DNS Forwarder.");
		}
	}

	if (isset($pconfig['enablessl']) && (!$certs_available || empty($pconfig['sslcertref']))) {
		$input_errors[] = gettext("Acting as an SSL/TLS server requires a valid server certificate");
	}

	// forwarding mode requires having valid DNS servers
	if (isset($pconfig['forwarding'])) {
		$founddns = false;
		foreach (get_dns_nameservers(false, true) as $dns_server) {
			if (!ip_in_subnet($dns_server, "127.0.0.0/8")) {
				$founddns = true;
			}
		}
		if ($founddns == false) {
			$input_errors[] = gettext("At least one DNS server must be specified under System > General Setup to enable Forwarding mode.");
		}
	}

	if (empty($pconfig['active_interface'])) {
		$input_errors[] = gettext("One or more Network Interfaces must be selected for binding.");
	} elseif (($config['system']['dnslocalhost'] != 'remote') && (!in_array("lo0", $pconfig['active_interface']) && !in_array("all", $pconfig['active_interface']))) {
		$input_errors[] = gettext("This system is configured to use the DNS Resolver as its DNS server, so Localhost or All must be selected in Network Interfaces.");
	}

	if (empty($pconfig['outgoing_interface'])) {
		$input_errors[] = gettext("One or more Outgoing Network Interfaces must be selected.");
	}

	if ($pconfig['port'] && !is_port($pconfig['port'])) {
		$input_errors[] = gettext("A valid port number must be specified.");
	}
	if ($pconfig['tlsport'] && !is_port($pconfig['tlsport'])) {
		$input_errors[] = gettext("A valid SSL/TLS port number must be specified.");
	}

	if (is_array($pconfig['active_interface']) && !empty($pconfig['active_interface'])) {
		$display_active_interface = $pconfig['active_interface'];
		$pconfig['active_interface'] = implode(",", $pconfig['active_interface']);
	}

	if ((isset($pconfig['regdhcp']) || isset($pconfig['regdhcpstatic'])) && !is_dhcp_server_enabled()) {
		$input_errors[] = gettext("DHCP Server must be enabled for DHCP Registration to work in DNS Resolver.");
	}

	if (($pconfig['system_domain_local_zone_type'] == "redirect") && isset($pconfig['regdhcp'])) {
		$input_errors[] = gettext('A System Domain Local Zone Type of "redirect" is not compatible with dynamic DHCP Registration.');
	}

	$display_custom_options = $pconfig['custom_options'];
	$pconfig['custom_options'] = base64_encode(str_replace("\r\n", "\n", $pconfig['custom_options']));

	if (is_array($pconfig['outgoing_interface']) && !empty($pconfig['outgoing_interface'])) {
		$display_outgoing_interface = $pconfig['outgoing_interface'];
		$pconfig['outgoing_interface'] = implode(",", $pconfig['outgoing_interface']);
	}

	$test_output = array();
	if (test_unbound_config($pconfig, $test_output)) {
		$input_errors[] = gettext("The generated config file cannot be parsed by unbound. Please correct the following errors:");
		$input_errors = array_merge($input_errors, $test_output);
	}

	if (!$input_errors) {
		$a_unboundcfg['enable'] = isset($pconfig['enable']);
		$a_unboundcfg['enablessl'] = isset($pconfig['enablessl']);
		$a_unboundcfg['port'] = $pconfig['port'];
		$a_unboundcfg['tlsport'] = $pconfig['tlsport'];
		$a_unboundcfg['sslcertref'] = $pconfig['sslcertref'];
		$a_unboundcfg['strictout'] = isset($pconfig['strictout']);
		$a_unboundcfg['dnssec'] = isset($pconfig['dnssec']);

		$a_unboundcfg['python'] = isset($pconfig['python']);
		if (isset($pconfig['python'])) {
			$a_unboundcfg['python_order'] = $pconfig['python_order'];
			$a_unboundcfg['python_script'] = $pconfig['python_script'];
		} else {
			if (isset($a_unboundcfg['python_order'])) {
				unset($a_unboundcfg['python_order']);
			}
			if (isset($a_unboundcfg['python_script'])) {
				unset($a_unboundcfg['python_script']);
			}
		}

		$a_unboundcfg['forwarding'] = isset($pconfig['forwarding']);
		$a_unboundcfg['forward_tls_upstream'] = isset($pconfig['forward_tls_upstream']);
		$a_unboundcfg['regdhcp'] = isset($pconfig['regdhcp']);
		$a_unboundcfg['regdhcpstatic'] = isset($pconfig['regdhcpstatic']);
		$a_unboundcfg['regovpnclients'] = isset($pconfig['regovpnclients']);
		$a_unboundcfg['active_interface'] = $pconfig['active_interface'];
		$a_unboundcfg['outgoing_interface'] = $pconfig['outgoing_interface'];
		$a_unboundcfg['system_domain_local_zone_type'] = $pconfig['system_domain_local_zone_type'];
		$a_unboundcfg['custom_options'] = $pconfig['custom_options'];

		write_config(gettext("DNS Resolver configured."));
		mark_subsystem_dirty('unbound');
	}

	$pconfig['active_interface'] = $display_active_interface;
	$pconfig['outgoing_interface'] = $display_outgoing_interface;
	$pconfig['custom_options'] = $display_custom_options;
}


if ($pconfig['custom_options']) {
	$customoptions = true;
} else {
	$customoptions = false;
}

if ($_POST['act'] == "del") {
	if ($_POST['type'] == 'host') {
		if ($a_hosts[$_POST['id']]) {
			unset($a_hosts[$_POST['id']]);
			write_config(gettext("Host override deleted from DNS Resolver."));
			mark_subsystem_dirty('unbound');
			header("Location: services_unbound.php");
			exit;
		}
	} elseif ($_POST['type'] == 'doverride') {
		if ($a_domainOverrides[$_POST['id']]) {
			unset($a_domainOverrides[$_POST['id']]);
			write_config(gettext("Domain override deleted from DNS Resolver."));
			mark_subsystem_dirty('unbound');
			header("Location: services_unbound.php");
			exit;
		}
	}
}

function build_if_list($selectedifs) {
	$interface_addresses = get_possible_listen_ips(true);
	$iflist = array('options' => array(), 'selected' => array());

	$iflist['options']['all']	= gettext("All");
	if (empty($selectedifs) || empty($selectedifs[0]) || in_array("all", $selectedifs)) {
		array_push($iflist['selected'], "all");
	}

	foreach ($interface_addresses as $laddr => $ldescr) {
		$iflist['options'][$laddr] = htmlspecialchars($ldescr);

		if ($selectedifs && in_array($laddr, $selectedifs)) {
			array_push($iflist['selected'], $laddr);
		}
	}

	unset($interface_addresses);

	return($iflist);
}

$pgtitle = array(gettext("Services"), gettext("DNS Resolver"), gettext("General Settings"));
$pglinks = array("", "@self", "@self");
$shortcut_section = "resolver";

include_once("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('unbound')) {
	print_apply_box(gettext("The DNS resolver configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}

$tab_array = array();
$tab_array[] = array(gettext("General Settings"), true, "services_unbound.php");
$tab_array[] = array(gettext("Advanced Settings"), false, "services_unbound_advanced.php");
$tab_array[] = array(gettext("Access Lists"), false, "/services_unbound_acls.php");
display_top_tabs($tab_array, true);

$form = new Form();

$section = new Form_Section('General DNS Resolver Options');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable DNS resolver',
	$pconfig['enable']
));

$section->addInput(new Form_Input(
	'port',
	'Listen Port',
	'number',
	$pconfig['port'],
	['placeholder' => '53']
))->setHelp('The port used for responding to DNS queries. It should normally be left blank unless another service needs to bind to TCP/UDP port 53.');

$section->addInput(new Form_Checkbox(
	'enablessl',
	'Enable SSL/TLS Service',
	'Respond to incoming SSL/TLS queries from local clients',
	$pconfig['enablessl']
))->setHelp('Configures the DNS Resolver to act as a DNS over SSL/TLS server which can answer queries from clients which also support DNS over TLS. ' .
		'Activating this option disables automatic interface response routing behavior, thus it works best with specific interface bindings.' );

if ($certs_available) {
	$section->addInput($input = new Form_Select(
		'sslcertref',
		'SSL/TLS Certificate',
		$pconfig['sslcertref'],
		cert_build_list('cert', 'IPsec')
	))->setHelp('The server certificate to use for SSL/TLS service. The CA chain will be determined automatically.');
} else {
	$section->addInput(new Form_StaticText(
		'SSL/TLS Certificate',
		sprintf('No Certificates have been defined. A certificate is required before SSL/TLS can be enabled. %1$s Create or Import %2$s a Certificate.',
		'<a href="system_certmanager.php">', '</a>')
	));
}

$section->addInput(new Form_Input(
	'tlsport',
	'SSL/TLS Listen Port',
	'number',
	$pconfig['tlsport'],
	['placeholder' => '853']
))->setHelp('The port used for responding to SSL/TLS DNS queries. It should normally be left blank unless another service needs to bind to TCP/UDP port 853.');

$activeiflist = build_if_list($pconfig['active_interface']);

$section->addInput(new Form_Select(
	'active_interface',
	'*Network Interfaces',
	$activeiflist['selected'],
	$activeiflist['options'],
	true
))->addClass('general', 'resizable')->setHelp('Interface IPs used by the DNS Resolver for responding to queries from clients. If an interface has both IPv4 and IPv6 IPs, both are used. Queries to other interface IPs not selected below are discarded. ' .
			'The default behavior is to respond to queries on every available IPv4 and IPv6 address.');

$outiflist = build_if_list($pconfig['outgoing_interface']);

$section->addInput(new Form_Select(
	'outgoing_interface',
	'*Outgoing Network Interfaces',
	$outiflist['selected'],
	$outiflist['options'],
	true
))->addClass('general', 'resizable')->setHelp('Utilize different network interface(s) that the DNS Resolver will use to send queries to authoritative servers and receive their replies. By default all interfaces are used.');

$section->addInput(new Form_Checkbox(
	'strictout',
	'Strict Outgoing Network Interface Binding',
	'Do not send recursive queries if none of the selected Outgoing Network Interfaces are available.',
	$pconfig['strictout']
))->setHelp('By default the DNS Resolver sends recursive DNS requests over any available interfaces if none of the selected Outgoing Network Interfaces are available. This option makes the DNS Resolver refuse recursive queries.');

$section->addInput(new Form_Select(
	'system_domain_local_zone_type',
	'*System Domain Local Zone Type',
	$pconfig['system_domain_local_zone_type'],
	unbound_local_zone_types()
))->setHelp('The local-zone type used for the %1$s system domain (System | General Setup | Domain).  Transparent is the default.', $g['product_label']);

$section->addInput(new Form_Checkbox(
	'dnssec',
	'DNSSEC',
	'Enable DNSSEC Support',
	$pconfig['dnssec']
));

$section->addInput(new Form_Checkbox(
	'python',
	'Python Module',
	'Enable Python Module',
	$pconfig['python']
))->setHelp('Enable the Python Module.');

$python_files = glob("{$g['unbound_chroot_path']}/*.py");
$python_scripts = array();
if (!empty($python_files)) {
	foreach ($python_files as $file) {
		$file = pathinfo($file, PATHINFO_FILENAME);
		$python_scripts[$file] = $file;
	}
}
else {
	$python_scripts = array('' => 'No Python Module scripts found');
}

$section->addInput(new Form_Select(
	'python_order',
	'Python Module Order',
	$pconfig['python_order'],
	[ 'pre_validator' => 'Pre Validator', 'post_validator' => 'Post Validator' ]
))->setHelp('Select the Python Module ordering.');

$section->addInput(new Form_Select(
	'python_script',
	'Python Module Script',
	$pconfig['python_script'],
	$python_scripts
))->setHelp('Select the Python module script to utilize.');

$section->addInput(new Form_Checkbox(
	'forwarding',
	'DNS Query Forwarding',
	'Enable Forwarding Mode',
	$pconfig['forwarding']
))->setHelp('If this option is set, DNS queries will be forwarded to the upstream DNS servers defined under'.
					' %1$sSystem &gt; General Setup%2$s or those obtained via dynamic ' .
					'interfaces such as DHCP, PPP, or OpenVPN (if DNS Server Override ' .
				        'is enabled there).','<a href="system.php">','</a>');

$section->addInput(new Form_Checkbox(
	'forward_tls_upstream',
	null,
	'Use SSL/TLS for outgoing DNS Queries to Forwarding Servers',
	$pconfig['forward_tls_upstream']
))->setHelp('When set in conjunction with DNS Query Forwarding, queries to all upstream forwarding DNS servers will be sent using SSL/TLS on the default port of 853. Note that ALL configured forwarding servers MUST support SSL/TLS queries on port 853.');

$section->addInput(new Form_Checkbox(
	'regdhcp',
	'DHCP Registration',
	'Register DHCP leases in the DNS Resolver',
	$pconfig['regdhcp']
))->setHelp('If this option is set, then machines that specify their hostname when requesting an IPv4 DHCP lease will be registered'.
					' in the DNS Resolver so that their name can be resolved.'.
	    				' Note that this will cause the Resolver to reload and flush its resolution cache whenever a DHCP lease is issued.'.
					' The domain in %1$sSystem &gt; General Setup%2$s should also be set to the proper value.','<a href="system.php">','</a>');

$section->addInput(new Form_Checkbox(
	'regdhcpstatic',
	'Static DHCP',
	'Register DHCP static mappings in the DNS Resolver',
	$pconfig['regdhcpstatic']
))->setHelp('If this option is set, then DHCP static mappings will be registered in the DNS Resolver, so that their name can be resolved. '.
					'The domain in %1$sSystem &gt; General Setup%2$s should also be set to the proper value.','<a href="system.php">','</a>');

$section->addInput(new Form_Checkbox(
	'regovpnclients',
	'OpenVPN Clients',
	'Register connected OpenVPN clients in the DNS Resolver',
	$pconfig['regovpnclients']
))->setHelp(sprintf('If this option is set, then the common name (CN) of connected OpenVPN clients will be ' .
	    'registered in the DNS Resolver, so that their name can be resolved. This only works for OpenVPN ' .
	    'servers (Remote Access SSL/TLS or User Auth with Username as Common Name option) operating ' .
	    'in "tun" mode. The domain in %sSystem: General Setup%s should also be set to the proper value.',
	    '<a href="system.php">','</a>'));

$btnadv = new Form_Button(
	'btnadvcustom',
	'Custom options',
	null,
	'fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	'Display Custom Options',
	$btnadv
));

$section->addInput(new Form_Textarea (
	'custom_options',
	'Custom options',
	$pconfig['custom_options']
))->setHelp('Enter any additional configuration parameters to add to the DNS Resolver configuration here, separated by a newline.');

$form->add($section);
print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Show advanced custom options ==============================================
	var showadvcustom = false;

	function show_advcustom(ispageload) {
		var text;
		// On page load decide the initial state based on the data.
		if (ispageload) {
			showadvcustom = <?=($customoptions ? 'true' : 'false');?>;
		} else {
			// It was a click, swap the state.
			showadvcustom = !showadvcustom;
		}

		hideInput('custom_options', !showadvcustom);

		if (showadvcustom) {
			text = "<?=gettext('Hide Custom Options');?>";
		} else {
			text = "<?=gettext('Display Custom Options');?>";
		}
		$('#btnadvcustom').html('<i class="fa fa-cog"></i> ' + text);
	}

	// If the enable checkbox is not checked, hide all inputs
	function hideGeneral() {
		var hide = ! $('#enable').prop('checked');

		hideMultiClass('general', hide);
		hideInput('port', hide);
		hideSelect('system_domain_local_zone_type', hide);
		hideCheckbox('strictout', hide);
		hideCheckbox('dnssec', hide);
		hideCheckbox('forwarding', hide);
		hideCheckbox('regdhcp', hide);
		hideCheckbox('regdhcpstatic', hide);
		hideCheckbox('regovpnclients', hide);
		hideInput('btnadvcustom', hide);
		hideInput('custom_options', hide || !showadvcustom);
	}

	// Un-hide additional controls
	$('#btnadvcustom').click(function(event) {
		show_advcustom();
	});

	// When 'enable' is clicked, disable/enable the following hide inputs
	$('#enable').click(function() {
		hideGeneral();
	});

	// On initial load
	if ($('#custom_options').val().length == 0) {
		hideInput('custom_options', true);
	}

	hideGeneral();
	show_advcustom(true);

	// When the Python Module 'enable' is clicked, disable/enable the Python Module options
	function show_python_script() {
		var python = $('#python').prop('checked');
		hideInput('python_order', !python);
		hideInput('python_script', !python);
	}
	show_python_script();
	$('#python').click(function () {
		show_python_script();
	});

});
//]]>
</script>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Host Overrides")?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap table-rowdblclickedit" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Host")?></th>
					<th><?=gettext("Parent domain of host")?></th>
					<th><?=gettext("IP to return for host")?></th>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Actions")?></th>
				</tr>
			</thead>
			<tbody>
<?php
$i = 0;
foreach ($a_hosts as $hostent):
?>
				<tr>
					<td>
						<?=$hostent['host']?>
					</td>
					<td>
						<?=$hostent['domain']?>
					</td>
					<td>
						<?=$hostent['ip']?>
					</td>
					<td>
						<?=htmlspecialchars($hostent['descr'])?>
					</td>
					<td>
						<a class="fa fa-pencil"	title="<?=gettext('Edit host override')?>" href="services_unbound_host_edit.php?id=<?=$i?>"></a>
						<a class="fa fa-trash"	title="<?=gettext('Delete host override')?>" href="services_unbound.php?type=host&amp;act=del&amp;id=<?=$i?>" usepost></a>
					</td>
				</tr>

<?php
	if ($hostent['aliases']['item'] && is_array($hostent['aliases']['item'])):
		foreach ($hostent['aliases']['item'] as $alias):
?>
				<tr>
					<td>
						<?=$alias['host']?>
					</td>
					<td>
						<?=$alias['domain']?>
					</td>
					<td>
						<?=gettext("Alias for ");?><?=$hostent['host'] ? $hostent['host'] . '.' . $hostent['domain'] : $hostent['domain']?>
					</td>
					<td>
						<i class="fa fa-angle-double-right text-info"></i>
						<?=htmlspecialchars($alias['description'])?>
					</td>
					<td>
						<a class="fa fa-pencil"	title="<?=gettext('Edit host override')?>" 	href="services_unbound_host_edit.php?id=<?=$i?>"></a>
					</td>
				</tr>
<?php
		endforeach;
	endif;
	$i++;
endforeach;
?>
			</tbody>
		</table>
	</div>
</div>

<span class="help-block">
	Enter any individual hosts for which the resolver's standard DNS lookup process should be overridden and a specific
	IPv4 or IPv6 address should automatically be returned by the resolver. Standard and also non-standard names and parent domains
	can be entered, such as 'test', 'nas.home.arpa', 'mycompany.localdomain', '1.168.192.in-addr.arpa', or 'somesite.com'. Any lookup attempt for
	the host will automatically return the given IP address, and the usual lookup server for the domain will not be queried for
	the host's records.
</span>

<nav class="action-buttons">
	<a href="services_unbound_host_edit.php" class="btn btn-sm btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Domain Overrides")?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap table-rowdblclickedit" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Domain")?></th>
					<th><?=gettext("Lookup Server IP Address")?></th>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Actions")?></th>
				</tr>
			</thead>

			<tbody>
<?php
$i = 0;
foreach ($a_domainOverrides as $doment):
?>
				<tr>
					<td>
						<?=$doment['domain']?>&nbsp;
					</td>
					<td>
						<?=$doment['ip']?>&nbsp;
					</td>
					<td>
						<?=htmlspecialchars($doment['descr'])?>&nbsp;
					</td>
					<td>
						<a class="fa fa-pencil"	title="<?=gettext('Edit domain override')?>" href="services_unbound_domainoverride_edit.php?id=<?=$i?>"></a>
						<a class="fa fa-trash"	title="<?=gettext('Delete domain override')?>" href="services_unbound.php?act=del&amp;type=doverride&amp;id=<?=$i?>" usepost></a>
					</td>
				</tr>
<?php
	$i++;
endforeach;
?>
			</tbody>
		</table>
	</div>
</div>

<span class="help-block">
	Enter any domains for which the resolver's standard DNS lookup process should be overridden and a different (non-standard)
	lookup server should be queried instead. Non-standard, 'invalid' and local domains, and subdomains, can also be entered,
	such as 'test', 'nas.home.arpa', 'mycompany.localdomain', '1.168.192.in-addr.arpa', or 'somesite.com'. The IP address is treated as the
	authoritative lookup server for the domain (including all of its subdomains), and other lookup servers will not be queried.
	If there are multiple authoritative DNS servers available for a domain then make a separate entry for each,
	using the same domain name.
</span>

<nav class="action-buttons">
	<a href="services_unbound_domainoverride_edit.php" class="btn btn-sm btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<div class="infoblock">
	<?php print_info_box(sprintf(gettext('If the DNS Resolver is enabled, the DHCP'.
		' service (if enabled) will automatically serve the LAN IP'.
		' address as a DNS server to DHCP clients so they will use'.
		' the DNS Resolver. If Forwarding is enabled, the DNS Resolver will use the DNS servers'.
		' entered in %1$sSystem &gt; General Setup%2$s'.
		' or those obtained via DHCP or PPP on WAN if &quot;Allow'.
		' DNS server list to be overridden by DHCP/PPP on WAN&quot;'.
		' is checked.'), '<a href="system.php">', '</a>'), 'info', false); ?>
</div>

<?php include("foot.inc");
