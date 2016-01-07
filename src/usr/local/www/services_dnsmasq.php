<?php
/*
	services_dnsmasq.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2003-2004 Bob Zoller <bob@kludgebox.com> and Manuel Kasper <mk@neon1.net
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
##|*IDENT=page-services-dnsforwarder
##|*NAME=Services: DNS Forwarder
##|*DESCR=Allow access to the 'Services: DNS Forwarder' page.
##|*MATCH=services_dnsmasq.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("system.inc");

$pconfig['enable'] = isset($config['dnsmasq']['enable']);
$pconfig['regdhcp'] = isset($config['dnsmasq']['regdhcp']);
$pconfig['regdhcpstatic'] = isset($config['dnsmasq']['regdhcpstatic']);
$pconfig['dhcpfirst'] = isset($config['dnsmasq']['dhcpfirst']);
$pconfig['strict_order'] = isset($config['dnsmasq']['strict_order']);
$pconfig['domain_needed'] = isset($config['dnsmasq']['domain_needed']);
$pconfig['no_private_reverse'] = isset($config['dnsmasq']['no_private_reverse']);
$pconfig['port'] = $config['dnsmasq']['port'];
$pconfig['custom_options'] = $config['dnsmasq']['custom_options'];

$pconfig['strictbind'] = isset($config['dnsmasq']['strictbind']);
if (!empty($config['dnsmasq']['interface'])) {
	$pconfig['interface'] = explode(",", $config['dnsmasq']['interface']);
} else {
	$pconfig['interface'] = array();
}

if (!is_array($config['dnsmasq']['hosts'])) {
	$config['dnsmasq']['hosts'] = array();
}

if (!is_array($config['dnsmasq']['domainoverrides'])) {
	$config['dnsmasq']['domainoverrides'] = array();
}

$a_hosts = &$config['dnsmasq']['hosts'];
$a_domainOverrides = &$config['dnsmasq']['domainoverrides'];

if ($_POST) {
	if ($_POST['apply']) {
		$retval = 0;
		$retval = services_dnsmasq_configure();
		$savemsg = get_std_save_message($retval);

		// Reload filter (we might need to sync to CARP hosts)
		filter_configure();
		/* Update resolv.conf in case the interface bindings exclude localhost. */
		system_resolvconf_generate();
		/* Start or restart dhcpleases when it's necessary */
		system_dhcpleases_configure();

		if ($retval == 0) {
			clear_subsystem_dirty('hosts');
		}
	} else {
		$pconfig = $_POST;
		unset($input_errors);

		$config['dnsmasq']['enable'] = ($_POST['enable']) ? true : false;
		$config['dnsmasq']['regdhcp'] = ($_POST['regdhcp']) ? true : false;
		$config['dnsmasq']['regdhcpstatic'] = ($_POST['regdhcpstatic']) ? true : false;
		$config['dnsmasq']['dhcpfirst'] = ($_POST['dhcpfirst']) ? true : false;
		$config['dnsmasq']['strict_order'] = ($_POST['strict_order']) ? true : false;
		$config['dnsmasq']['domain_needed'] = ($_POST['domain_needed']) ? true : false;
		$config['dnsmasq']['no_private_reverse'] = ($_POST['no_private_reverse']) ? true : false;
		$config['dnsmasq']['custom_options'] = str_replace("\r\n", "\n", $_POST['custom_options']);
		$config['dnsmasq']['strictbind'] = ($_POST['strictbind']) ? true : false;

		if (isset($_POST['enable']) && isset($config['unbound']['enable'])) {
			if ($_POST['port'] == $config['unbound']['port']) {
				$input_errors[] = "The DNS Resolver is enabled using this port. Choose a non-conflicting port, or disable DNS Resolver.";
			}
		}

		if ($_POST['port']) {
			if (is_port($_POST['port'])) {
				$config['dnsmasq']['port'] = $_POST['port'];
			} else {
				$input_errors[] = gettext("You must specify a valid port number");
			}
		} else if (isset($config['dnsmasq']['port'])) {
			unset($config['dnsmasq']['port']);
		}

		if (is_array($_POST['interface'])) {
			$config['dnsmasq']['interface'] = implode(",", $_POST['interface']);
		} elseif (isset($config['dnsmasq']['interface'])) {
			unset($config['dnsmasq']['interface']);
		}

		if ($config['dnsmasq']['custom_options']) {
			$args = '';
			foreach (preg_split('/\s+/', $config['dnsmasq']['custom_options']) as $c) {
				$args .= escapeshellarg("--{$c}") . " ";
			}
			exec("/usr/local/sbin/dnsmasq --test $args", $output, $rc);
			if ($rc != 0) {
				$input_errors[] = gettext("Invalid custom options");
			}
		}

		if (!$input_errors) {
			write_config();
			mark_subsystem_dirty('hosts');
		}
	}
}

