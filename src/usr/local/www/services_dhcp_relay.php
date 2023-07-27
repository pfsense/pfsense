<?php
/*
 * services_dhcp_relay.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Justin Ellison <justin@techadvise.com>
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
##|*IDENT=page-services-dhcprelay
##|*NAME=Services: DHCP Relay
##|*DESCR=Allow access to the 'Services: DHCP Relay' page.
##|*MATCH=services_dhcp_relay.php*
##|-PRIV

require_once('guiconfig.inc');
require_once('filter.inc');

$pconfig['enable'] = config_path_enabled('dhcrelay');

$pconfig['interface'] = explode(',', config_get_path('dhcrelay/interface', ''));

$pconfig['agentoption'] = config_path_enabled('dhcrelay', 'agentoption');
$pconfig['server'] = config_get_path('dhcrelay/server');
$pconfig['carpstatusvip'] = config_get_path('dhcrelay/carpstatusvip', 'none');

function has_dhcpd_enabled(string $if): bool
{
	foreach (config_get_path('dhcpd', []) as $dhcpif => $dhcpconf) {
		if (($dhcpif === $if) &&
		    isset($dhcpconf['enable']) &&
		    config_path_enabled('interfaces/' . $dhcpif)) {
			return (true);
		}
	}

	return (false);
}

function has_valid_static_ip(string $if): bool
{
	$ifconf = config_get_path('interfaces/'.$if);

	return (!empty($ifconf['subnet']) && ($ifconf['subnet'] < 31) && is_ipaddrv4($ifconf['ipaddr']));
}

$iflist = array_intersect_key(
	get_configured_interface_with_descr(),
	array_flip(
		array_filter(
			array_keys(get_configured_interface_with_descr()),
			function($if) {
				return (!has_dhcpd_enabled($if) && has_valid_static_ip($if) &&
				    !is_pseudo_interface(convert_friendly_interface_to_real_interface_name($if)));
			}
		)
	)
);

$carpiflist = array_merge(['none' => 'none'], array_intersect_key(
       	get_configured_vip_list_with_descr('inet', VIP_CARP),
	array_flip(
		array_filter(
			array_keys(get_configured_vip_list_with_descr('inet', VIP_CARP)),
			function($if) {
				return (has_valid_static_ip($if) &&
				    !is_pseudo_interface(convert_friendly_interface_to_real_interface_name($if)));
			}
		)
	)
));

if ($_POST) {

	unset($input_errors);

	$pconfig = $_POST;

	$svrlist = [];

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = ['interface'];
		$reqdfieldsn = [gettext('Downstream Interface(s)')];
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
	}

	for ($idx = 0; $idx < count($_POST); $idx++) {
		if ($_POST['server'.$idx]) {
			if (empty($_POST['server'.$idx])) {
				continue;
			}

			if (!is_ipaddrv4($_POST['server'.$idx])) {
				$input_errors[] = sprintf(gettext('Upstream Server IPv4 address %s is not a valid IPv4 address.'), $_POST['server'.$idx]);
			}

			$svrlist[] = $_POST['server'.$idx];
		}
	}

	// Check that the user input something in one of the Destination Server fields
	if (empty($svrlist) && $_POST['enable']) {
		$input_errors[] = gettext('At least one Upstream Server IPv4 address must be specified.');
	}

	// Now $svrlist is a comma separated list of servers ready to save to the config system
	$svrlist = implode(',', $svrlist);
	$pconfig['server'] = $svrlist;

	if (!$input_errors) {
		init_config_arr(['dhcrelay']);
		config_set_path('dhcrelay/enable', $_POST['enable'] ? true : false);
		if (isset($_POST['interface']) &&
		    is_array($_POST['interface'])) {
			config_set_path('dhcrelay/interface',
					implode(",", $_POST['interface']));
		} else {
			config_del_path('dhcrelay/interface');
		}
		config_set_path('dhcrelay/agentoption', $_POST['agentoption'] ? true : false);
		config_set_path('dhcrelay/server', $svrlist);
		config_set_path('dhcrelay/carpstatusvip', $_POST['carpstatusvip']);

		write_config("DHCP Relay settings saved");

		$changes_applied = true;
		$retval = 0;
		$retval |= services_dhcrelay_configure();
		$retval |= filter_configure();
	}
}

$pgtitle = [gettext('Services'), gettext('DHCP Relay')];
$shortcut_section = 'dhcrelay';

include('head.inc');

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval);
}

$form = new Form(gettext('Save'));

$section = new Form_Section(gettext('DHCP Relay Configuration'));

$input = new Form_Checkbox(
	'enable',
	gettext('Enable'),
	gettext('Enable DHCP Relay'),
	(!empty($iflist) ? $pconfig['enable'] : false)
);
if (empty($iflist)) {
	$input->setAttribute('disabled', true);
}
$section->addInput($input);

$section->addInput(new Form_Select(
	'interface',
	'*'.gettext('Downstream Interface(s)'),
	$pconfig['interface'],
	$iflist,
	true
))->setHelp(gettext('Interface(s) from which requests from clients or other relay agents will be accepted.') . '%s' .
    gettext('Interfaces running DHCP Server or without a static IPv4 address will not be shown.'), '<br />');

$section->addInput(new Form_Select(
	'carpstatusvip',
	'*'.gettext('CARP Status VIP'),
	$pconfig['carpstatusvip'],
	$carpiflist,
))->setHelp(gettext('DHCP Relay will be stopped when the chosen CARP VIP is in BACKUP status, and started in MASTER status.'));

$section->addInput(new Form_Checkbox(
	'agentoption',
	null,
	'Append circuit ID and agent ID to requests',
	$pconfig['agentoption']
))->setHelp(gettext('If this is checked, the DHCP Relay will append the circuit ID (%s interface number) and the agent ID to the DHCP request.'),
    g_get('product_label'));

$servers = explode(',', $pconfig['server']);
$last = (count($servers) - 1);
foreach ($servers as $counter => $server) {
	$group = new Form_Group(($counter == 0) ? '*'.gettext('Upstream Servers') : '');
	$group->addClass('repeatable');

	$group->add(new Form_IpAddress(
		'server' . $counter,
		gettext('Upstream Server'),
		$server,
		'V4'
	))->addClass('autotrim')
          ->setHelp(($counter == $last) ? gettext('IPv4 address of the upstream server to which requests are relayed.') : '');

	$group->add(new Form_Button(
		'deleterow' . $counter,
		gettext('Delete'),
		null,
		'fa-trash'
	))->addClass('btn-warning');

	$section->add($group);
	$counter++;
}

$group = new Form_Group(null);
$button = new Form_Button(
	'addrow',
	gettext('Add Upstream Server'),
	null,
	'fa-plus'
);
$button->addClass('btn-success btn-sm addbtn');
$group->add($button);
$section->add($group);

$form->add($section);

print($form);
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Suppress "Delete" button if there are fewer than two rows
	checkLastRow();

	// Automatically remove whitespace on changes to .autotrim elements
	$('body').on('change', '.autotrim', function () {
		$(this).val($(this).val().replace(/\s/g, ''));
	});
});
//]]>
</script>

<?php
include('foot.inc');
