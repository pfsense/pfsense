<?php
/* $Id$ */
/*
        load_balancer_protocol_edit.php
        part of pfSense (https://www.pfsense.org/)

        Copyright (C) 2008 Bill Marquette <bill.marquette@gmail.com>.
        All rights reserved.

        Redistribution and use in source and binary forms, with or without
        modification, are permitted provided that the following conditions are met:

        1. Redistributions of source code must retain the above copyright notice,
           this list of conditions and the following disclaimer.

        2. Redistributions in binary form must reproduce the above copyright
           notice, this list of conditions and the following disclaimer in the
           documentation and/or other materials provided with the distribution.

        THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
        INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
        AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
        AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
        OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
        SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
        INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
        CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
        ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
        POSSIBILITY OF SUCH DAMAGE.
*/
/*
	pfSense_MODULE:	routing
*/

##|+PRIV
##|*IDENT=page-services-loadbalancer-relay-protocol-edit
##|*NAME=Services: Load Balancer: Relay Protocol: Edit page
##|*DESCR=Allow access to the 'Services: Load Balancer: Relay Protocol: Edit' page.
##|*MATCH=load_balancer_relay_protocol_edit.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['load_balancer']['lbprotocol'])) {
	$config['load_balancer']['lbprotocol'] = array();
}
$a_protocol = &$config['load_balancer']['lbprotocol'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_protocol[$id]) {
	$pconfig = $a_protocol[$id];
	$pconfig['type'] = $a_protocol[$id]['type'];
	$pconfig['descr'] = $a_protocol[$id]['descr'];
	$pconfig['lbaction'] = array();
	$pconfig['options'] = $a_protocol[$id]['options'];
} else {
	/* Some sane page defaults */
	$pconfig['type'] = 'http';
}

$changedesc = gettext("Load Balancer: Relay Protocol:") . " ";
$changecount = 0;



