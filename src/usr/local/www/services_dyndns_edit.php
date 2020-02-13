<?php
/*
 * services_dyndns_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-dynamicdnsclient
##|*NAME=Services: Dynamic DNS client
##|*DESCR=Allow access to the 'Services: Dynamic DNS client' page.
##|*MATCH=services_dyndns_edit.php*
##|-PRIV

/* returns true if $uname is a valid DynDNS username */
function is_dyndns_username($uname) {
	if (!is_string($uname)) {
		return false;
	}

	if (preg_match("/[^a-z0-9\-\+.@_:]/i", $uname)) {
		return false;
	} else {
		return true;
	}
}

require_once("guiconfig.inc");

init_config_arr(array('dyndnses', 'dyndns'));
$a_dyndns = &$config['dyndnses']['dyndns'];

$id = $_REQUEST['id'];

if (isset($id) && isset($a_dyndns[$id])) {
	$pconfig['username'] = $a_dyndns[$id]['username'];
	$pconfig['password'] = $a_dyndns[$id]['password'];
	$pconfig['host'] = $a_dyndns[$id]['host'];
	$pconfig['domainname'] = $a_dyndns[$id]['domainname'];
	$pconfig['mx'] = $a_dyndns[$id]['mx'];
	$pconfig['type'] = $a_dyndns[$id]['type'];
	$pconfig['enable'] = !isset($a_dyndns[$id]['enable']);
	$pconfig['interface'] = $a_dyndns[$id]['interface'];
	$pconfig['wildcard'] = isset($a_dyndns[$id]['wildcard']);
	$pconfig['proxied'] = isset($a_dyndns[$id]['proxied']);
	$pconfig['verboselog'] = isset($a_dyndns[$id]['verboselog']);
	$pconfig['curl_ipresolve_v4'] = isset($a_dyndns[$id]['curl_ipresolve_v4']);
	$pconfig['curl_ssl_verifypeer'] = isset($a_dyndns[$id]['curl_ssl_verifypeer']);
	$pconfig['zoneid'] = $a_dyndns[$id]['zoneid'];
	$pconfig['ttl'] = $a_dyndns[$id]['ttl'];
	$pconfig['updateurl'] = $a_dyndns[$id]['updateurl'];
	$pconfig['resultmatch'] = $a_dyndns[$id]['resultmatch'];
	$pconfig['requestif'] = $a_dyndns[$id]['requestif'];
	$pconfig['descr'] = $a_dyndns[$id]['descr'];
}

