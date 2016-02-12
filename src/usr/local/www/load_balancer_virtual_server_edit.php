<?php
/*
	load_balancer_virtual_server_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2005-2008 Bill Marquette <bill.marquette@gmail.com>
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
##|*IDENT=page-loadbalancer-virtualserver-edit
##|*NAME=Load Balancer: Virtual Server: Edit
##|*DESCR=Allow access to the 'Load Balancer: Virtual Server: Edit' page.
##|*MATCH=load_balancer_virtual_server_edit.php*
##|-PRIV

require("guiconfig.inc");

if (isset($_POST['referer'])) {
	$referer = $_POST['referer'];
} else {
	$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/load_balancer_virtual_server.php');
}

if (!is_array($config['load_balancer']['virtual_server'])) {
	$config['load_balancer']['virtual_server'] = array();
}
$a_vs = &$config['load_balancer']['virtual_server'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_vs[$id]) {
  $pconfig = $a_vs[$id];
} else {
  // Sane defaults
  $pconfig['mode'] = 'redirect_mode';
}

$changedesc = gettext("Load Balancer: Virtual Server:") . " ";
$changecount = 0;

$allowed_protocols = array("tcp", "dns");

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	switch ($pconfig['mode']) {
		case "redirect_mode": {
			$reqdfields = explode(" ", "ipaddr name mode");
			$reqdfieldsn = array(gettext("IP Address"), gettext("Name"), gettext("Mode"));
			break;
		}
		case "relay_mode": {
			$reqdfields = explode(" ", "ipaddr name mode relay_protocol");
			$reqdfieldsn = array(gettext("IP Address"), gettext("Name"), gettext("Mode"), gettext("Relay Protocol"));
			break;
		}
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	for ($i = 0; isset($config['load_balancer']['virtual_server'][$i]); $i++) {
		if (($_POST['name'] == $config['load_balancer']['virtual_server'][$i]['name']) && ($i != $id)) {
			$input_errors[] = gettext("This virtual server name has already been used.	Virtual server names must be unique.");
		}
	}

	if (preg_match('/[ \/]/', $_POST['name'])) {
		$input_errors[] = gettext("You cannot use spaces or slashes in the 'name' field.");
	}

	if (strlen($_POST['name']) > 32) {
		$input_errors[] = gettext("The 'name' field must be 32 characters or less.");
	}

	if ($_POST['port'] != "" && !is_portoralias($_POST['port'])) {
		$input_errors[] = gettext("The port must be an integer between 1 and 65535, a port alias, or left blank.");
	}

	if (!is_ipaddroralias($_POST['ipaddr']) && !is_subnetv4($_POST['ipaddr'])) {
		$input_errors[] = sprintf(gettext("%s is not a valid IP address, IPv4 subnet, or alias."), $_POST['ipaddr']);
	} else if (is_subnetv4($_POST['ipaddr']) && subnet_size($_POST['ipaddr']) > 64) {
		$input_errors[] = sprintf(gettext("%s is a subnet containing more than 64 IP addresses."), $_POST['ipaddr']);
	}

	if (!in_array($_POST['relay_protocol'], $allowed_protocols)) {
		$input_errors[] = gettext("The submitted relay protocol is not valid.");
	}

	if ((strtolower($_POST['relay_protocol']) == "dns") && !empty($_POST['sitedown'])) {
		$input_errors[] = gettext("You cannot select a Fall Back Pool when using the DNS relay protocol.");
	}

	if (!$input_errors) {
		$vsent = array();
		if (isset($id) && $a_vs[$id]) {
			$vsent = $a_vs[$id];
		}
		if ($vsent['name'] != "") {
			$changedesc .= " " . sprintf(gettext("modified '%s' vs:"), $vsent['name']);
		} else {
			$changedesc .= " " . sprintf(gettext("created '%s' vs:"), $_POST['name']);
		}

		update_if_changed("name", $vsent['name'], $_POST['name']);
		update_if_changed("descr", $vsent['descr'], $_POST['descr']);
		update_if_changed("poolname", $vsent['poolname'], $_POST['poolname']);
		update_if_changed("port", $vsent['port'], $_POST['port']);
		update_if_changed("sitedown", $vsent['sitedown'], $_POST['sitedown']);
		update_if_changed("ipaddr", $vsent['ipaddr'], $_POST['ipaddr']);
		update_if_changed("mode", $vsent['mode'], $_POST['mode']);
		update_if_changed("relay protocol", $vsent['relay_protocol'], $_POST['relay_protocol']);

		if ($_POST['sitedown'] == "") {
			unset($vsent['sitedown']);
		}

		if (isset($id) && $a_vs[$id]) {
			if ($a_vs[$id]['name'] != $_POST['name']) {
				/* Because the VS name changed, mark the old name for cleanup. */
				cleanup_lb_mark_anchor($a_vs[$id]['name']);
			}
			$a_vs[$id] = $vsent;
		} else {
			$a_vs[] = $vsent;
		}

		if ($changecount > 0) {
			/* Mark virtual server dirty */
			mark_subsystem_dirty('loadbalancer');
			write_config($changedesc);
		}

		header("Location: load_balancer_virtual_server.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("Load Balancer"), gettext("Virtual Servers"), gettext("Edit"));
$shortcut_section = "relayd-virtualservers";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Edit Load Balancer - Virtual Server Entry');

$section->addInput(new Form_Input(
	'name',
	'Name',
	'text',
	$pconfig['name']
));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
));


