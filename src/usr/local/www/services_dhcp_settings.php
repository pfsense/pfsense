<?php
/*
 * services_dhcp_settings.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-dhcpserver-settings
##|*NAME=Services: DHCP Server: Settings
##|*DESCR=Allow access to the 'Services: DHCP Server: Settings' page.
##|*MATCH=services_dhcp_settings.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("filter.inc");
require_once('rrd.inc');
require_once("shaper.inc");
require_once("util.inc");
require_once('services_dhcp.inc');

if (!g_get('services_dhcp_server_enable')) {
	header("Location: /");
	exit;
}

if (dhcp_is_backend('isc')) {
	header('Location: /services_dhcp.php');
	exit;
}

config_init_path('kea');
$pconfig = config_get_path('kea', []);

$iflist = get_configured_interface_with_descr();
$subnets = kea_build_subnet_list();

if ($_POST['apply']) {
	$changes_applied = true;
	$retval = dhcp_apply_changes();
} elseif ($_POST['act']) {
	switch($_POST['act']) {
	case 'save':
		[$input_errors, $pconfig] = dhcp_do_settings_post($subnets);
		$subnets = kea_build_subnet_list(); /* refresh subnet list */
		break;
	default:
		header('Location: /services_dhcp_settings.php');
		exit;
	}
}

$pgtitle = array(gettext('Services'), gettext('DHCP Server'), gettext('Settings'));
$pglinks = array('', '@self', '@self');

$shortcut_section = 'dhcp';
if (dhcp_is_backend('kea')) {
	$shortcut_section = 'kea-dhcp4';
}

include('head.inc');

if (config_path_enabled('dhcrelay')) {
	print_info_box(gettext('DHCP Relay is currently enabled. DHCP Server canot be enabled while the DHCP Relay is enabled on any interface.'), 'danger', false);
}

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('dhcpd')) {
	print_apply_box(gettext('The DHCP Server configuration has changed.') . '<br />' . gettext('The changes must be applied for them to take effect.'));
}

/* active tabs */
$tab_array = array();
$tabscounter = 0;
$i = 0;
$have_small_subnet = false;

$tab_array[] = [gettext('Settings'), true, 'services_dhcp_settings.php'];

foreach ($iflist as $ifent => $ifname) {
	$oc = config_get_path("interfaces/{$ifent}");

	/* Not static IPv4 or subnet >= 31 */
	if ($oc['subnet'] >= 31) {
		$have_small_subnet = true;
		$example_name = $ifname;
		$example_cidr = $oc['subnet'];
		continue;
	}
	if (!is_ipaddrv4($oc['ipaddr']) || empty($oc['subnet'])) {
		continue;
	}

	$tab_array[] = array($ifname, false, "services_dhcp.php?if={$ifent}");
	$tabscounter++;
}

if ($tabscounter === 0) {
	print_info_box(gettext('DHCP Server can only be enabled on interfaces configured with a static IPv4 address. This system has none.'), 'danger', false);
}

display_top_tabs($tab_array);

$form = new Form();

$section = new Form_Section(gettext('High Availability'));

$section->addInput(new Form_Checkbox(
	'ha_enable',
	gettext('Enable'),
	gettext('Enable high availability'),
	array_path_enabled($pconfig, 'ha'),
))->setHelp(gettext('Enable hot-standby high availability (HA) for Kea DHCP services.'));

$group = new Form_Group(gettext('Node Role'));

$group->add(new Form_Checkbox(
	'ha_role',
	null,
	gettext('Primary'),
	(array_get_path($pconfig, 'ha/role', 'primary') === 'primary'),
	'primary'
))->displayAsRadio()
  ->setWidth(2);

$group->add(new Form_Checkbox(
	'ha_role',
	null,
	gettext('Standby'),
	(array_get_path($pconfig, 'ha/role', 'primary') === 'standby'),
	'standby'
))->displayAsRadio()
  ->setWidth(2);

