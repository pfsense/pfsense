<?php
/*
	services_snmp.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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
##|*IDENT=page-services-snmp
##|*NAME=Services: SNMP
##|*DESCR=Allow access to the 'Services: SNMP' page.
##|*MATCH=services_snmp.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");

if (!is_array($config['snmpd'])) {
	$config['snmpd'] = array();
	$config['snmpd']['rocommunity'] = "public";
	$config['snmpd']['pollport'] = "161";
}

if (!is_array($config['snmpd']['modules'])) {
	$config['snmpd']['modules'] = array();
	$config['snmpd']['modules']['mibii'] = true;
	$config['snmpd']['modules']['netgraph'] = true;
	$config['snmpd']['modules']['pf'] = true;
	$config['snmpd']['modules']['hostres'] = true;
	$config['snmpd']['modules']['bridge'] = true;
	$config['snmpd']['modules']['ucd'] = true;
	$config['snmpd']['modules']['regex'] = true;
}

$pconfig['enable'] = isset($config['snmpd']['enable']);
$pconfig['pollport'] = $config['snmpd']['pollport'];
$pconfig['syslocation'] = $config['snmpd']['syslocation'];
$pconfig['syscontact'] = $config['snmpd']['syscontact'];
$pconfig['rocommunity'] = $config['snmpd']['rocommunity'];
/* disabled until some docs show up on what this does.
$pconfig['rwenable'] = isset($config['snmpd']['rwenable']);
$pconfig['rwcommunity'] = $config['snmpd']['rwcommunity'];
*/
$pconfig['trapenable'] = isset($config['snmpd']['trapenable']);
$pconfig['trapserver'] = $config['snmpd']['trapserver'];
$pconfig['trapserverport'] = $config['snmpd']['trapserverport'];
$pconfig['trapstring'] = $config['snmpd']['trapstring'];

$pconfig['mibii'] = isset($config['snmpd']['modules']['mibii']);
$pconfig['netgraph'] = isset($config['snmpd']['modules']['netgraph']);
$pconfig['pf'] = isset($config['snmpd']['modules']['pf']);
$pconfig['hostres'] = isset($config['snmpd']['modules']['hostres']);
$pconfig['bridge'] = isset($config['snmpd']['modules']['bridge']);
$pconfig['ucd'] = isset($config['snmpd']['modules']['ucd']);
$pconfig['regex'] = isset($config['snmpd']['modules']['regex']);
$pconfig['bindip'] = $config['snmpd']['bindip'];

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable']) {
		if (strstr($_POST['syslocation'], "#")) {
			$input_errors[] = gettext("Invalid character '#' in system location");
		}
		if (strstr($_POST['syscontact'], "#")) {
			$input_errors[] = gettext("Invalid character '#' in system contact");
		}
		if (strstr($_POST['rocommunity'], "#")) {
			$input_errors[] = gettext("Invalid character '#' in read community string");
		}

		$reqdfields = explode(" ", "rocommunity");
		$reqdfieldsn = array(gettext("Community"));
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		$reqdfields = explode(" ", "pollport");
		$reqdfieldsn = array(gettext("Polling Port"));
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);


	}

	if ($_POST['trapenable']) {
		if (strstr($_POST['trapstring'], "#")) {
			$input_errors[] = gettext("Invalid character '#' in SNMP trap string");
		}

		$reqdfields = explode(" ", "trapserver");
		$reqdfieldsn = array(gettext("Trap server"));
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		$reqdfields = explode(" ", "trapserverport");
		$reqdfieldsn = array(gettext("Trap server port"));
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		$reqdfields = explode(" ", "trapstring");
		$reqdfieldsn = array(gettext("Trap string"));
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
	}

/* disabled until some docs show up on what this does.
	if ($_POST['rwenable']) {
		$reqdfields = explode(" ", "rwcommunity");
		$reqdfieldsn = explode(",", "Write community string");
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
	}
*/



	if (!$input_errors) {
		$config['snmpd']['enable'] = $_POST['enable'] ? true : false;
		$config['snmpd']['pollport'] = $_POST['pollport'];
		$config['snmpd']['syslocation'] = $_POST['syslocation'];
		$config['snmpd']['syscontact'] = $_POST['syscontact'];
		$config['snmpd']['rocommunity'] = $_POST['rocommunity'];
		/* disabled until some docs show up on what this does.
		$config['snmpd']['rwenable'] = $_POST['rwenable'] ? true : false;
		$config['snmpd']['rwcommunity'] = $_POST['rwcommunity'];
		*/
		$config['snmpd']['trapenable'] = $_POST['trapenable'] ? true : false;
		$config['snmpd']['trapserver'] = $_POST['trapserver'];
		$config['snmpd']['trapserverport'] = $_POST['trapserverport'];
		$config['snmpd']['trapstring'] = $_POST['trapstring'];

		$config['snmpd']['modules']['mibii'] = $_POST['mibii'] ? true : false;
		$config['snmpd']['modules']['netgraph'] = $_POST['netgraph'] ? true : false;
		$config['snmpd']['modules']['pf'] = $_POST['pf'] ? true : false;
		$config['snmpd']['modules']['hostres'] = $_POST['hostres'] ? true : false;
		$config['snmpd']['modules']['bridge'] = $_POST['bridge'] ? true : false;
		$config['snmpd']['modules']['ucd'] = $_POST['ucd'] ? true : false;
		$config['snmpd']['modules']['regex'] = $_POST['regex'] ? true : false;
		$config['snmpd']['bindip'] = $_POST['bindip'];

		write_config();

		$retval = 0;
		$retval = services_snmpd_configure();
		$savemsg = get_std_save_message($retval);
	}
}

