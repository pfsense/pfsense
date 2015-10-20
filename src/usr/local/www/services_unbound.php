<?php
/* $Id$ */
/*
	services_unbound.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2004, 2005 Scott Ullrich
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
/*
	pfSense_MODULE: dnsresolver
*/

##|+PRIV
##|*IDENT=page-services-unbound
##|*NAME=Services: DNS Resolver page
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

if (!is_array($config['unbound']['hosts'])) {
	$config['unbound']['hosts'] = array();
}

$a_hosts =& $config['unbound']['hosts'];

if (!is_array($config['unbound']['domainoverrides'])) {
	$config['unbound']['domainoverrides'] = array();
}

$a_domainOverrides = &$config['unbound']['domainoverrides'];

if (isset($config['unbound']['enable'])) {
	$pconfig['enable'] = true;
}
if (isset($config['unbound']['dnssec'])) {
	$pconfig['dnssec'] = true;
}
if (isset($config['unbound']['forwarding'])) {
	$pconfig['forwarding'] = true;
}
if (isset($config['unbound']['regdhcp'])) {
	$pconfig['regdhcp'] = true;
}
if (isset($config['unbound']['regdhcpstatic'])) {
	$pconfig['regdhcpstatic'] = true;
}
if (isset($config['unbound']['txtsupport'])) {
	$pconfig['txtsupport'] = true;
}

$pconfig['port'] = $config['unbound']['port'];
$pconfig['custom_options'] = base64_decode($config['unbound']['custom_options']);

if (empty($config['unbound']['active_interface'])) {
	$pconfig['active_interface'] = array();
} else {
	$pconfig['active_interface'] = explode(",", $config['unbound']['active_interface']);
}

if (empty($config['unbound']['outgoing_interface'])) {
	$pconfig['outgoing_interface'] = array();
} else {
	$pconfig['outgoing_interface'] = explode(",", $config['unbound']['outgoing_interface']);
}