$group->setHelp(gettext('Only one node should be configured as primary.'));

$section->add($group);

$section->addInput(new Form_Input(
	'ha_localname',
	gettext('Local Name'),
	'text',
	array_get_path($pconfig, 'ha/localname'),
	['placeholder' => kea_defaults('name')]
))->setHelp(gettext('Name of the local instance. This value must be hostname-like.'));

$group = new Form_Group(gettext('Local Address'));

$group->add(new Form_IpAddress(
	'ha_localip',
	null,
	array_get_path($pconfig, 'ha/localip'),
	'BOTH'
))->addClass('autotrim')
  ->setWidth(3)
  ->setHelp(gettext('Listening address of the local instance.'));

$group->add(new Form_Input(
	'ha_localport',
	null,
	'number',
	array_get_path($pconfig, 'ha/localport'),
	['min' => 1, 'max' => 65535, 'placeholder' => kea_defaults('listenport')]
))->addClass('autotrim')
  ->setWidth(2)
  ->setHelp(gettext('Port'));

$section->add($group);

$section->addInput(new Form_Input(
	'ha_remotename',
	gettext('Remote Name'),
	'text',
	array_get_path($pconfig, 'ha/remotename'),
))->setHelp(gettext('Name of the remote instance. This value must be hostname-like.'));

$group = new Form_Group(gettext('Remote Address'));

$group->add(new Form_IpAddress(
	'ha_remoteip',
	null,
	array_get_path($pconfig, 'ha/remoteip'),
	'BOTH'
))->addClass('autotrim')
  ->setWidth(3)
  ->setHelp(gettext('Listening address of the remote instance.'));

$group->add(new Form_Input(
	'ha_remoteport',
	null,
	'number',
	array_get_path($pconfig, 'ha/remoteport'),
	['min' => 1, 'max' => 65535, 'placeholder' => kea_defaults('listenport')]
))->addClass('autotrim')
  ->setWidth(2)
  ->setHelp(gettext('Port'));

$section->add($group);

$btnadv = new Form_Button(
	'btnadvopts',
	gettext('Display Advanced'),
	null,
	'fa-solid fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	gettext('Advanced Options'),
	$btnadv
))->setHelp(gettext('Additional parameters that control the behavior of Kea HA.'));

$section->addInput(new Form_Input(
	'ha_heartbeatdelay',
	gettext('Heartbeat Delay'),
	'number',
	array_get_path($pconfig, 'ha/heartbeatdelay'),
	['placeholder' => kea_defaults('heartbeatdelay')]
))->addClass('advopt')
  ->setHelp(sprintf(gettext('Specifies a duration in milliseconds between sending the last heartbeat and the next heartbeat.%s' .
			'The heartbeats are sent periodically to gather the status of the partner and to verify whether the partner is still operating.'), '<br/>'));

$section->addInput(new Form_Input(
	'ha_maxresponsedelay',
	gettext('Max Response Delay'),
	'number',
	array_get_path($pconfig, 'ha/maxresponsedelay'),
	['placeholder' => kea_defaults('maxresponsedelay')]
))->addClass('advopt')
  ->setHelp(sprintf(gettext('Specifies a duration in milliseconds since the last successful communication with the partner, after which the server assumes that communication with the partner is interrupted.%s' .
			'Notice: This duration should be greater than the heartbeat delay.'), '<br/>'));

$section->addInput(new Form_Input(
	'ha_maxackdelay',
	gettext('Max Ack Delay'),
	'number',
	array_get_path($pconfig, 'ha/maxackdelay'),
	['placeholder' => kea_defaults('maxackdelay')]
))->addClass('advopt')
  ->setHelp(gettext('Specifies the maximum time in milliseconds for the client to try to communicate with the DHCP server, after which this server assumes that the client failed to communicate with the DHCP server (is “unacked”).'));

