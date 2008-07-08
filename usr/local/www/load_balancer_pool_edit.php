<?php
/* $Id$ */
/*
        load_balancer_pool_edit.php
        part of pfSense (http://www.pfsense.com/)

        Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
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

require("guiconfig.inc");
if (!is_array($config['load_balancer']['lbpool'])) {
	$config['load_balancer']['lbpool'] = array();
}
$a_pool = &$config['load_balancer']['lbpool'];

if (isset($_POST['id']))
	$id = $_POST['id'];
else
	$id = $_GET['id'];

if (isset($id) && $a_pool[$id]) {
	$pconfig['monitorip'] = $a_pool[$id]['monitorip'];
	$pconfig['behaviour'] = $a_pool[$id]['behaviour'];
	$pconfig['name'] = $a_pool[$id]['name'];
	$pconfig['desc'] = $a_pool[$id]['desc'];
	$pconfig['port'] = $a_pool[$id]['port'];
	$pconfig['servers'] = &$a_pool[$id]['servers'];
	$pconfig['serversdisabled'] = &$a_pool[$id]['serversdisabled'];
	$pconfig['monitor'] = $a_pool[$id]['monitor'];
}

$changedesc = "Load Balancer: Pool: ";
$changecount = 0;

if ($_POST) {
	$changecount++;

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "name port monitor servers");
	$reqdfieldsn = explode(",", "Name,Port,Monitor,Server List");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	/* Ensure that our pool names are unique */
	for ($i=0; isset($config['load_balancer']['lbpool'][$i]); $i++)
		if (($_POST['name'] == $config['load_balancer']['lbpool'][$i]['name']) && ($i != $id))
			$input_errors[] = "This pool name has already been used.  Pool names must be unique.";
	if (!is_port($_POST['port']))
		if($_POST['type'] == "server")
			$input_errors[] = "The port must be an integer between 1 and 65535.";
	if (is_array($_POST['servers'])) {
		foreach($pconfig['servers'] as $svrent) {
			if (!is_ipaddr($svrent)) {
				$input_errors[] = "{$svrent} is not a valid IP address (in \"enabled\" list).";
			}
		}
	}
	if (is_array($_POST['serversdisabled'])) {
		foreach($pconfig['serversdisabled'] as $svrent) {
			if (!is_ipaddr($svrent)) {
				$input_errors[] = "{$svrent} is not a valid IP address (in \"disabled\" list).";
			}
		}
	}

	if ($_POST['monitor'] != "TCP" && $_POST['monitor'] != "HTTP" && $_POST['monitor'] != "ICMP")
		$input_errors[] = "Invalid monitor chosen.";

	if(!isset($_POST['behaviour'])) {
			$input_errors[] = "No pool behaviour chosen.";
	}

	if (!$input_errors) {
		$poolent = array();
		if(isset($id) && $a_pool[$id])
			$poolent = $a_pool[$id];
		if($poolent['name'] != "")
			$changedesc .= " modified '{$poolent['name']}' pool:";
		
		/* kill off old static route */
		if(is_ipaddr($poolent['monitorip']))
			mwexec("route delete {$poolent['monitorip']}");
		
		update_if_changed("behaviour", $poolent['behaviour'], $_POST['behaviour']);
		update_if_changed("monitorip", $poolent['monitorip'], $_POST['monitorip']);
		update_if_changed("name", $poolent['name'], $_POST['name']);
		update_if_changed("description", $poolent['desc'], $_POST['desc']);
		update_if_changed("port", $poolent['port'], $_POST['port']);
		update_if_changed("servers", $poolent['servers'], $_POST['servers']);
		update_if_changed("serversdisabled", $poolent['serversdisabled'], $_POST['serversdisabled']);
		update_if_changed("monitor", $poolent['monitor'], $_POST['monitor']);

		if (isset($id) && $a_pool[$id]) {
			/* modify all virtual servers with this name */
			for ($i = 0; isset($config['load_balancer']['virtual_server'][$i]); $i++) {
				if ($config['load_balancer']['virtual_server'][$i]['pool'] == $a_pool[$id]['name'])
					$config['load_balancer']['virtual_server'][$i]['pool'] = $poolent['name'];
			}
			$a_pool[$id] = $poolent;
		} else
			$a_pool[] = $poolent;
		
		if ($changecount > 0) {
			/* Mark pool dirty */
			conf_mount_rw();
			touch($d_vsconfdirty_path);
			write_config($changedesc);
		}

		header("Location: load_balancer_pool.php");
		exit;
	}
}

$pgtitle = array("Load Balancer","Pool","Edit");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script type="text/javascript" language="javascript" src="pool.js"></script>