$section->addInput(new Form_IpAddress(
	'ipaddr',
	'IP Address',
	$pconfig['ipaddr']
))->setHelp('This is normally the WAN IP address that you would like the server to listen on. ' .
			'All connections to this IP and port will be forwarded to the pool cluster. ' .
			'You may also specify a host alias listed in Firewall -&gt; Aliases here.');

$section->addInput(new Form_Input(
	'port',
	'Port',
	'number',
	$pconfig['port']
))->setHelp('Port that the clients will connect to. All connections to this port will be forwarded to the pool cluster. ' .
			'If left blank listening ports from the pool will be used.' .
			'You may also specify a port alias listed in Firewall -&gt; Aliases here.');

if (count($config['load_balancer']['lbpool']) == 0) {
	$section->addInput(new Form_StaticText(
		'Virtual Server Pool',
		'Please add a pool on the "Pools" tab to use this feature. '
	));
} else {

	$list = array();
	for ($i = 0; isset($config['load_balancer']['lbpool'][$i]); $i++) {
		$list[$config['load_balancer']['lbpool'][$i]['name']] = $config['load_balancer']['lbpool'][$i]['name'];
	}

	$section->addInput(new Form_Select(
		'poolname',
		'Virtual Server Pool',
		$pconfig['poolname'],
		$list
	));
}

if (count($config['load_balancer']['lbpool']) == 0) {
	$section->addInput(new Form_StaticText(
		'Fall-back Pool',
		'Please add a pool on the "Pools" tab to use this feature. '
	));
} else {

	$list = array();
	for ($i = 0; isset($config['load_balancer']['lbpool'][$i]); $i++) {
		$list[$config['load_balancer']['lbpool'][$i]['name']] = $config['load_balancer']['lbpool'][$i]['name'];
	}

	$section->addInput(new Form_Select(
		'sitedown',
		'Fall-back Pool',
		$pconfig['sitedown'],
		$list
	));
}

$section->addInput(new Form_Input(
	'mode',
	null,
	'hidden',
	'redirect_mode'
));

$section->addInput(new Form_Select(
	'relay_protocol',
	'Relay Protocol',
	$pconfig['relay_protocol'],
	['tcp' => 'TCP', 'dns' => 'DNS']
));

if (isset($id) && $a_vs[$id] && $_GET['act'] != 'dup') {
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->add($section);
print($form);

print_info_box(gettext('Don\'t forget to add a firewall rule for the virtual server/pool after you have finished setting it up.'));
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {
    // --------- Autocomplete -----------------------------------------------------------------------------------------
    var addressarray = <?= json_encode(get_alias_list(array("host", "network", "openvpn", "urltable"))) ?>;
    var customarray = <?= json_encode(get_alias_list(array("port", "url_ports", "urltable_ports"))) ?>;

    $('#ipaddr').autocomplete({
        source: addressarray
    });

    $('#port').autocomplete({
        source: customarray
    });
});
//]]>
</script>
<?php
include("foot.inc");
