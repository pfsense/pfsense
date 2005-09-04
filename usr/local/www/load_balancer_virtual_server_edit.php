#!/usr/local/bin/php
<?php
/* $Id$ */
/*
        load_balancer_virtual_server_edit.php
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
if (!is_array($config['load_balancer']['virtual_server'])) {
        $config['load_balancer']['virtual_server'] = array();
}
$a_vs = &$config['load_balancer']['virtual_server'];

if (isset($_POST['id']))
	$id = $_POST['id'];
else
	$id = $_GET['id'];

if (isset($id) && $a_vs[$id]) {
	$pconfig['ipaddr'] = $a_vs[$id]['ipaddr'];
	$pconfig['port'] = $a_vs[$id]['port'];
	$pconfig['pool'] = $a_vs[$id]['pool'];
	$pconfig['desc'] = $a_vs[$id]['desc'];
	$pconfig['name'] = $a_vs[$id]['name'];
	$pconfig['sitedown'] = $a_vs[$id]['sitedown'];
}

$changedesc = "Load Balancer: Virtual Server: ";
$changecount = 0;

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "ipaddr name port");
	$reqdfieldsn = explode(",", "IP Address, Name, Port");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	for ($i=0; isset($config['load_balancer']['virtual_server'][$i]); $i++)
		if (($_POST['name'] == $config['load_balancer']['virtual_server'][$i]['name']) && ($i != $id))
			$input_errors[] = "This virtual server name has already been used.  Virtual server names must be unique.";

	if (!is_port($_POST['port']))
		$input_errors[] = "The port must be an integer between 1 and 65535.";

	if(!is_ipaddr($_POST['ipaddr']))
		$input_errors[] = "{$_POST['ipaddr']} is not a valid IP address.";

	if(($_POST['sitedown'] != "") && (!is_ipaddr($_POST['sitedown'])))
		$input_errors[] = "{$_POST['sitedown']} is not a valid IP address.";

	if (!$input_errors) {
		$vsent = array();
		if(isset($id) && $a_vs[$id])
			$vsent = $a_vs[$id];
		if($vsent['name'] != "")
			$changedesc .= " modified '{$vsent['name']}' vs:";
		else
			$changedesc .= " created '{$_POST['name']}' vs:";

		update_if_changed("name", $vsent['name'], $_POST['name']);
		update_if_changed("desc", $vsent['desc'], $_POST['desc']);
		update_if_changed("pool", $vsent['pool'], $_POST['pool']);
		update_if_changed("port", $vsent['port'], $_POST['port']);
		update_if_changed("sitedown", $vsent['sitedown'], $_POST['sitedown']);
		update_if_changed("ipaddr", $vsent['ipaddr'], $_POST['ipaddr']);

		if (isset($id) && $a_vs[$id])
			$a_vs[$id] = $vsent;
		else
			$a_vs[] = $vsent;


		if ($changecount > 0) {
			/* Mark virtual server dirty */
			touch($d_vsconfdirty_path);
			write_config($changedesc);
		}

		header("Location: load_balancer_virtual_server.php");
		exit;
	}
}

$pgtitle = "Load Balancer: Virtual Server: Edit";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="load_balancer_virtual_server_edit.php" method="post" name="iform" id="iform">
<script type="text/javascript" language="javascript" src="pool.js">
</script>

              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr align="left">
		  <td width="22%" valign="top" class="vncellreq">Name</td>
                  <td width="78%" class="vtable" colspan="2">
                    <input name="name" type="text" <?if(isset($pconfig['name'])) echo "value=\"{$pconfig['name']}\"";?>size="32" maxlength="32">
                  </td>
		</tr>
                <tr align="left">
		  <td width="22%" valign="top" class="vncellreq">Description</td>
                  <td width="78%" class="vtable" colspan="2">
                    <input name="desc" type="text" <?if(isset($pconfig['desc'])) echo "value=\"{$pconfig['desc']}\"";?>size="64">
                  </td>
		</tr>
                <tr align="left">
		  <td width="22%" valign="top" class="vncellreq">IP Address</td>
                  <td width="78%" class="vtable" colspan="2">
                    <input name="ipaddr" type="text" <?if(isset($pconfig['ipaddr'])) echo "value=\"{$pconfig['ipaddr']}\"";?> size="16" maxlength="16">
		    <br><b>NOTE:</b> This is normally the WAN IP address that you would like the server to listen on.  All connections to this IP/PORT will be forwarded to the pool cluster.
                  </td>
		</tr>
                <tr align="left">
		  <td width="22%" valign="top" class="vncellreq">Port</td>
                  <td width="78%" class="vtable" colspan="2">
                    <input name="port" type="text" <?if(isset($pconfig['port'])) echo "value=\"{$pconfig['port']}\"";?> size="16" maxlength="16">
		    <br><b>NOTE:</b> This is the PORT that the clients will connect to.  All connections to this port will be forwarded to the pool cluster.
                  </td>
		</tr>
                <tr align="left">
		  <td width="22%" valign="top" class="vncellreq">Virtual Server Pool</td>
                  <td width="78%" class="vtable" colspan="2">
                    <select id="pool" name="pool">
			<?php
				for ($i = 0; isset($config['load_balancer']['lbpool'][$i]); $i++) {
					echo "<option value=\"{$config['load_balancer']['lbpool'][$i]['name']}\">{$config['load_balancer']['lbpool'][$i]['name']}</option>";
				}
			?>
			</select>
                  </td>
		</tr>
                <tr align="left">
		  <td width="22%" valign="top" class="vncellreq">Pool Down Server</td>
                  <td width="78%" class="vtable" colspan="2">
                    <input name="sitedown" type="text" <?if(isset($pconfig['sitedown'])) echo "value=\"{$pconfig['sitedown']}\"";?> size="16" maxlength="16">
		    <br><b>NOTE:</b> This is the server that clients will be redirected to if *ALL* servers in the pool are offline.
                  </td>
		</tr>
                <tr align="left">
                  <td align="left" valign="bottom">
			<input name="Submit" type="submit" class="formbtn" value="Submit">
			<?php if (isset($id) && $a_vs[$id]): ?>
			<input name="id" type="hidden" value="<?=$id;?>">
			<?php endif; ?>
		  </td>
		</tr>
              </table>
</form>
<br><span class="red"><strong>Note:</strong></span> Don't forget to add a firewall rule for the virtual server/pool after your finished setting it up.
<?php include("fend.inc"); ?>
</body>
</html>
