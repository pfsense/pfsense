<?php
/* $Id$ */
/*
        load_balancer_pool_edit.php
        part of pfSense (https://www.pfsense.org/)

        Copyright (C) 2005-2008 Bill Marquette <bill.marquette@gmail.com>.
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
##|*IDENT=page-loadbalancer-pool-edit
##|*NAME=Load Balancer: Pool: Edit page
##|*DESCR=Allow access to the 'Load Balancer: Pool: Edit' page.
##|*MATCH=load_balancer_pool_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("filter.inc");
require_once("util.inc");

if (!is_array($config['load_balancer']['lbpool'])) {
	$config['load_balancer']['lbpool'] = array();
}
$a_pool = &$config['load_balancer']['lbpool'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

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

if ($_POST) {
	$changecount++;

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "name mode port monitor servers");
	$reqdfieldsn = array(gettext("Name"),gettext("Mode"),gettext("Port"),gettext("Monitor"),gettext("Server List"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	/* Ensure that our pool names are unique */
	for ($i=0; isset($config['load_balancer']['lbpool'][$i]); $i++)
		if (($_POST['name'] == $config['load_balancer']['lbpool'][$i]['name']) && ($i != $id))
			$input_errors[] = gettext("This pool name has already been used.  Pool names must be unique.");

	if (strpos($_POST['name'], " ") !== false)
		$input_errors[] = gettext("You cannot use spaces in the 'name' field.");

	if (in_array($_POST['name'], $reserved_table_names))
		$input_errors[] = sprintf(gettext("The name '%s' is a reserved word and cannot be used."), $_POST['name']);

	if (is_alias($_POST['name']))
		$input_errors[] = sprintf(gettext("Sorry, an alias is already named %s."), $_POST['name']);

	if (!is_portoralias($_POST['port']))
		$input_errors[] = gettext("The port must be an integer between 1 and 65535, or a port alias.");

	// May as well use is_port as we want a positive integer and such.
	if (!empty($_POST['retry']) && !is_port($_POST['retry']))
		$input_errors[] = gettext("The retry value must be an integer between 1 and 65535.");

	if (is_array($_POST['servers'])) {
		foreach($pconfig['servers'] as $svrent) {
			if (!is_ipaddr($svrent) && !is_subnetv4($svrent)) {
				$input_errors[] = sprintf(gettext("%s is not a valid IP address or IPv4 subnet (in \"enabled\" list)."), $svrent);
			}
			else if (is_subnetv4($svrent) && subnet_size($svrent) > 64) {
				$input_errors[] = sprintf(gettext("%s is a subnet containing more than 64 IP addresses (in \"enabled\" list)."), $svrent);
			}
		}
	}
	if (is_array($_POST['serversdisabled'])) {
		foreach($pconfig['serversdisabled'] as $svrent) {
			if (!is_ipaddr($svrent) && !is_subnetv4($svrent)) {
				$input_errors[] = sprintf(gettext("%s is not a valid IP address or IPv4 subnet (in \"disabled\" list)."), $svrent);
			}
			else if (is_subnetv4($svrent) && subnet_size($svrent) > 64) {
				$input_errors[] = sprintf(gettext("%s is a subnet containing more than 64 IP addresses (in \"disabled\" list)."), $svrent);
			}
		}
	}
	$m = array();
	for ($i=0; isset($config['load_balancer']['monitor_type'][$i]); $i++)
		$m[$config['load_balancer']['monitor_type'][$i]['name']] = $config['load_balancer']['monitor_type'][$i];

	if (!isset($m[$_POST['monitor']]))
		$input_errors[] = gettext("Invalid monitor chosen.");

	if (!$input_errors) {
		$poolent = array();
		if(isset($id) && $a_pool[$id])
			$poolent = $a_pool[$id];
		if($poolent['name'] != "")
			$changedesc .= sprintf(gettext(" modified '%s' pool:"), $poolent['name']);
		
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
				if ($config['load_balancer']['virtual_server'][$i]['lbpool'] == $a_pool[$id]['name'])
					$config['load_balancer']['virtual_server'][$i]['lbpool'] = $poolent['name'];
			}
			$a_pool[$id] = $poolent;
		} else
			$a_pool[] = $poolent;
		
		if ($changecount > 0) {
			/* Mark pool dirty */
			mark_subsystem_dirty('loadbalancer');
			write_config($changedesc);
		}

		header("Location: load_balancer_pool.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("Load Balancer"),gettext("Pool"),gettext("Edit"));
$shortcut_section = "relayd";

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script type="text/javascript">
function clearcombo(){
  for (var i=document.iform.serversSelect.options.length-1; i>=0; i--){
    document.iform.serversSelect.options[i] = null;
  }
  document.iform.serversSelect.selectedIndex = -1;
}
</script>

<script type="text/javascript" src="/javascript/autosuggest.js"></script>
<script type="text/javascript" src="/javascript/suggestions.js"></script>
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>

	<form action="load_balancer_pool_edit.php" method="post" name="iform" id="iform">
	<table width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("Add/edit Load Balancer - Pool entry"); ?></td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Name"); ?></td>
			<td width="78%" class="vtable" colspan="2">
				<input name="name" type="text" <?if(isset($pconfig['name'])) echo "value=\"{$pconfig['name']}\"";?> size="16" maxlength="16">
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Mode"); ?></td>
			<td width="78%" class="vtable" colspan="2">
				<select id="mode" name="mode" onChange="enforceFailover(); checkPoolControls();">
					<option value="loadbalance" <?if(!isset($pconfig['mode']) || ($pconfig['mode'] == "loadbalance")) echo "selected";?>><?=gettext("Load Balance");?></option>
					<option value="failover"  <?if($pconfig['mode'] == "failover") echo "selected";?>><?=gettext("Manual Failover");?></option>
				</select>
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
			<td width="78%" class="vtable" colspan="2">
				<input name="descr" type="text" <?if(isset($pconfig['descr'])) echo "value=\"{$pconfig['descr']}\"";?>size="64">
			</td>
		</tr>

		<tr align="left">
			<td width="22%" valign="top" id="monitorport_text" class="vncellreq"><?=gettext("Port"); ?></td>
			<td width="78%" class="vtable" colspan="2">
				<input class="formfldalias" id="port" name="port" type="text" <?if(isset($pconfig['port'])) echo "value=\"{$pconfig['port']}\"";?> size="16" maxlength="16"><br />
				<div id="monitorport_desc">
					<?=gettext("This is the port your servers are listening on."); ?><br />
					<?=gettext("You may also specify a port alias listed in Firewall -&gt; Aliases here."); ?>
				</div>
				<script type="text/javascript">
				//<![CDATA[
					var addressarray = <?= json_encode(get_alias_list(array("port", "url_ports", "urltable_ports"))) ?>;
					var oTextbox1 = new AutoSuggestControl(document.getElementById("port"), new StateSuggestions(addressarray));
				//]]>
				</script>
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" id="retry_text" class="vncell"><?=gettext("Retry"); ?></td>
			<td width="78%" class="vtable" colspan="2">
				<input name="retry" type="text" <?if(isset($pconfig['retry'])) echo "value=\"{$pconfig['retry']}\"";?> size="16" maxlength="16"><br />
				<div id="retry_desc"><?=gettext("Optionally specify how many times to retry checking a server before declaring it down."); ?></div>
			</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("Add item to pool"); ?></td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Monitor"); ?></td>
			<td width="78%" class="vtable" colspan="2">
				<?php if(count($config['load_balancer']['monitor_type'])): ?>
				<select id="monitor" name="monitor">
					<?php
						foreach ($config['load_balancer']['monitor_type'] as $monitor) {
							if ($monitor['name'] == $pconfig['monitor']) {
								$selected=" selected";
							} else {
								$selected = "";
							}
							echo "<option value=\"{$monitor['name']}\"{$selected}>{$monitor['name']}</option>";
						}
					?>
				<?php else: ?>
					<b><?=gettext("NOTE"); ?>:</b> <?=gettext("Please add a monitor IP address on the monitors tab if you wish to use this feature."); ?>
				<?php endif; ?>
				</select>
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Server IP Address"); ?></td>
			<td width="78%" class="vtable" colspan="2">
				<input name="ipaddr" type="text" size="16" style="float: left;"> 
				<input class="formbtn" type="button" name="button1" value="<?=gettext("Add to pool"); ?>" onclick="AddServerToPool(document.iform); enforceFailover(); checkPoolControls();"><br />
			</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("Current Pool Members"); ?></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Members"); ?></td>
			<td width="78%" class="vtable" colspan="2" valign="top">
				<table>
					<tbody>
					<tr>
						<td>
							<center>
								<b><?=gettext("Pool Disabled"); ?></b>
							<p/>
							<select id="serversDisabledSelect" name="serversdisabled[]" multiple="true" size="5">
							
<?php
	if (is_array($pconfig['serversdisabled'])) {
		foreach($pconfig['serversdisabled'] as $svrent) {
			if($svrent != '') echo "    <option value=\"{$svrent}\">{$svrent}</option>\n";
		}
	}
	echo "</select>";
?>
							<p/>
							<input class="formbtn" type="button" name="removeDisabled" value="<?=gettext("Remove"); ?>" onclick="RemoveServerFromPool(document.iform, 'serversdisabled[]');" />
						</td>

						<td valign="middle">
							<input class="formbtn" type="button" id="moveToEnabled" name="moveToEnabled" value=">" onclick="moveOptions(document.iform.serversDisabledSelect, document.iform.serversSelect); checkPoolControls();" /><br />
							<input class="formbtn" type="button" id="moveToDisabled" name="moveToDisabled" value="<" onclick="moveOptions(document.iform.serversSelect, document.iform.serversDisabledSelect); checkPoolControls();" />
						</td>

						<td>
							<center>
								<b><?=gettext("Enabled (default)"); ?></b>
							<p/>
							<select id="serversSelect" name="servers[]" multiple="true" size="5">
							
<?php
if (is_array($pconfig['servers'])) {
	foreach($pconfig['servers'] as $svrent) {
		echo "    <option value=\"{$svrent}\">{$svrent}</option>\n";
	}
}
echo "</select>";
?>
							<p/>
							<input class="formbtn" type="button" name="removeEnabled" value="<?=gettext("Remove"); ?>" onclick="RemoveServerFromPool(document.iform, 'servers[]');" />
						</td>
					</tr>
					</tbody>
				</table>
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<br />
				<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" onClick="AllServers('serversSelect', true); AllServers('serversDisabledSelect', true);"> 
				<input type="button" class="formbtn" value="<?=gettext("Cancel"); ?>" onclick="history.back()">
				<?php if (isset($id) && $a_pool[$id] && $_GET['act'] != 'dup'): ?>
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
