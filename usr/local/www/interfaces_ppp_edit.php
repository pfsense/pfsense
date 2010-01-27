<?php
/*
	interfaces_lan.php
	part of pfSense(http://pfsense.org)

	Originally written by Adam Lebsack <adam at holonyx dot com>
	Changes by Chris Buechler <cmb at pfsense dot org> 
	Additions by Scott Ullrich <sullrich@pfsense.org>
	
	Copyright (C) 2004-2009 BSD Perimeter LLC.
	Copyright (C) 2009 Scott Ullrich
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
	pfSense_MODULE:	interfaces
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
	$pconfig['ap'] = $a_ppps[$id]['ap'];
	$pconfig['initstr'] = $a_ppps[$id]['initstr'];
	$pconfig['username'] = $a_ppps[$id]['username'];
	$pconfig['password'] = $a_ppps[$id]['password'];
	$pconfig['gateway'] = $a_ppps[$id]['gateway'];
	$pconfig['localip'] = $a_ppps[$id]['localip'];
	if (isset($a_ppps[$id]['defaultgw']))
		$pconfig['defaultgw'] = true;
	$pconfig['phone'] = $a_ppps[$id]['phone'];
	$pconfig['dialcmd'] = base64_decode($a_ppps[$id]['dialcmd']);
	$pconfig['connect-max-attempts'] = $a_ppps[$id]['connect-max-attempts'];
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
		$ppp['ap'] = $_POST['ap'];
		$ppp['phone'] = $_POST['phone'];
		$ppp['dialcmd'] = base64_encode($_POST['dialcmd']);
		$ppp['username'] = $_POST['username'];
		$ppp['password'] = $_POST['password'];
		$ppp['localip'] = $_POST['localip'];
		$ppp['gateway'] = $_POST['gateway'];
		if ($_POST['defaultgw'] == "on")
			$ppp['defaultgw'] = true;
		else
			unset($ppp['defaultgw']);
		$ppp['linespeed'] = $_POST['linespeed'];
		$ppp['connect-max-attempts'] = $_POST['connect-max-attempts'];
		$ppp['descr'] = $_POST['descr'];

		interfaces_ppp_configure();

		if (isset($id) && $a_ppps[$id])
			$a_ppps[$id] = $ppp;
		else
			$a_ppps[] = $ppp;

		write_config();

		header("Location: interfaces_ppp.php");
		exit;
	}
}

$pgtitle = "Interfaces: PPP: Edit";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<form action="interfaces_ppp_edit.php" method="post" name="iform" id="iform">
	<script src="/javascript/scriptaculous/prototype.js" type="text/javascript">
	</script>
	<script type="text/javascript">
	function prefill_att() {
		$('dialcmd').value = '"ABORT BUSY ABORT NO\\\\sCARRIER TIMEOUT 5 \\"\\" AT OK-AT-OK ATQ0V1E1S0=0&C1&D2+FCLASS=0 OK \AT+CGDCONT=1,\\\\\\"IP\\\\\\",\\\\\\"ISP.CINGULAR\\\\\\" OK \\\\dATDT\\\\T \TIMEOUT 40 CONNECT"';
		$('phone').value = "*99#";
		$('username').value = "att";
		$('password').value = "att";
		$('linespeed').value = "921600";
	}
	function prefill_sprint() {
		$('dialcmd').value = '"ABORT BUSY ABORT NO\\\\sCARRIER TIMEOUT 5 \\"\\" AT OK-AT-OK ATE1Q0 OK \\\\dATDT\\\\T TIMEOUT 40 CONNECT"';
		$('phone').value = "#777";
		$('username').value = "sprint";
		$('password').value = "sprint";
		$('linespeed').value = "921600";
	}
	function prefill_vzw() {
		$('dialcmd').value = '"ABORT BUSY ABORT NO\\\\sCARRIER TIMEOUT 5 \\"\\" AT OK-AT-OK ATE1Q0s7=60 OK \\\\dATDT\\\\T TIMEOUT 40 CONNECT"';
		$('phone').value = "#777";
		$('username').value = "123@vzw3g.com";
		$('password').value = "vzw";
		$('linespeed').value = "921600";
	}
	</script>
	<table width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
			<td colspan="2" valign="top" class="listtopic">PPP configuration</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq">Parent interface</td>
			<td width="78%" class="vtable">
				<select name="port" id="port" class="formselect">
				<?php
					$portlist = glob("/dev/cua*");
					foreach ($portlist as $port) {
						if(preg_match("/\.(lock|init)$/", $port))
							continue;
						echo "<option value=\"".trim($port)."\"";
						if ($pconfig['port'] == $port)
							echo "selected";
						echo ">{$port}</option>";
					}
				?>
				</select>
				<p/>
				Pre-fill connection information: 
				<a href='#' onClick='javascript:prefill_att();'>ATT</A>
				<a href='#' onClick='javascript:prefill_sprint();'>Sprint</A>
				<a href='#' onClick='javascript:prefill_vzw();'>Verizon</A>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Link Type</td>
			<td width="78%" class="vtable">
				<input type="checkbox" value="on" id="defaultgw" name="defaultgw" <?php if (isset($pconfig['defaultgw'])) "echo checked"; ?>>This link will be used as default gateway.
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Init String</td>
			<td width="78%" class="vtable">
				<textarea id="initstr" name="initstr"><?=htmlspecialchars($pconfig['initstr']);?></textarea>
				<br><span class="vexpl">Enter the modem initialization string here</span>
			</td>
		</tr>
 		<tr>
 		  <td width="22%" valign="top" class="vncell">AP Hostname</td>
 		  <td width="78%" class="vtable">
 		    <input name="ap" type="text" class="formfld unknown" id="ap" size="40" value="<?=htmlspecialchars($pconfig['ap']);?>">
 		</td>
 		</tr>
 		<tr>
 		  <td width="22%" valign="top" class="vncell">Dial command</td>
 		  <td width="78%" class="vtable">
			<textarea rows="4" cols="65" name="dialcmd" id="dialcmd"><?=htmlspecialchars($pconfig['dialcmd']);?></textarea>
 		  </td>
 		</tr>
 		<tr>
 		  <td width="22%" valign="top" class="vncell">Phone Number</td>
 		  <td width="78%" class="vtable">
 		    <input name="phone" type="text" class="formfld unknown" id="phone" size="40" value="<?=htmlspecialchars($pconfig['phone']);?>">
 		  </td>
 		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Username</td>
			<td width="78%" class="vtable">
				<input name="username" type="text" class="formfld user" id="username" size="20" value="<?=htmlspecialchars($pconfig['username']);?>">
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Password</td>
			<td width="78%" class="vtable">
				<input name="password" type="password" class="formfld pwd" id="password" value="<?=htmlspecialchars($pconfig['password']);?>">
			</td>
		</tr>
 		<tr>
 		  <td width="22%" valign="top" class="vncell">Local IP</td>
 		  <td width="78%" class="vtable">
			<input name="localip" type="text" class="formfld unknown" id="localip" size="40" value="<?=htmlspecialchars($pconfig['localip']);?>">
			<span><p>Note: Enter your IP address here if it is not automatically assigned.</span>
 		  </td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Remote IP</td>
			<td width="78%" class="vtable">
				<input name="gateway" type="text" class="formfld unknown" id="gateway" size="40" value="<?=htmlspecialchars($pconfig['gateway']);?>">
				<span><p>Note: Enter the remote IP here if not automatically assigned. This is where the packets will be routed, equivalent to the gateway.</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Line Speed</td>
			<td width="78%" class="vtable">
				<input name="linespeed" type="text" class="formfld unknown" id="linespeed" size="40" value="<?=htmlspecialchars($pconfig['linespeed']);?>">
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Maximum connection retry</td>
			<td width="78%" class="vtable">
				<input name="connect-max-attempts" type="text" class="formfld unknown" id="connect-max-attempts" size="2" value="<?=htmlspecialchars($pconfig['connect-max-attempts']);?>">
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Description</td>
			<td width="78%" class="vtable">
				<input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
				<br> <span class="vexpl">You may enter a description here for your reference (not parsed).</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input name="Submit" type="submit" class="formbtn" value="Save"> 
				<input type="button" value="Cancel" onclick="history.back()">
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