if ($_POST) {
	$pconfig = $_POST;
	unset($input_errors);

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
		if (isset($_POST['enable']) && isset($config['dnsmasq']['enable'])) {
			if ($_POST['port'] == $config['dnsmasq']['port']) {
				$input_errors[] = "The DNS Forwarder is enabled using this port. Choose a non-conflicting port, or disable the DNS Forwarder.";
			}
		}

		if (empty($_POST['active_interface'])) {
			$input_errors[] = "One or more Network Interfaces must be selected for binding.";
		} else if (!isset($config['system']['dnslocalhost']) && (!in_array("lo0", $_POST['active_interface']) && !in_array("all", $_POST['active_interface']))) {
			$input_errors[] = "This system is configured to use the DNS Resolver as its DNS server, so Localhost or All must be selected in Network Interfaces.";
		}

		if (empty($_POST['outgoing_interface'])) {
			$input_errors[] = "One or more Outgoing Network Interfaces must be selected.";
		}

		if ($_POST['port']) {
			if (is_port($_POST['port'])) {
				$a_unboundcfg['port'] = $_POST['port'];
			} else {
				$input_errors[] = gettext("You must specify a valid port number.");
			}
		} else if (isset($config['unbound']['port'])) {
			unset($config['unbound']['port']);
		}

		if (isset($_POST['enable'])) {
			$a_unboundcfg['enable'] = true;
		} else {
			unset($a_unboundcfg['enable']);
		}
		if (isset($_POST['dnssec'])) {
			$a_unboundcfg['dnssec'] = true;
		} else {
			unset($a_unboundcfg['dnssec']);
		}
		if (isset($_POST['forwarding'])) {
			$a_unboundcfg['forwarding'] = true;
		} else {
			unset($a_unboundcfg['forwarding']);
		}
		if (isset($_POST['regdhcp'])) {
			$a_unboundcfg['regdhcp'] = true;
		} else {
			unset($a_unboundcfg['regdhcp']);
		}
		if (isset($_POST['regdhcpstatic'])) {
			$a_unboundcfg['regdhcpstatic'] = true;
		} else {
			unset($a_unboundcfg['regdhcpstatic']);
		}
		if (isset($_POST['txtsupport'])) {
			$a_unboundcfg['txtsupport'] = true;
		} else {
			unset($a_unboundcfg['txtsupport']);
		}
		if (is_array($_POST['active_interface']) && !empty($_POST['active_interface'])) {
			$a_unboundcfg['active_interface'] = implode(",", $_POST['active_interface']);
		}

		if (is_array($_POST['outgoing_interface']) && !empty($_POST['outgoing_interface'])) {
			$a_unboundcfg['outgoing_interface'] = implode(",", $_POST['outgoing_interface']);
		}

		$a_unboundcfg['custom_options'] = base64_encode(str_replace("\r\n", "\n", $_POST['custom_options']));

		if (!$input_errors) {
			write_config("DNS Resolver configured.");
			mark_subsystem_dirty('unbound');
		}
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

function build_if_list() {
	$interface_addresses = get_possible_listen_ips(true);
	$iflist = array('options' => array(), 'selected' => array());

	$iflist['options']['all']	= "All";
	if (empty($pconfig['interface']) || empty($pconfig['interface'][0]))
		array_push($iflist['selected'], "all");

	foreach ($interface_addresses as $laddr => $ldescr) {
		$iflist['options'][$laddr] = htmlspecialchars($ldescr);

		if ($pconfig['interface'] && in_array($laddr, $pconfig['interface']))
			array_push($iflist['selected'], $laddr);
	}

	unset($interface_addresses);

	return($iflist);
}

$closehead = false;
$pgtitle = array(gettext("Services"), gettext("DNS Resolver"));
$shortcut_section = "resolver";

include_once("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg)
	print_info_box($savemsg, 'success');

$tab_array = array();
$tab_array[] = array(gettext("General settings"), true, "services_unbound.php");
$tab_array[] = array(gettext("Advanced settings"), false, "services_unbound_advanced.php");
$tab_array[] = array(gettext("Access Lists"), false, "/services_unbound_acls.php");
display_top_tabs($tab_array, true);

require_once('classes/Form.class.php');

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
	'text',
	$pconfig['port']
))->setHelp('The port used for responding to DNS queries. It should normally be left blank unless another service needs to bind to TCP/UDP port 53.');

$iflist = build_if_list();

$section->addInput(new Form_Select(
	'active_interface',
	'Network Interfaces',
	$iflist['selected'],
	$iflist['options'],
	true
))->setHelp('Interface IPs used by the DNS Resolver for responding to queries from clients. If an interface has both IPv4 and IPv6 IPs, both are used. Queries to other interface IPs not selected below are discarded. ' .
			'The default behavior is to respond to queries on every available IPv4 and IPv6 address.');

$section->addInput(new Form_Select(
	'outgoing_interface',
	'Outgoing Network Interfaces',
	$iflist['selected'],
	$iflist['options'],
	true
))->setHelp('Utilize different network interface(s) that the DNS Resolver will use to send queries to authoritative servers and receive their replies. By default all interfaces are used.');

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

$section->addInput(new Form_Checkbox(
	'txtsupport',
	'TXT Comment Support',
	'Register DHCP static mappings in the DNS Resolver',
	$pconfig['txtsupport']
))->setHelp('Any descriptions associated with Host entries and DHCP Static mappings will create a corresponding TXT record.');

$btnadvdns = new Form_Button(
	'btnadvdns',
	'Advanced'
);

$btnadvdns->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'Advanced',
	$btnadvdns . '&nbsp;' . 'Show advanced optionss'
));

$section->addInput(new Form_TextArea (
	'custom_options',
	'Custom options',
	$pconfig['custom_options']
))->setHelp('Enter any additional configuration parameters to add to the DNS Resolver configuration here, separated by a newline');

$form->add($section);
print($form);

