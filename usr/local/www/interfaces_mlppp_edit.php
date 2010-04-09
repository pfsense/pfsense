<?php
/* $Id$ */
/*
	interfaces_mlppp_edit.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.
	Copyright (C) 2010 Gabriel B. <gnoahb@gmail.com>.
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
##|*IDENT=page-interfaces-mlppp-edit
##|*NAME=Interfaces: mlppp: Edit page
##|*DESCR=Allow access to the 'Interfaces: mlppp: Edit' page.
##|*MATCH=interfaces_mlppp_edit.php*
##|-PRIV

require("guiconfig.inc");

function remove_bad_chars($string) {
	return preg_replace('/[^a-z|_|0-9]/i','',$string);
}

if (!is_array($config['ppps']['ppp']))
	$config['ppps']['ppp'] = array();

$a_ppps = &$config['ppps']['ppp'];

$portlist = get_interface_list();

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_ppps[$id]) {
	$pconfig['type'] = $a_ppps[$id]['type'];
	$pconfig['username'] = $a_ppps[$id]['username'];
	$pconfig['password'] = $a_ppps[$id]['password'];
	if (isset($a_ppps[$id]['defaultgw']))
		$pconfig['defaultgw'] = true;
	if (isset($a_ppps[$id]['ondemand'])){
		$pconfig['ondemand'] = true;
		$pconfig['idletimeout'] = $a_ppps[$id]['idletimeout'];
	}
	$pconfig['descr'] = $a_ppps[$id]['descr'];
	if (isset($a_ppps[$id]['vjcomp']))
		$pconfig['vjcomp'] = true;
	if (isset($a_ppps[$id]['tcpmssfix']))
		$pconfig['tcpmssfix'] = true;
	$pconfig['bandwidth'] = $a_ppps[$id]['bandwidth'];
	$pconfig['mtu'] = $a_ppps[$id]['mtu'];
	$pconfig['mru'] = $a_ppps[$id]['mru'];
	if ($a_ppps[$id]['type'] == "ppp") {
		$pconfig['serialports'] = $a_ppps[$id]['ports'];
		$pconfig['initstr'] = base64_decode($a_ppps[$id]['initstr']);
		$pconfig['simpin'] = $a_ppps[$id]['simpin'];
		$pconfig['pin-wait'] = $a_ppps[$id]['pin-wait'];
		$pconfig['apn'] = $a_ppps[$id]['apn'];
		$pconfig['apnum'] = $a_ppps[$id]['apnum'];
		$pconfig['phone'] = $a_ppps[$id]['phone'];
		$pconfig['connect-timeout'] = $a_ppps[$id]['connect-timeout'];
		$pconfig['localip'] = $a_ppps[$id]['localip'];
		$pconfig['gateway'] = $a_ppps[$id]['gateway'];
	}
	if ($a_ppps[$id]['type'] == "pptp") {
		$pconfig['interfaces'] = $a_ppps[$id]['ports'];
		$pconfig['localip'] = $a_ppps[$id]['localip'];
		$pconfig['subnet'] = $a_ppps[$id]['subnet'];
		$pconfig['gateway'] = $a_ppps[$id]['gateway'];
	}
	if ($a_ppps[$id]['type'] == "pppoe") {
		$pconfig['interfaces'] = $a_ppps[$id]['ports'];
		$pconfig['provider'] = $a_ppps[$id]['provider'];
		/* ================================================ */
		/* = force a connection reset at a specific time? = */
		/* ================================================ */
		
		if (isset($wancfg['pppoe']['pppoe-reset-type'])) {
			$resetTime = getMPDResetTimeFromConfig();  
			$pconfig['pppoe_preset'] = true;
			if ($wancfg['pppoe']['pppoe-reset-type'] == "custom") {
				$resetTime_a = split(" ", $resetTime);
				$pconfig['pppoe_pr_custom'] = true;
				$pconfig['pppoe_resetminute'] = $resetTime_a[0];
				$pconfig['pppoe_resethour'] = $resetTime_a[1];
				/*  just initialize $pconfig['pppoe_resetdate'] if the
				 *  coresponding item contains appropriate numeric values.
				 */
				if ($resetTime_a[2] <> "*" && $resetTime_a[3] <> "*") 
					$pconfig['pppoe_resetdate'] = "{$resetTime_a[3]}/{$resetTime_a[2]}/" . date("Y");
			} else if ($wancfg['pppoe']['pppoe-reset-type'] == "preset") {
				$pconfig['pppoe_pr_preset'] = true;
				switch ($resetTime) {
					case CRON_MONTHLY_PATTERN:
						$pconfig['pppoe_monthly'] = true;
						break;
					case CRON_WEEKLY_PATTERN:
						$pconfig['pppoe_weekly'] = true;
						break;
					case CRON_DAILY_PATTERN:
						$pconfig['pppoe_daily'] = true;
						break;
					case CRON_HOURLY_PATTERN:
						$pconfig['pppoe_hourly'] = true;
						break;
				}
			}
		}
	}
	
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;
	
	/* filter out spaces from descriptions  */
	$_POST['descr'] = remove_bad_chars($_POST['descr']);
	/* okay first of all, cause we are just hiding the PPPoE HTML
	 * fields releated to PPPoE resets, we are going to unset $_POST
	 * vars, if the reset feature should not be used. Otherwise the
	 * data validation procedure below, may trigger a false error
	 * message.
	 */
	if (empty($_POST['pppoe_preset'])) {
		unset($_POST['pppoe_pr_type']);                
		unset($_POST['pppoe_resethour']);
		unset($_POST['pppoe_resetminute']);
		unset($_POST['pppoe_resetdate']);
		unset($_POST['pppoe_pr_preset_val']);
	}

	/* input validation */

	switch($_POST['type']) {
		case "PPP":
			$reqdfields = explode(" ", "serialports, phone");
			$reqdfieldsn = explode(",", "Serial Port(s), Phone Number");
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
			break;
		case "PPPoE":
			if ($_POST['ondemand']) {
				$reqdfields = explode(" ", "interfaces username password dialondemand idletimeout");
				$reqdfieldsn = explode(",", "Link Interface(s),PPPoE username,PPPoE password,Dial on demand,Idle timeout value");
			} else {
				$reqdfields = explode(" ", "interfaces username password");
				$reqdfieldsn = explode(",", "Link Interface(s),PPPoE username,PPPoE password");
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
			break;
		case "PPTP":
			if ($_POST['ondemand']) {
				$reqdfields = explode(" ", "interfaces username password localip subnet gateway dialondemand idletimeout");
				$reqdfieldsn = explode(",", "Link Interface(s),PPTP username,PPTP password,PPTP local IP address,PPTP subnet,PPTP remote IP address,Dial on demand,Idle timeout value");
			} else {
				$reqdfields = explode(" ", "interfaces username password localip subnet gateway");
				$reqdfieldsn = explode(",", "Link Interface(s),PPTP username,PPTP password,PPTP local IP address,PPTP subnet,PPTP remote IP address");
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
			break;
	}
	if (($_POST['provider'] && !is_domain($_POST['provider']))) 
		$input_errors[] = "The service name contains invalid characters.";
	if (($_POST['idletimeout'] != "") && !is_numericint($_POST['idletimeout'])) 
		$input_errors[] = "The idle timeout value must be an integer.";
	if ($_POST['pppoe_resethour'] <> "" && !is_numericint($_POST['pppoe_resethour']) && 
		$_POST['pppoe_resethour'] >= 0 && $_POST['pppoe_resethour'] <=23) 
		$input_errors[] = gettext("A valid PPPoE reset hour must be specified (0-23).");
	if ($_POST['pppoe_resetminute'] <> "" && !is_numericint($_POST['pppoe_resetminute']) && 
		$_POST['pppoe_resetminute'] >= 0 && $_POST['pppoe_resetminute'] <=59) 
		$input_errors[] = gettext("A valid PPPoE reset minute must be specified (0-59).");
	if ($_POST['pppoe_resetdate'] <> "" && !is_numeric(str_replace("/", "", $_POST['pppoe_resetdate']))) 
		$input_errors[] = gettext("A valid PPPoE reset date must be specified (mm/dd/yyyy).");
	if (($_POST['localip'] && !is_ipaddr($_POST['localip']))) 
		$input_errors[] = "A valid PPTP local IP address must be specified.";
	if (($_POST['subnet'] && !is_numeric($_POST['subnet']))) 
		$input_errors[] = "A valid PPTP subnet bit count must be specified.";
	if (($_POST['gateway'] && !is_ipaddr($_POST['gateway']))) 
		$input_errors[] = "A valid PPTP remote IP address must be specified.";
		
	if ($_POST['mtu'] && ($_POST['mtu'] < 576)) 
		$input_errors[] = "The MTU must be greater than 576 bytes.";
	if ($_POST['mru'] && ($_POST['mru'] < 576)) 
		$input_errors[] = "The MRU must be greater than 576 bytes.";
/*
	foreach ($a_ppps as $ppp) {
		if (isset($id) && ($a_ppps[$id]) && ($a_ppps[$id] === $ppp))
			continue;

		if ($ppp['serialport'] == $_POST['serialport']) {
			$input_errors[] = "Serial port is in use";
			break;
		}
	}
*/	

	if (!$input_errors) {
		$ppp = array();
		$ppp['type'] = $_POST['type'];
		$ppp['username'] = $_POST['username'];
		$ppp['password'] = $_POST['password'];
		$ppp['defaultgw'] = $_POST['defaultgw'] ? true : false;
		switch($_POST['type']) {
			case "ppp":
				$ppp['ports'] = implode(',', $_POST['serialports']);
				if (!empty($_POST['initstr']))
					$ppp['initstr'] = base64_encode($_POST['initstr']);
				else
					unset($ppp['initstr']);
				if (!empty($_POST['simpin'])) {
					$ppp['simpin'] = $_POST['simpin'];
					$ppp['pin-wait'] = $_POST['pin-wait'];
				} else {
					unset($ppp['simpin']);
					unset($ppp['pin-wait']);
				}
				
				if (!empty($_POST['apn'])){
					$ppp['apn'] = $_POST['apn'];
					if (!empty($_POST['apnum']))
						$ppp['apnum'] = $_POST['apnum'];
					else
						$ppp['apnum'] = "1";
				} else {
					unset($ppp['apn']);
					unset($ppp['apnum']);
				}
				$ppp['phone'] = $_POST['phone'];
				if (!empty($_POST['localip']))
					$ppp['localip'] = $_POST['localip'];
				else
					unset($ppp['localip']);
				if (!empty($_POST['gateway']))
					$ppp['gateway'] = $_POST['gateway'];
				else
					unset($ppp['gateway']);
				if (!empty($_POST['connect-timeout']))
					$ppp['connect-timeout'] = $_POST['connect-timeout'];
				else
					unset($ppp['connect-timeout']);
				break;
			case "pppoe":
				$ppp['ports'] = implode(',', $_POST['interfaces']);
				if (!empty($_POST['provider']))
					$ppp['provider'] = $_POST['provider'];
				else
					unset($ppp['provider']);
				break;
			case "pptp":
				$ppp['ports'] = implode(',', $_POST['interfaces']);
				$ppp['localip'] = $_POST['localip'];
				$ppp['subnet'] = $_POST['subnet'];
				$ppp['gateway'] = $_POST['gateway'];
				break;
		}
		if (!empty($_POST['descr']))
			$ppp['descr'] = $_POST['descr'];
		else
			unset($ppp['descr']);
		$ppp['ondemand'] = $_POST['ondemand'] ? true : false;
		if (isset($ppp['ondemand']))
			$ppp['idletimeout'] = $_POST['idletimeout'];
		else 
			unset($ppp['idletimeout']);
		$ppp['vjcomp'] = $_POST['vjcomp'] ? true : false;
		$ppp['tcpmssfix'] = $_POST['tcpmssfix'] ? true : false;
		if (isset($_POST['bandwidth']))
			$ppp['bandwidth'] = $_POST['bandwidth'];
		else 
			unset($ppp['bandwidth']);
	
        $iflist = get_configured_interface_list();
        /*
        foreach ($iflist as $if) {
        	if ($config['interfaces'][$if]['if'] == basename($a_ppps[$id]['port']))
				$config['interfaces'][$if]['if'] = basename($ppp['port']);
		}
		*/
		if (isset($id) && $a_ppps[$id])
			$a_ppps[$id] = $ppp;
		else
			$a_ppps[] = $ppp;

		write_config();

		header("Location: interfaces_mlppp.php");
		exit;
	}
}

$closehead = false;
$pgtitle = array("Interfaces","MLPPP","Edit");
include("head.inc");
$types = array("select" => "Select", "ppp" => "PPP", "pppoe" => "PPPoE", "pptp" => "PPTP"/*,  "l2tp" => "L2TP", "tcp" => "TCP", "udp" => "UDP", "ng" => "Netgraph" */  ); 


?>

<script type="text/javascript">

	function updateType(t){
		switch(t) {
			case "select": {
				document.getElementById("ppp").style.display = 'none';
				document.getElementById("pppoe").style.display = 'none';
				document.getElementById("pptp").style.display = 'none';
				document.getElementById("interface").style.display = 'none';
				document.getElementById("serialport").style.display = 'none';
				document.getElementById("ipfields").style.display = 'none';
				break;
			}
			case "ppp": {
				document.getElementById("select").style.display = 'none';
				document.getElementById("pppoe").style.display = 'none';
				document.getElementById("pptp").style.display = 'none';
				document.getElementById("interface").style.display = 'none';
				document.getElementById("serialport").style.display = '';
				document.getElementById("ipfields").style.display = '';
				document.getElementById("subnet").style.display = 'none';
				break;
			}
			case "pppoe": {
				document.getElementById("select").style.display = 'none';
				document.getElementById("ppp").style.display = 'none';
				document.getElementById("pptp").style.display = 'none';
				document.getElementById("interface").style.display = '';
				document.getElementById("serialport").style.display = 'none';
				document.getElementById("ipfields").style.display = 'none';
				break;
			}
			case "pptp": {
				document.getElementById("select").style.display = 'none';
				document.getElementById("ppp").style.display = 'none';
				document.getElementById("pppoe").style.display = 'none';
				document.getElementById("interface").style.display = '';
				document.getElementById("serialport").style.display = 'none';
				document.getElementById("ipfields").style.display = '';
				document.getElementById("subnet").style.display = '';
				break;
			}
		}
		$(t).show();
	}
	function show_allcfg(obj) {
		if (obj.checked)
			$('allcfg').show();
		else
			$('allcfg').hide();
	}
	
	function show_periodic_reset(obj) {
		if (obj.checked)
			$('presetwrap').show();
		else
			$('presetwrap').hide();
	}

	function show_mon_config() {
		document.getElementById("showmonbox").innerHTML='';
		aodiv = document.getElementById('showmon');
		aodiv.style.display = "block";
	}

	function openwindow(url) {
		var oWin = window.open(url,"pfSensePop","width=620,height=400,top=150,left=150");
		if (oWin==null || typeof(oWin)=="undefined") 
			return false;
		else 
			return true;
	}
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
</head>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onLoad="updateType(<?php echo "'{$pconfig['type']}'";?>)">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="interfaces_mlppp_edit.php" method="post" name="iform" id="iform">
              <table id="interfacetable" width="100%" border="0" cellpadding="6" cellspacing="0">
              	<tr>
					<td colspan="2" valign="top" class="listtopic">MLPPP configuration</td>
				</tr>
				<tr>
					<td valign="middle" class="vncell"><strong>Link Type</strong></td>
					<td class="vtable"> 
						<select name="type" onChange="updateType(this.value);"  class="formselect" id="type">
						<?php 
							foreach ($types as $key => $opt) { 
								echo "<option onClick=\"updateType('{$key}');\"";
								if ($key == $pconfig['type']) 
									echo " selected";
								echo " value=\"{$key}\" >" . htmlspecialchars($opt);
								echo "</option>";
							} 
						?>
						</select>
					</td>
				</tr>
				<tr style="display:none" name="interface" id="interface" >
					<td width="22%" valign="top" class="vncellreq">Member interface(s)</td>
					<td width="78%" class="vtable">
						<select name="interfaces[]" multiple="true" class="formselect">
							<?php
								foreach ($portlist as $ifn => $ifinfo)
								if (is_jumbo_capable($ifn)) {
									echo "<option value=\"{$ifn}\"";
									if (stristr($pconfig['interfaces'], $ifn))
										echo "selected";
									echo ">";
									echo htmlspecialchars($ifn . " (" . $ifinfo['mac'] . ")");
									echo "</option>";
								}
							?>
						</select>
						<br/><span class="vexpl">Interfaces participating in the multilink connection.</span>
					</td>
            	</tr>
            	<tr style="display:none" name="serialport" id="serialport">
					<td width="22%" valign="top" class="vncellreq">Member interface(s)</td>
					<td width="78%" class="vtable">
						<select name="serialports[]" multiple="true" class="formselect">
						<?php
							$serportlist = glob("/dev/cua*");
							$modems = glob("/dev/modem*");
							$serportlist = array_merge($serportlist, $modems);
							foreach ($serportlist as $port) {
								if(preg_match("/\.(lock|init)$/", $port))
									continue;
								echo "<option value=\"".trim($port)."\"";
								if ($pconfig['port'] == $port)
									echo "selected";
								echo ">{$port}</option>";
							}
						?>
						</select>
						<br/><span class="vexpl">Serial Ports participating in the multilink connection.</span>
						<p/>
						<a href='#' onClick='javascript:prefill_att();'>ATT</A>
						<a href='#' onClick='javascript:prefill_sprint();'>Sprint</A>
						<a href='#' onClick='javascript:prefill_vzw();'>Verizon</A>
					</td>
				</tr>

				<tr>
					<td valign="top" class="vncell">Username</td>
					<td class="vtable">
					<input name="username" type="text" class="formfld usr" id="username" size="20" value="<?=htmlspecialchars($pconfig['username']);?>">
					</td>
			    </tr>
			    <tr>
					<td valign="top" class="vncell">Password</td>
					<td class="vtable">
					<input name="password" type="text" class="formfld pwd" id="password" size="20" value="<?=htmlspecialchars($pconfig['password']);?>">
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell">Gateway</td>
					<td width="78%" class="vtable">
						<input type="checkbox" value="on" id="defaultgw" name="defaultgw" <?php if (isset($pconfig['defaultgw'])) echo "checked"; ?>>This link will be used as the default gateway.
					</td>
				</tr>
				<tr>
            	<td valign="top" class="vncell">Dial On Demand</td>
					<td class="vtable">
						<input type="checkbox" value="on" id="ondemand" name="ondemand" <?php if (isset($pconfig['ondemand'])) echo "checked"; ?>> Enable Dial-on-Demand mode
						<br> <span class="vexpl">This option causes the interface to operate in dial-on-demand mode, allowing you to have a virtual full time connection. 
						The interface is configured, but the actual connection of the link is delayed until qualifying outgoing traffic is detected. </span>
					</td>
			    </tr>
			    <tr>
					<td valign="top" class="vncell">Idle Timeout</td>
					<td class="vtable">
						<input name="idletimeout" type="text" class="formfld unknown" id="idletimeout" size="6" value="<?=htmlspecialchars($pconfig['idletimeout']);?>">
						<br> <span class="vexpl">Idle Timeout goes with the OnDemand selection above. If OnDemand is not checked this is ignored.</span>
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
					<td colspan="2" valign="top" height="16"></td>
				</tr>
			    <tr style="display:none" name="select" id="select">
				</tr>
				<tr style="display:none" name="ppp" id="ppp">
					<td colspan="2" style="padding:0px;">
						<table width="100%" border="0" cellpadding="6" cellspacing="0">
							<tr>
								<td colspan="2" valign="top" class="listtopic">PPP configuration</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Init String</td>
								<td width="78%" class="vtable">
									<input type="text" size="40" class="formfld unknown" id="initstr" name="initstr" value="<?=htmlspecialchars($pconfig['initstr']);?>">
									<br><span class="vexpl">Note: Enter the modem initialization string here. Do NOT include the "AT" string at the beginning of the command. Many modern USB 3G
									modems don't need an initialization string.</span>
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
								<td width="22%" valign="top" class="vncell">Connection Timeout</td>
								<td width="78%" class="vtable">
									<input name="connect-timeout" type="text" class="formfld unknown" id="connect-timeout" size="2" value="<?=htmlspecialchars($pconfig['connect-timeout']);?>">
									<br><span class="vexpl">Note: Enter timeout in seconds for connection to be established (sec.) Default is 45 sec.</span>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr style="display:none" name="pppoe" id="pppoe">
					<td colspan="2" style="padding:0px;">
						<table width="100%" border="0" cellpadding="6" cellspacing="0">
							<tr>
								<td colspan="2" valign="top" class="listtopic">PPPoE configuration</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Service name</td>
								<td width="78%" class="vtable"><input name="provider" type="text" class="formfld unknown" id="provider" size="20" value="<?=htmlspecialchars($pconfig['provider']);?>">
									<br> <span class="vexpl">Hint: this field can usually be left empty</span>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Periodic reset");?></td>
								<td width="78%" class="vtable">
									<input name="pppoe_preset" type="checkbox" id="pppoe_preset" value="yes" <?php if ($pconfig['pppoe_preset']) echo "checked=\"checked\""; ?> onclick="show_periodic_reset(this);" />
									<?= gettext("enable periodic PPPoE resets"); ?>
									<br />
									<?php if ($pconfig['pppoe_preset']): ?>
									<table id="presetwrap" cellspacing="0" cellpadding="0" width="100%">
									<?php else: ?>
									<table id="presetwrap" cellspacing="0" cellpadding="0" width="100%" style="display: none;">
										<?php endif; ?>
										<tr>
											<td align="left" valign="top">
												<p style="margin: 4px; padding: 4px 0 4px 0; width: 94%;">
													<input name="pppoe_pr_type" type="radio" id="pppoe_pr_custom" value="custom" <?php if ($pconfig['pppoe_pr_custom']) echo "checked=\"checked\""; ?> onclick="if (this.checked) { Effect.Appear('pppoecustomwrap', { duration: 0.0 }); Effect.Fade('pppoepresetwrap', { duration: 0.0 }); }" /> 
														<?= gettext("provide a custom reset time"); ?>
														<br />
														<input name="pppoe_pr_type" type="radio" id="pppoe_pr_preset" value="preset" <?php if ($pconfig['pppoe_pr_preset']) echo "checked=\"checked\""; ?> onclick="if (this.checked) { Effect.Appear('pppoepresetwrap', { duration: 0.0 }); Effect.Fade('pppoecustomwrap', { duration: 0.0 }); }" /> 
															<?= gettext("select reset time from a preset"); ?>
														</p>
														<?php if ($pconfig['pppoe_pr_custom']): ?>
															<p style="margin: 2px; padding: 4px; width: 94%;" id="pppoecustomwrap">
															<?php else: ?>
																<p style="margin: 2px; padding: 4px; width: 94%; display: none;" id="pppoecustomwrap">
																<?php endif; ?>
																<input type="text" name="pppoe_resethour" class="fd_incremental_inp_range_0_23 fd_increment_1 fd_classname_dec_buttonDec fd_classname_inc_buttonInc" maxlength="2" id="pppoe_resethour" value="<?= $pconfig['pppoe_resethour']; ?>" size="3" /> 
																<?= gettext("hour (0-23)"); ?><br />
																<input type="text" name="pppoe_resetminute" class="fd_incremental_inp_range_0_59 fd_increment_1 fd_classname_dec_buttonDec fd_classname_inc_buttonInc" maxlength="2" id="pppoe_resetminute" value="<?= $pconfig['pppoe_resetminute']; ?>" size="3" /> 
																<?= gettext("minute (0-59)"); ?><br />
																<input name="pppoe_resetdate" type="text" class="w8em format-m-d-y highlight-days-67" id="pppoe_resetdate" maxlength="10" size="10" value="<?=htmlspecialchars($pconfig['pppoe_resetdate']);?>" /> 
																<?= gettext("reset at a specific date (mm/dd/yyyy)"); ?>
																<br />&nbsp;<br />
																<span class="red"><strong>Note: </strong></span>
																If you leave the date field empty, the reset will be executed each day at the time you did specify using the minutes and hour field.
															</p>
															<?php if ($pconfig['pppoe_pr_preset']): ?>
																<p style="margin: 2px; padding: 4px; width: 94%;" id="pppoepresetwrap">
																<?php else: ?>
																	<p style="margin: 2px; padding: 4px; width: 94%; display: none;" id="pppoepresetwrap">
																	<?php endif; ?>
																	<input name="pppoe_pr_preset_val" type="radio" id="pppoe_monthly" value="monthly" <?php if ($pconfig['pppoe_monthly']) echo "checked=\"checked\""; ?> /> 
																	<?= gettext("reset at each month ('0 0 1 * *')"); ?>
																	<br />
																	<input name="pppoe_pr_preset_val" type="radio" id="pppoe_weekly" value="weekly" <?php if ($pconfig['pppoe_weekly']) echo "checked=\"checked\""; ?> /> 
																	<?= gettext("reset at each week ('0 0 * * 0')"); ?>
																	<br />
																	<input name="pppoe_pr_preset_val" type="radio" id="pppoe_daily" value="daily" <?php if ($pconfig['pppoe_daily']) echo "checked=\"checked\""; ?> /> 
																	<?= gettext("reset at each day ('0 0 * * *')"); ?>
																	<br />
																	<input name="pppoe_pr_preset_val" type="radio" id="pppoe_hourly" value="hourly" <?php if ($pconfig['pppoe_hourly']) echo "checked=\"checked\""; ?> /> 
																	<?= gettext("reset at each hour ('0 * * * *')"); ?>
													</p>
											</td>
										</tr>
									</table>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr style="display:none" name="pptp" id="pptp">
					<td colspan="2" style="padding:0px;">
						<table width="100%" border="0" cellpadding="6" cellspacing="0">
							<tr>
								<td colspan="2" valign="top" class="listtopic">PPTP configuration</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr style="display:none" id="ipfields">
					<td colspan="2" style="padding:0px;">
						<table width="100%" border="0" cellpadding="6" cellspacing="0">
							<tr>
								<td width="22%" valign="top" class="vncell">Local IP address</td>
								<td width="78%" class="vtable"> 
									<input name="localip" type="text" class="formfld unknown" id="localip" size="20"  value="<?=htmlspecialchars($pconfig['localip']);?>">
									/
									<select style="display:none" class="formselect" id="subnet">
									<?php for ($i = 31; $i > 0; $i--): ?>
										<option value="<?=$i;?>" <?php if ($i == $pconfig['subnet']) echo "selected"; ?>>
											<?=$i;?>
										</option>
									<?php endfor; ?>
									</select>
									<br><span class="vexpl">Note: Enter the local IP here if not automatically assigned. Subnet is ignored for PPP connections.</span>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Remote IP (Gateway)</td>
								<td width="78%" class="vtable">
									<input name="gateway" type="text" class="formfld unknown" id="gateway" size="20" value="<?=htmlspecialchars($pconfig['gateway']);?>">
									<br><span class="vexpl">Note: Enter the remote IP here if not automatically assigned. This is where the packets will be routed.</span>
								</td>
							</tr>
						</table>
					</td>
			    <tr>
					<td colspan="2" valign="top" height="16"></td>
				</tr>
				<tr>
					<td colspan="2" valign="top" class="listtopic">Advanced Options</td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell">Compression</td>
					<td width="78%" class="vtable">
						<input type="checkbox" value="on" id="vjcomp" name="vjcomp" <?php if (isset($pconfig['vjcomp'])) echo "checked"; ?>>&nbsp;Disable vjcomp(compression).
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell">TCPmssfix</td>
					<td width="78%" class="vtable">
						<input type="checkbox" value="on" id="tcpmssfix" name="tcpmssfix" <?php if (isset($pconfig['tcpmssfix'])) echo "checked"; ?>>&nbsp;Enable tcpmssfix.
					</td>
				</tr>
				<tr>
					<td width="22%" width="100" valign="top" class="vncell">Bandwidth</td>
					<td width="78%" class="vtable">
					<input name="bandwidth" type="text" class="formfld unknown" id="bandwidth" size="10" value="<?=htmlspecialchars($pconfig['bandwidth']);?>">&nbsp;(bits/sec)
					<br> <span class="vexpl">Set Bandwidth for each link if links have different bandwidths, otherwise, leave blank.</span>
				  </td>
				</tr>
				<tr>
				  <td width="22%" width="100" valign="top" class="vncell">Link MTU</td>
				  <td width="78%" class="vtable">
					<input name="mtu" type="text" class="formfld unknown" id="mtu" size="6" value="<?=htmlspecialchars($pconfig['mtu']);?>">
					<br> <span class="vexpl">Set MTU for each link if links have different bandwidths, otherwise, leave blank.</span>
				  </td>
				</tr>
				<tr>
				  <td width="22%" width="100" valign="top" class="vncell">Link MRU</td>
				  <td width="78%" class="vtable">
					<input name="mru" type="text" class="formfld unknown" id="mru" size="6" value="<?=htmlspecialchars($pconfig['mru']);?>">
					<br> <span class="vexpl">Set MRU for each link separated by commas, otherwise, leave blank.</span>
				  </td>
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