$section->addInput(new Form_Input(
	'ha_maxunackedclients',
	gettext('Max Unacked Clients'),
	'number',
	array_get_path($pconfig, 'ha/maxunackedclients'),
	['min' => 0, 'placeholder' => kea_defaults('maxunackedclients')]
))->addClass('advopt')
  ->setHelp(gettext('Specifies how many “unacked” clients are allowed (see max-ack-delay) before this server assumes that the partner is offline and transitions to the partner-down state.'));

$section->addInput(new Form_Input(
	'ha_maxrejectedleaseupdates',
	gettext('Max Rejected Updates'),
	'number',
	array_get_path($pconfig, 'ha/maxrejectedleaseupdates'),
	['placeholder' => kea_defaults('maxrejectedleaseupdates')]
))->addClass('advopt')
  ->setHelp(gettext('Specifies how many lease updates for distinct clients can fail, due to a conflict between the lease and the partner configuration or state, before the server transitions to the terminated state.'));

$section->addInput(new Form_StaticText(
	gettext('TLS Transport'),
	gettext('TLS transport settings are not synchronized and must be configured manually on both the local and remote instance.')
));

$section->addInput(new Form_Checkbox(
	'ha_tls',
	gettext('Enable'),
	gettext('Enable TLS transport'),
	array_path_enabled($pconfig, 'ha', 'tls'),
))->setHelp(gettext('When checked, TLS transport will be used to secure Kea HA heart beat and lease syncronization traffic.'));

$section->addInput(new Form_Select(
	'ha_scertref',
	gettext('Server Certificate'),
	array_get_path($pconfig, 'ha/scertref'),
	kea_server_cert_list()
))->setHelp(gettext('Certificates known to be incompatible with use for HTTPS are not included in this list, such as certificates ' .
				    'using incompatible ECDSA curves or weak digest algorithms.'));

$section->addInput(new Form_Checkbox(
	'ha_mutualtls',
	gettext('Mutual TLS'),
	gettext('Enable mutual TLS'),
	array_path_enabled($pconfig, 'ha', 'mutualtls'),
))->setHelp(gettext('When checked, the Kea HA client will offer a client certificate to the remote instance for mutual verification.'));

$section->addInput(new Form_Select(
	'ha_ccertref',
	gettext('Client Certificate'),
	array_get_path($pconfig, 'ha/ccertref'),
	kea_client_cert_list()
))->setHelp(gettext('Certificates known to be incompatible with use for HTTPS are not included in this list, such as certificates ' .
				    'using incompatible ECDSA curves or weak digest algorithms.'));

$form->add($section);

$form->addGlobal(new Form_Input(
	'act',
	'',
	'hidden',
	'save'
));

print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$('#btnadvopts').data('hide', false);

	function show_advopts(ispageload) {
		var hide = !$('#btnadvopts').data('hide');

		if (ispageload) {
			$('.advopt').each(function() {
				if ($(this).val().trim().length) {
					hide = false;
					return false;
				}
			});
		}

		$('.advopt').each(function() {
			hideInput($(this).prop('id'), hide);
		});

		$('#btnadvopts').data('hide', hide);

		var text = "<?=gettext('Hide Advanced')?>";
		if (hide) {
			text = "<?=gettext('Display Advanced')?>";
		}

		var children = $('#btnadvopts').children();
		$('#btnadvopts').text(text).prepend(children);
	}

	function update_tls_section() {
		var tlsshow = $('#ha_tls').prop('checked');
		var mtlsshow = $('#ha_mutualtls').prop('checked');

		hideInput('ha_scertref', !tlsshow);
		hideInput('ha_ccertref', !(tlsshow && mtlsshow));
		hideCheckbox('ha_mutualtls', !tlsshow);
	}

	$('#ha_tls').on('change', function() {
		update_tls_section();
	});

	$('#ha_mutualtls').on('change', function() {
		update_tls_section();
	});

	$('#btnadvopts').on('click', function() {
		show_advopts(false);
	});

	update_tls_section();
	show_advopts(true);
});
//]]>
</script>

<?php
include('foot.inc');
