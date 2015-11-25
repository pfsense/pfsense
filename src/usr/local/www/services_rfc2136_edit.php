<?php
/*
	services_rfc2136_edit.php
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
/*
	pfSense_MODULE: dnsupdate
*/

##|+PRIV
##|*IDENT=page-services-rfc2136edit
##|*NAME=Services: RFC 2136 Client: Edit
##|*DESCR=Allow access to the 'Services: RFC 2136 Client: Edit' page.
##|*MATCH=services_rfc2136.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['dnsupdates']['dnsupdate'])) {
	$config['dnsupdates']['dnsupdate'] = array();
}

$a_rfc2136 = &$config['dnsupdates']['dnsupdate'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && isset($a_rfc2136[$id])) {
	$pconfig['enable'] = isset($a_rfc2136[$id]['enable']);
	$pconfig['host'] = $a_rfc2136[$id]['host'];
	$pconfig['ttl'] = $a_rfc2136[$id]['ttl'];
	if (!$pconfig['ttl']) {
		$pconfig['ttl'] = 60;
	}
	$pconfig['keydata'] = $a_rfc2136[$id]['keydata'];
	$pconfig['keyname'] = $a_rfc2136[$id]['keyname'];
	$pconfig['keytype'] = $a_rfc2136[$id]['keytype'];
	if (!$pconfig['keytype']) {
		$pconfig['keytype'] = "zone";
	}
	$pconfig['server'] = $a_rfc2136[$id]['server'];
	$pconfig['interface'] = $a_rfc2136[$id]['interface'];
	$pconfig['usetcp'] = isset($a_rfc2136[$id]['usetcp']);
	$pconfig['usepublicip'] = isset($a_rfc2136[$id]['usepublicip']);
	$pconfig['recordtype'] = $a_rfc2136[$id]['recordtype'];
	if (!$pconfig['recordtype']) {
		$pconfig['recordtype'] = "both";
	}
	$pconfig['descr'] = $a_rfc2136[$id]['descr'];

}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = array();
	$reqdfieldsn = array();
	$reqdfields = array_merge($reqdfields, explode(" ", "host ttl keyname keydata"));
	$reqdfieldsn = array_merge($reqdfieldsn, array(gettext("Hostname"), gettext("TTL"), gettext("Key name"), gettext("Key")));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (($_POST['host'] && !is_domain($_POST['host']))) {
		$input_errors[] = gettext("The DNS update host name contains invalid characters.");
	}
	if (($_POST['ttl'] && !is_numericint($_POST['ttl']))) {
		$input_errors[] = gettext("The DNS update TTL must be an integer.");
	}
	if (($_POST['keyname'] && !is_domain($_POST['keyname']))) {
		$input_errors[] = gettext("The DNS update key name contains invalid characters.");
	}

	if (!$input_errors) {
		$rfc2136 = array();
		$rfc2136['enable'] = $_POST['enable'] ? true : false;
		$rfc2136['host'] = $_POST['host'];
		$rfc2136['ttl'] = $_POST['ttl'];
		$rfc2136['keyname'] = $_POST['keyname'];
		$rfc2136['keytype'] = $_POST['keytype'];
		$rfc2136['keydata'] = $_POST['keydata'];
		$rfc2136['server'] = $_POST['server'];
		$rfc2136['usetcp'] = $_POST['usetcp'] ? true : false;
		$rfc2136['usepublicip'] = $_POST['usepublicip'] ? true : false;
		$rfc2136['recordtype'] = $_POST['recordtype'];
		$rfc2136['interface'] = $_POST['interface'];
		$rfc2136['descr'] = $_POST['descr'];

		if (isset($id) && $a_rfc2136[$id]) {
			$a_rfc2136[$id] = $rfc2136;
		} else {
			$a_rfc2136[] = $rfc2136;
		}

		write_config(gettext("New/Edited RFC2136 dnsupdate entry was posted."));

		if ($_POST['Submit'] == gettext("Save & Force Update")) {
			$retval = services_dnsupdate_process("", $rfc2136['host'], true);
		} else {
			$retval = services_dnsupdate_process();
		}

		header("Location: services_rfc2136.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("RFC 2136 Client"), gettext("Edit"));
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg)
	print_info_box($savemsg);

$form = new Form;

$section = new Form_Section('RFC 2136 client');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	null,
	$pconfig['enable']
));

$optionlist = array();
$iflist = get_configured_interface_with_descr();

foreach ($iflist as $ifnam => $ifdescr)
	$optionlist[$ifnam] = $ifdescr;

$section->addInput(new Form_Select(
	'interface',
	'Interface',
	$pconfig['interface'],
	$optionlist
));

$section->addInput(new Form_Input(
	'host',
	'Hostname',
	'text',
	$pconfig['host']
))->setHelp('Fully qualified hostname of the host to be updated');

$section->addInput(new Form_Input(
	'ttl',
	'TTL (seconds)',
	'number',
	$pconfig['ttl']
));

$section->addInput(new Form_Input(
	'keyname',
	'Key name',
	'text',
	$pconfig['keyname']
))->setHelp('This must match the setting on the DNS server.');

$group = new Form_Group('Key Type');

$group->add(new Form_Checkbox(
	'keytype',
	'Key Type',
	'Zone',
	($pconfig['keytype']=='zone'),
	'zone'
))->displayAsRadio();

$group->add($input = new Form_Checkbox(
	'keytype',
	'Key Type',
	'Host',
	($pconfig['keytype']=='host'),
	'host'
))->displayAsRadio();

$group->add($input = new Form_Checkbox(
	'keytype',
	'Key Type',
	'User',
	($pconfig['keytype']=='user'),
	'user'
))->displayAsRadio();

$section->add($group);

$section->addInput(new Form_Input(
	'keydata',
	'Key',
	'text',
	$pconfig['keydata']
))->setHelp('Paste an HMAC-MD5 key here.');

$section->addInput(new Form_Input(
	'server',
	'Server',
	'text',
	$pconfig['server']
));

$section->addInput(new Form_Checkbox(
	'usetcp',
	'Protocol',
	'Use TCP instead of UDP',
	$pconfig['usetcp']
));

$section->addInput(new Form_Checkbox(
	'usepublicip',
	'Use public IP',
	'If the interface IP is private, attempt to fetch and use the public IP instead.',
	$pconfig['usepublicip']
));

$group = new Form_Group('Record Type');

$group->add(new Form_Checkbox(
	'recordtype',
	'Record Type',
	'A (IPv4)',
	($pconfig['recordtype']=='A'),
	'A'
))->displayAsRadio();

$group->add($input = new Form_Checkbox(
	'recordtype',
	'Record Type',
	'AAAA (IPv6)',
	($pconfig['recordtype']=='AAAA'),
	'AAAA'
))->displayAsRadio();

$group->add($input = new Form_Checkbox(
	'recordtype',
	'Record Type',
	'Both',
	($pconfig['recordtype']=='both'),
	'both'
))->displayAsRadio();

$section->add($group);

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference (not parsed).');

if (isset($id) && $a_rfc2136[$id]){
    	$section->addInput(new Form_Input(
    	'id',
    	null,
    	'hidden',
    	$id
	));
}

$form->add($section);
print($form);

print_info_box(sprintf('You must configure a DNS server in %sSystem: ' .
					'General setup %sor allow the DNS server list to be overridden ' .
					'by DHCP/PPP on WAN for dynamic DNS updates to work.','<a href="system.php">', '</a>'));

include("foot.inc");