if ($_GET['act'] == "del") {
	if ($_GET['type'] == 'host') {
		if ($a_hosts[$_GET['id']]) {
			unset($a_hosts[$_GET['id']]);
			write_config();
			mark_subsystem_dirty('hosts');
			header("Location: services_dnsmasq.php");
			exit;
		}
	} elseif ($_GET['type'] == 'doverride') {
		if ($a_domainOverrides[$_GET['id']]) {
			unset($a_domainOverrides[$_GET['id']]);
			write_config();
			mark_subsystem_dirty('hosts');
			header("Location: services_dnsmasq.php");
			exit;
		}
	}
}

function build_if_list() {
	global $pconfig;

	$interface_addresses = get_possible_listen_ips(true);
	$iflist = array('options' => array(), 'selected' => array());

	$iflist['options'][""]	= "All";
	if (empty($pconfig['interface']) || empty($pconfig['interface'][0])) {
		array_push($iflist['selected'], "");
	}

	foreach ($interface_addresses as $laddr => $ldescr) {
		$iflist['options'][$laddr] = htmlspecialchars($ldescr);

		if ($pconfig['interface'] && in_array($laddr, $pconfig['interface'])) {
			array_push($iflist['selected'], $laddr);
		}
	}

	unset($interface_addresses);

	return($iflist);
}

$pgtitle = array(gettext("Services"), gettext("DNS Forwarder"));
$shortcut_section = "forwarder";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('hosts')) {
	print_info_box_np(gettext("The DNS forwarder configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));
}

$form = new Form();

$section = new Form_Section('General DNS Forwarder Options');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable DNS forwarder',
	$pconfig['enable']
))->toggles('.toggle-dhcp', 'disable');

$section->addInput(new Form_Checkbox(
	'regdhcp',
	'DHCP Registration',
	'Register DHCP leases in DNS forwarder',
	$pconfig['regdhcp']
))->setHelp(sprintf("If this option is set, then machines that specify".
			" their hostname when requesting a DHCP lease will be registered".
			" in the DNS forwarder, so that their name can be resolved.".
			" You should also set the domain in %sSystem:".
			" General setup%s to the proper value.",'<a href="system.php">','</a>'))
	->addClass('toggle-dhcp');

$section->addInput(new Form_Checkbox(
	'regdhcpstatic',
	'Static DHCP',
	'Register DHCP static mappings in DNS forwarder',
	$pconfig['regdhcpstatic']
))->setHelp(sprintf("If this option is set, then DHCP static mappings will ".
					"be registered in the DNS forwarder, so that their name can be ".
					"resolved. You should also set the domain in %s".
					"System: General setup%s to the proper value.",'<a href="system.php">','</a>'))
	->addClass('toggle-dhcp');

$section->addInput(new Form_Checkbox(
	'dhcpfirst',
	'Prefer DHCP',
	'Resolve DHCP mappings first',
	$pconfig['dhcpfirst']
))->setHelp(sprintf("If this option is set, then DHCP mappings will ".
					"be resolved before the manual list of names below. This only ".
					"affects the name given for a reverse lookup (PTR)."))
	->addClass('toggle-dhcp');

$group = new Form_Group('DNS Query Forwarding');

$group->add(new Form_Checkbox(
	'strict_order',
	'DNS Query Forwarding',
	'Query DNS servers sequentially',
	$pconfig['strict_order']
))->setHelp(sprintf("If this option is set, %s DNS Forwarder (dnsmasq) will ".
					"query the DNS servers sequentially in the order specified (<i>System - General Setup - DNS Servers</i>), ".
					"rather than all at once in parallel. ", $g['product_name']));

$group->add(new Form_Checkbox(
	'domain_needed',
	null,
	'Require domain',
	$pconfig['domain_needed']
))->setHelp(sprintf("If this option is set, %s DNS Forwarder (dnsmasq) will ".
					"not forward A or AAAA queries for plain names, without dots or domain parts, to upstream name servers.	 ".
					"If the name is not known from /etc/hosts or DHCP then a \"not found\" answer is returned. ", $g['product_name']));

$group->add(new Form_Checkbox(
	'no_private_reverse',
	null,
	'Do not forward private reverse lookups',
	$pconfig['no_private_reverse']
))->setHelp(sprintf("If this option is set, %s DNS Forwarder (dnsmasq) will ".
					"not forward reverse DNS lookups (PTR) for private addresses (RFC 1918) to upstream name servers.  ".
					"Any entries in the Domain Overrides section forwarding private \"n.n.n.in-addr.arpa\" names to a specific server are still forwarded. ".
					"If the IP to name is not known from /etc/hosts, DHCP or a specific domain override then a \"not found\" answer is immediately returned. ", $g['product_name']));

