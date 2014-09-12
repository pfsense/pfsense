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
##|*IDENT=page-services-loadbalancer-relay-action-edit
##|*NAME=Services: Load Balancer: Relay Action: Edit page
##|*DESCR=Allow access to the 'Services: Load Balancer: Relay Action: Edit' page.
##|*MATCH=load_balancer_relay_action_edit.php*
##|-PRIV

require("guiconfig.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/load_balancer_relay_action.php');

if (!is_array($config['load_balancer']['lbaction'])) {
	$config['load_balancer']['lbaction'] = array();
}
$a_action = &$config['load_balancer']['lbaction'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_action[$id]) {
  $pconfig = array();
	$pconfig = $a_action[$id];
} else {
  // XXX - TODO, this isn't sane for this page :)
	/* Some sane page defaults */
	$pconfig['protocol'] = 'http';
	$pconfig['direction'] = 'request';
	$pconfig['type'] = 'cookie';	
	$pconfig['action'] = 'change';
}

$changedesc = gettext("Load Balancer: Relay Action:") . " ";
$changecount = 0;

$kv = array('key', 'value');
$vk = array('value', 'key');
$hr_actions = array();
$hr_actions['append'] = $vk;
$hr_actions['change'] = $kv;
$hr_actions['expect'] = $vk;
$hr_actions['filter'] = $vk;
$hr_actions['hash'] = 'key';
$hr_actions['log'] = 'key';
// mark is disabled until I can figure out how to make the display clean
//$hr_actions['mark'] = array('value', 'key', 'id');
//$hr_actions[] = 'label';
//$hr_actions[] = 'no label';
$hr_actions['remove'] = 'key';
//$hr_actions[] = 'return error';
/* Setup decision tree */
$action = array();
$actions['protocol']['http'] = 'HTTP';
$actions['protocol']['tcp'] = 'TCP';
$actions['protocol']['dns'] = 'DNS';
$actions['direction'] = array();
$actions['direction']['request'] = array();
$actions['direction']['request']['cookie'] = $hr_actions;
$actions['direction']['request']['header'] = $hr_actions;
$actions['direction']['request']['path'] = $hr_actions;
$actions['direction']['request']['query'] = $hr_actions;
$actions['direction']['request']['url'] = $hr_actions;
$actions['direction']['response'] = array();
$actions['direction']['response']['cookie'] = $hr_actions;
$actions['direction']['response']['header'] = $hr_actions;
//$action['http']['tcp'] = array();
//$action['http']['ssl'] = array();



if ($_POST) {
	$changecount++;

	unset($input_errors);
	$pconfig = $_POST;

  // Peel off the action and type from the post and fix $pconfig
  $action = explode('_', $pconfig['action']);
  $pconfig['action'] = $action[2];
  $pconfig['type'] = $action[1];
  unset($pconfig["type_{$pconfig['direction']}"]);

	/* input validation */
	$reqdfields = explode(" ", "name protocol direction action descr");
	$reqdfieldsn = array(gettext("Name"),gettext("Protocol"),gettext("Direction"),gettext("Action"),gettext("Description"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	/* Ensure that our monitor names are unique */
	for ($i=0; isset($config['load_balancer']['lbactions'][$i]); $i++)
		if (($_POST['name'] == $config['load_balancer']['lbactions'][$i]['name']) && ($i != $id))
			$input_errors[] = gettext("This action name has already been used.  Action names must be unique.");

	if (strpos($_POST['name'], " ") !== false)
		$input_errors[] = gettext("You cannot use spaces in the 'name' field.");

	if (!$input_errors) {
		$actent = array();
		if(isset($id) && $a_action[$id])
			$actent = $a_action[$id];
		if($actent['name'] != "")
			$changedesc .= " " . sprintf(gettext("modified '%s' action:"), $actent['name']);
		
		update_if_changed("name", $actent['name'], $pconfig['name']);
		update_if_changed("protocol", $actent['protocol'], $pconfig['protocol']);
		update_if_changed("type", $actent['type'], $pconfig['type']);
		update_if_changed("direction", $actent['direction'], $pconfig['direction']);
		update_if_changed("description", $actent['descr'], $pconfig['descr']);
    update_if_changed("action", $actent['action'], $pconfig['action']);
    switch ($pconfig['action']) {
      case "append":
      case "change":
      case "expect":
      case "filter": {
        update_if_changed("value", $actent['options']['value'], $pconfig['option_action_value']);
        update_if_changed("key", $actent['options']['akey'], $pconfig['option_action_key']);
        break; 
      }
      case "hash":
      case "log": {
        update_if_changed("key", $actent['options']['akey'], $pconfig['option_action_key']);
        break;
      }
    }
    
		if (isset($id) && $a_action[$id]) {
//    XXX - TODO
			/* modify all virtual servers with this name */
//			for ($i = 0; isset($config['load_balancer']['virtual_server'][$i]); $i++) {
//				if ($config['load_balancer']['virtual_server'][$i]['protocol'] == $a_protocol[$id]['name'])
//					$config['load_balancer']['virtual_server'][$i]['protocol'] = $protent['name'];
//			}
			$a_action[$id] = $actent;
		} else {
			$a_action[] = $actent;
		}
		if ($changecount > 0) {
			/* Mark config dirty */
			mark_subsystem_dirty('loadbalancer');
			write_config($changedesc);
		}

		header("Location: load_balancer_relay_action.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("Load Balancer"),gettext("Relay Action"),gettext("Edit"));
$shortcut_section = "relayd";

include("head.inc");
	$types = array("http" => gettext("HTTP"), "tcp" => gettext("TCP"), "dns" => gettext("DNS"));
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<script type="text/javascript">

function updateProtocol(m) {
  // Default to HTTP
  if (m == "") {
    m = "http";
  }
	switch (m) {
		case "dns": {
			jQuery('#type_row').hide();
			jQuery('#tcp_options_row').hide();
			jQuery('#ssl_options_row').hide();
			jQuery('#direction_row').hide();
			jQuery('#action_row').hide();
			break;
		}
		case "tcp": {
			jQuery('#type_row').hide();
			jQuery('#tcp_options_row').show();
			jQuery('#ssl_options_row').hide();
			jQuery('#direction_row').hide();
			jQuery('#action_row').hide();
			break;
		}
		case "http": {
			jQuery('#type_row').show();
			jQuery('#tcp_options_row').hide();
			jQuery('#ssl_options_row').show();
			jQuery('#direction_row').show();
			jQuery('#direction').prop('disabled',false);
			jQuery('#type_' + jQuery('#direction').val()).prop('disabled',false);
			jQuery('#type_' + jQuery('#direction').val()).show();
			jQuery('#action_row').show();
<?php
  /* Generate lots of .appear() entries for the action row select list
   * based on what's been either preconfigured or "defaults"
   * This really did have to be done in PHP.
   */
  if (isset($pconfig['type'])) {
    $dtype = $pconfig['type'];
    $ddir = $pconfig['direction'];
  } else {
    $dtype = "cookie";
    $ddir = "request";
  }
	foreach ($actions['direction'][$ddir] as $type => $tv) {
		foreach ($actions['direction'][$ddir][$type] as $action => $av ) {
			if($dtype == $type) {
				echo "jQuery('#{$ddir}_{$type}_{$action}').show();";
 			}
		}
	}
?>

			break;
		}
	}
}

function updateDirection(d) {
  // Default to request
  if (d == "") {
    d = "request";
  }

  switch (d) {
    case "request": {
      jQuery('#type_response').prop('disabled',true);
      jQuery('#type_response').hide();
      jQuery('#type_request').prop('disabled',false);
      jQuery('#type_request').show();
      break;
    }
    case "response": {
      jQuery('#type_request').prop('disabled',true);
      jQuery('#type_request').hide();
      jQuery('#type_response').prop('disabled',false);
      jQuery('#type_response').show();
      break;
    }
  }
}


function updateType(t){
  // Default to action_row
  // XXX - does this actually make any sense?
  if (t == "") {
    t = "action_row";
  }

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


function updateAction(a) {
  // Default to change
  if (a == "") {
    a = "change";
  }
  switch(a) {
    case "append": {
      jQuery('#input_action_value').show();
      jQuery('#option_action_value').prop('disabled',false);
      jQuery('#input_action_key').show();
      jQuery('#option_action_key').prop('disabled',false);
      jQuery('#input_action_id').hide();
      jQuery('#option_action_id').prop('disabled',true);
      jQuery('#action_action_value').html("&nbsp;to&nbsp;");
      jQuery('#action_action_id').html("");
      break;
    }
    case "change": {
      jQuery('#input_action_value').show();
      jQuery('#option_action_value').prop('disabled',false);
      jQuery('#input_action_key').show();
      jQuery('#option_action_key').prop('disabled',false);
      jQuery('#input_action_id').hide();
      jQuery('#option_action_id').prop('disabled',true);
      jQuery('#action_action_value').html("&nbsp;of&nbsp;");
      jQuery('#action_action_id').html("");
      break;
    }
    case "expect": {
      jQuery('#input_action_value').show();
      jQuery('#option_action_value').prop('disabled',false);
      jQuery('#input_action_key').show();
      jQuery('#option_action_key').prop('disabled',false);
      jQuery('#input_action_id').hide();
      jQuery('#option_action_id').prop('disabled',true);
      jQuery('#action_action_value').html("&nbsp;from&nbsp;");
      jQuery('#action_action_id').html("");
      break;
    }
    case "filter": {
      jQuery('#input_action_value').show();
      jQuery('#option_action_value').prop('disabled',false);
      jQuery('#input_action_key').show();
      jQuery('#option_action_key').prop('disabled',false);
      jQuery('#input_action_id').hide();
      jQuery('#option_action_id').prop('disabled',true);
      jQuery('#action_action_value').html("&nbsp;from&nbsp;");
      jQuery('#action_action_id').html("");
      break;
    }
    case "hash": {
      jQuery('#input_action_value').hide();
      jQuery('#option_action_value').prop('disabled',true);
      jQuery('#input_action_key').show();
      jQuery('#option_action_key').prop('disabled',false);
      jQuery('#input_action_id').hide();
      jQuery('#option_action_id').prop('disabled',true);
      jQuery('#action_action_value').html("");
      jQuery('#action_action_id').html("");
      break;
    }
    case "log": {
      jQuery('#input_action_value').hide();
      jQuery('#option_action_value').prop('disabled',true);
      jQuery('#input_action_key').show();
      jQuery('#option_action_key').prop('disabled',false);
      jQuery('#input_action_id').hide();
      jQuery('#option_action_id').prop('disabled',true);
      jQuery('#action_action_value').html("");
      jQuery('#action_action_id').html("");
      break;
    }
    case "mark": {
      jQuery('#input_action_value').show();
      jQuery('#option_action_value').prop('disabled',false);
      jQuery('#input_action_key').show();
      jQuery('#option_action_key').prop('disabled',false);
      jQuery('#input_action_id').show();
      jQuery('#option_action_id').prop('disabled',false);
      jQuery('#action_action_value').html("&nbsp;from&nbsp;");
      jQuery('#action_action_id').html("&nbsp;with&nbsp;");
      break;
    }
  }
}


function num_options() {
	return jQuery('#options_table').children().length - 1;
}


jQuery(document).ready(function() {
  updateProtocol('<?=htmlspecialchars($pconfig['protocol'])?>');  
  updateDirection('<?=htmlspecialchars($pconfig['direction'])?>');  
  updateType('<?=htmlspecialchars($pconfig['type'])?>');  
  updateAction('<?=htmlspecialchars($pconfig['action'])?>');  
});

</script>

<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
	<form action="load_balancer_relay_action_edit.php" method="post" name="iform" id="iform">
	<table width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("Edit Load Balancer - Relay Action entry"); ?></td>
		</tr>
		<tr align="left" id="name">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Name"); ?></td>
			<td width="78%" class="vtable" colspan="2">
				<input name="name" type="text" <?if(isset($pconfig['name'])) echo "value=\"{$pconfig['name']}\"";?> size="16" maxlength="16">
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Description"); ?></td>
			<td width="78%" class="vtable" colspan="2">
				<input name="descr" type="text" <?if(isset($pconfig['descr'])) echo "value=\"{$pconfig['descr']}\"";?>size="64">
			</td>
		</tr>
<!-- Protocol -->
		<tr align="left" id="protocol_row">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Protocol"); ?></td>
			<td width="78%" class="vtable" colspan="2">
				<select id="protocol" name="protocol">
<?
	foreach ($actions['protocol'] as $key => $val) {
		if(isset($pconfig['protocol']) && $pconfig['protocol'] == $key) {
			$selected = " selected";
		} else {
			$selected = "";
		}
		echo "<option value=\"{$key}\" onclick=\"updateProtocol('{$key}');\"{$selected}>{$val}</option>\n";
	}
?>
				</select>
			</td>
		</tr>

<!-- Direction -->
		<tr align="left" id="direction_row">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Direction"); ?></td>
			<td width="78%" class="vtable" colspan="2">
				<select id="direction" name="direction" style="disabled">
<?
	foreach ($actions['direction'] as $key => $val) {
		if(isset($pconfig['direction']) && $pconfig['direction'] == $key) {
			$selected = " selected";
		} else {
			$selected = "";
		}
		echo "<option value=\"{$key}\" onclick=\"updateDirection('{$key}');\"{$selected}>{$key}</option>\n";
	}
?>
				</select>

			</td>
		</tr>

<!-- Type -->
    <tr align="left" id="type_row"<?= $pconfig['protocol'] == "http" ? "" : " style=\"display:none;\""?>>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Type"); ?></td>
			<td width="78%" class="vtable" colspan="2">
<?
	foreach ($actions['direction'] as $dir => $v) {
		echo"		<select id=\"type_{$dir}\" name=\"type_{$dir}\" style=\"display:none; disabled;\">";
		foreach ($actions['direction'][$dir] as $key => $val) {
			if(isset($pconfig['type']) && $pconfig['type'] == $key) {
				$selected = " selected";
			} else {
				$selected = "";
			}
			echo "<option value=\"{$key}\" onclick=\"updateDirection('$key');\"{$selected}>{$key}</option>\n";
		}
	}
?>
				</select>
			</td>
		</tr>

<!-- Action -->
    <tr align="left" id="action_row"<?= $pconfig['protocol'] == "http" ? "" : " style=\"display:none;\""?>>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Action"); ?></td>
			<td width="78%" class="vtable" colspan="2">
				<select id="action" name="action" style=\"display: none;\">
<?
	foreach ($actions['direction'] as $dir => $dv) {
		foreach ($actions['direction'][$dir] as $type => $tv) {
			foreach ($actions['direction'][$dir][$type] as $action => $av ) {
				if(isset($pconfig['action']) && $pconfig['action'] == $action) {
					$selected = " selected";
				} else if ($action == "change" ){
  					$selected = " selected";
  				} else {
  					$selected = "";
				}
				echo "<option id=\"{$dir}_{$type}_{$action}\" value=\"{$dir}_{$type}_{$action}\" onClick=\"updateAction('$action');\" style=\"display: none;\"{$selected}>{$action}</option>\n";
			}
		}
	}
?>
				</select>
<br />
<table><tr>
<td><div id="input_action_value"><?=gettext("Value"); ?>&nbsp;<input id="option_action_value" name="option_action_value" type="text" <?if(isset($pconfig['options']['value'])) echo "value=\"{$pconfig['options']['value']}\"";?>size="20"></div></td>
<td><div id="action_action_value"></div></td>
<td><div id="input_action_key"><?=gettext("Key"); ?>&nbsp;<input id="option_action_key" name="option_action_key" type="text" <?if(isset($pconfig['options']['akey'])) echo "value=\"{$pconfig['options']['akey']}\"";?>size="20"></div></td>
<td><div id="action_action_id"></div></td>
<td><div id="input_action_id"><?=gettext("ID"); ?>&nbsp;<input id="option_action_id" name="option_action_id" type="text" <?if(isset($pconfig['options']['id'])) echo "value=\"{$pconfig['options']['id']}\"";?>size="20"></div></td>
</tr></table>
			</td>
		</tr>
		<tr align="left" id="tcp_options_row"<?= $pconfig['protocol'] == "tcp" ? "" : " style=\"display:none;\""?>>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Options"); ?></td>
			<td width="78%" class="vtable" colspan="2">
				XXX: <?=gettext("TODO"); ?>
				<select id="options" name="options">
<!-- XXX TODO >
<?
	foreach ($types as $key => $val) {
		if(isset($pconfig['protocol']) && $pconfig['protocol'] == $key) {
			$selected = " selected";
		} else {
			$selected = "";
		}
		echo "<option value=\"{$key}\" onclick=\"updateType('{$key}');\"{$selected}>{$val}</option>\n";
	}
?>
				</select>
< XXX TODO -->
			</td>
		</tr>
		<tr align="left" id="ssl_options_row"<?= $pconfig['protocol'] == "http" ? "" : " style=\"display:none;\""?>>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Options"); ?></td>
			<td width="78%" class="vtable" colspan="2">
				XXX: <?=gettext("TODO"); ?>
<!-- XXX TODO >
				<select id="options" name="options">
<?
	foreach ($types as $key => $val) {
		if(isset($pconfig['protocol']) && $pconfig['protocol'] == $key) {
			$selected = " selected";
		} else {
			$selected = "";
		}
		echo "<option value=\"{$key}\" onclick=\"updateType('{$key}');\"{$selected}>{$val}</option>\n";
	}
?>
				</select>
< XXX TODO -->
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>">
				<input type="button" class="formbtn" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=$referer;?>'" />
				<?php if (isset($id) && $a_action[$id] && $_GET['act'] != 'dup'): ?>
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