if ($_POST['save'] || $_POST['force']) {
	global $dyndns_split_domain_types;
	unset($input_errors);
	$pconfig = $_POST;

	if (($pconfig['type'] == "freedns" || $pconfig['type'] == "freedns-v6" || $pconfig['type'] == "namecheap" || $pconfig['type'] == "digitalocean" || $pconfig['type'] == "digitalocean-v6" || $pconfig['type'] == "linode" || $pconfig['type'] == "linode-v6" || $pconfig['type'] == "gandi-livedns")
	    && $_POST['username'] == "") {
		$_POST['username'] = "none";
	}

	/* input validation */
	$reqdfields = array();
	$reqdfieldsn = array();
	$reqdfields = array("type");
	$reqdfieldsn = array(gettext("Service type"));

	if ($pconfig['type'] != "custom" && $pconfig['type'] != "custom-v6") {
		if ($pconfig['type'] != "dnsomatic") {
			$reqdfields[] = "host";
			$reqdfieldsn[] = gettext("Hostname");
		}
		$reqdfields[] = "passwordfld";
		$reqdfieldsn[] = gettext("Password");
		$reqdfields[] = "username";
		$reqdfieldsn[] = gettext("Username");
		if (in_array($pconfig['type'], $dyndns_split_domain_types)) {
			$reqdfields[] = "domainname";
			$reqdfieldsn[] = gettext("Domain name");
		}
	} else {
		$reqdfields[] = "updateurl";
		$reqdfieldsn[] = gettext("Update URL");
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['passwordfld'] != $_POST['passwordfld_confirm']) {
		$input_errors[] = gettext("Password and confirmed password must match.");
	}

	if (isset($_POST['host']) && in_array("host", $reqdfields)) {
		$allow_wildcard = false;
		/* Namecheap can have a @. and *. in hostname */
		if ($pconfig['type'] == "namecheap" && ($_POST['host'] == '*.' || $_POST['host'] == '*' || $_POST['host'] == '@.' || $_POST['host'] == '@')) {
			$host_to_check = $_POST['domainname'];
		} elseif (($pconfig['type'] == "cloudflare") || ($pconfig['type'] == "cloudflare-v6")) {
			$host_to_check = $_POST['host'] == '@' ? $_POST['domainname'] : ( $_POST['host'] . '.' . $_POST['domainname'] );
			$allow_wildcard = true;
		} elseif ((($pconfig['type'] == "godaddy") || ($pconfig['type'] == "godaddy-v6")) && ($_POST['host'] == '@.' || $_POST['host'] == '@')) {
			$host_to_check = $_POST['domainname'];
		} elseif (($pconfig['type'] == "digitalocean" || $pconfig['type'] == "digitalocean-v6" || $pconfig['type'] == "gandi-livedns") && ($_POST['host'] == '@.' || $_POST['host'] == '@')) {
			$host_to_check = $_POST['domainname'];
		} elseif (($pconfig['type'] == "linode") || ($pconfig['type'] == "linode-v6")) {
			$host_to_check = $_POST['host'] == '@' ? $_POST['domainname'] : ( $_POST['host'] . '.' . $_POST['domainname'] );
			$allow_wildcard = true;
		} elseif (($pconfig['type'] == "route53") || ($pconfig['type'] == "route53-v6")) {
			$host_to_check = $_POST['host'];
			$allow_wildcard = true;
		} elseif ($pconfig['type'] == "hover") {
			/* hover allows hostnames '@' and '*' also */
			if ((strcmp("@", $_POST['host']) == 0) || (strcmp("*", $_POST['host']) == 0)) {
				$host_to_check = $_POST['domainname'];
			} else {
				$host_to_check = $_POST['host'] . '.' . $_POST['domainname'];
			}
		} else {
			$host_to_check = $_POST['host'];

			/* No-ip can have a @ in hostname */
			if (substr($pconfig['type'], 0, 4) == "noip") {
				$last_to_check = strrpos($host_to_check, '@');
				if ($last_to_check !== false) {
					$host_to_check = substr_replace(
						$host_to_check, '.', $last_to_check, 1);
				}
				unset($last_to_check);
			}
		}

		if ($pconfig['type'] != "custom" && $pconfig['type'] != "custom-v6") {
			if (!is_domain($host_to_check, $allow_wildcard)) {
				$input_errors[] = gettext("The hostname contains invalid characters.");
			}
		}

		unset($host_to_check);
	}
	if (($_POST['mx'] && !is_domain($_POST['mx']))) {
		$input_errors[] = gettext("The MX contains invalid characters.");
	}
	if ((in_array("username", $reqdfields) && $_POST['username'] && !is_dyndns_username($_POST['username'])) || ((in_array("username", $reqdfields)) && ($_POST['username'] == ""))) {
		$input_errors[] = gettext("The username contains invalid characters.");
	}

	if (!$input_errors) {
		$dyndns = array();
		$dyndns['type'] = $_POST['type'];
		$dyndns['username'] = $_POST['username'];
		if ($_POST['passwordfld'] != DMYPWD) {
			$dyndns['password'] = base64_encode($_POST['passwordfld']);
		} else {
			$dyndns['password'] = $a_dyndns[$id]['password'];;
		}
		$dyndns['host'] = $_POST['host'];
		$dyndns['domainname'] = $_POST['domainname'];
		$dyndns['mx'] = $_POST['mx'];
		$dyndns['wildcard'] = $_POST['wildcard'] ? true : false;
		$dyndns['proxied'] = $_POST['proxied'] ? true : false;
		$dyndns['verboselog'] = $_POST['verboselog'] ? true : false;
		$dyndns['curl_ipresolve_v4'] = $_POST['curl_ipresolve_v4'] ? true : false;
		$dyndns['curl_ssl_verifypeer'] = $_POST['curl_ssl_verifypeer'] ? true : false;
		// In this place enable means disabled
		if ($_POST['enable']) {
			unset($dyndns['enable']);
		} else {
			$dyndns['enable'] = true;
		}
		$dyndns['interface'] = $_POST['interface'];
		$dyndns['zoneid'] = $_POST['zoneid'];
		$dyndns['ttl'] = $_POST['ttl'];
		$dyndns['updateurl'] = $_POST['updateurl'];
		// Trim hard-to-type but sometimes returned characters
		$dyndns['resultmatch'] = trim($_POST['resultmatch'], "\t\n\r");
		($dyndns['type'] == "custom" || $dyndns['type'] == "custom-v6") ? $dyndns['requestif'] = $_POST['requestif'] : $dyndns['requestif'] = $_POST['interface'];
		$dyndns['descr'] = $_POST['descr'];
		$dyndns['force'] = isset($_POST['force']);

		if ($dyndns['username'] == "none") {
			$dyndns['username'] = "";
		}

		if (isset($id) && $a_dyndns[$id]) {
			$a_dyndns[$id] = $dyndns;
		} else {
			$a_dyndns[] = $dyndns;
			$id = count($a_dyndns) - 1;
		}

		$dyndns['id'] = $id;
		//Probably overkill, but its better to be safe
		for ($i = 0; $i < count($a_dyndns); $i++) {
			$a_dyndns[$i]['id'] = $i;
		}

		write_config(gettext("Dynamic DNS client configured."));

		services_dyndns_configure_client($dyndns);

		header("Location: services_dyndns.php");
		exit;
	}
}

