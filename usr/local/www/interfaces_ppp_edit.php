<?php
/*
	interfaces_ppp_edit.php
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
	$pconfig['initstr'] = base64_decode($a_ppps[$id]['initstr']);
	$pconfig['simpin'] = $a_ppps[$id]['simpin'];
	$pconfig['pin-wait'] = $a_ppps[$id]['pin-wait'];
	$pconfig['apn'] = $a_ppps[$id]['apn'];
	$pconfig['apnum'] = $a_ppps[$id]['apnum'];
	$pconfig['phone'] = $a_ppps[$id]['phone'];
	$pconfig['username'] = $a_ppps[$id]['username'];
	$pconfig['password'] = $a_ppps[$id]['password'];
	$pconfig['localip'] = $a_ppps[$id]['localip'];
	$pconfig['gateway'] = $a_ppps[$id]['gateway'];
	if (isset($a_ppps[$id]['defaultgw']))
		$pconfig['defaultgw'] = true;
	$pconfig['connect-timeout'] = $a_ppps[$id]['connect-timeout'];
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
		if ($_POST['initstr'] <> "")
			$ppp['initstr'] = base64_encode($_POST['initstr']);
		else
			unset($ppp['initstr']);
			
		if ($_POST['simpin'] <> "") {
			$ppp['simpin'] = $_POST['simpin'];
			$ppp['pin-wait'] = $_POST['pin-wait'];
		} else {
			unset($ppp['simpin']);
			unset($ppp['pin-wait']);
		}
		
		$ppp['apn'] = $_POST['apn'];
		if ($ppp['apn'] <> ""){
			if ($_POST['apnum'] <> "")
				$ppp['apnum'] = $_POST['apnum'];
			else
				$ppp['apnum'] = "1";
		} else {
			unset($ppp['apn']);
			unset($ppp['apnum']);
		}
		
		$ppp['phone'] = $_POST['phone'];
		$ppp['username'] = $_POST['username'];
		$ppp['password'] = $_POST['password'];
		$ppp['localip'] = $_POST['localip'];
		$ppp['gateway'] = $_POST['gateway'];
		if ($_POST['defaultgw'] == "on")
			$ppp['defaultgw'] = true;
		else
			unset($ppp['defaultgw']);
		if ($_POST['connect-timeout'] <> "")
			$ppp['connect-timeout'] = $_POST['connect-timeout'];
		else
			unset($ppp['connect-timeout']);
		$ppp['descr'] = $_POST['descr'];

        $iflist = get_configured_interface_list();
        foreach ($iflist as $if) {
        	if ($config['interfaces'][$if]['if'] == basename($a_ppps[$id]['port'])) {
				$config['interfaces'][$if]['if'] = basename($ppp['port']);
				$thisif = $if;
			}
		}
		if (isset($id) && $a_ppps[$id])
			$a_ppps[$id] = $ppp;
		else
			$a_ppps[] = $ppp;

		write_config();
		
		if (!empty($thisif))
			interface_ppp_configure($thisif);
		
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
		$('initstr').value = "Q0V1E1S0=0&C1&D2+FCLASS=0";
		$('apn').value = "ISP.CINGULAR";
		$('apnum').value = "1";
		$('phone').value = "*99#";
		$('username').value = "att";
		$('password').value = "att";
	}
	function prefill_sprint() {
		$('initstr').value = "E1Q0";
		$('apn').value = "";
		$('apnum').value = "";
		$('phone').value = "#777";
		$('username').value = "sprint";
		$('password').value = "sprint";
	}
	function prefill_vzw() {
		$('initstr').value = "E1Q0s7=60";
		$('apn').value = "";
		$('apnum').value = "";
		$('phone').value = "#777";
		$('username').value = "123@vzw3g.com";
		$('password').value = "vzw";
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
					$modems = glob("/dev/modem*");
					$portlist = array_merge($portlist, $modems);
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
				<input type="checkbox" value="on" id="defaultgw" name="defaultgw" <?php if (isset($pconfig['defaultgw'])) echo "checked"; ?>>This link will be used as the default gateway.
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Init String</td>
			<td width="78%" class="vtable">
				<input type="text" size="40" class="formfld unknown" id="initstr" name="initstr" value="<?=htmlspecialchars($pconfig['initstr']);?>">
				<br><span class="vexpl">Note: Enter the modem initialization string here. Do NOT include the "AT" string at the beginning of the command.</span>
			</td>
		</tr>
 		<tr>
 		  <td width="22%" valign="top" class="vncell">Sim PIN</td>
 		  <td width="78%" class="vtable">
 		    <input name="simpin" type="text" class="formfld unknown" id="simpin" size="12" value="<?=htmlspecialchars($pconfig['simpin']);?>">
 		</td>
 		</tr>
  		<tr>
 		  <td width="22%" valign="top" class="vncell">Sim PIN wait</td>
 		  <td width="78%" class="vtable">
 		    <input name="pin-wait" type="text" class="formfld unknown" id="pin-wait" size="2" value="<?=htmlspecialchars($pconfig['pin-wait']);?>">
 		    <br><span class="vexpl">Note: Time to wait for SIM to discover network after PIN is sent to SIM (seconds).</span>
 		</td>
 		</tr>
 		<tr>
 		  <td width="22%" valign="top" class="vncell">Access Point Name (APN)</td>
 		  <td width="78%" class="vtable">
 		    <input name="apn" type="text" class="formfld unknown" id="apn" size="40" value="<?=htmlspecialchars($pconfig['apn']);?>">
 		</td>
 		</tr>
 		<tr>
 		  <td width="22%" valign="top" class="vncell">APN number (optional)</td>
 		  <td width="78%" class="vtable">
 		    <input name="apnum" type="text" class="formfld unknown" id="apnum" size="2" value="<?=htmlspecialchars($pconfig['apnum']);?>">
 		    <br><span class="vexpl">Note: Defaults to 1 if you set APN above. Ignored if you set no APN above.</span>
 		</td>
 		</tr>
 		<tr>
 		  <td width="22%" valign="top" class="vncell">Phone Number</td>
 		  <td width="78%" class="vtable">
 		    <input name="phone" type="text" class="formfld unknown" id="phone" size="40" value="<?=htmlspecialchars($pconfig['phone']);?>">
 		    <br><span class="vexpl">Note: Typically (*99# or *99***# or *99***1#) for GSM networks and *777 for CDMA networks</span>
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
			<br><span class="vexpl">Note: Enter your IP address here if it is not automatically assigned.</span>
 		  </td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Remote IP (Gateway)</td>
			<td width="78%" class="vtable">
				<input name="gateway" type="text" class="formfld unknown" id="gateway" size="40" value="<?=htmlspecialchars($pconfig['gateway']);?>">
				<br><span class="vexpl">Note: Enter the remote IP here if not automatically assigned. This is where the packets will be routed.</span>
			</td>
		</tr>
		<tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Connection Timeout</td>
			<td width="78%" class="vtable">
				<input name="connect-timeout" type="text" class="formfld unknown" id="connect-timeout" size="2" value="<?=htmlspecialchars($pconfig['connect-timeout']);?>">
				<br><span class="vexpl">Note: Enter timeout in seconds for connection to be established (sec.) Default is 45 sec.</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Description</td>
			<td width="78%" class="vtable">
				<input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
				<br><span class="vexpl">You may enter a description here for your reference (not parsed).</span>
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
