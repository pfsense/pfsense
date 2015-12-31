<?php
/*
	services_dyndns_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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

require("guiconfig.inc");

if (!is_array($config['dyndnses']['dyndns'])) {
	$config['dyndnses']['dyndns'] = array();
}

$a_dyndns = &$config['dyndnses']['dyndns'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && isset($a_dyndns[$id])) {
	$pconfig['username'] = $a_dyndns[$id]['username'];
	$pconfig['password'] = $a_dyndns[$id]['password'];
	$pconfig['host'] = $a_dyndns[$id]['host'];
	$pconfig['mx'] = $a_dyndns[$id]['mx'];
	$pconfig['type'] = $a_dyndns[$id]['type'];
	$pconfig['enable'] = !isset($a_dyndns[$id]['enable']);
	$pconfig['interface'] = $a_dyndns[$id]['interface'];
	$pconfig['wildcard'] = isset($a_dyndns[$id]['wildcard']);
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

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	if (($pconfig['type'] == "freedns" || $pconfig['type'] == "namecheap") && $_POST['username'] == "") {
		$_POST['username'] = "none";
	}

	/* input validation */
	$reqdfields = array();
	$reqdfieldsn = array();
	$reqdfields = array("type");
	$reqdfieldsn = array(gettext("Service type"));

	if ($pconfig['type'] != "custom" && $pconfig['type'] != "custom-v6") {
		$reqdfields[] = "host";
		$reqdfieldsn[] = gettext("Hostname");
		$reqdfields[] = "passwordfld";
		$reqdfieldsn[] = gettext("Password");
		$reqdfields[] = "username";
		$reqdfieldsn[] = gettext("Username");
	} else {
		$reqdfields[] = "updateurl";
		$reqdfieldsn[] = gettext("Update URL");
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['passwordfld'] != $_POST['passwordfld_confirm']) {
		$input_errors[] = gettext("Password and confirmed password must match.");
	}

	if (isset($_POST['host']) && in_array("host", $reqdfields)) {
		/* Namecheap can have a @. in hostname */
		if ($pconfig['type'] == "namecheap" && substr($_POST['host'], 0, 2) == '@.') {
			$host_to_check = substr($_POST['host'], 2);
		} else {
			$host_to_check = $_POST['host'];
		}

		if ($pconfig['type'] != "custom" && $pconfig['type'] != "custom-v6") {
			if (!is_domain($host_to_check)) {
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
			$dyndns['password'] = $_POST['passwordfld'];
		} else {
			$dyndns['password'] = $a_dyndns[$id]['password'];;
		}
		$dyndns['host'] = $_POST['host'];
		$dyndns['mx'] = $_POST['mx'];
		$dyndns['wildcard'] = $_POST['wildcard'] ? true : false;
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

		write_config();

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

$pgtitle = array(gettext("Services"), gettext("Dynamic DNS"), gettext("Dynamic DNS Client"), gettext("Edit"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
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
	'Service Type',
	$pconfig['type'],
	build_type_list()
));

$interfacelist = build_if_list();

$section->addInput(new Form_Select(
	'interface',
	'Interface to monitor',
	$pconfig['interface'],
	$interfacelist
));

$section->addInput(new Form_Select(
	'requestif',
	'Interface to send update from',
	$pconfig['requestif'],
	$interfacelist
))->setHelp('This is almost always the same as the Interface to Monitor. ');

$section->addInput(new Form_Input(
	'host',
	'Hostname',
	'text',
	$pconfig['host']
))->setHelp('Enter the complete fully qualified domain name. Example: myhost.dyndns.org'. '<br />' .
			'he.net tunnelbroker: Enter your tunnel ID' . '<br />' .
			'GleSYS: Enter your record ID' . '<br />' .
			'DNSimple: Enter only the domain name.');

$section->addInput(new Form_Input(
	'mx',
	'MX',
	'text',
	$pconfig['mx']
))->setHelp('Note: With DynDNS service you can only use a hostname, not an IP address. '.
			'Set this option only if you need a special MX record. Not all services support this.');

$section->addInput(new Form_Checkbox(
	'wildcard',
	'Wildcards',
	'Enable Wildcard',
	$pconfig['wildcard']
));

$section->addInput(new Form_Checkbox(
	'verboselog',
	'Verbose logging',
	'Enable verbose logging',
	$pconfig['verboselog']
));

$section->addInput(new Form_Checkbox(
	'curl_ipresolve_v4',
	'CURL options',
	'Force IPv4 resolving',
	$pconfig['curl_ipresolve_v4']
));

$section->addInput(new Form_Checkbox(
	'curl_ssl_verifypeer',
	null,
	'Verify SSL peer',
	$pconfig['curl_ssl_verifypeer']
));

$section->addInput(new Form_Input(
	'username',
	'Username',
	'text',
	$pconfig['username']
))->setHelp('Username is required for all types except Namecheap, FreeDNS and Custom Entries.' . '<br />' .
			'Route 53: Enter your Access Key ID.' . '<br />' .
			'GleSYS: Enter your API user.' . '<br />' .
			'For Custom Entries, Username and Password represent HTTP Authentication username and passwords.');

$section->addPassword(new Form_Input(
	'passwordfld',
	'Password',
	'password',
	$pconfig['password']
))->setHelp('FreeDNS (freedns.afraid.org): Enter your "Authentication Token" provided by FreeDNS.' . '<br />' .
			'Route 53: Enter your Secret Access Key.' . '<br />' .
			'GleSYS: Enter your API key.' . '<br />' .
			'DNSimple: Enter your API token.');

$section->addInput(new Form_Input(
	'zoneid',
	'Zone ID',
	'text',
	$pconfig['zoneid']
))->setHelp('Enter Zone ID that you received when you created your domain in Route 53.' . '<br />' .
			'DNSimple: Enter the Record ID of record to update.');

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
))->sethelp('This field should be identical to what your DDNS Provider will return if the update succeeds, leave it blank to disable checking of returned results.' . '<br />' .
			'If you need the new IP to be included in the request, put %IP% in its place.' . '<br />' .
			'If you need to include multiple possible values, separate them with a |. If your provider includes a |, escape it with \\|)' . '<br />' .
			'Tabs (\\t), newlines (\\n) and carriage returns (\\r) at the beginning or end of the returned results are removed before comparison.');

