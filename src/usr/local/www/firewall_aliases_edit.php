<?php
/*
 * firewall_aliases_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-firewall-alias-edit
##|*NAME=Firewall: Alias: Edit
##|*DESCR=Allow access to the 'Firewall: Alias: Edit' page.
##|*MATCH=firewall_aliases_edit.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_Once("alias-utils.inc");

if (isset($_POST['referer'])) {
	$referer = $_POST['referer'];
} else {
	$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_aliases.php');
}

// Keywords not allowed in names, see globals.inc for list.
global $pf_reserved_keywords;

$reserved_ifs = get_configured_interface_list(true);
$pf_reserved_keywords = array_merge($pf_reserved_keywords, $reserved_ifs, $reserved_table_names);
$max_alias_addresses = 5000;

init_config_arr(array('aliases', 'alias'));
$a_aliases = &$config['aliases']['alias'];

// Debugging
if ($debug) {
	unlink_if_exists("{$g['tmp_path']}/alias_rename_log.txt");
}

$singular_types = array(
	'host'	=> gettext("host"),
	'network' => gettext("network"),
	'port' => gettext("port"),
	'url' => gettext("URL (IP)"),
	'url_ports' => gettext("URL (Port)"),
	'urltable' => gettext("URL Table (IP)"),
	'urltable_ports' => gettext("URL Table (Port)"),
);

if (isset($_REQUEST['id']) && is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

$dup = false;
if (isset($_REQUEST['dup']) && is_numericint($_REQUEST['dup'])) {
	$id = $_REQUEST['dup'];
	$dup = true;
}

if (isset($id) && $a_aliases[$id]) {
	$original_alias_name = $a_aliases[$id]['name'];
	if (!$dup) {
		$pconfig['name'] = $a_aliases[$id]['name'];
	}
	$pconfig['detail'] = $a_aliases[$id]['detail'];
	$pconfig['address'] = $a_aliases[$id]['address'];
	$pconfig['type'] = $a_aliases[$id]['type'];
	$pconfig['descr'] = html_entity_decode($a_aliases[$id]['descr']);

	if (preg_match("/urltable/i", $a_aliases[$id]['type'])) {
		$pconfig['address'] = $a_aliases[$id]['url'];
		$pconfig['updatefreq'] = $a_aliases[$id]['updatefreq'];
	}
	if ($a_aliases[$id]['aliasurl'] <> "") {
		if (is_array($a_aliases[$id]['aliasurl'])) {
			$pconfig['address'] = implode(" ", $a_aliases[$id]['aliasurl']);
		} else {
			$pconfig['address'] = $a_aliases[$id]['aliasurl'];
		}
	}
}

if ($dup) {
	unset($id);
}

if ($_POST['save']) {
	// Remember the original name on an attempt to save
	$origname = $_POST['origname'];
} else {
	// Set the original name on edit (or add, when this will be blank)
	$origname = $pconfig['name'];
}

if ($_REQUEST['exportaliases']) {
	$expdata = array_map('alias_idn_to_utf8', explode(" ", $a_aliases[$id]['address']));
	$expdata = implode("\n", $expdata);
	$expdata .= "\n";
	send_user_download('data', $expdata, "{$_POST['origname']}.txt");
}

$tab = $_REQUEST['tab'];

if (empty($tab)) {
	if (preg_match("/url/i", $pconfig['type'])) {
		$tab = 'url';
	} else if ($pconfig['type'] == 'host') {
		$tab = 'ip';
	} else {
		$tab = $pconfig['type'];
	}
}

$pgtitle = array(gettext("Firewall"), gettext("Aliases"), gettext("Edit"));
$pglinks = array("", "firewall_aliases.php?tab=" . $tab, "@self");

if ($_POST['save']) {
	$input_errors = saveAlias($_POST, $id);

	if (!$input_errors) {
		mark_subsystem_dirty('aliases');

		if (!empty($tab)) {
			header("Location: firewall_aliases.php?tab=" . htmlspecialchars ($tab));
		} else {
			header("Location: firewall_aliases.php");
		}

		exit;
	}
}

include("head.inc");

$section_str = array(
	'network' => gettext("Network(s)"),
	'host'	=> gettext("Host(s)"),
	'port' => gettext("Port(s)"),
	'url' => gettext("URL (IPs)"),
	'url_ports' => gettext("URL (Ports)"),
	'urltable' => gettext("URL Table (IPs)"),
	'urltable_ports' => gettext("URL Table (Ports)")
);

$btn_str = array(
	'network' => gettext("Add Network"),
	'host'	=> gettext("Add Host"),
	'port' => gettext("Add Port"),
	'url' => gettext("Add URL"),
	'url_ports' => gettext("Add URL"),
	'urltable' => gettext("Add URL Table"),
	'urltable_ports' => gettext("Add URL Table")
);

$label_str = array(
	'network' => gettext("Network or FQDN"),
	'host'	=> gettext("IP or FQDN"),
	'port' => gettext("Port"),
	'url' => gettext("URL (IPs)"),
	'url_ports' => gettext("URL (Ports)"),
	'urltable' => gettext("URL Table (IPs)"),
	'urltable_ports' => gettext("URL Table (Ports)")
);

$special_cidr_usage_text = gettext("The value after the \"/\" is the update frequency in days.");

$help = array(
	'network' => gettext("Networks are specified in CIDR format. Select the CIDR mask that pertains to each entry. /32 specifies a single IPv4 host, /128 specifies a single IPv6 host, /24 specifies 255.255.255.0, /64 specifies a normal IPv6 network, etc. Hostnames (FQDNs) may also be specified, using a /32 mask for IPv4 or /128 for IPv6. An IP range such as 192.168.1.1-192.168.1.254 may also be entered and a list of CIDR networks will be derived to fill the range."),
	'host' => gettext("Enter as many hosts as desired. Hosts must be specified by their IP address or fully qualified domain name (FQDN). FQDN hostnames are periodically re-resolved and updated. If multiple IPs are returned by a DNS query, all are used. An IP range such as 192.168.1.1-192.168.1.10 or a small subnet such as 192.168.1.16/28 may also be entered and a list of individual IP addresses will be generated."),
	'port' => gettext("Enter ports as desired, with a single port or port range per entry. Port ranges can be expressed by separating with a colon."),
	'url' => gettext("Enter as many URLs as desired. After saving, the URLs will be downloaded and the items imported into the alias. Use only with small sets of IP addresses (less than 3000)."),
	'url_ports' => gettext("Enter as many URLs as desired. After saving, the URLs will be downloaded and the items imported into the alias. Use only with small sets of Ports (less than 3000)."),
	'urltable' => gettext("Enter a single URL containing a large number of IPs and/or Subnets. After saving, the URLs will be downloaded and a table file containing these addresses will be created. This will work with large numbers of addresses (30,000+) or small numbers.") .
		"<br /><b>" . $special_cidr_usage_text . "</b>",
	'urltable_ports' => gettext("Enter a single URL containing a list of Port numbers and/or Port ranges. After saving, the URL will be downloaded.") .
		"<br /><b>" . $special_cidr_usage_text . "</b>",
);

// Tab type specific patterns.
// Intentionally loose (valid character check only, no pattern recognition).
// Can be tightened up with pattern recognition as desired for each tab type.
// Network and host types allow an optional CIDR following the address or an address range using dash separator,
// and there may be multiple items separated by spaces - "192.168.1.0/24 192.168.2.4-192.168.2.19"
// On submit, strings like that are parsed and expanded into the appropriate individual entries and then validated.
$pattern_str = array(
	'network'			=> '[a-zA-Z0-9_:.-]+(/[0-9]+)?( [a-zA-Z0-9_:.-]+(/[0-9]+)?)*',	// Alias Name, Host Name, IP Address, FQDN, Network or IP Address Range
	'host'				=> '[\pL0-9_:.-]+(/[0-9]+)?( [a-zA-Z0-9_:.-]+(/[0-9]+)?)*',	// Alias Name, Host Name, IP Address, FQDN
	'port'				=> '[a-zA-Z0-9_:]+',	// Alias Name, Port Number, or Port Number Range
	'url'				=> '.*',				// Alias Name or URL
	'url_ports'			=> '.*',				// Alias Name or URL
	'urltable'			=> '.*',				// Alias Name or URL
	'urltable_ports'	=> '.*'					// Alias Name or URL
);

$title_str = array(
	'network'			=> 'An IPv4 network address like 1.2.3.0, an IPv6 network address like 1:2a:3b:ffff::0, IP address range, FQDN or an alias',
	'host'				=> 'An IPv4 address like 1.2.3.4, an IPv6 address like 1:2a:3b:ffff::1, IP address range, FQDN or an alias',
	'port'				=> 'A port number, port number range or an alias',
	'url'				=> 'URL',
	'url_ports'			=> 'URL',
	'urltable'			=> 'URL',
	'urltable_ports'	=> 'URL'
);

$placeholder_str = array(
	'network'			=> 'Address',
	'host'				=> 'Address',
	'port'				=> 'Port',
	'url'				=> 'URL',
	'url_ports'			=> 'URL',
	'urltable'			=> 'URL',
	'urltable_ports'	=> 'URL'
);

$types = array(
	'host'	=> gettext("Host(s)"),
	'network' => gettext("Network(s)"),
	'port' => gettext("Port(s)"),
	'url' => gettext("URL (IPs)"),
	'url_ports' => gettext("URL (Ports)"),
	'urltable' => gettext("URL Table (IPs)"),
	'urltable_ports' => gettext("URL Table (Ports)"),
);

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form;

$form->addGlobal(new Form_Input(
	'tab',
	null,
	'hidden',
	$tab
));

$form->addGlobal(new Form_Input(
	'origname',
	null,
	'hidden',
	$origname
));

if (isset($id) && $a_aliases[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$section = new Form_Section('Properties');

// Experiment: Pre-pending the input title/label with '*' causes the element-required class to be added to the label
// which adds text decoration to indicate this is a required field. See pfSense.css
$section->addInput(new Form_Input(
	'name',
	'*Name',
	'text',
	$pconfig['name']
))->setPattern('[a-zA-Z0-9_]+')->setHelp('The name of the alias may only consist '.
	'of the characters "a-z, A-Z, 0-9 and _".');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$section->addInput(new Form_Select(
	'type',
	'*Type',
	isset($pconfig['type']) ? $pconfig['type'] : $tab,
	$types
));

$form->add($section);

$section = new Form_Section($section_str[$tab]);
// Make somewhere to park the help text, and give it a class so we can update it later
$section->addInput(new Form_StaticText(
	'Hint',
	'<span class="helptext">' . $help[$tab] . '</span>'
));

// If no addresses have been defined, we'll make up a blank set
if ($pconfig['address'] == "") {
	$pconfig['address'] = '';
	$pconfig['address_subnet'] = '';
	$pconfig['detail'] = '';
}

$counter = 0;
$addresses = explode(" ", $pconfig['address']);
$details = explode("||", $pconfig['detail']);

while ($counter < count($addresses)) {
	if (($pconfig['type'] != "host") && is_subnet($addresses[$counter])) {
		list($address, $address_subnet) = explode("/", $addresses[$counter]);
	} else {
		$address = $addresses[$counter];
		if (isset($pconfig['updatefreq'])) {
			// Note: There is only 1 updatefreq possible.
			// The alias types that use updatefreq only allow a single entry.
			$address_subnet = $pconfig['updatefreq'];
		} else {
			$address_subnet = "";
		}
	}

	$group = new Form_Group($counter == 0 ? $label_str[$tab]:'');
	$group->addClass('repeatable');

	$group->add(new Form_IpAddress(
		'address' . $counter,
		'Address',
		alias_idn_to_utf8($address),
		'ALIASV4V6'
	))->addMask('address_subnet' . $counter, $address_subnet)->setWidth(4)->setPattern($pattern_str[$tab]);

	$group->add(new Form_Input(
		'detail' . $counter,
		'Description',
		'text',
		$details[$counter]
	))->setWidth(4);

	$group->add(new Form_Button(
		'deleterow' . $counter,
		'Delete',
		null,
		'fa-trash'
	))->addClass('btn-warning');

	$section->add($group);
	$counter++;
}

if ((isset($id) && $a_aliases[$id]) && !preg_match("/url/i", $pconfig['type'])) {
	$form->addGlobal(new Form_Button(
		'exportaliases',
		'Export to file',
		null,
		'fa-download'
	))->addClass('btn-primary');
}

$form->addGlobal(new Form_Button(
	'addrow',
	$btn_str[$tab],
	null,
	'fa-plus'
))->addClass('btn-success addbtn');

$form->add($section);

print $form;
?>

<script type="text/javascript">
//<![CDATA[
addressarray = <?= json_encode(array_exclude($pconfig['name'], get_alias_list($pconfig['type']))) ?>;

events.push(function() {

	var disable_subnets;

	function typechange() {
		var tab = $('#type').find('option:selected').val();

		disable_subnets = (tab == 'host') || (tab == 'port') || (tab == 'url') || (tab == 'url_ports');

		// Enable/disable address_subnet so its value gets POSTed or not, as appropriate.
		$("[id^='address_subnet']").prop("disabled", disable_subnets);

		// Show or hide the slash plus address_subnet field so the user does not even see it if it is not relevant.
		hideMask('address_subnet', disable_subnets);

		// Set the help text to match the tab
		var helparray = <?=json_encode($help);?>;
		$('.helptext').html(helparray[tab]);

		// Set the section heading by tab type
		var sectionstr = <?=json_encode($section_str);?>;
		$('.panel-title:last').text(sectionstr[tab]);

		var buttonstr = <?=json_encode($btn_str);?>;
		$('.btn-success').prop('value', buttonstr[tab]);
		$('.btn-success').html('<i class="fa fa-plus icon-embed-btn"></i>' + buttonstr[tab]);

		// Set the input field label by tab
		var labelstr = <?=json_encode($label_str);?>;
		$('.repeatable:first').find('label').text(labelstr[tab]);

		// Set the input field pattern by tab type
		var patternstr = <?=json_encode($pattern_str);?>;
		var titlestr = <?=json_encode($title_str);?>;
		var placeholderstr = <?=json_encode($placeholder_str);?>;
		$("[id^='address']").each(function () {
			if (/^address[0-9]+$/.test(this.id)) {
				$('#' + this.id).prop('pattern', patternstr[tab]);
				$('#' + this.id).prop('title', titlestr[tab]);
				$('#' + this.id).prop('placeholder', placeholderstr[tab]);
			}
		});

		// Hide and disable rows other than the first
		hideRowsAfter(1, (tab == 'urltable') || (tab == 'urltable_ports'));

		// The add button and delete buttons must not show on URL Table IP or URL table ports
		if ((tab == 'urltable') || (tab == 'urltable_ports')) {
			hideClass('addbtn', true);
			$('[id^=deleterow]').hide();
		} else {
			hideClass('addbtn', false);
			$('[id^=deleterow]').show();
		}
	}

	// Hide and disable all rows >= that specified
	function hideRowsAfter(row, hide) {
		var idx = 0;

		$('.repeatable').each(function(el) {
			if (idx >= row) {
				hideRow(idx, hide);
			}

			idx++;
		});
	}

	function hideRow(row, hide) {
		if (hide) {
			$('#deleterow' + row).parent('div').parent().addClass('hidden');
		} else {
			$('#deleterow' + row).parent('div').parent().removeClass('hidden');
		}

		// We need to disable the elements so they are not submitted in the POST
		$('#address' + row).prop("disabled", hide);
		$('#address_subnet' + row).prop("disabled", hide || disable_subnets);
		$('#detail' + row).prop("disabled", hide);
		$('#deleterow' + row).prop("disabled", hide);
	}

	// On load . .
	typechange();

	// Suppress "Delete row" button if there are fewer than two rows
	checkLastRow();

	// Autocomplete
	$('[id^=address]').each(function() {
		if (this.id.substring(0, 8) != "address_") {
			$(this).autocomplete({
				source: addressarray
			});
		}
	});

	// on click . .
	$('#type').on('change', function() {
		typechange();
	});

});
//]]>
</script>

<?php
include("foot.inc");
