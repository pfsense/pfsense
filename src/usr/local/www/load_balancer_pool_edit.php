<?php
/*
	load_balancer_pool_edit.php
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
##|*IDENT=page-loadbalancer-pool-edit
##|*NAME=Load Balancer: Pool: Edit
##|*DESCR=Allow access to the 'Load Balancer: Pool: Edit' page.
##|*MATCH=load_balancer_pool_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("filter.inc");
require_once("util.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/load_balancer_pool.php');

if (!is_array($config['load_balancer']['lbpool'])) {
	$config['load_balancer']['lbpool'] = array();
}

$a_pool = &$config['load_balancer']['lbpool'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_pool[$id]) {
	$pconfig['name'] = $a_pool[$id]['name'];
	$pconfig['mode'] = $a_pool[$id]['mode'];
	$pconfig['descr'] = $a_pool[$id]['descr'];
	$pconfig['port'] = $a_pool[$id]['port'];
	$pconfig['retry'] = $a_pool[$id]['retry'];
	$pconfig['servers'] = &$a_pool[$id]['servers'];
	$pconfig['serversdisabled'] = &$a_pool[$id]['serversdisabled'];
	$pconfig['monitor'] = $a_pool[$id]['monitor'];
}

$changedesc = gettext("Load Balancer: Pool:") . " ";
$changecount = 0;

$allowed_modes = array("loadbalance", "failover");

if ($_POST) {
	$changecount++;

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "name mode port monitor servers");
	$reqdfieldsn = array(gettext("Name"), gettext("Mode"), gettext("Port"), gettext("Monitor"), gettext("Server List"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	/* Ensure that our pool names are unique */
	for ($i=0; isset($config['load_balancer']['lbpool'][$i]); $i++) {
		if (($_POST['name'] == $config['load_balancer']['lbpool'][$i]['name']) && ($i != $id)) {
			$input_errors[] = gettext("This pool name has already been used.  Pool names must be unique.");
		}
	}

	if (preg_match('/[ \/]/', $_POST['name'])) {
		$input_errors[] = gettext("You cannot use spaces or slashes in the 'name' field.");
	}

	if (strlen($_POST['name']) > 16) {
		$input_errors[] = gettext("The 'name' field must be 16 characters or less.");
	}

	if (in_array($_POST['name'], $reserved_table_names)) {
		$input_errors[] = sprintf(gettext("The name '%s' is a reserved word and cannot be used."), $_POST['name']);
	}

	if (is_alias($_POST['name'])) {
		$input_errors[] = sprintf(gettext("Sorry, an alias is already named %s."), $_POST['name']);
	}

	if (!is_portoralias($_POST['port'])) {
		$input_errors[] = gettext("The port must be an integer between 1 and 65535, or a port alias.");
	}

	// May as well use is_port as we want a positive integer and such.
	if (!empty($_POST['retry']) && !is_port($_POST['retry'])) {
		$input_errors[] = gettext("The retry value must be an integer between 1 and 65535.");
	}

	if (!in_array($_POST['mode'], $allowed_modes)) {
		$input_errors[] = gettext("The submitted mode is not valid.");
	}

	if (is_array($_POST['servers'])) {
		foreach ($pconfig['servers'] as $svrent) {
			if (!is_ipaddr($svrent) && !is_subnetv4($svrent)) {
				$input_errors[] = sprintf(gettext("%s is not a valid IP address or IPv4 subnet (in \"enabled\" list)."), $svrent);
			} else if (is_subnetv4($svrent) && subnet_size($svrent) > 64) {
				$input_errors[] = sprintf(gettext("%s is a subnet containing more than 64 IP addresses (in \"enabled\" list)."), $svrent);
			}
		}
	}

	if (is_array($_POST['serversdisabled'])) {
		foreach ($pconfig['serversdisabled'] as $svrent) {
			if (!is_ipaddr($svrent) && !is_subnetv4($svrent)) {
				$input_errors[] = sprintf(gettext("%s is not a valid IP address or IPv4 subnet (in \"disabled\" list)."), $svrent);
			} else if (is_subnetv4($svrent) && subnet_size($svrent) > 64) {
				$input_errors[] = sprintf(gettext("%s is a subnet containing more than 64 IP addresses (in \"disabled\" list)."), $svrent);
			}
		}
	}

	$m = array();

	for ($i=0; isset($config['load_balancer']['monitor_type'][$i]); $i++) {
		$m[$config['load_balancer']['monitor_type'][$i]['name']] = $config['load_balancer']['monitor_type'][$i];
	}

	if (!isset($m[$_POST['monitor']])) {
		$input_errors[] = gettext("Invalid monitor chosen.");
	}

	if (!$input_errors) {
		$poolent = array();
		if (isset($id) && $a_pool[$id]) {
			$poolent = $a_pool[$id];
		}

		if ($poolent['name'] != "") {
			$changedesc .= sprintf(gettext(" modified '%s' pool:"), $poolent['name']);
		}

		update_if_changed("name", $poolent['name'], $_POST['name']);
		update_if_changed("mode", $poolent['mode'], $_POST['mode']);
		update_if_changed("description", $poolent['descr'], $_POST['descr']);
		update_if_changed("port", $poolent['port'], $_POST['port']);
		update_if_changed("retry", $poolent['retry'], $_POST['retry']);
		update_if_changed("servers", $poolent['servers'], $_POST['servers']);
		update_if_changed("serversdisabled", $poolent['serversdisabled'], $_POST['serversdisabled']);
		update_if_changed("monitor", $poolent['monitor'], $_POST['monitor']);

		if (isset($id) && $a_pool[$id]) {
			/* modify all virtual servers with this name */
			for ($i = 0; isset($config['load_balancer']['virtual_server'][$i]); $i++) {
				if ($config['load_balancer']['virtual_server'][$i]['lbpool'] == $a_pool[$id]['name']) {
					$config['load_balancer']['virtual_server'][$i]['lbpool'] = $poolent['name'];
				}
			}
			$a_pool[$id] = $poolent;
		} else {
			$a_pool[] = $poolent;
		}

		if ($changecount > 0) {
			/* Mark pool dirty */
			mark_subsystem_dirty('loadbalancer');
			write_config($changedesc);
		}

		header("Location: load_balancer_pool.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("Load Balancer"), gettext("Pools"), gettext("Edit"));
$shortcut_section = "relayd";

include("head.inc");
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Disables the specified input element
	function disableInput(id, disable) {
		$('#' + id).prop("disabled", disable);
	}

	// Select every option in the specified multiselect
	function AllServers(id, selectAll) {
	   for (i = 0; i < id.length; i++)	   {
		   id.eq(i).prop('selected', selectAll);
	   }
	}

	// Move all selected options from one multiselect to another
	function moveOptions(From, To)	{
		var len = From.length;
		var option;

		if (len > 0) {
			for (i=0; i<len; i++) {
				if (From.eq(i).is(':selected')) {
					option = From.eq(i).val();
					value  = From.eq(i).text();
					To.append(new Option(value, option));
					From.eq(i).remove();
				}
			}
		}
	}

	function checkPoolControls() {

		if ($("#mode").val() == "failover") {
			disableInput('movetoenabled', $('[name="servers[]"] option').length > 0);
		} else {
			disableInput('movetoenabled', false);
		}
	}

	// Move (copy/delete) all but one of the items in the Enabled (server) list to the Disabled list
	function enforceFailover() {
		if ($('#mode').val() != 'failover') {
			return;
		}

		var len = $('[name="servers[]"] option').length;
		var option;

		if (len > 1) {
			for (i=len-1; i>0; i--) {
				option = $('[name="servers[]"] option').eq(i).val();
				$('[name="serversdisabled[]"]').append(new Option(option, option));
				$('[name="servers[]"] option').eq(i).remove();
			}
		}
	}

	// On click . .
	$("#btnaddtopool").click(function() {
		$('[name="servers[]"]').append(new Option($('#ipaddr').val(), $('#ipaddr').val()));
		enforceFailover();
		checkPoolControls();
	});

	$('#mode').on('change', function() {
		enforceFailover();
		checkPoolControls();
	});

	$("#removeenabled").click(function() {
		$('[name="servers[]"] option:selected').remove();
	});

	$("#removedisabled").click(function() {
		$('[name="serversdisabled[]"] option:selected').remove();
	});

	$("#movetodisabled").click(function() {
		moveOptions($('[name="servers[]"] option'), $('[name="serversdisabled[]"]'));
	});

	$("#movetoenabled").click(function() {
		moveOptions($('[name="serversdisabled[]"] option'), $('[name="servers[]"]'));
	});

	// On initial page load
	checkPoolControls();

	// On submit
	$('form').submit(function() {
		AllServers($('[name="servers[]"] option'), true);
		AllServers($('[name="serversdisabled[]"] option'), true);
	});

});
//]]>
</script>

