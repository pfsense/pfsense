<?php
/*
	services_router_advertisements.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2010 Seth Mos <seth.mos@dds.nl>
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
##|*IDENT=page-services-router-advertisements
##|*NAME=Services: Router Advertisements
##|*DESCR=Allow access to the 'Services: Router Advertisements' page.
##|*MATCH=services_router_advertisements.php*
##|-PRIV

require("guiconfig.inc");

if (!$g['services_dhcp_server_enable']) {
	header("Location: /");
	exit;
}

/*	Fix failover DHCP problem
 *	http://article.gmane.org/gmane.comp.security.firewalls.pfsense.support/18749
 */
ini_set("memory_limit", "64M");

$if = $_GET['if'];
if ($_POST['if']) {
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

if (!$_GET['if']) {
	$savemsg = "<p><b>" . gettext("The DHCPv6 Server can only be enabled on interfaces configured with static, non unique local IP addresses") . ".</b></p>" .
		"<p><b>" . gettext("Only interfaces configured with a static IP will be shown") . ".</b></p>";
}

$iflist = get_configured_interface_with_descr();

/* set the starting interface */
if (!$if || !isset($iflist[$if])) {
	foreach ($iflist as $ifent => $ifname) {
		$oc = $config['interfaces'][$ifent];
		if ((is_array($config['dhcpdv6'][$ifent]) && !isset($config['dhcpdv6'][$ifent]['enable']) && !(is_ipaddrv6($oc['ipaddrv6']) && (!is_linklocal($oc['ipaddrv6'])))) ||
		    (!is_array($config['dhcpdv6'][$ifent]) && !(is_ipaddrv6($oc['ipaddrv6']) && (!is_linklocal($oc['ipaddrv6']))))) {
			continue;
		}
		$if = $ifent;
		break;
	}
}

if (is_array($config['dhcpdv6'][$if])) {
	/* RA specific */
	$pconfig['ramode'] = $config['dhcpdv6'][$if]['ramode'];
	$pconfig['rapriority'] = $config['dhcpdv6'][$if]['rapriority'];
	if ($pconfig['rapriority'] == "") {
		$pconfig['rapriority'] = "medium";
	}
	$pconfig['rainterface'] = $config['dhcpdv6'][$if]['rainterface'];
	$pconfig['radomainsearchlist'] = $config['dhcpdv6'][$if]['radomainsearchlist'];
	list($pconfig['radns1'], $pconfig['radns2'], $pconfig['radns3']) = $config['dhcpdv6'][$if]['radnsserver'];
	$pconfig['rasamednsasdhcp6'] = isset($config['dhcpdv6'][$if]['rasamednsasdhcp6']);

	$pconfig['subnets'] = $config['dhcpdv6'][$if]['subnets']['item'];
}
if (!is_array($pconfig['subnets'])) {
	$pconfig['subnets'] = array();
}

$advertise_modes = array("disabled" => "Disabled",
	"router" => "Router Only",
	"unmanaged" => "Unmanaged",
	"managed" => "Managed",
	"assist" => "Assisted",
	"stateless_dhcp" => "Stateless DHCP");
$priority_modes = array("low" => "Low",
	"medium" => "Normal",
	"high" => "High");
$carplist = get_configured_carp_interface_list();

$subnets_help = '<span class="help-block">' . gettext("Subnets are specified in CIDR format.  " .
	"Select the CIDR mask that pertains to each entry.	" .
	"/128 specifies a single IPv6 host; /64 specifies a normal IPv6 network; etc.  " .
	"If no subnets are specified here, the Router Advertisement (RA) Daemon will advertise to the subnet to which the router's interface is assigned." .
	'</span>');

if ($_POST) {
	unset($input_errors);

	$pconfig = $_POST;

	/* input validation */

	$pconfig['subnets'] = array();
	for ($x = 0; $x < 5000; $x += 1) {
		$address = trim($_POST['subnet_address' . $x]);
		if ($address === "") {
			continue;
		}

		$bits = trim($_POST['subnet_bits' . $x]);
		if ($bits === "") {
			$bits = "128";
		}

		if (is_alias($address)) {
			$pconfig['subnets'][] = $address;
		} else {
			$pconfig['subnets'][] = $address . "/" . $bits;
			if (!is_ipaddrv6($address)) {
				$input_errors[] = sprintf(gettext("An invalid subnet or alias was specified. [%s/%s]"), $address, $bits);
			}
		}
	}

	if (($_POST['radns1'] && !is_ipaddrv6($_POST['radns1'])) || ($_POST['radns2'] && !is_ipaddrv6($_POST['radns2'])) || ($_POST['radns3'] && !is_ipaddrv6($_POST['radns3']))) {
		$input_errors[] = gettext("A valid IPv6 address must be specified for each of the DNS servers.");
	}
	if ($_POST['radomainsearchlist']) {
		$domain_array=preg_split("/[ ;]+/", $_POST['radomainsearchlist']);
		foreach ($domain_array as $curdomain) {
			if (!is_domain($curdomain)) {
				$input_errors[] = gettext("A valid domain search list must be specified.");
				break;
			}
		}
	}

	if (!$input_errors) {
		if (!is_array($config['dhcpdv6'][$if])) {
			$config['dhcpdv6'][$if] = array();
		}

		$config['dhcpdv6'][$if]['ramode'] = $_POST['ramode'];
		$config['dhcpdv6'][$if]['rapriority'] = $_POST['rapriority'];
		$config['dhcpdv6'][$if]['rainterface'] = $_POST['rainterface'];

		$config['dhcpdv6'][$if]['radomainsearchlist'] = $_POST['radomainsearchlist'];
		unset($config['dhcpdv6'][$if]['radnsserver']);
		if ($_POST['radns1']) {
			$config['dhcpdv6'][$if]['radnsserver'][] = $_POST['radns1'];
		}
		if ($_POST['radns2']) {
			$config['dhcpdv6'][$if]['radnsserver'][] = $_POST['radns2'];
		}
		if ($_POST['radns3']) {
			$config['dhcpdv6'][$if]['radnsserver'][] = $_POST['radns3'];
		}

		$config['dhcpdv6'][$if]['rasamednsasdhcp6'] = ($_POST['rasamednsasdhcp6']) ? true : false;

		if (count($pconfig['subnets'])) {
			$config['dhcpdv6'][$if]['subnets']['item'] = $pconfig['subnets'];
		} else {
			unset($config['dhcpdv6'][$if]['subnets']);
		}

		write_config();
		$retval = services_radvd_configure();
		$savemsg = get_std_save_message($retval);
	}
}

$pgtitle = array(gettext("Services"), gettext("Router Advertisements"));

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

/* active tabs */
$tab_array = array();
$tabscounter = 0;
$i = 0;
foreach ($iflist as $ifent => $ifname) {
	$oc = $config['interfaces'][$ifent];
	// We need at least one interface configured with a NON-LOCAL IPv6 static address. fd80:8dba:82e1::/64 fits the bill
	if ((is_array($config['dhcpdv6'][$ifent]) && !isset($config['dhcpdv6'][$ifent]['enable']) && !(is_ipaddrv6($oc['ipaddrv6']) && (!is_linklocal($oc['ipaddrv6'])))) ||
	    (!is_array($config['dhcpdv6'][$ifent]) && !(is_ipaddrv6($oc['ipaddrv6']) && (!is_linklocal($oc['ipaddrv6']))))) {
		continue;
	}

	if ($ifent == $if) {
		$active = true;
	} else {
		$active = false;
	}

	$tab_array[] = array($ifname, $active, "services_router_advertisements.php?if={$ifent}");
	$tabscounter++;
}

if ($tabscounter == 0) {
	include("foot.inc");
	exit;
}

display_top_tabs($tab_array);

$tab_array = array();
$tab_array[] = array(gettext("DHCPv6 Server"),		 false, "services_dhcpv6.php?if={$if}");
$tab_array[] = array(gettext("Router Advertisements"), true,  "services_router_advertisements.php?if={$if}");
display_top_tabs($tab_array, false, 'nav nav-tabs');

$form = new Form(new Form_Button(
	'Submit',
	gettext("Save")
));

$section = new Form_Section('Advertisements');

$section->addInput(new Form_Select(
	'ramode',
	'Router mode',
	$pconfig['ramode'],
	$advertise_modes
))->setHelp('Select the Operating Mode for the Router Advertisement (RA) Daemon. Use:' . '<br />' .
			'&nbsp;<strong>Router Only</strong> to only advertise this router' . '<br />' .
			'&nbsp;<strong>Unmanaged</strong> for Router Advertising with Stateless Autoconfig' . '<br />' .
			'&nbsp;<strong>Managed</strong> for assignment through a DHCPv6 Server' . '<br />' .
			'&nbsp;<strong>Assisted</strong> for DHCPv6 Server assignment combined with Stateless Autoconfig.' .
			'It is not required to activate this DHCPv6 server when set to "Managed", this can be another host on the network');

$section->addInput(new Form_Select(
	'rapriority',
	'Router priority',
	$pconfig['rapriority'],
	$priority_modes
))->setHelp('Select the Priority for the Router Advertisement (RA) Daemon.');

$carplistif = array();
if (count($carplist) > 0) {
	foreach ($carplist as $ifname => $vip) {
		if ((preg_match("/^{$if}_/", $ifname)) && (is_ipaddrv6($vip))) {
			$carplistif[$ifname] = $vip;
		}
	}
}

if (count($carplistif) > 0) {
	$list = array();

	foreach ($carplistif as $ifname => $vip) {
		$list['interface'] = strtoupper($if);
		$list[$ifname] = $ifname . ' - ' . $vip;
	}

	$section->addInput(new Form_Select(
		'rainterface',
		'RA Interface',
		$pconfig['rainterface'],
		$list
	))->setHelp('Select the Interface for the Router Advertisement (RA) Daemon.');
}

$section->addInput(new Form_StaticText(
	'RA Subnets',
	$subnets_help
));


if (empty($pconfig['subnets'])) {
	$pconfig['subnets'] = array('0' => '/128');
}

$counter = 0;
$numrows = count($pconfig['subnets']) - 1;

foreach ($pconfig['subnets'] as $subnet) {
	$address_name = "subnet_address" . $counter;
	$bits_name = "subnet_bits" . $counter;
	list($address, $subnet) = explode("/", $subnet);

	$group = new Form_Group($counter == 0 ? 'Subnets':'');

	$group->add(new Form_IpAddress(
		$address_name,
		null,
		$address
	))->addMask($bits_name, $subnet);

	$group->add(new Form_Button(
		'deleterow' . $counter,
		'Delete'
	))->removeClass('btn-primary')->addClass('btn-warning');

	$group->addClass('repeatable');

	$section->add($group);

	$counter++;
}

$section->addInput(new Form_Button(
	'addrow',
	'Add'
))->removeClass('btn-primary')->addClass('btn-success');

$form->add($section);

$section = new Form_Section('DNS Configuration');

for ($idx=1; $idx<=3; $idx++) {
	$section->addInput(new Form_IpAddress(
		'radns' . $idx,
		'Server ' . $idx,
		$pconfig['radns' . $idx]
	))->setPattern('[a-zA-Z0-9\_\.\:]+')->setHelp(($idx < 3) ? '':'Leave blank to use the system default DNS servers - this interface\'s IP if DNS Forwarder or Resolver is enabled, otherwise the servers configured on the General page');
}

$section->addInput(new Form_Input(
	'radomainsearchlist',
	'Domain search list',
	'text',
	$pconfig['radomainsearchlist']
))->setHelp('The RA server can optionally provide a domain search list. Use the semicolon character as separator ');

$section->addInput(new Form_Checkbox(
	'rasamednsasdhcp6',
	'Settings',
	'Use same settings as DHCPv6 server',
	$pconfig['rasamednsasdhcp6']
));

$section->addInput(new Form_Input(
	'if',
	null,
	'hidden',
	$if
));


$form->add($section);
print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Suppress "Delete row" button if there are fewer than two rows
	checkLastRow();

	// --------- Autocomplete -----------------------------------------------------------------------------------------
	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "openvpn", "urltable"))) ?>;

	$('#radns1, #radns2, #radns3').autocomplete({
		source: addressarray
	});

});
//]]>
</script>

<?php include("foot.inc");