function build_iplist() {
	$listenips = get_possible_listen_ips();
	$iplist = array();
	$iplist[''] = 'All';

	foreach ($listenips as $lip => $ldescr) {
		$iplist[$lip] = $ldescr;
	}
	unset($listenips);

	return($iplist);
}

$pgtitle = array(gettext("Services"), gettext("SNMP"));
$shortcut_section = "snmp";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg);
}

$form = new Form();

$section = new Form_Section('SNMP Daemon');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable the SNMP Daemon and its controls',
	$pconfig['enable']
));

$form->add($section);

$section = new Form_Section('SNMP Daemon settings');

$section->addInput(new Form_Input(
	'pollport',
	'Polling Port',
	'text',
	($pconfig['pollport'] ? $pconfig['pollport']:'161')
))->setHelp('Enter the port to accept polling events on (default 161)');

$section->addInput(new Form_Input(
	'syslocation',
	'System Location',
	'text',
	$pconfig['syslocation']
));

$section->addInput(new Form_Input(
	'syscontact',
	'System Contact',
	'text',
	$pconfig['syscontact']
));

$section->addInput(new Form_Input(
	'rocommunity',
	'Read Community String',
	'text',
	$pconfig['rocommunity']
))->setHelp('The community string is like a password, restricting access to querying SNMP to hosts knowing the community string. Use a strong value here to protect from unauthorized information disclosure.');

$form->add($section);

$section = new Form_Section('SNMP Traps Enable');

$section->addInput(new Form_Checkbox(
	'trapenable',
	'Enable',
	'Enable the SNMP Trap and its controls',
	$pconfig['trapenable']
))->toggles('.toggle-traps');

$form->add($section);

$section = new Form_Section('SNMP Trap settings');

if ($pconfig['trapenable']) {
	$section->addClass('toggle-traps', 'in');
} else {
	$section->addClass('toggle-traps', 'collapse');
}

$section->addInput(new Form_Input(
	'trapserver',
	'Trap server',
	'text',
	$pconfig['trapserver']
))->setHelp('Enter the trap server name');

$section->addInput(new Form_Input(
	'trapserverport',
	'Trap Server Port',
	'text',
	($pconfig['trapserverport'] ? $pconfig['trapserverport']:'162')
))->setHelp('Enter the port to send the traps to (default 162)');

$section->addInput(new Form_Input(
	'trapstring',
	'SNMP Trap String',
	'text',
	$pconfig['trapstring']
));

$form->add($section);

$section = new Form_Section('SNMP Modules');

$group = new Form_MultiCheckboxGroup('SNMP modules');

$group->add(new Form_MultiCheckbox(
	'mibii',
	null,
	'MibII',
	$pconfig['mibii']
));

$group->add(new Form_MultiCheckbox(
	'netgraph',
	null,
	'Netgraph',
	$pconfig['netgraph']
));

$group->add(new Form_MultiCheckbox(
	'pf',
	null,
	'PF',
	$pconfig['pf']
));

$group->add(new Form_MultiCheckbox(
	'hostres',
	null,
	'Host Resources',
	$pconfig['hostres']
));

$group->add(new Form_MultiCheckbox(
	'ucd',
	null,
	'UCD',
	$pconfig['ucd']
));

$group->add(new Form_MultiCheckbox(
	'regex',
	null,
	'Regex',
	$pconfig['regex']
));

$section->add($group);
$form->add($section);

$section = new Form_Section('Interface Binding');

$section->addInput(new Form_Select(
	'bindip',
	'Bind Interface',
	$pconfig['bindip'],
	build_iplist()
));

$form->add($section);

print($form);
?>

<script type="text/javascript">
//<![CDATA[

// hostres requires mibii so we force that here
events.push(function() {

	noMibii = false;

	$('#junk').hide();
	hostresChange();

	function hostresChange() {
		if ($('#hostres').prop('checked')) {
			$('#mibii').prop('checked', true);
			noMibii = true;
		} else {
			noMibii = false;
		}
	}

	$('#hostres').change(function() {
		hostresChange();
	});


	$('#mibii').change(function() {
		if (noMibii) {
			$('#mibii').prop('checked', 'true');
		}
	});

	$('[name=btntoggleall]').hide();
});
//]]>
</script>

<?php include("foot.inc");