<?php
if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Add/Edit Load Balancer - Pool Entry');

$section->addInput(new Form_Input(
	'name',
	'Name',
	'text',
	$pconfig['name']
));

$section->addInput(new Form_Select(
	'mode',
	'Mode',
	$pconfig['mode'],
	array(
		'loadbalance' => gettext('Load Balance'),
		'failover' => gettext('Manual Failover')
	)
));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
));

$section->addInput(new Form_Input(
	'port',
	'Port',
	'text',
	$pconfig['port']
))->setHelp('This is the port your servers are listening on. You may also specify a port alias listed in Firewall -> Aliases here.');

$section->addInput(new Form_Input(
	'retry',
	'Retry',
	'number',
	$pconfig['retry'],
	['min' => '1', 'max' => '65536']
))->setHelp('Optionally specify how many times to retry checking a server before declaring it down.');

$form->add($section);

$section = new Form_Section('Add Item to the Pool');

$monitorlist = array();

foreach ($config['load_balancer']['monitor_type'] as $monitor) {
	$monitorlist[$monitor['name']] = $monitor['name'];
}

if (count($config['load_balancer']['monitor_type'])) {
	$section->addInput(new Form_Select(
		'monitor',
		'Monitor',
		$pconfig['monitor'],
		$monitorlist
	));
} else {
	$section->addInput(new Form_StaticText(
		'Monitor',
		'Please add a monitor IP address on the monitors tab if you wish to use this feature."'
	));
}

