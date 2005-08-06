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
if (!is_array($config['load_balancer']['pool'])) {
        $config['load_balancer']['pool'] = array();
}
$a_pool = &$config['load_balancer']['pool'];

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
echo "<pre>";
print_r($_POST);
echo "</pre>";
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
//	$reqdfields = explode(" ", "name");
//	$reqdfieldsn = explode(",", "Name");

//	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (($_POST['subnet'] && !is_ipaddr($_POST['subnet']))) {
		$input_errors[] = "A valid IP address must be specified.";
	}

	if ($_POST['ipaddr'] == $config['interfaces']['wan']['ipaddr'])
		$input_errors[] = "The WAN IP address may not be used in a virtual entry.";

	if ($_POST['ipaddr'] == $config['interfaces']['lan']['ipaddr'])
		$input_errors[] = "The LAN IP address may not be used in a virtual entry.";

	/* check for overlaps with other virtual IP */
	foreach ($a_pool as $poolent) {
		if (isset($id) && ($a_pool[$id]) && ($a_pool[$id] === $poolent))
			continue;

		if (isset($_POST['subnet']) && $_POST['subnet'] == $poolent['subnet']) {
			$input_errors[] = "There is already a virtual IP entry for the specified IP address.";
			break;
		}
	}

	/* check for overlaps with 1:1 NAT */
	if (is_array($config['nat']['onetoone'])) {
		foreach ($config['nat']['onetoone'] as $natent) {
			if (check_subnets_overlap($_POST['ipaddr'], 32, $natent['external'], $natent['subnet'])) {
				$input_errors[] = "A 1:1 NAT mapping overlaps with the specified IP address.";
				break;
			}
		}
	}

	if (!$input_errors) {
		$poolent = array();
		
		$poolent['name'] = $_POST['name'];
		$poolent['desc'] = $_POST['desc'];
		$poolent['port'] = $_POST['port'];
		$poolent['servers'] = $_POST['servers'];
		$poolent['monitor'] = $_POST['monitor'];

		if (isset($id) && $a_pool[$id]) {
			/* modify all virtual IP rules with this name */
			for ($i = 0; isset($config['load_balancer']['virtual_server'][$i]); $i++) {
				if ($config['load_balancer']['virtual_server'][$i]['pool'] == $a_pool[$id]['name'])
					$config['load_balancer']['virtual_server'][$i]['pool'] = $poolent['name'];
			}
			$a_pool[$id] = $poolent;
		} else
			$a_pool[] = $poolent;

echo "<pre>";
print_r($poolent);
echo "</pre>";
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
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="load_balancer_pool_edit.php" method="post" name="iform" id="iform">
<script type="text/javascript" language="javascript" src="pool.js">
</script>

              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr align="left">
                  <td class="vtable" colspan="2">
                    Name: <input name="name" type="text" <?if(isset($pconfig['name'])) echo "value=\"{$pconfig['name']}\"";?> size="16" maxlength="16">
                  </td>
		</tr>
                <tr align="left">
                  <td class="vtable" colspan="2">
                    Description: <input name="desc" type="text" <?if(isset($pconfig['desc'])) echo "value=\"{$pconfig['desc']}\"";?>size="64">
                  </td>
		</tr>
                <tr align="left">
                  <td class="vtable" colspan="2">
                    Port: <input name="port" type="text" <?if(isset($pconfig['port'])) echo "value=\"{$pconfig['port']}\"";?> size="16" maxlength="16">
                  </td>
		</tr>
                <tr align="left">
                  <td class="vtable" align="left" valign="bottom">
		    IP
                    <input name="ipaddr" type="text" size="16"> 
		    <input class="formbtn" type="button" name="button1" value="->" onclick="AddServerToPool(document.iform);">
                  </td>
                  <td class="vtable" align="left" valign="bottom">
		    <input class="formbtn" type="button" name="button2" value="<-" onclick="RemoveServerFromPool(document.iform);">
			List:
			<select id="serversSelect" name="servers[]" multiple="true" size="4" width="22">
			<?php  if (is_array($pconfig['servers']))
				foreach($pconfig['servers'] as $svrent) {
					echo "<option value=\"{$svrent}\">{$svrent}</option>";
				}
			?>
			</select>
                  </td>
                </tr>
              </table>
<input name="Submit" type="submit" class="formbtn" value="Save" onClick="AllServers('serversSelect', true)">
</form>
<?php include("fend.inc"); ?>
</body>
</html>