function build_type_list() {
	$types = explode(",", DYNDNS_PROVIDER_DESCRIPTIONS);
	$vals = explode(" ", DYNDNS_PROVIDER_VALUES);
	$typelist = array();

	for ($j = 0; $j < count($vals); $j++) {
		$typelist[$vals[$j]] = htmlspecialchars($types[$j]);
	}

	return($typelist);
}

function build_if_list() {
	$list = array();

	$iflist = get_configured_interface_with_descr();

	foreach ($iflist as $if => $ifdesc) {
		$list[$if] = $ifdesc;
	}

	unset($iflist);

	$grouplist = return_gateway_groups_array();

	foreach ($grouplist as $name => $group) {
		$list[$name] = 'GW Group ' . $name;
	}

	unset($grouplist);

	return($list);
}

$pgtitle = array(gettext("Services"), gettext("Dynamic DNS"), gettext("Dynamic DNS Clients"), gettext("Edit"));
$pglinks = array("", "services_dyndns.php", "services_dyndns.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form;

$section = new Form_Section('Dynamic DNS Client');

// Confusingly the 'enable' checkbox is labelled 'Disable', but thats the way it works!
// No action (hide or disable) is taken on selecting this.
$section->addInput(new Form_Checkbox(
	'enable',
	'Disable',
	'Disable this client',
	$pconfig['enable']
));

$section->addInput(new Form_Select(
	'type',
	'*Service Type',
	$pconfig['type'],
	build_type_list()
));

$interfacelist = build_if_list();

$section->addInput(new Form_Select(
	'interface',
	'*Interface to monitor',
	$pconfig['interface'],
	$interfacelist
))->setHelp('If the interface IP address is private the public IP address will be fetched and used instead.');

$section->addInput(new Form_Select(
	'requestif',
	'*Interface to send update from',
	$pconfig['requestif'],
	$interfacelist
))->setHelp('This is almost always the same as the Interface to Monitor. ');

$group = new Form_Group('*Hostname');

$group->add(new Form_Input(
	'host',
	'Hostname',
	'text',
	$pconfig['host']
));
$group->add(new Form_Input(
	'domainname',
	'Domain name',
	'text',
	$pconfig['domainname']
));

$group->setHelp('Enter the complete fully qualified domain name. Example: myhost.dyndns.org%1$s' .
			'DNS Made Easy: Dynamic DNS ID (NOT hostname)%1$s' .
			'he.net tunnelbroker: Enter the tunnel ID.%1$s' .
			'GleSYS: Enter the record ID.%1$s' .
			'DNSimple: Enter only the domain name.%1$s' .
			'Namecheap, Cloudflare, GratisDNS, Hover, ClouDNS, GoDaddy, Linode: Enter the hostname and the domain separately, with the domain being the domain or subdomain zone being handled by the provider.%1$s' .
			'DigitalOcean: Enter the record ID as the hostname and the domain separately.%1$s' .
			'Cloudflare, Linode: Enter @ as the hostname to indicate an empty field.', '<br />');

$section->add($group);

$section->addInput(new Form_Input(
	'mx',
	'MX',
	'text',
	$pconfig['mx']
))->setHelp('Note: With DynDNS service only a hostname can be used, not an IP address. '.
			'Set this option only if a special MX record is needed. Not all services support this.');

$section->addInput(new Form_Checkbox(
	'wildcard',
	'Wildcards',
	'Enable Wildcard',
	$pconfig['wildcard']
));

$section->addInput(new Form_Checkbox(
	'proxied',
	'Cloudflare Proxy',
	'Enable Proxy',
	$pconfig['proxied']
))->setHelp('Note: This enables Cloudflare Virtual DNS proxy.  When Enabled it will route all traffic '.
			'through their servers. By Default this is disabled and your Real IP is exposed.'.
			'More info: %s', '<a href="https://blog.cloudflare.com/announcing-virtual-dns-ddos-mitigation-and-global-distribution-for-dns-traffic/" target="_blank">Cloudflare Blog</a>');

$section->addInput(new Form_Checkbox(
	'verboselog',
	'Verbose logging',
	'Enable verbose logging',
	$pconfig['verboselog']
));

$section->addInput(new Form_Checkbox(
	'curl_ipresolve_v4',
	'HTTP API DNS Options',
	'Force IPv4 DNS Resolution',
	$pconfig['curl_ipresolve_v4']
));

$section->addInput(new Form_Checkbox(
	'curl_ssl_verifypeer',
	'HTTP API SSL/TLS Options',
	'Verify SSL/TLS Certificate Trust',
	$pconfig['curl_ssl_verifypeer']
))->setHelp('When set, the server must provide a valid SSL/TLS certificate trust chain which can be verified by this firewall.');

$section->addInput(new Form_Input(
	'username',
	'Username',
	'text',
	$pconfig['username'],
	['autocomplete' => 'new-password']
))->setHelp('Username is required for all types except Namecheap, FreeDNS, FreeDNS-v6, DigitalOcean, Linode and Custom Entries.%1$s' .
			'Azure: Enter your Azure AD application ID%1$s' .
			'DNS Made Easy: Dynamic DNS ID%1$s' .
			'DNSimple: User account ID (In the URL after the \'/a/\')%1$s' .
			'Route 53: Enter the Access Key ID.%1$s' .
			'GleSYS: Enter the API user.%1$s' .
			'Dreamhost: Enter a value to appear in the DNS record comment.%1$s' .
			'Godaddy:: Enter the API key.%1$s' .
			'For Custom Entries, Username and Password represent HTTP Authentication username and passwords.', '<br />');

$section->addPassword(new Form_Input(
	'passwordfld',
	'Password',
	'password',
	$pconfig['password']
))->setHelp('FreeDNS (freedns.afraid.org): Enter the "Authentication Token" provided by FreeDNS.%1$s' .
			'Azure: client secret of the AD application%1$s' .
			'DNS Made Easy: Dynamic DNS Password%1$s' .
			'DigitalOcean: Enter API token%1$s' .
			'Route 53: Enter the Secret Access Key.%1$s' .
			'GleSYS: Enter the API key.%1$s' .
			'Dreamhost: Enter the API Key.%1$s' .
			'Gandi LiveDNS: Enter API token%1$s' .
			'GoDaddy: Enter the API secret.%1$s' .
			'DNSimple: Enter the API token.%1$s' .
			'Linode: Enter the Personal Access Token.%1$s' .
			'Cloudflare: Enter the Global API Key.', '<br />');

$section->addInput(new Form_Input(
	'zoneid',
	'Zone ID',
	'text',
	$pconfig['zoneid']
))->setHelp('Route53: Enter AWS Zone ID.%1$s' .
			'Azure: Enter the resource id of the of the DNS Zone%1$s' .
			'DNSimple: Enter the Record ID of record to update.', '<br />');

$section->addInput(new Form_Input(
	'updateurl',
	'Update URL',
	'text',
	$pconfig['updateurl']
))->setHelp('This is the only field required by for Custom Dynamic DNS, and is only used by Custom Entries.');

$section->addInput(new Form_Textarea(
	'resultmatch',
	'Result Match',
	$pconfig['resultmatch']
))->sethelp('This field should be identical to what the DDNS Provider will return if the update succeeds, leave it blank to disable checking of returned results.%1$s' .
			'To include the new IP in the request, put %%IP%% in its place.%1$s' .
			'To include multiple possible values, separate them with a |. If the provider includes a |, escape it with \\|)%1$s' .
			'Tabs (\\t), newlines (\\n) and carriage returns (\\r) at the beginning or end of the returned results are removed before comparison.', '<br />');

$section->addInput(new Form_Input(
	'ttl',
	'TTL',
	'text',
	$pconfig['ttl']
))->setHelp('Choose TTL for the dns record.');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).%1$s' .
			'This field will be used in the Dynamic DNS Status Widget for Custom services.', '<br />');