$section->add($group);

$section->addInput(new Form_Input(
	'port',
	'Listen Port',
	'number',
	$pconfig['port'],
	['placeholder' => '53']
))->setHelp('The port used for responding to DNS queries. It should normally be left blank unless another service needs to bind to TCP/UDP port 53.');

$iflist = build_if_list();

$section->addInput(new Form_Select(
	'interface',
	'Interfaces',
	$iflist['selected'],
	$iflist['options'],
	true
))->setHelp('Interface IPs used by the DNS Forwarder for responding to queries from clients. If an interface has both IPv4 and IPv6 IPs, both are used. Queries to other interface IPs not selected below are discarded. ' .
			'The default behavior is to respond to queries on every available IPv4 and IPv6 address.');

$section->addInput(new Form_Checkbox(
	'strictbind',
	'Strict binding',
	'Strict interface binding',
	$pconfig['strictbind']
))->setHelp('If this option is set, the DNS forwarder will only bind to the interfaces containing the IP addresses selected above, ' .
					'rather than binding to all interfaces and discarding queries to other addresses.' . '<br /><br />' .
					'This option does NOT work with IPv6. If set, dnsmasq will not bind to IPv6 addresses.');

$section->addInput(new Form_Textarea(
	'custom_options',
	'Custom options',
	$pconfig['custom_options']
))->setHelp('Enter any additional options you would like to add to the dnsmasq configuration here, separated by a space or newline')
	->addClass('advanced');

$form->add($section);
print($form);

print_info_box(sprintf("If the DNS forwarder is enabled, the DHCP".
	" service (if enabled) will automatically serve the LAN IP".
	" address as a DNS server to DHCP clients so they will use".
	" the forwarder. The DNS forwarder will use the DNS servers".
	" entered in %sSystem: General setup%s".
	" or those obtained via DHCP or PPP on WAN if the &quot;Allow".
	" DNS server list to be overridden by DHCP/PPP on WAN&quot;".
	" is checked. If you don't use that option (or if you use".
	" a static IP address on WAN), you must manually specify at".
	" least one DNS server on the %sSystem:".
	"General setup%s page.",'<a href="system.php">','</a>','<a href="system.php">','</a>'), 'info');
?>

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
foreach ($a_hosts as $i => $hostent):
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
						<a class="fa fa-pencil"	title="<?=gettext('Edit host override')?>" 	href="services_dnsmasq_edit.php?id=<?=$i?>"></a>
						<a class="fa fa-trash"	title="<?=gettext('Delete host override')?>"	href="services_dnsmasq.php?type=host&amp;act=del&amp;id=<?=$i?>"></a>
					</td>
				</tr>

<?php
	if ($hostent['aliases']['item'] && is_array($hostent['aliases']['item'])):
		foreach ($hostent['aliases']['item'] as $i => $alias):
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
						<a class="fa fa-pencil"	title="<?=gettext('Edit host override')?>" 	href="services_dnsmasq_edit.php?id=<?=$i?>"></a>
					</td>
				</tr>
<?php
		endforeach;
	endif;
endforeach;
?>
			</tbody>
		</table>
	</div>
</div>

<nav class="action-buttons">
	<a href="services_dnsmasq_edit.php" class="btn btn-sm btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<?php
print_info_box(gettext("Entries in this section override individual results from the forwarders.") .
				gettext("Use these for changing DNS results or for adding custom DNS records."), 'info');
?>

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
foreach ($a_domainOverrides as $i => $doment):
?>
				<tr>
					<td>
						<?=$doment['domain']?>
					</td>
					<td>
						<?=$doment['ip']?>
					</td>
					<td>
						<?=htmlspecialchars($doment['descr'])?>
					</td>
					<td>
						<a class="fa fa-pencil"	title="<?=gettext('Edit domain override')?>" href="services_dnsmasq_domainoverride_edit.php?id=<?=$i?>"></a>
						<a class="fa fa-trash"	title="<?=gettext('Delete domain override')?>" href="services_dnsmasq.php?act=del&amp;type=doverride&amp;id=<?=$i?>"></a>
					</td>
				</tr>
<?php
endforeach;
?>
			</tbody>
		</table>
	</div>
</div>

<nav class="action-buttons">
	<a href="services_dnsmasq_domainoverride_edit.php" class="btn btn-sm btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// On clicking the "Apply" button, submit the main form, not the little form the button lives in
//	$('[name=apply]').prop('type', 'button');

//    $('[name=apply]').click(function() {
//        $('form:last').submit();
//    });
// });
//]]>
</script>
<?php
print_info_box(gettext("Entries in this area override an entire domain, and subdomains, by specifying an".
						" authoritative DNS server to be queried for that domain."), 'info');

include("foot.inc");