if ($_POST) {
	$changecount++;

	unset($input_errors);
	$pconfig = $_POST;


	/* input validation */
	$reqdfields = explode(" ", "name type descr");
	$reqdfieldsn = array(gettext("Name"),gettext("Type"),gettext("Description"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	/* Ensure that our monitor names are unique */
	for ($i=0; isset($config['load_balancer']['lbprotocol'][$i]); $i++)
		if (($_POST['name'] == $config['load_balancer']['lbprotocol'][$i]['name']) && ($i != $id))
			$input_errors[] = gettext("This protocol name has already been used.  Protocol names must be unique.");

	if (strpos($_POST['name'], " ") !== false)
		$input_errors[] = gettext("You cannot use spaces in the 'name' field.");

	switch($_POST['type']) {
		case 'tcp':
		case 'http':
		case 'https':
		case 'dns': {
			break;
		}
	}

	if (!$input_errors) {
		$protent = array();
		if(isset($id) && $a_protocol[$id])
			$protent = $a_protocol[$id];
		if($protent['name'] != "")
			$changedesc .= " " . sprintf(gettext("modified '%s' load balancing protocol:"), $protent['name']);
		
		update_if_changed(gettext("name"), $protent['name'], $pconfig['name']);
		update_if_changed(gettext("type"), $protent['type'], $pconfig['type']);
		update_if_changed(gettext("description"), $protent['descr'], $pconfig['descr']);
		update_if_changed(gettext("type"), $protent['type'], $pconfig['type']);
		update_if_changed(gettext("action"), $protent['lbaction'], $pconfig['lbaction']);

		if (isset($id) && $a_protocol[$id]) {
			/* modify all virtual servers with this name */
/*
			for ($i = 0; isset($config['load_balancer']['virtual_server'][$i]); $i++) {
				if ($config['load_balancer']['virtual_server'][$i]['protocol'] == $a_protocol[$id]['name'])
					$config['load_balancer']['virtual_server'][$i]['protocol'] = $protent['name'];
			}
*/	
			$a_protocol[$id] = $protent;
		} else {
			$a_protocol[] = $protent;
    }	
    
		if ($changecount > 0) {
			/* Mark config dirty */
			mark_subsystem_dirty('loadbalancer');
			write_config($changedesc);
		}

		header("Location: load_balancer_relay_protocol.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("Load Balancer"),gettext("Relay Protocol"),gettext("Edit"));
$shortcut_section = "relayd";

include("head.inc");

$types = array("http" => gettext("HTTP"), "tcp" => gettext("TCP"), "dns" => gettext("DNS"));
?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script type="text/javascript">
function updateType(t){
	switch(t) {
<?php
	/* OK, so this is sick using php to generate javascript, but it needed to be done */
	foreach ($types as $key => $val) {
		echo "		case \"{$key}\": {\n";
		$t = $types;
		foreach ($t as $k => $v) {
			if ($k != $key) {
				echo "			jQuery('#{$k}').hide();\n";
			}
		}
		echo "		}\n";
	}
?>
	}
	jQuery('#' + t).show();
}

function num_options() {
	return jQuery('#options_table').children().length - 1;
}

/*
jQuery(document).ready(function(){
  $$('.action').each(function(action) {
    new Draggable(action, {revert: true, ghosting: true});
  });
  Droppables.add('actions', {
    accept: 'action', onDrop: function(action) {
      var new_action = new Element('li');
      new Draggable(new_action, {revert: true});
      $('action_list').appendChild(new_action);
    }
  });
});
*/
</script>

<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
	<form action="load_balancer_relay_protocol_edit.php" method="post" name="iform" id="iform">
	<table width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("Edit Load Balancer - Relay Protocol entry"); ?></td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Name"); ?></td>
			<td width="78%" class="vtable" colspan="2">
				<input name="name" type="text" <?if(isset($pconfig['name'])) echo "value=\"{$pconfig['name']}\"";?> size="16" maxlength="16">
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Type"); ?></td>
			<td width="78%" class="vtable" colspan="2">
				<select id="type" name="type">
<?
	foreach ($types as $key => $val) {
		if(isset($pconfig['type']) && $pconfig['type'] == $key) {
			$selected = " selected";
		} else {
			$selected = "";
		}
		echo "<option value=\"{$key}\" onclick=\"updateType('{$key}');\"{$selected}>{$val}</option>\n";
	}
?>
				</select>
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Description"); ?></td>
			<td width="78%" class="vtable" colspan="2">
				<input name="descr" type="text" <?if(isset($pconfig['descr'])) echo "value=\"{$pconfig['descr']}\"";?>size="64">
			</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("Add / remove available actions"); ?></td>
		</tr>
		<tr align="left" id="actions">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Actions"); ?></td>
			<td width="78%" class="vtable" colspan="2">
				<table>
					<tbody>
					<tr>
						<td>
							<center>
							<b><?=gettext("Available Actions"); ?></b>
							<br />
							<select id="available_action" name="available_action[]" multiple="true" size="5">
<?php
if (is_array($config['load_balancer']['lbaction'])) {
	foreach($config['load_balancer']['lbaction'] as $actent) {
		if($actent != '') echo "    <option value=\"{$actent['name']}\">{$actent['name']}</option>\n";
	}
}
echo "</select>";
?>
							<br />
						</td>
						<td valign="middle">
							<center>
								<input class="formbtn" type="button" name="copyToEnabled" value="<?=gettext("Add"); ?>" onclick="copyOption($('available_action'), $('lbaction'));" /><br />
								<input class="formbtn" type="button" name="removeFromEnabled" value="<?=gettext("Remove"); ?>" onclick="deleteOption($('lbaction'));" />
							</center>
						</td>

						<td>
							<center>
							<b><?=gettext("Enabled Actions"); ?></b>
							<br />
							<select id="lbaction" name="lbaction[]" multiple="true" size="5">
<?php
if (is_array($pconfig['lbaction'])) {
	foreach($pconfig['lbaction'] as $actent) {
		echo "    <option value=\"{$actent}\">{$actent}</option>\n";
	}
}
echo "</select>";
?>
							<br />
						</td>
					</tr>
					</tbody>
				</table>
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" onClick="AllOptions($('lbaction'), true); AllOptions($('available_action'), false);"><input type="button" class="formbtn" value="<?=gettext("Cancel"); ?>" onclick="history.back()">
				<?php if (isset($id) && $a_protocol[$id] && $_GET['act'] != 'dup'): ?>
				<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>">
				<?php endif; ?>
			</td>
		</tr>
	</table>
	</form>
<br />
<?php include("fend.inc"); ?>
</body>
</html>
