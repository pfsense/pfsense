<?php
/* $Id$ */
/*
        load_balancer_protocol_edit.php
        part of pfSense (http://www.pfsense.com/)

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
if (!is_array($config['load_balancer']['lbaction'])) {
	$config['load_balancer']['lbaction'] = array();
}
$a_action = &$config['load_balancer']['lbaction'];

if (isset($_POST['id']))
	$id = $_POST['id'];
else
	$id = $_GET['id'];

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

$changedesc = "Load Balancer: Relay Action: ";
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
	$reqdfields = explode(" ", "name protocol direction action desc");
	$reqdfieldsn = explode(",", "Name,Protocol,Direction,Action,Description");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	/* Ensure that our monitor names are unique */
	for ($i=0; isset($config['load_balancer']['lbactions'][$i]); $i++)
		if (($_POST['name'] == $config['load_balancer']['lbactions'][$i]['name']) && ($i != $id))
			$input_errors[] = "This action name has already been used.  Action names must be unique.";


	if (!$input_errors) {
		$actent = array();
		if(isset($id) && $a_action[$id])
			$actent = $a_action[$id];
		if($actent['name'] != "")
			$changedesc .= " modified '{$actent['name']}' action:";
		
		update_if_changed("name", $actent['name'], $pconfig['name']);
		update_if_changed("protocol", $actent['protocol'], $pconfig['protocol']);
		update_if_changed("type", $actent['type'], $pconfig['type']);
		update_if_changed("direction", $actent['direction'], $pconfig['direction']);
		update_if_changed("description", $actent['desc'], $pconfig['desc']);
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

$pgtitle = array("Services", "Load Balancer","Relay Action","Edit");
#$statusurl = "status_slbd_vs.php";
$statusurl = "status_slbd_pool.php";
$logurl = "diag_logs_relayd.php";

include("head.inc");
	$types = array("http" => "HTTP", "tcp" => "TCP", "dns" => "DNS");
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<script language="javascript">

function updateProtocol(m) {
  // Default to HTTP
  if (m == "") {
    m = "http";
  }
	switch (m) {
		case "dns": {
			$('type_row').hide();
			$('tcp_options_row').hide();
			$('ssl_options_row').hide();
			$('direction_row').hide();
			$('action_row').hide();
			break;
		}
		case "tcp": {
			$('type_row').hide();
			$('tcp_options_row').appear();
			$('ssl_options_row').hide();
			$('direction_row').hide();
			$('action_row').hide();
			break;
		}
		case "http": {
			$('type_row').appear();
			$('tcp_options_row').hide();
			$('ssl_options_row').appear();
			$('direction_row').appear();
			$('direction').enable();
			$('type_' + $('direction').getValue()).enable();
			$('type_' + $('direction').getValue()).appear();
			$('action_row').appear();
<?
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
 				echo "$('{$ddir}_{$type}_{$action}').appear();";
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
      $('type_response').disable();
      $('type_response').hide();
      $('type_request').enable();
      $('type_request').appear();
      break;    
    }
    case "response": {
      $('type_request').disable();    
      $('type_request').hide();    
      $('type_response').enable();    
      $('type_response').appear();    
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
				echo "			$('{$k}').hide();\n";
			}
		}
		echo "		}\n";
	}
?>
	}
	$(t).appear();	
}


function updateAction(a) {
  // Default to change
  if (a == "") {
    a = "change";
  }

  switch(a) {
    case "append": {
      $('input_action_value').appear();
      $('option_action_value').enable();
      $('input_action_key').appear();
      $('option_action_key').enable();
      $('input_action_id').hide();
      $('option_action_id').disable();
      $('action_action_value').update("&nbsp;to&nbsp;");
      $('action_action_id').update("");
      break;
    }
    case "change": {
      $('input_action_value').appear();
      $('option_action_value').enable();
      $('input_action_key').appear();
      $('option_action_key').enable();
      $('input_action_id').hide();
      $('option_action_id').disable();
      $('action_action_value').update("&nbsp;of&nbsp;");
      $('action_action_id').update("");
      break;
    }
    case "expect": {
      $('input_action_value').appear();
      $('option_action_value').enable();
      $('input_action_key').appear();
      $('option_action_key').enable();
      $('input_action_id').hide();
      $('option_action_id').disable();
      $('action_action_value').update("&nbsp;from&nbsp;");
      $('action_action_id').update("");
      break;
    }
    case "filter": {
      $('input_action_value').appear();
      $('option_action_value').enable();
      $('input_action_key').appear();
      $('option_action_key').enable();
      $('input_action_id').hide();
      $('option_action_id').disable();
      $('action_action_value').update("&nbsp;from&nbsp;");
      $('action_action_id').update("");
      break;
    }
    case "hash": {
      $('input_action_value').hide();
      $('option_action_value').disable();
      $('input_action_key').appear();
      $('option_action_key').enable();
      $('input_action_id').hide();
      $('option_action_id').disable();
      $('action_action_value').update("");
      $('action_action_id').update("");
      break;
    }
    case "log": {
      $('input_action_value').hide();
      $('option_action_value').disable();
      $('input_action_key').appear();
      $('option_action_key').enable();
      $('input_action_id').hide();
      $('option_action_id').disable();
      $('action_action_value').update("");
      $('action_action_id').update("");
      break;
    }
    case "mark": {
      $('input_action_value').appear();
      $('option_action_value').enable();
      $('input_action_key').appear();
      $('option_action_key').enable();
      $('input_action_id').appear();
      $('option_action_id').enable();
      $('action_action_value').update("&nbsp;from&nbsp;");
      $('action_action_id').update("&nbsp;with&nbsp;");
      break;
    }
  }
}


