<?php
/*
	services_unbound.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2014 Warren Baker (warren@pfsense.org)
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
##|*IDENT=page-services-dnsresolver
##|*NAME=Services: DNS Resolver
##|*DESCR=Allow access to the 'Services: DNS Resolver' page.
##|*MATCH=services_unbound.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("unbound.inc");
require_once("system.inc");

if (!is_array($config['unbound'])) {
	$config['unbound'] = array();
}

$a_unboundcfg =& $config['unbound'];

if (!is_array($a_unboundcfg['hosts'])) {
	$a_unboundcfg['hosts'] = array();
}

$a_hosts =& $a_unboundcfg['hosts'];

if (!is_array($a_unboundcfg['domainoverrides'])) {
	$a_unboundcfg['domainoverrides'] = array();
}

$a_domainOverrides = &$a_unboundcfg['domainoverrides'];

if (isset($a_unboundcfg['enable'])) {
	$pconfig['enable'] = true;
}
if (isset($a_unboundcfg['dnssec'])) {
	$pconfig['dnssec'] = true;
}
if (isset($a_unboundcfg['forwarding'])) {
	$pconfig['forwarding'] = true;
}
if (isset($a_unboundcfg['regdhcp'])) {
	$pconfig['regdhcp'] = true;
}
if (isset($a_unboundcfg['regdhcpstatic'])) {
	$pconfig['regdhcpstatic'] = true;
}

$pconfig['port'] = $a_unboundcfg['port'];
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

if ($_POST) {
	if ($_POST['apply']) {
		$retval = services_unbound_configure();
		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			clear_subsystem_dirty('unbound');
		}
		/* Update resolv.conf in case the interface bindings exclude localhost. */
		system_resolvconf_generate();
		/* Start or restart dhcpleases when it's necessary */
		system_dhcpleases_configure();
	} else {
		$pconfig = $_POST;
		unset($input_errors);

		if (isset($pconfig['enable']) && isset($config['dnsmasq']['enable'])) {
			if ($pconfig['port'] == $config['dnsmasq']['port']) {
				$input_errors[] = "The DNS Forwarder is enabled using this port. Choose a non-conflicting port, or disable the DNS Forwarder.";
			}
		}

		if (empty($pconfig['active_interface'])) {
			$input_errors[] = "One or more Network Interfaces must be selected for binding.";
		} else if (!isset($config['system']['dnslocalhost']) && (!in_array("lo0", $pconfig['active_interface']) && !in_array("all", $pconfig['active_interface']))) {
			$input_errors[] = "This system is configured to use the DNS Resolver as its DNS server, so Localhost or All must be selected in Network Interfaces.";
		}

		if (empty($pconfig['outgoing_interface'])) {
			$input_errors[] = "One or more Outgoing Network Interfaces must be selected.";
		}

		if (empty($pconfig['system_domain_local_zone_type'])) {
			$input_errors[] = "A System Domain Local-Zone Type must be selected.";
		}

		if ($pconfig['port'] && !is_port($pconfig['port'])) {
			$input_errors[] = gettext("You must specify a valid port number.");
		}

		if (is_array($pconfig['active_interface']) && !empty($pconfig['active_interface'])) {
			$display_active_interface = $pconfig['active_interface'];
			$pconfig['active_interface'] = implode(",", $pconfig['active_interface']);
		}

		$display_custom_options = $pconfig['custom_options'];
		$pconfig['custom_options'] = base64_encode(str_replace("\r\n", "\n", $pconfig['custom_options']));

		if (is_array($pconfig['outgoing_interface']) && !empty($pconfig['outgoing_interface'])) {
			$display_outgoing_interface = $pconfig['outgoing_interface'];
			$pconfig['outgoing_interface'] = implode(",", $pconfig['outgoing_interface']);
		}

		if (isset($pconfig['system_domain_local_zone_type']) && !empty($pconfig['system_domain_local_zone_type'])) {
			$display_system_domain_local_zone_type = $pconfig['system_domain_local_zone_type'];
			$pconfig['system_domain_local_zone_type'] = $pconfig['system_domain_local_zone_type'];
		}

		$test_output = array();
		if (test_unbound_config($pconfig, $test_output)) {
			$input_errors[] = gettext("The generated config file cannot be parsed by unbound. Please correct the following errors:");
			$input_errors = array_merge($input_errors, $test_output);
		}

		if (!$input_errors) {
			$a_unboundcfg['enable'] = isset($pconfig['enable']);
			$a_unboundcfg['port'] = $pconfig['port'];
			$a_unboundcfg['dnssec'] = isset($pconfig['dnssec']);
			$a_unboundcfg['forwarding'] = isset($pconfig['forwarding']);
			$a_unboundcfg['regdhcp'] = isset($pconfig['regdhcp']);
			$a_unboundcfg['regdhcpstatic'] = isset($pconfig['regdhcpstatic']);
			$a_unboundcfg['active_interface'] = $pconfig['active_interface'];
			$a_unboundcfg['outgoing_interface'] = $pconfig['outgoing_interface'];
			$a_unboundcfg['system_domain_local_zone_type'] = $pconfig['system_domain_local_zone_type'];
			$a_unboundcfg['custom_options'] = $pconfig['custom_options'];

			write_config("DNS Resolver configured.");
			mark_subsystem_dirty('unbound');
		}

		$pconfig['active_interface'] = $display_active_interface;
		$pconfig['outgoing_interface'] = $display_outgoing_interface;
		$pconfig['system_domain_local_zone_type'] = $display_system_domain_local_zone_type;
		$pconfig['custom_options'] = $display_custom_options;
	}
}