$group = new Form_Group('Server IP Address');

$group->add(new Form_IpAddress(
	'ipaddr',
	'IP Address',
	$pconfig['ipaddr']
));

$group->add(new Form_Button(
	'btnaddtopool',
	'Add to pool',
	null,
	'fa-plus'
))->setAttribute('type','button')->addClass('btn-success');

$section->add($group);

$form->add($section);

$section = new Form_Section('Current Pool Members');

$group = new Form_Group('Members');

$list = array();

if (is_array($pconfig['serversdisabled'])) {
	foreach ($pconfig['serversdisabled'] as $svrent) {
		if ($svrent != '') {
			$list[$svrent] = $svrent;
		}
	}
}

$group->add(new Form_Select(
	'serversdisabled',
	null,
	$pconfig['serversdisabled'],
	$list,
	true
))->setHelp('Disabled');

$group->add(new Form_Select(
	'servers',
	null,
	$pconfig['servers'],
	is_array($pconfig['servers']) ? array_combine($pconfig['servers'], $pconfig['servers']) : array(),
	true
))->setHelp('Enabled (Default)');

$section->add($group);

$group = new Form_Group('');

$group->add(new Form_Button(
	'removedisabled',
	'Remove',
	null,
	'fa-trash'
))->setAttribute('type','button')->addClass('btn-danger btn-sm');

$group->add(new Form_Button(
	'removeenabled',
	'Remove',
	null,
	'fa-trash'
))->setAttribute('type','button')->addClass('btn-danger btn-sm');

$section->add($group);

$group = new Form_Group('');

$group->add(new Form_Button(
	'movetoenabled',
	'Move to enabled list',
	null,
	'fa-angle-double-right'
))->setAttribute('type','button')->addClass('btn-info btn-sm');

$group->add(new Form_Button(
	'movetodisabled',
	'Move to disabled list',
	null,
	'fa-angle-double-left'
))->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->add($group);

if (isset($id) && $a_pool[$id] && $_GET['act'] != 'dup') {
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->add($section);

print($form);
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// --------- Autocomplete -----------------------------------------------------------------------------------------
	var customarray = <?= json_encode(get_alias_list(array("port", "url_ports", "urltable_ports"))) ?>;

	$('#port').autocomplete({
		source: customarray
	});
});
//]]>
</script>

<?php
include("foot.inc");
