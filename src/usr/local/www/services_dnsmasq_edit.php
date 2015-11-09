<?php
/*
	services_dnsmasq_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2003-2004 Bob Zoller <bob@kludgebox.com> and Manuel Kasper <mk@neon1.net>
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
/*
	pfSense_MODULE: dnsforwarder
*/

##|+PRIV
##|*IDENT=page-services-dnsforwarder-edithost
##|*NAME=Services: DNS Forwarder: Edit host page
##|*DESCR=Allow access to the 'Services: DNS Forwarder: Edit host' page.
##|*MATCH=services_dnsmasq_edit.php*
##|-PRIV

function hostcmp($a, $b) {
	return strcasecmp($a['host'], $b['host']);
}

function hosts_sort() {
	global $g, $config;

	if (!is_array($config['dnsmasq']['hosts'])) {
		return;
	}

	usort($config['dnsmasq']['hosts'], "hostcmp");
}

require("guiconfig.inc");

if (!is_array($config['dnsmasq']['hosts']))
	$config['dnsmasq']['hosts'] = array();

$a_hosts = &$config['dnsmasq']['hosts'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_hosts[$id]) {
	$pconfig['host'] = $a_hosts[$id]['host'];
	$pconfig['domain'] = $a_hosts[$id]['domain'];
	$pconfig['ip'] = $a_hosts[$id]['ip'];
	$pconfig['descr'] = $a_hosts[$id]['descr'];
	$pconfig['aliases'] = $a_hosts[$id]['aliases'];
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "domain ip");
	$reqdfieldsn = array(gettext("Domain"), gettext("IP address"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['host']) {
		if (!is_hostname($_POST['host'])) {
			$input_errors[] = gettext("The hostname can only contain the characters A-Z, 0-9 and '-'. It may not start or end with '-'.");
		} else {
			if (!is_unqualified_hostname($_POST['host'])) {
				$input_errors[] = gettext("A valid hostname is specified, but the domain name part should be omitted");
			}
		}
	}

	if (($_POST['domain'] && !is_domain($_POST['domain']))) {
		$input_errors[] = gettext("A valid domain must be specified.");
	}

	if (($_POST['ip'] && !is_ipaddr($_POST['ip']))) {
		$input_errors[] = gettext("A valid IP address must be specified.");
	}

	/* collect aliases */
	$aliases = array();
	foreach ($_POST as $key => $value) {
		$entry = '';
		if (!substr_compare('aliashost', $key, 0, 9)) {
			$entry = substr($key, 9);
			$field = 'host';
		}
		elseif (!substr_compare('aliasdomain', $key, 0, 11)) {
			$entry = substr($key, 11);
			$field = 'domain';
		}
		elseif (!substr_compare('aliasdescription', $key, 0, 16)) {
			$entry = substr($key, 16);
			$field = 'description';
		}
		if (ctype_digit($entry)) {
			$aliases[$entry][$field] = $value;
		}
	}
	$pconfig['aliases']['item'] = $aliases;

	/* validate aliases */
	foreach ($aliases as $idx => $alias) {
		$aliasreqdfields = array('aliasdomain' . $idx);
		$aliasreqdfieldsn = array(gettext("Alias Domain"));

		do_input_validation($_POST, $aliasreqdfields, $aliasreqdfieldsn, $input_errors);
		if ($alias['host']) {
			if (!is_hostname($alias['host'])) {
				$input_errors[] = gettext("Hostnames in an alias list can only contain the characters A-Z, 0-9 and '-'. They may not start or end with '-'.");
			} else {
				if (!is_unqualified_hostname($alias['host'])) {
					$input_errors[] = gettext("A valid alias hostname is specified, but the domain name part should be omitted");
				}
			}
		}

		if (($alias['domain'] && !is_domain($alias['domain']))) {
			$input_errors[] = gettext("A valid domain must be specified in alias list.");
		}
	}

	/* check for overlaps */
	foreach ($a_hosts as $hostent) {
		if (isset($id) && ($a_hosts[$id]) && ($a_hosts[$id] === $hostent)) {
			continue;
		}

		if (($hostent['host'] == $_POST['host']) &&
		    ($hostent['domain'] == $_POST['domain']) &&
		    ((is_ipaddrv4($hostent['ip']) && is_ipaddrv4($_POST['ip'])) ||
		     (is_ipaddrv6($hostent['ip']) && is_ipaddrv6($_POST['ip'])))) {
			$input_errors[] = gettext("This host/domain already exists.");
			break;
		}
	}

	if (!$input_errors) {
		$hostent = array();
		$hostent['host'] = $_POST['host'];
		$hostent['domain'] = $_POST['domain'];
		$hostent['ip'] = $_POST['ip'];
		$hostent['descr'] = $_POST['descr'];
		$hostent['aliases']['item'] = $aliases;

		if (isset($id) && $a_hosts[$id]) {
			$a_hosts[$id] = $hostent;
		} else {
			$a_hosts[] = $hostent;
		}
		hosts_sort();

		mark_subsystem_dirty('hosts');

		write_config();

		header("Location: services_dnsmasq.php");
		exit;
	}
}

