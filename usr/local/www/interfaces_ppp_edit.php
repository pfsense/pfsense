<?php
/*
	interfaces_lan.php
	part of pfSense(http://pfsense.org)

	Originally written by Adam Lebsack <adam at holonyx dot com>
	Changes by Chris Buechler <cmb at pfsense dot org> 
	
	Copyright (C) 2004-2008 BSD Perimeter LLC.
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

##|+PRIV
##|*IDENT=page-interfaces-ppp-edit
##|*NAME=Interfaces: PPP: Edit page
##|*DESCR=Allow access to the 'Interfaces: PPP: Edit' page.
##|*MATCH=interfaces_ppp_edit.php*
##|-PRIV


require("guiconfig.inc");

if (!is_array($config['ppps']['ppp']))
	$config['ppps']['ppp'] = array();

$a_ppps = &$config['ppps']['ppp'];


$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_ppps[$id]) {
	$pconfig['port'] = $a_ppps[$id]['port'];
	$pconfig['pppif'] = $a_ppps[$id]['pppif'];
	$pconfig['initstr'] = $a_ppps[$id]['initstr'];
	$pconfig['phone'] = $a_ppps[$id]['phone'];
	$pconfig['linespeed'] = $a_ppps[$id]['linespeed'];
	$pconfig['descr'] = $a_ppps[$id]['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "port");
	$reqdfieldsn = explode(",", "Serial Port");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	foreach ($a_ppps as $ppp) {
		if (isset($id) && ($a_ppps[$id]) && ($a_ppps[$id] === $ppp))
			continue;

		if ($ppp['port'] == $_POST['port']) {
			$input_errors[] = "Port is in use";
			break;
		}
	}

	if (!$input_errors) {
		$ppp = array();
		$ppp['port'] = $_POST['port'];
		$ppp['initstr'] = $_POST['initstr'];
		$ppp['phone'] = $_POST['phone'];
		$ppp['linespeed'] = $_POST['linespeed'];
		$ppp['descr'] = $_POST['descr'];
		$ppp['pppif'] = interface_ppp_configure($ppp);
                if ($ppp['pppif'] == "" || !stristr($ppp['pppif'], "ppp"))
                        $input_errors[] = "Error occured creating interface, please retry.";
                else {
			if (isset($id) && $a_ppps[$id])
				$a_ppps[$id] = $ppp;
			else
				$a_ppps[] = $ppp;

			write_config();

			header("Location: interfaces_ppp.php");
			exit;
		}
	}
}

$pgtitle = "Interfaces: PPP: Edit";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="interfaces_ppp_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
				<tr>
					<td colspan="2" valign="top" class="listtopic">PPP configuration</td>
				</tr>
				<tr>
                  <td width="22%" valign="top" class="vncellreq">Parent interface</td>
                  <td width="78%" class="vtable">
                    <select name="port" class="formfld">
                      <?php
					 	$portlist = glob("/dev/cua*");
					 	foreach ($portlist as $port) {
							if(preg_match("/\.(lock|init)$/", $port))
								continue;
							echo "<option value=\"{$port}\"";
							if ($pconfig['port'] = $port)
								echo "selected";
							echo ">";
                      		echo $port;
                    		echo "</option>";
						}
		      			?>
                    </select>
                </tr>
				<tr>
                  <td width="22%" valign="top" class="vncell">Init String</td>
                  <td width="78%" class="vtable">
                    <textarea name="initstr"><?=htmlspecialchars($pconfig['initstr']);?></textarea>
                    <br> <span class="vexpl">Enter the modem initialization string here</span></td>
                </tr>
		<tr>
                  <td width="22%" valign="top" class="vncell">Phone Number</td>
                  <td width="78%" class="vtable">
                    <input name="phone" type="text" class="formfld" id="phone" size="40" value="<?=htmlspecialchars($pconfig['phone']);?>">
                  </td>
                </tr>
		<tr>
                  <td width="22%" valign="top" class="vncell">Line Speed</td>
                  <td width="78%" class="vtable">
                    <input name="linespeed" type="text" class="formfld" id="linespeed" size="40" value="<?=htmlspecialchars($pconfig['linespeed']);?>">
                  </td>
                </tr>

		<tr>
                  <td width="22%" valign="top" class="vncell">Description</td>
                  <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
                    <br> <span class="vexpl">You may enter a description here
                    for your reference (not parsed).</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="Save"> <input type="button" value="Cancel" onclick="history.back()">
                    <?php if (isset($id) && $a_ppps[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