print_info_box(sprintf(gettext("If the DNS Resolver is enabled, the DHCP".
" service (if enabled) will automatically serve the LAN IP".
" address as a DNS server to DHCP clients so they will use".
" the DNS Resolver. If Forwarding, is enabled, the DNS Resolver will use the DNS servers".
" entered in %sSystem: General setup%s".
" or those obtained via DHCP or PPP on WAN if the &quot;Allow".
" DNS server list to be overridden by DHCP/PPP on WAN&quot;".
" is checked."),'<a href="system.php">','</a>'));
?>

<script>
//<![CDATA[
events.push(function(){

	// If the enable checkbox is not checked, disable the next three checkboxes
	function disableDHCP() {
		var hide = ! $('#enable').prop('checked');

		disableInput('port', hide);
		disableInput('active_interface', hide);
		disableInput('outgoing_interface', hide);
		disableInput('regdhcpstatic', hide);
		disableInput('dnssec', hide);
		disableInput('forwarding', hide);
		disableInput('regdhcp', hide);
		disableInput('regdhcpstatic', hide);
		disableInput('txtsupport', hide);
		disableInput('btnadvdns', hide);
	}

	// Make the ‘aditional options’ button a plain button, not a submit button
	$("#btnadvdns").prop('type','button');

	// Un-hide aditional  controls
	$("#btnadvdns").click(function() {
		hideInput('custom_options', false);

	});

	// When 'enable' is clicked, diable/enable the following three checkboxes
	$('#enable').click(function() {
		disableDHCP();
	});

	// On initial load
	if($('#custom_options').val().length == 0) {
		hideInput('custom_options', true);
	}

	disableDHCP();

});
//]]>
</script>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Host Overrides")?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed">
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
						<?=strtolower($hostent['host'])?>
					</td>
					<td>
						<?=strtolower($hostent['domain'])?>
					</td>
					<td>
						<?=$hostent['ip']?>&nbsp;
					</td>
					<td>
						<?=htmlspecialchars($hostent['descr'])?>
					</td>
					<td>
						<a href="services_unbound_host_edit.php?id=<?=$i?>" class="btn btn-xs btn-info"><?=gettext('Edit')?></a>
						<a href="services_unbound.php?type=host&amp;act=del&amp;id=<?=$i?>" class="btn btn-xs btn-danger"><?=gettext('Delete')?></a>
					</td>
				</tr>

<?php
	if ($hostent['aliases']['item'] && is_array($hostent['aliases']['item'])):
		foreach ($hostent['aliases']['item'] as $alias):
?>
				<tr>
					<td>
						<?=strtolower($alias['host'])?>
					</td>
					<td>
						<?=strtolower($alias['domain'])?>
					</td>
					<td>
						Alias for <?=$hostent['host'] ? $hostent['host'] . '.' . $hostent['domain'] : $hostent['domain']?>
					</td>
					<td>
						<?=htmlspecialchars($alias['description'])?>
					</td>
					<td>
						<a href="services_unbound_host_edit.php?id=<?=$i?>" class="btn btn-xs btn-info"><?=gettext('Edit')?></a>
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
	<a href="services_unbound_host_edit.php" class="btn btn-sm btn-success"><?=gettext('Add')?></a>
</nav>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Domain Overrides")?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed">
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
						<?=strtolower($doment['domain'])?>&nbsp;
					</td>
					<td>
						<?=$doment['ip']?>&nbsp;
					</td>
					<td>
						<?=htmlspecialchars($doment['descr'])?>&nbsp;
					</td>
					<td>
						<a href="services_unbound_domainoverride_edit.php?id=<?=$i?>" class="btn btn-xs btn-info"><?=gettext('Edit')?></a>
						<a href="services_unbound.php?act=del&amp;type=doverride&amp;id=<?=$i?>" class="btn btn-xs btn-danger"><?=gettext('Delete')?></a>
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
	<a href="services_unbound_domainoverride_edit.php" class="btn btn-sm btn-success"><?=gettext('Add')?></a>
</nav>
<?php include("foot.inc");