$section->addInput(new Form_Input(
	'ttl',
	'TTL',
	'text',
	$pconfig['ttl']
))->setHelp('Choose TTL for your dns record.');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference (not parsed).');

if (isset($id) && $a_dyndns[$id]) {
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));

	$form->addGlobal(new Form_Button(
		'force',
		'Save & Force Update'
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
				hideInput('resultmatch', false);
				hideInput('updateurl', false);
				hideInput('requestif', false);
				hideCheckbox('curl_ipresolve_v4', false);
				hideCheckbox('curl_ssl_verifypeer', false);
				hideInput('host', true);
				hideInput('mx', true);
				hideCheckbox('wildcard', true);
				hideInput('zoneid', true);
				hideInput('ttl', true);
				break;

			case "dnsimple":
			case "route53":
				hideInput('resultmatch', true);
				hideInput('updateurl', true);
				hideInput('requestif', true);
				hideCheckbox('curl_ipresolve_v4', true);
				hideCheckbox('curl_ssl_verifypeer', false);
				hideInput('host', false);
				hideInput('mx', false);
				hideCheckbox('wildcard', false);
				hideInput('zoneid', false);
				hideInput('ttl', false);
				break;

			default:
				hideInput('resultmatch', true);
				hideInput('updateurl', true);
				hideInput('requestif', true);
				hideCheckbox('curl_ipresolve_v4', true);
				hideCheckbox('curl_ssl_verifypeer', true);
				hideInput('host', false);
				hideInput('mx', false);
				hideCheckbox('wildcard', false);
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
