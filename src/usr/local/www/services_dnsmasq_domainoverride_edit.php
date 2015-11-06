<?php
/*
	services_dnsmasq_domainoverride_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *  Copyright (c)  2003-2005 Bob Zoller <bob@kludgebox.com> and Manuel Kasper <mk@neon1.net>
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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
	pfSense_MODULE: dnsforwarder
*/

##|+PRIV
##|*IDENT=page-services-dnsforwarder-editdomainoverride
##|*NAME=Services: DNS Forwarder: Edit Domain Override page
##|*DESCR=Allow access to the 'Services: DNS Forwarder: Edit Domain Override' page.
##|*MATCH=services_dnsmasq_domainoverride_edit.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['dnsmasq']['domainoverrides'])) {
	   $config['dnsmasq']['domainoverrides'] = array();
}

$a_domainOverrides = &$config['dnsmasq']['domainoverrides'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_domainOverrides[$id]) {
	$pconfig['domain'] = $a_domainOverrides[$id]['domain'];
	if (is_ipaddr($a_domainOverrides[$id]['ip']) && ($a_domainOverrides[$id]['ip'] != '#')) {
		$pconfig['ip'] = $a_domainOverrides[$id]['ip'];
	} else {
		$dnsmasqpieces = explode('@', $a_domainOverrides[$id]['ip'], 2);
		$pconfig['ip'] = $dnsmasqpieces[0];
		$pconfig['dnssrcip'] = $dnsmasqpieces[1];
	}
	$pconfig['descr'] = $a_domainOverrides[$id]['descr'];
}

if ($_POST) {
	   unset($input_errors);
	   $pconfig = $_POST;

	   /* input validation */
	   $reqdfields = explode(" ", "domain ip");
	   $reqdfieldsn = array(gettext("Domain"),gettext("IP address"));

	   do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	   function String_Begins_With($needle, $haystack) {
		   return (substr($haystack, 0, strlen($needle))==$needle);
	   }

	   if (String_Begins_With(_msdcs, $_POST['domain'])) {
			   $subdomainstr = substr($_POST['domain'], 7);

			   if ($subdomainstr && !is_domain($subdomainstr)) {
				  $input_errors[] = gettext("A valid domain must be specified after _msdcs.");
			   }
	   }
	   elseif ($_POST['domain'] && !is_domain($_POST['domain'])) {
			   $input_errors[] = gettext("A valid domain must be specified.");
	   }

	   if ($_POST['ip'] && !is_ipaddr($_POST['ip']) && ($_POST['ip'] != '#') && ($_POST['ip'] != '!')) {
			  $input_errors[] = gettext("A valid IP address must be specified, or # for an exclusion or ! to not forward at all.");
	   }

	   if ($_POST['dnssrcip'] && !in_array($_POST['dnssrcip'], get_configured_ip_addresses())) {
			  $input_errors[] = gettext("An interface IP address must be specified for the DNS query source.");
	   }

	   if (!$input_errors) {
			$doment = array();
			$doment['domain'] = $_POST['domain'];

			if (empty($_POST['dnssrcip']))
				$doment['ip'] = $_POST['ip'];
			else
			   $doment['ip'] = $_POST['ip'] . "@" . $_POST['dnssrcip'];

			$doment['descr'] = $_POST['descr'];

		if (isset($id) && $a_domainOverrides[$id]) {
			$a_domainOverrides[$id] = $doment;
		} else {
			$a_domainOverrides[] = $doment;
		}

		$retval = services_dnsmasq_configure();

		write_config();

		header("Location: services_dnsmasq.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("DNS Forwarder"), gettext("Edit Domain Override"));
$shortcut_section = "forwarder";
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

require_once('classes/Form.class.php');

$form = new Form();

$section = new Form_Section('Domain override options');

$section->addInput(new Form_Input(
	'domain',
	'Domain',
	'text',
	$pconfig['domain']
))->setHelp('Domain to override (NOTE: this does not have to be a valid TLD!)' . '<br />' .
			'e.g.: test or mycompany.localdomain or 1.168.192.in-addr.arpa');

$section->addInput(new Form_IpAddress(
	'ip',
	'IP Address',
	$pconfig['ip']
))->setHelp('IP address of the authoritative DNS server for this domain' . '<br />' .
			'e.g.: 192.168.100.100' . '<br />' .
			'Or enter # for an exclusion to pass through this host/subdomain to standard nameservers instead of a previous override.' . '<br />' .
			'Or enter ! for lookups for this host/subdomain to NOT be forwarded anywhere.');

$section->addInput(new Form_IpAddress(
	'dnssrcip',
	'Source IP',
	$pconfig['dnssrcip']
))->setHelp('Source IP address for queries to the DNS server for the override domain. Leave blank unless your DNS server is accessed through a VPN tunnel.');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference (not parsed).');

if (isset($id) && $a_domainOverrides[$id]) {
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$pconfig['id']
	))->setHelp('You may enter a description here for your reference (not parsed).');
}

$form->add($section);
print($form);

include("foot.inc");
