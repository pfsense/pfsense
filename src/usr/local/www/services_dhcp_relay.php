<?php
/*
 * services_dhcp_relay.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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

require_once("guiconfig.inc");
require_once("filter.inc");
$pconfig['enable'] = config_path_enabled('dhcrelay');

if (empty(config_get_path('dhcrelay/interface'))) {
	$pconfig['interface'] = array();
} else {
	$pconfig['interface'] = explode(",", config_get_path('dhcrelay/interface'));
}

$pconfig['agentoption'] = config_path_enabled('dhcrelay', 'agentoption');
$pconfig['server'] = config_path_enabled('dhcrelay', 'server') ? config_get_path('dhcrelay/server') : null;
$pconfig['carpstatusvip'] = config_path_enabled('dhcrelay', 'carpstatusvip') ? config_get_path('dhcrelay/carpstatusvip') : 'none';

$iflist = array_intersect_key(
	get_configured_interface_with_descr(),
	array_flip(
		array_filter(
			array_keys(get_configured_interface_with_descr()),
			function($if) {
				return (get_interface_ip($if) &&
				    !is_pseudo_interface(convert_friendly_interface_to_real_interface_name($if)));
			}
		)
	)
);

$carpiflist = array_merge(array('none' => 'none'), array_intersect_key(
       	get_configured_vip_list_with_descr('inet', VIP_CARP),
	array_flip(
		array_filter(
			array_keys(get_configured_vip_list_with_descr('inet', VIP_CARP)),
			function($if) {
				return (get_interface_ip($if) &&
				    !is_pseudo_interface(convert_friendly_interface_to_real_interface_name($if)));
			}
		)
	)
));

/*   set the enabled flag which will tell us if DHCP server is enabled
 *   on any interface.   We will use this to disable dhcp-relay since
 *   the two are not compatible with each other.
 */
$dhcpd_enabled = false;
foreach (config_get_path('dhcpd', []) as $dhcpif => $dhcp) {
	if (empty($dhcp)) {
		continue;
	}
	if (isset($dhcp['enable']) && config_path_enabled("interfaces/{$dhcpif}")) {
		$dhcpd_enabled = true;
		break;
	}
}

if ($_POST) {

	unset($input_errors);

	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = explode(' ', 'interface');
		$reqdfieldsn = array(gettext('Interface'));

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
	}

	$svrlist = '';
	for ($idx=0; $idx<count($_POST); $idx++) {
		if ($_POST['server' . $idx]) {
			if (!empty($_POST['server' . $idx])) { // Filter out any empties
				if (!is_ipaddrv4($_POST['server' . $idx])) {
					$input_errors[] = sprintf(gettext('Upstream Server address %s is not a valid IPv4 address.'), $_POST['server' . $idx]);
				}

				if (!empty($svrlist)) {
					$svrlist .= ',';
				}

				$svrlist .= $_POST['server' . $idx];
			}
		}
	}

	// Check that the user input something in one of the Destination Server fields
	if (empty($svrlist) && $_POST['enable']) {
		$input_errors[] = gettext('At least one Upstream Server must be specified.');
	}

	// Now $svrlist is a comma separated list of servers ready to save to the config system
	$pconfig['server'] = $svrlist;

	if (!$input_errors) {
		config_init_path('dhcrelay');
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
$shortcut_section = 'dhcp-relay';
include('head.inc');

if ($dhcpd_enabled) {
	print_info_box(gettext('DHCP Relay cannot be enabled while DHCP Server is enabled on any interface.'), 'danger', false);
}

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied && !$dhcpd_enabled) {
	print_apply_result_box($retval);
}

$form = new Form;

$section = new Form_Section(gettext('DHCP Relay Configuration'));

$group = new Form_Group(gettext('Enable'));
$enable_cb = new Form_Checkbox(
	'enable',
	gettext('Enable'),
	gettext('Enable DHCP Relay'),
	($dhcpd_enabled ? false : $pconfig['enable'])
);

if ($dhcpd_enabled) {
	$enable_cb->setAttribute('disabled', true);
}

$group->add($enable_cb);
$section->add($group);

$section->addInput(new Form_Select(
	'interface',
	'*'.gettext('Downstream Interfaces'),
	$pconfig['interface'],
	$iflist,
	true
))->setHelp(gettext('Interfaces without an IPv4 address will not be shown.'));

$section->addInput(new Form_Select(
	'carpstatusvip',
	'*'.gettext('CARP Status VIP'),
	$pconfig['carpstatusvip'],
	$carpiflist,
))->setHelp(gettext('DHCP Relay will be stopped when the ' .
	    'chosen VIP is in BACKUP status, and started in MASTER status.'));

$section->addInput(new Form_Checkbox(
	'agentoption',
	null,
	gettext('Append circuit ID and agent ID to requests'),
	$pconfig['agentoption']
))->setHelp(gettext('Append the circuit ID (interface number) and the agent ID to the DHCP request.'));

$counter = 0;
foreach (explode(',', $pconfig['server']) as $server) {
	$group = new Form_Group($counter == 0 ? '*' . gettext('Upstream Servers') : '');
	$group->addClass('repeatable');

	$group->add(new Form_IpAddress(
		'server' . $counter,
		gettext('Upstream Server'),
		$server,
		'V4'
	))->addClass('autotrim');

	$group->add(new Form_Button(
		'deleterow' . $counter,
		gettext('Delete'),
		null,
		'fa-solid fa-trash-can'
	))->addClass('btn-sm btn-warning');

	$section->add($group);
	$counter++;
}

$group = new Form_Group(null);
$group->add(new Form_Button(
	'addrow',
	gettext('Add Upstream Server'),
	null,
	'fa-solid fa-plus'
))->addClass('btn-success addbtn')
  ->setHelp(gettext('The IPv4 addresses of the servers to which DHCP requests are relayed.'));
$section->add($group);

$form->add($section);

print $form;
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Suppress "Delete row" button if there are fewer than two rows
	checkLastRow();
});
//]]>
</script>

<?php
include("foot.inc");
