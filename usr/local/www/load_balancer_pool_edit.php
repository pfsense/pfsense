#!/usr/local/bin/php
<?php
/* $Id */
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
	$pconfig['name'] = $a_pool[$id]['name'];
	$pconfig['desc'] = $a_pool[$id]['desc'];
	$pconfig['port'] = $a_pool[$id]['port'];
	$pconfig['servers'] = $a_pool[$id]['servers'];
	$pconfig['monitor'] = $a_pool[$id]['monitor'];
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "name");
	$reqdfieldsn = explode(",", "Name");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	/* Ensure that our pool names are unique */
	for ($i=0; isset($config['load_balancer']['lbpool'][$i]); $i++)
		if (($_POST['name'] == $config['load_balancer']['lbpool'][$i]['name']) && ($i != $id))
			$input_errors[] = "This pool name has already been used.  Pool names must be unique.";

	if (!$input_errors) {
		$poolent = array();
		
		$poolent['name'] = $_POST['name'];
		$poolent['desc'] = $_POST['desc'];
		$poolent['port'] = $_POST['port'];
		$poolent['servers'] = $_POST['servers'];
		$poolent['monitor'] = $_POST['monitor'];

		if (isset($id) && $a_pool[$id]) {
			/* modify all virtual servers with this name */
			for ($i = 0; isset($config['load_balancer']['virtual_server'][$i]); $i++) {
				if ($config['load_balancer']['virtual_server'][$i]['pool'] == $a_pool[$id]['name'])
					$config['load_balancer']['virtual_server'][$i]['pool'] = $poolent['name'];
			}
			$a_pool[$id] = $poolent;
		} else
			$a_pool[] = $poolent;

		touch($d_poolconfdirty_path);

		write_config();

		header("Location: load_balancer_pool.php");
		exit;
	}
}

$pgtitle = "Load Balancer: Pool: Edit";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script type="text/javascript" language="javascript" src="pool.js"></script>

<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
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
			<td width="22%" valign="top" class="vncellreq">Port</td>
			<td width="78%" class="vtable" colspan="2">
				<input name="port" type="text" <?if(isset($pconfig['port'])) echo "value=\"{$pconfig['port']}\"";?> size="16" maxlength="16"> - server pool port, this is the port your servers are listening to.
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncellreq">Monitor</td>
			<td width="78%" class="vtable" colspan="2">
				<select id="monitor" name="monitor">
					<option value="TCP">TCP</option>
					<!-- billm - XXX: add HTTP/HTTPS here -->
				</select>
			</td>
		</tr>
		<tr align="left">
			<td width="22%" valign="top" class="vncellreq">IP</td>
			<td width="78%" class="vtable">
				<input name="ipaddr" type="text" size="16"> 
				<input class="formbtn" type="button" name="button1" value="-&gt;" onclick="AddServerToPool(document.iform);">
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq">List</td>
			<td width="78%" class="vtable" colspan="2" valign="top">
				<table>
					<tbody>
					<tr>
						<td valign="top">
							<input class="formbtn" type="button" name="button2" value="&lt;-" onclick="RemoveServerFromPool(document.iform);">
						</td>
						<td>
							<select id="serversSelect" name="servers[]" multiple="true" size="4" width="22">
<?php
							if (is_array($pconfig['servers']))
								foreach($pconfig['servers'] as $svrent) {
									echo "<option value=\"{$svrent}\">{$svrent}</option>";
								}
?>
							</select>			
						</td>
					</tr>
					</tbody>
				</table>
			</td>
		</tr>
		<tr align="left">
			<td colspan="2" align="center" align="left" valign="bottom">
				<input name="Submit" type="submit" class="formbtn" value="Save" onClick="AllServers('serversSelect', true)">
				<?php if (isset($id) && $a_pool[$id]): ?>
				<input name="id" type="hidden" value="<?=$id;?>">
				<?php endif; ?>
			</td>
		</tr>
	</table>
	</form>

<?php include("fend.inc"); ?>
</body>
</html>