// Delete a row in the options table
if($_GET['act'] == "delopt") {
	$idx = $_GET['id'];

	if($pconfig['aliases'] && is_array($pconfig['aliases']['item'][$idx])) {
	   unset($pconfig['aliases']['item'][$idx]);
	}
}

// Add an option row
if($_GET['act'] == "addopt") {
    if(!is_array($pconfig['aliases']['item']))
        $pconfig['aliases']['item'] = array();

	array_push($pconfig['aliases']['item'], array('host' => null, 'domain' => null, 'description' => null));
}

$pgtitle = array(gettext("Services"),gettext("DNS Forwarder"),gettext("Edit Host Override"));
$shortcut_section = "forwarder";
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

require_once('classes/Form.class.php');

$form = new Form();

$section = new Form_Section('Host override options');

$section->addInput(new Form_Input(
	'host',
	'Host',
	'text',
	$pconfig['host']
))->setHelp('Name of the host, without the domain part' . '<br />' .
			'e.g.: "myhost"');

$section->addInput(new Form_Input(
	'domain',
	'Domain',
	'text',
	$pconfig['domain']
))->setHelp('Domain of the host' . '<br />' .
			'e.g.: "example.com"');

$section->addInput(new Form_IpAddress(
	'ip',
	'IP Address',
	$pconfig['ip']
))->setHelp('IP address of the host' . '<br />' .
			'e.g.: 192.168.100.100 or fd00:abcd::1');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference (not parsed).');

if (isset($id) && $a_hosts[$id]) {
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$pconfig['id']
	));
}

$form->add($section);

$section = new Form_Section('Additional names for this host');

if( $pconfig['aliases']['item']) {
	$counter = 0;
	$last = count($pconfig['aliases']['item']) - 1;

	foreach($pconfig['aliases']['item'] as $item) {
		$group = new Form_Group(null);

		$group->add(new Form_Input(
			'aliashost' . $counter,
			null,
			'text',
			$item['host']
		))->setHelp($counter == $last ? 'Host name':null);

		$group->add(new Form_Input(
			'aliasdomain' . $counter,
			null,
			'text',
			$item['domain']
		))->setHelp($counter == $last ? 'Domain':null);

		$group->add(new Form_Input(
			'aliasdescription' . $counter,
			null,
			'text',
			$item['description']
		))->setHelp($counter == $last ? 'Description':null);

		$btn = new Form_Button(
			'btn' . $counter,
			'Delete',
			'services_dnsmasq_edit.php?act=delopt' . '&id=' . $counter
		);

		$btn->removeClass('btn-primary')->addClass('btn-danger btn-sm');
		$group->add($btn);
		$section->add($group);
		$counter++;
	}
}

$btnaddopt = new Form_Button(
	'btnaddopt',
	'Add Host name',
	'services_dnsmasq_edit.php?act=addopt'
);

$btnaddopt->removeClass('btn-primary')->addClass('btn-success btn-sm');

$section->addInput($btnaddopt);

$form->add($section);
print($form);

include("foot.inc");