if ($_GET['act'] == "del") {
	if ($_GET['type'] == 'host') {
		if ($a_hosts[$_GET['id']]) {
			unset($a_hosts[$_GET['id']]);
			write_config();
			mark_subsystem_dirty('unbound');
			header("Location: services_unbound.php");
			exit;
		}
	} elseif ($_GET['type'] == 'doverride') {
		if ($a_domainOverrides[$_GET['id']]) {
			unset($a_domainOverrides[$_GET['id']]);
			write_config();
			mark_subsystem_dirty('unbound');
			header("Location: services_unbound.php");
			exit;
		}
	}
}

function build_if_list($selectedifs) {
	$interface_addresses = get_possible_listen_ips(true);
	$iflist = array('options' => array(), 'selected' => array());

	$iflist['options']['all']	= "All";
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

$pgtitle = array(gettext("Services"), gettext("DNS Resolver"), gettext("General"));
$shortcut_section = "resolver";

include_once("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('unbound')) {
	print_info_box_np(gettext("The configuration of the DNS Resolver has been changed. You must apply changes for them to take effect."));
}

$tab_array = array();
$tab_array[] = array(gettext("General settings"), true, "services_unbound.php");
$tab_array[] = array(gettext("Advanced settings"), false, "services_unbound_advanced.php");
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

$activeiflist = build_if_list($pconfig['active_interface']);

$section->addInput(new Form_Select(
	'active_interface',
	'Network Interfaces',
	$activeiflist['selected'],
	$activeiflist['options'],
	true
))->addClass('general')->setHelp('Interface IPs used by the DNS Resolver for responding to queries from clients. If an interface has both IPv4 and IPv6 IPs, both are used. Queries to other interface IPs not selected below are discarded. ' .
			'The default behavior is to respond to queries on every available IPv4 and IPv6 address.');

$outiflist = build_if_list($pconfig['outgoing_interface']);

$section->addInput(new Form_Select(
	'outgoing_interface',
	'Outgoing Network Interfaces',
	$outiflist['selected'],
	$outiflist['options'],
	true
))->addClass('general')->setHelp('Utilize different network interface(s) that the DNS Resolver will use to send queries to authoritative servers and receive their replies. By default all interfaces are used.');

$unbound_local_zone_types = array("deny" => gettext("Deny"), "refuse" => gettext("Refuse"), "static" => gettext("Static"), "transparent" => gettext("Transparent"), "typetransparent" => gettext("Type Transparent"), "redirect" => gettext("Redirect"), "inform" => gettext("Inform"), "inform_deny" => gettext("Inform Deny"), "nodefault" => gettext("No Default"));

$section->addInput(new Form_Select(
	'system_domain_local_zone_type',
	'System Domain Local Zone Type',
	$pconfig['system_domain_local_zone_type'],
	$unbound_local_zone_types
))->setHelp('The local-zone type used for the pfSense system domain (System | General Setup | Domain).  Transparent is the default.  Local-Zone type descriptions are available in the unbound.conf(5) manual pages.');

$section->addInput(new Form_Checkbox(
	'dnssec',
	'DNSSEC',
	'Enable DNSSEC Support',
	$pconfig['dnssec']
));

$section->addInput(new Form_Checkbox(
	'forwarding',
	'DNS Query Forwarding',
	'Enable Forwarding Mode',
	$pconfig['forwarding']
));

$section->addInput(new Form_Checkbox(
	'regdhcp',
	'DHCP Registration',
	'Register DHCP leases in the DNS Resolver',
	$pconfig['regdhcp']
))->setHelp(sprintf('If this option is set, then machines that specify their hostname when requesting a DHCP lease will be registered'.
					' in the DNS Resolver, so that their name can be resolved.'.
					' You should also set the domain in %sSystem: General setup%s to the proper value.','<a href="system.php">','</a>'));

$section->addInput(new Form_Checkbox(
	'regdhcpstatic',
	'Static DHCP',
	'Register DHCP static mappings in the DNS Resolver',
	$pconfig['regdhcpstatic']
))->setHelp(sprintf('If this option is set, then DHCP static mappings will be registered in the DNS Resolver, so that their name can be '.
					'resolved. You should also set the domain in %s'.
					'System: General setup%s to the proper value.','<a href="system.php">','</a>'));

$btnadvdns = new Form_Button(
	'btnadvdns',
	'Custom options'
);

$btnadvdns->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'Custom options',
	$btnadvdns . '&nbsp;' . 'Show custom options'
));