if (isset($id) && $a_dyndns[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));

	$form->addGlobal(new Form_Button(
		'force',
		'Save & Force Update',
		null,
		'fa-refresh'
	))->removeClass('btn-primary')->addClass('btn-info');
}

$form->add($section);

print($form);

// Certain input elements are hidden/shown based on the service type in the following script
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	function setVisible(service) {
		switch (service) {
			case "custom" :
			case "custom-v6" :
				hideGroupInput('domainname', true);
				hideInput('resultmatch', false);
				hideInput('updateurl', false);
				hideInput('requestif', false);
				hideCheckbox('curl_ipresolve_v4', false);
				hideCheckbox('curl_ssl_verifypeer', false);
				hideInput('host', true);
				hideInput('mx', true);
				hideCheckbox('wildcard', true);
				hideCheckbox('proxied', true);
				hideInput('zoneid', true);
				hideInput('ttl', true);
				break;

			case "dnsimple":
				hideGroupInput('domainname', true);
				hideInput('resultmatch', true);
				hideInput('updateurl', true);
				hideInput('requestif', true);
				hideCheckbox('curl_ipresolve_v4', true);
				hideCheckbox('curl_ssl_verifypeer', false);
				hideInput('host', false);
				hideInput('mx', false);
				hideCheckbox('wildcard', false);
				hideCheckbox('proxied', true);
				hideInput('zoneid', false);
				hideInput('ttl', false);
				break;
			case "route53-v6":
			case "route53":
				hideGroupInput('domainname', true);
				hideInput('resultmatch', true);
				hideInput('updateurl', true);
				hideInput('requestif', true);
				hideCheckbox('curl_ipresolve_v4', true);
				hideCheckbox('curl_ssl_verifypeer', false);
				hideInput('host', false);
				hideInput('mx', true);
				hideCheckbox('wildcard', true);
				hideCheckbox('proxied', true);
				hideInput('zoneid', false);
				hideInput('ttl', false);
				break;
			case "namecheap":
			case "gratisdns":
			case "hover":
				hideGroupInput('domainname', false);
				hideInput('resultmatch', true);
				hideInput('updateurl', true);
				hideInput('requestif', true);
				hideCheckbox('curl_ipresolve_v4', true);
				hideCheckbox('curl_ssl_verifypeer', true);
				hideInput('host', false);
				hideInput('mx', false);
				hideCheckbox('wildcard', false);
				hideCheckbox('proxied', true);
				hideInput('zoneid', true);
				hideInput('ttl', true);
				break;
			case "cloudns":
				hideGroupInput('domainname', false);
				hideInput('resultmatch', true);
				hideInput('updateurl', true);
				hideInput('requestif', true);
				hideCheckbox('curl_ipresolve_v4', true);
				hideCheckbox('curl_ssl_verifypeer', true);
				hideInput('host', false);
				hideInput('mx', false);
				hideCheckbox('wildcard', false);
				hideCheckbox('proxied', true);
				hideInput('zoneid', true);
				hideInput('ttl', false);
				break;
			case 'dreamhost':
			case 'dreamhost-v6':
				hideGroupInput('domainname', true);
				hideInput('resultmatch', true);
				hideInput('updateurl', true);
				hideInput('requestif', true);
				hideCheckbox('curl_ipresolve_v4', true);
				hideCheckbox('curl_ssl_verifypeer', true);
				hideInput('host', false);
				hideInput('mx', true);
				hideCheckbox('wildcard', true);
				hideCheckbox('proxied', true);
				hideInput('zoneid', true);
				hideInput('ttl', true);
				break;
			case "cloudflare-v6":
			case "cloudflare":
				hideGroupInput('domainname', false);
				hideInput('resultmatch', true);
				hideInput('updateurl', true);
				hideInput('requestif', true);
				hideCheckbox('curl_ipresolve_v4', true);
				hideCheckbox('curl_ssl_verifypeer', true);
				hideInput('host', false);
				hideInput('mx', true);
				hideCheckbox('wildcard', true);
				hideCheckbox('proxied', false);
				hideInput('zoneid', true);
				hideInput('ttl', false);
				break;
			case "digitalocean":
		        case "digitalocean-v6":
				hideGroupInput('domainname', false);
				hideInput('resultmatch', true);
				hideInput('updateurl', true);
				hideInput('requestif', true);
				hideCheckbox('curl_ipresolve_v4', true);
				hideCheckbox('curl_ssl_verifypeer', true);
				hideInput('username', true);
				hideInput('host', false);
				hideInput('mx', true);
				hideCheckbox('wildcard', true);
				hideCheckbox('proxied', true);
				hideInput('zoneid', true);
				hideInput('ttl', false);
				break;
			case "godaddy":
			case "godaddy-v6":
				hideGroupInput('domainname', false);
				hideInput('resultmatch', true);
				hideInput('updateurl', true);
				hideInput('requestif', true);
				hideCheckbox('curl_ipresolve_v4', true);
				hideCheckbox('curl_ssl_verifypeer', true);
				hideInput('host', false);
				hideInput('mx', true);
				hideCheckbox('wildcard', true);
				hideCheckbox('proxied', true);
				hideInput('zoneid', true);
				hideInput('ttl', false);
				break;
			case "azurev6":
			case "azure":
				hideGroupInput('domainname', true);
				hideInput('resultmatch', true);
				hideInput('updateurl', true);
				hideInput('requestif', true);
				hideCheckbox('curl_ipresolve_v4', true);
				hideCheckbox('curl_ssl_verifypeer', true);
				hideInput('host', false);
				hideInput('mx', true);
				hideCheckbox('wildcard', true);
				hideCheckbox('proxied', true);
				hideInput('zoneid', false);
				hideInput('ttl', false);
				break;
			case "linode-v6":
			case "linode":
				hideGroupInput('domainname', false);
				hideInput('resultmatch', true);
				hideInput('updateurl', true);
				hideInput('requestif', true);
				hideCheckbox('curl_ipresolve_v4', true);
				hideCheckbox('curl_ssl_verifypeer', true);
				hideInput('host', false);
				hideInput('mx', true);
				hideCheckbox('wildcard', true);
				hideCheckbox('proxied', true);
				hideInput('zoneid', true);
				hideInput('ttl', false);
				break;
			case "gandi-livedns": // NOTE: same as digitalocean
				hideGroupInput('domainname', false);
				hideInput('resultmatch', true);
				hideInput('updateurl', true);
				hideInput('requestif', true);
				hideCheckbox('curl_ipresolve_v4', true);
				hideCheckbox('curl_ssl_verifypeer', true);
				hideInput('username', true);
				hideInput('host', false);
				hideInput('mx', true);
				hideCheckbox('wildcard', true);
				hideCheckbox('proxied', true);
				hideInput('zoneid', true);
				hideInput('ttl', false);
				break;
			default:
				hideGroupInput('domainname', true);
				hideInput('resultmatch', true);
				hideInput('updateurl', true);
				hideInput('requestif', true);
				hideCheckbox('curl_ipresolve_v4', true);
				hideCheckbox('curl_ssl_verifypeer', true);
				hideInput('host', false);
				hideInput('mx', false);
				hideCheckbox('wildcard', false);
				hideCheckbox('proxied', true);
				hideInput('zoneid', true);
				hideInput('ttl', true);
		}
	}

	// When the 'Service type" selector is changed, we show/hide certain elements
	$('#type').on('change', function() {
		setVisible(this.value);
	});

	// ---------- On initial page load ------------------------------------------------------------

	setVisible($('#type').val());

});
//]]>
</script>

<?php include("foot.inc");