function num_options() {
	return $('options_table').childElements().length - 1;
}


document.observe("dom:loaded", function() {
  updateProtocol('<?=$pconfig['protocol']?>');  
  updateDirection('<?=$pconfig['direction']?>');  
  updateType('<?=$pconfig['type']?>');  
  updateAction('<?=$pconfig['action']?>');  
});

</script>

<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
	<form action="load_balancer_relay_action_edit.php" method="post" name="iform" id="iform">
	<table width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
			<td colspan="2" valign="top" class="listtopic">Edit Load Balancer - Relay Action entry</td>
		</tr>
		<tr align="left" id="name">
			<td width="22%" valign="top" class="vncellreq">Name</td>
			<td width="78%" class="vtable" colspan="2">
				<input name="name" type="text" <?if(isset($pconfig['name'])) echo "value=\"{$pconfig['name']}\"";?> size="16" maxlength="16">
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncellreq">Description</td>
			<td width="78%" class="vtable" colspan="2">
				<input name="desc" type="text" <?if(isset($pconfig['desc'])) echo "value=\"{$pconfig['desc']}\"";?>size="64">
			</td>
		</tr>
<!-- Protocol -->
		<tr align="left" id="protocol_row">
			<td width="22%" valign="top" class="vncellreq">Protocol</td>
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
			<td width="22%" valign="top" class="vncellreq">Direction</td>
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
			<td width="22%" valign="top" class="vncellreq">Type</td>
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
			<td width="22%" valign="top" class="vncellreq">Action</td>
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
<br/>
<table><tr>
<td><div id="input_action_value">Value&nbsp;<input id="option_action_value" name="option_action_value" type="text" <?if(isset($pconfig['options']['value'])) echo "value=\"{$pconfig['options']['value']}\"";?>size="20"></div></td>
<td><div id="action_action_value"></div></td>
<td><div id="input_action_key">Key&nbsp;<input id="option_action_key" name="option_action_key" type="text" <?if(isset($pconfig['options']['akey'])) echo "value=\"{$pconfig['options']['akey']}\"";?>size="20"></div></td>
<td><div id="action_action_id"></div></td>
<td><div id="input_action_id">ID&nbsp;<input id="option_action_id" name="option_action_id" type="text" <?if(isset($pconfig['options']['id'])) echo "value=\"{$pconfig['options']['id']}\"";?>size="20"></div></td>
</tr></table>
			</td>
		</tr>
		<tr align="left" id="tcp_options_row"<?= $pconfig['protocol'] == "tcp" ? "" : " style=\"display:none;\""?>>
			<td width="22%" valign="top" class="vncellreq">Options</td>
			<td width="78%" class="vtable" colspan="2">
				XXX: TODO
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
			<td width="22%" valign="top" class="vncellreq">Options</td>
			<td width="78%" class="vtable" colspan="2">
				XXX: TODO
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
				<input name="Submit" type="submit" class="formbtn" value="Save"><input type="button" class="formbtn" value="Cancel" onclick="history.back()">
				<?php if (isset($id) && $a_action[$id] && $_GET['act'] != 'dup'): ?>
				<input name="id" type="hidden" value="<?=$id;?>">
				<?php endif; ?>
			</td>
		</tr>
	</table>
	</form>
<br>
<?php include("fend.inc"); ?>
</body>
</html>