$section->addInput(new Form_Textarea (
	'custom_options',
	'Custom options',
	$pconfig['custom_options']
))->setHelp('Enter any additional configuration parameters to add to the DNS Resolver configuration here, separated by a newline');

$form->add($section);
print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// If the enable checkbox is not checked, hide all inputs
	function hideGeneral() {
		var hide = ! $('#enable').prop('checked');

		hideMultiClass('general', hide);
		hideInput('port', hide);
		hideSelect('system_domain_local_zone_type', hide);
		hideCheckbox('dnssec', hide);
		hideCheckbox('forwarding', hide);
		hideCheckbox('regdhcp', hide);
		hideCheckbox('regdhcpstatic', hide);
		hideInput('btnadvdns', hide);
	}

	// Make the 'additional options' button a plain button, not a submit button
	$("#btnadvdns").prop('type','button');

	// Un-hide additional  controls
	$("#btnadvdns").click(function() {
		hideInput('custom_options', false);
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

});
//]]>
</script>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Host Overrides")?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Host")?></th>
					<th><?=gettext("Domain")?></th>
					<th><?=gettext("IP")?></th>
					<th><?=gettext("Description")?></th>
					<th></th>
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
						<a class="fa fa-trash"	title="<?=gettext('Delete host override')?>" href="services_unbound.php?type=host&amp;act=del&amp;id=<?=$i?>"></a>
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
						Alias for <?=$hostent['host'] ? $hostent['host'] . '.' . $hostent['domain'] : $hostent['domain']?>
					</td>
					<td>
						<i class="fa fa-angle-double-right text-info"></i>
						<?=htmlspecialchars($alias['description'])?>
					</td>
					<td>
						<a a class="fa fa-pencil"	title="<?=gettext('Edit host override')?>" 	href="services_unbound_host_edit.php?id=<?=$i?>"></a>
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

<nav class="action-buttons">
	<a href="services_unbound_host_edit.php" class="btn btn-sm btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Domain Overrides")?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Domain")?></th>
					<th><?=gettext("IP")?></th>
					<th><?=gettext("Description")?></th>
					<th></th>
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
						<a class="fa fa-trash"	title="<?=gettext('Delete domain override')?>" href="services_unbound.php?act=del&amp;type=doverride&amp;id=<?=$i?>"></a>
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

<nav class="action-buttons">
	<a href="services_unbound_domainoverride_edit.php" class="btn btn-sm btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<div class="infoblock">
	<?=print_info_box(sprintf(gettext("If the DNS Resolver is enabled, the DHCP".
		" service (if enabled) will automatically serve the LAN IP".
		" address as a DNS server to DHCP clients so they will use".
		" the DNS Resolver. If Forwarding is enabled, the DNS Resolver will use the DNS servers".
		" entered in %sSystem: General setup%s".
		" or those obtained via DHCP or PPP on WAN if &quot;Allow".
		" DNS server list to be overridden by DHCP/PPP on WAN&quot;".
		" is checked."), '<a href="system.php">', '</a>'), 'info')?>
</div>

<?php include("foot.inc");