<script language="javascript">
function clearcombo(){
  for (var i=document.iform.serversSelect.options.length-1; i>=0; i--){
    document.iform.serversSelect.options[i] = null;
  }
  document.iform.serversSelect.selectedIndex = -1;
}
</script>

<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>

	<form action="load_balancer_pool_edit.php" method="post" name="iform" id="iform">
	<table width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr align="left">
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

		<tr align="left">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Behavior");?></td>
			<td width="78%" class="vtable" colspan="2">
				<input type="radio" name="behaviour" id="behaviour" value="balance"<?php if($pconfig['behaviour'] == 
"balance") echo " CHECKED"; ?>><?=gettext("Load Balancing");?><br>
				<input type="radio" name="behaviour" id="behaviour" value="failover"<?php if($pconfig['behaviour'] == 
"failover") echo " CHECKED"; ?>><?=gettext("Failover");?><br>
				Load Balancing: both active. Failover order: top -&gt; down.
			</td>
		</tr>

		<tr align="left">
			<td width="22%" valign="top" id="monitorport_text" class="vncellreq">Port</td>
			<td width="78%" class="vtable" colspan="2">
				<input name="port" type="text" <?if(isset($pconfig['port'])) echo "value=\"{$pconfig['port']}\"";?> size="16" maxlength="16"><br>
				<div id="monitorport_desc">This is the port your servers are listening on.</div>
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncellreq">Monitor</td>
			<td width="78%" class="vtable" colspan="2">
				<select id="monitor" name="monitor">
					<option value="TCP"<?php if($pconfig['monitor'] == "TCP") echo " SELECTED"; ?>>TCP</option>
					<option value="ICMP"<?php if($pconfig['monitor'] == "ICMP") echo " SELECTED"; ?>>ICMP</option>
					<!-- billm - XXX: add HTTP/HTTPS here -->
				</select>
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" id="monitorip_text" class="vncell">Monitor IP</td>
			<td width="78%" class="vtable" colspan="2">
				<div style="float: none;">
				<input size="16" id="monitorip" name="monitorip" value="<?php echo $pconfig['monitorip']; ?>" style="float: left;">
				</div>
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncellreq"><div id="interfacename_text"></div></td>
			<td width="78%" class="vtable" colspan="2">
				<input name="ipaddr" type="text" size="16" style="float: left;">
				<input class="formbtn" type="button" name="button1" value="Add to pool" onclick="AddServerToPool(document.iform);"><br>
				<div id="interfacename_desc"></div>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq">List</td>
			<td width="78%" class="vtable" colspan="2" valign="top">
				<table>
					<tbody>
					<tr>
						<td>
							Disabled<br/>
							<select id="serversDisabledSelect" name="serversdisabled[]" multiple="true" size="5">
							
<?php
if (is_array($pconfig['serversdisabled'])) {
	foreach($pconfig['serversdisabled'] as $svrent) {
		if($svrent != '') echo "    <option value=\"{$svrent}\">{$svrent}</option>\n";
	}
}
echo "</select>";
?>
							<br/>
							<input class="formbtn" type="button" name="removeDisabled" value="Remove" onclick="RemoveServerFromPool(document.iform, 'serversdisabled[]');" />
						</td>

						<td valign="middle">
							<input class="formbtn" type="button" name="moveToEnabled" value=">" onclick="moveOptions(document.iform.serversDisabledSelect, document.iform.serversSelect);" /><br/>
							<input class="formbtn" type="button" name="moveToDisabled" value="<" onclick="moveOptions(document.iform.serversSelect, document.iform.serversDisabledSelect);" />
						</td>

						<td>
							Enabled (default)<br/>
							<select id="serversSelect" name="servers[]" multiple="true" size="5">
							
<?php
if (is_array($pconfig['servers'])) {
	foreach($pconfig['servers'] as $svrent) {
		echo "    <option value=\"{$svrent}\">{$svrent}</option>\n";
	}
}
echo "</select>";
?>
							<br/>
							<input class="formbtn" type="button" name="removeEnabled" value="Remove" onclick="RemoveServerFromPool(document.iform, 'servers[]');" />
						</td>
						<td valign="top">
							<input class="formbtn" type="button" name="moveUp" value="Move up" onclick="up(document.iform.serversSelect);" /><br/>
							<input class="formbtn" type="button" name="moveDown" value="Move down" onclick="down(document.iform.serversSelect);" />
						</td>
					</tr>
					</tbody>
				</table>
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input name="Submit" type="submit" class="formbtn" value="Save" onClick="AllServers('serversSelect', true); AllServers('serversDisabledSelect', true);">
				<?php if (isset($id) && $a_pool[$id]): ?>
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
