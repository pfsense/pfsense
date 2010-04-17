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
##|*NAME=Interfaces: MLPPP: Edit page
##|*DESCR=Allow access to the 'Interfaces: MLPPP: Edit' page.
##|*MATCH=interfaces_mlppp_edit.php*
##|-PRIV

require("guiconfig.inc");

define("CRON_PPPOE_CMD_FILE", "/conf/pppoe{$if}restart");
define("CRON_MONTHLY_PATTERN", "0 0 1 * *");
define("CRON_WEEKLY_PATTERN", "0 0 * * 0");
define("CRON_DAILY_PATTERN", "0 0 * * *");
define("CRON_HOURLY_PATTERN", "0 * * * *");

if (!is_array($config['ppps']['ppp']))
	$config['ppps']['ppp'] = array();

$a_ppps = &$config['ppps']['ppp'];

$portlist = get_interface_list();

function getMPDCRONSettings() {
	global $config;
	if (is_array($config['cron']['item'])) {
		for ($i = 0; $i < count($config['cron']['item']); $i++) {
			$item = $config['cron']['item'][$i];
			if (strpos($item['command'], CRON_PPPOE_CMD_FILE) !== false) {
				return array("ID" => $i, "ITEM" => $item);
			}
		}
	}
	return NULL;
}

function getMPDResetTimeFromConfig() {
	$itemhash = getMPDCRONSettings();
	$cronitem = $itemhash['ITEM'];
	if (isset($cronitem)) {
		return "{$cronitem['minute']} {$cronitem['hour']} {$cronitem['mday']} {$cronitem['month']} {$cronitem['wday']}";
	} else {
		return NULL;
	}
}

function remove_bad_chars($string) {
	return preg_replace('/[^a-z|_|0-9]/i','',$string);
}

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];


if (isset($id) && $a_ppps[$id]) {
	$pconfig['type'] = $a_ppps[$id]['type'];
	$pconfig['username'] = $a_ppps[$id]['username'];
	$pconfig['password'] = $a_ppps[$id]['password'];
	if (isset($a_ppps[$id]['defaultgw']))
		$pconfig['defaultgw'] = true;
	if (isset($a_ppps[$id]['ondemand']))
		$pconfig['ondemand'] = true;
	$pconfig['idletimeout'] = $a_ppps[$id]['idletimeout'];
	$pconfig['descr'] = $a_ppps[$id]['descr'];
	$pconfig['bandwidth'] = $a_ppps[$id]['bandwidth'];
	$pconfig['mtu'] = $a_ppps[$id]['mtu'];
	$pconfig['mru'] = $a_ppps[$id]['mru'];
	$pconfig['mrru'] = $a_ppps[$id]['mrru'];
	if (isset($a_ppps[$id]['shortseq']))
		$pconfig['shortseq'] = true;
	if (isset($a_ppps[$id]['acfcomp']))
		$pconfig['acfcomp'] = true;
	if (isset($a_ppps[$id]['protocomp']))
		$pconfig['protocomp'] = true;
	if (isset($a_ppps[$id]['vjcomp']))
		$pconfig['vjcomp'] = true;
	if (isset($a_ppps[$id]['tcpmssfix']))
		$pconfig['tcpmssfix'] = true;
	if ($a_ppps[$id]['type'] == "ppp") {
		$pconfig['interfaces'] = $a_ppps[$id]['ports'];
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
		
		if (isset($a_ppps[$id]['pppoe-reset-type'])) {
			$resetTime = getMPDResetTimeFromConfig();  
			$pconfig['pppoe_preset'] = true;
			if ($a_ppps[$id]['pppoe-reset-type'] == "custom") {
				$resetTime_a = split(" ", $resetTime);
				$pconfig['pppoe_pr_custom'] = true;
				$pconfig['pppoe_resetminute'] = $resetTime_a[0];
				$pconfig['pppoe_resethour'] = $resetTime_a[1];
				/*  just initialize $pconfig['pppoe_resetdate'] if the
				 *  coresponding item contains appropriate numeric values.
				 */
				if ($resetTime_a[2] <> "*" && $resetTime_a[3] <> "*") 
					$pconfig['pppoe_resetdate'] = "{$resetTime_a[3]}/{$resetTime_a[2]}/" . date("Y");
			} else if ($a_ppps[$id]['pppoe-reset-type'] == "preset") {
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
		case "ppp":
			$reqdfields = explode(" ", "interfaces phone");
			$reqdfieldsn = explode(",", "Link Interface(s),Phone Number");
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
			break;
		case "pppoe":
			if ($_POST['ondemand']) {
				$reqdfields = explode(" ", "interfaces username password ondemand idletimeout");
				$reqdfieldsn = explode(",", "Link Interface(s),Username,Password,Dial on demand,Idle timeout value");
			} else {
				$reqdfields = explode(" ", "interfaces username password");
				$reqdfieldsn = explode(",", "Link Interface(s),Username,Password");
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
			break;
		case "pptp":
			if ($_POST['ondemand']) {
				$reqdfields = explode(" ", "interfaces username password localip subnet gateway ondemand idletimeout");
				$reqdfieldsn = explode(",", "Link Interface(s),Username,Password,Local IP address,Subnet,Remote IP address,Dial on demand,Idle timeout value");
			} else {
				$reqdfields = explode(" ", "interfaces username password localip subnet gateway");
				$reqdfieldsn = explode(",", "Link Interface(s),Username,Password,Local IP address,Subnet,Remote IP address");
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
			break;
		default:
			$input_errors[] = "Please choose a Link Type.";
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
		$ppp['ondemand'] = $_POST['ondemand'] ? true : false;
		if (!empty($_POST['idletimeout']))
			$ppp['idletimeout'] = $_POST['idletimeout'];
		else 
			unset($ppp['idletimeout']);
		if (!empty($_POST['descr']))
			$ppp['descr'] = $_POST['descr'];
		else
			unset($ppp['descr']);
		switch($_POST['type']) {
			case "ppp":
				$ppp['ports'] = implode(',', $_POST['interfaces']);
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
				handle_pppoe_reset();
				
				break;
			case "pptp":
				$ppp['ports'] = implode(',', $_POST['interfaces']);
				$ppp['localip'] = $_POST['localip'];
				$ppp['subnet'] = $_POST['subnet'];
				$ppp['gateway'] = $_POST['gateway'];
				break;
			default:
				break;
			
		}
		/* reset cron items if necessary */
		if (empty($_POST['pppoe_preset'])) {
			/* test whether a cron item exists and unset() it if necessary */
			$itemhash = getMPDCRONSettings();
			$item = $itemhash['ITEM'];
			if (isset($item))
				unset($config['cron']['item'][$itemhash['ID']]); 
		}
		$ppp['shortseq'] = $_POST['shortseq'] ? true : false;
		$ppp['acfcomp'] = $_POST['acfcomp'] ? true : false;
		$ppp['protocomp'] = $_POST['protocomp'] ? true : false;
		$ppp['vjcomp'] = $_POST['vjcomp'] ? true : false;
		$ppp['tcpmssfix'] = $_POST['tcpmssfix'] ? true : false;
		if (isset($_POST['bandwidth']))
			$ppp['bandwidth'] = $_POST['bandwidth'];
		else 
			unset($ppp['bandwidth']);
		if (isset($_POST['mtu']))
			$ppp['mtu'] = $_POST['mtu'];
		else 
			unset($ppp['mtu']);
		if (isset($_POST['mru']))
			$ppp['mru'] = $_POST['mru'];
		else 
			unset($ppp['mru']);
			
			
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
} // end if($_POST)

function handle_pppoe_reset() {
	global $_POST, $config, $g, $ppp, $if;
	/* perform a periodic reset? */
	if(!isset($_POST['pppoe_preset'])) {
		setup_pppoe_reset_file($if, false);		
		return;
	}
	if (!is_array($config['cron']['item'])) 
		$config['cron']['item'] = array(); 
	$itemhash = getMPDCRONSettings();
	$item = $itemhash['ITEM'];
	if (empty($item)) 
		$item = array();
	if (isset($_POST['pppoe_pr_type']) && $_POST['pppoe_pr_type'] == "custom") {
		$ppp['pppoe-reset-type'] = "custom";
		$pconfig['pppoe_pr_custom'] = true;
		$item['minute'] = $_POST['pppoe_resetminute'];
		$item['hour'] = $_POST['pppoe_resethour'];
		if (isset($_POST['pppoe_resetdate']) && $_POST['pppoe_resetdate'] <> "" && strlen($_POST['pppoe_resetdate']) == 10) {
			$date = explode("/", $_POST['pppoe_resetdate']);
			$item['mday'] = $date[1];
			$item['month'] = $date[0];
		} else {
			$item['mday'] = "*";
			$item['month'] = "*";
		}
		$item['wday'] = "*";
		$item['who'] = "root";
		$item['command'] = CRON_PPPOE_CMD_FILE;
	} else if (isset($_POST['pppoe_pr_type']) && $_POST['pppoe_pr_type'] = "preset") {
		$ppp['pppoe-reset-type'] = "preset";
		$pconfig['pppoe_pr_preset'] = true;
		switch ($_POST['pppoe_pr_preset_val']) {
			case "monthly":
				$item['minute'] = "0";
				$item['hour'] = "0";
				$item['mday'] = "1";
				$item['month'] = "*";
				$item['wday'] = "*";
				$item['who'] = "root";
				$item['command'] = CRON_PPPOE_CMD_FILE;
				break;
	        case "weekly":
				$item['minute'] = "0";
				$item['hour'] = "0";
				$item['mday'] = "*";
				$item['month'] = "*";
				$item['wday'] = "0";
				$item['who'] = "root";
				$item['command'] = CRON_PPPOE_CMD_FILE;
				break;
			case "daily":
				$item['minute'] = "0";
				$item['hour'] = "0";
				$item['mday'] = "*";
				$item['month'] = "*";
				$item['wday'] = "*";
				$item['who'] = "root";
				$item['command'] = CRON_PPPOE_CMD_FILE;
				break;
			case "hourly":
				$item['minute'] = "0";
				$item['hour'] = "*";
				$item['mday'] = "*";
				$item['month'] = "*";
				$item['wday'] = "*";
				$item['who'] = "root";
				$item['command'] = CRON_PPPOE_CMD_FILE;
				break;
		} // end switch
	} // end if
	if (isset($itemhash['ID'])) 
		$config['cron']['item'][$itemhash['ID']] = $item;
	else 
		$config['cron']['item'][] = $item;
	/* finally install the pppoerestart file */
	if (isset($_POST['pppoe_preset'])) {
		setup_pppoe_reset_file($if, true);
		//$ppp['pppoe_reset'] = true;
		$ppp['pppoe_preset'] = true;
		sigkillbypid("{$g['varrun_path']}/cron.pid", "HUP");
	} else {
		//unset($ppp['pppoe_reset']);
		unset($ppp['pppoe_preset']);		
		setup_pppoe_reset_file($if, false);	
	}
}

$closehead = false;
$pgtitle = array("Interfaces","MLPPP","Edit");
include("head.inc");

$types = array("select" => "Select", "ppp" => "PPP", "pppoe" => "PPPoE", "pptp" => "PPTP"/*,  "l2tp" => "L2TP", "tcp" => "TCP", "udp" => "UDP", "ng" => "Netgraph" */  ); 

?>

<script type="text/javascript">

	function updateType(t){
		var ports = document.iform["interfaces[]"];
		switch(t) {
			case "select": {
				$('ppp','pppoe','pptp','ipfields').invoke('hide');
				for(var j=0; j < document.iform["interfaces[]"].length; j++){
					ports.options[j] = null;
				}
				break;
			}
			case "ppp": {
				for(var j=0; j < document.iform["interfaces[]"].length; j++){
					if (document.iform["interfaces[]"].options[j].value.indexOf("/dev/") < 0){
						ports.options[j] = null;
					}
				}
				$('select','pppoe','pptp','subnet').invoke('hide');
				$('ipfields').show();
				
				break;
			}
			case "pppoe": {
			for(var j=0; j < document.iform["interfaces[]"].length; j++){
					if (document.iform["interfaces[]"].options[j].value.indexOf("/dev/") > 0){
						document.iform["interfaces[]"].options[j] = null;
			}
				}
				$('select','ppp','pptp','ipfields').invoke('hide');
				break;
			}
			case "pptp": {
			for(var j=0; j < document.iform["interfaces[]"].length; j++){
					if (document.iform["interfaces[]"].options[j].value.indexOf("/dev/") > 0){
						document.iform["interfaces[]"].options[j] = null;
					}
			}
				$('select','ppp','pppoe').invoke('hide');
				$('ipfields','subnet').invoke('show');
				break;
			}
			default:
				break;
		}
		
		$(t).show();
		//history.go(0);
	}
	
	function show_periodic_reset(obj) {
		if (obj.checked)
			$('presetwrap').show();
		else
			$('presetwrap').hide();
	}

	function show_bandwidth_input_boxes() {
		var bboxes = $('interfaces').innerHTML;
		alert("hello" . bboxes);
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
	//document.observe("dom:loaded", function() { updateType(<?php echo "'{$pconfig['type']}'";?>); });
</script>
</head>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC" >
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
								echo " value=\"{$key}\" >" . htmlspecialchars($opt) . "</option>";
							} 
						?>
						</select>
					</td>
				</tr>
				<tr name="interface" id="interface" >
					<td width="22%" valign="top" class="vncellreq">Member interface(s)</td>
					<td width="78%" class="vtable">
						<select name="interfaces[]" multiple="true" class="formselect">
							<?php
								$serial = glob("/dev/cua*");
								$modems = glob("/dev/modem*");
								$ports = array_merge($serial, $modems);
								foreach ($portlist as $ifn => $ifinfo){
								//if (is_jumbo_capable($ifn)) {
									echo "<option value=\"{$ifn}\"";
									if (stristr($pconfig['interfaces'], $ifn))
										echo " selected";
									echo ">" . htmlspecialchars($ifn . " (" . $ifinfo['mac'] . ")") . "</option>";
								}
								foreach ($ports as $port) {
									if(preg_match("/\.(lock|init)$/", $port))
										continue;
									echo "<option value=\"".trim($port)."\"";
									if (stristr($pconfig['interfaces'], $port))
										echo " selected";
									echo ">{$port}</option>";
								}
							?>
						</select>
						<br/><span class="vexpl">Interfaces participating in the multilink connection.</span>
					</td>
            	</tr>
				<tr>
					<td width="22%" valign="top" class="vncell">Username</td>
					<td width="78%" class="vtable">
					<input name="username" type="text" class="formfld usr" id="username" size="20" value="<?=htmlspecialchars($pconfig['username']);?>">
					</td>
			    </tr>
			    <tr>
					<td width="22%" valign="top" class="vncell">Password</td>
					<td width="78%" class="vtable">
					<input name="password" type="password" class="formfld pwd" id="password" size="20" value="<?=htmlspecialchars($pconfig['password']);?>">
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
						<br/> <span class="vexpl">This option causes the interface to operate in dial-on-demand mode, allowing you to have a virtual full time connection. 
						The interface is configured, but the actual connection of the link is delayed until qualifying outgoing traffic is detected. </span>
					</td>
			    </tr>
			    <tr>
					<td valign="top" class="vncell">Idle Timeout</td>
					<td class="vtable">
						<input name="idletimeout" type="text" class="formfld unknown" id="idletimeout" size="6" value="<?=htmlspecialchars($pconfig['idletimeout']);?>"> seconds
						<br/> <span class="vexpl">Sets the idle timeout value for the bundle. If no incoming or outgoing packets are transmitted for the set number of seconds 
						the connection is brought down. An idle timeout of zero disables this feature. <bold>Default is 0.</bold>
						<br/>When the idle timeout occurs, if the dial-on-demand option is enabled, mpd goes back into dial-on-demand mode. Otherwise, the interface is brought down and all associated routes removed.</span>
					</td>
			    </tr>
				<tr>
					<td width="22%" valign="top" class="vncell">Description</td>
					<td width="78%" class="vtable">
						<input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
						<br/> <span class="vexpl">You may enter a description here for your reference (not parsed).</span>
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
									<br/><span class="vexpl">Note: Enter the modem initialization string here. Do NOT include the "AT" string at the beginning of the command. Many modern USB 3G
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
								<br/><span class="vexpl">Note: Time to wait for SIM to discover network after PIN is sent to SIM (seconds).</span>
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
								<br/><span class="vexpl">Note: Defaults to 1 if you set APN above. Ignored if you set no APN above.</span>
							</td>
							</tr>
							<tr>
							  <td width="22%" valign="top" class="vncell">Phone Number</td>
							  <td width="78%" class="vtable">
								<input name="phone" type="text" class="formfld unknown" id="phone" size="40" value="<?=htmlspecialchars($pconfig['phone']);?>">
								<br/><span class="vexpl">Note: Typically (*99# or *99***# or *99***1#) for GSM networks and *777 for CDMA networks</span>
							  </td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Connection Timeout</td>
								<td width="78%" class="vtable">
									<input name="connect-timeout" type="text" class="formfld unknown" id="connect-timeout" size="2" value="<?=htmlspecialchars($pconfig['connect-timeout']);?>">
									<br/><span class="vexpl">Note: Enter timeout in seconds for connection to be established (sec.) Default is 45 sec.</span>
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
									<br/> <span class="vexpl">Hint: this field can usually be left empty</span>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Periodic reset");?></td>
								<td width="78%" class="vtable">
									<input name="pppoe_preset" type="checkbox" id="pppoe_preset" value="yes" <?php if ($pconfig['pppoe_preset']) echo "checked"; ?> onclick="show_periodic_reset(this);" />
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
									<select style="display:none" name="subnet" class="formselect" id="subnet">
									<?php for ($i = 31; $i > 0; $i--): ?>
										<option value="<?=$i;?>"<?php if ($i == $pconfig['subnet']) echo "selected"; ?>><?=$i;?></option>
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
					<td width="22%" width="100" valign="top" class="vncell">Bandwidth</td>
					<td width="78%" class="vtable">
					<input name="bandwiths" type="checkbox" id="bandwiths" value="yes" <?php if (isset($pconfig['bandwidth'])) echo "checked"; ?> onclick="show_bandwidth_input_boxes();" />
					Set <bold>unequal</bold> bandwidths for links in this multilink connection.
					<span id="bandwidth_input" style="display:none">
						
						<br> <span class="vexpl">Set Bandwidth for each link ONLY if links have different bandwidths.</span>
					</span>
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
					<td width="22%" valign="top" class="vncell">Compression</td>
					<td width="78%" class="vtable">
						<input type="checkbox" value="on" id="vjcomp" name="vjcomp" <?php if (isset($pconfig['vjcomp'])) echo "checked"; ?>>&nbsp;Enable vjcomp(compression).
						<br/> <span class="vexpl">This option enables Van Jacobson TCP header compression, which saves several bytes per TCP data packet. 
						You almost always want this option. This compression ineffective for TCP connections with enabled modern extensions like time 
						stamping or SACK, which modify TCP options between sequential packets.</span>
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell">TCPmssFix</td>
					<td width="78%" class="vtable">
						<input type="checkbox" value="on" id="tcpmssfix" name="tcpmssfix" <?php if (isset($pconfig['tcpmssfix'])) echo "checked"; ?>>&nbsp;Enable tcpmssfix.
						<br/> <span class="vexpl">This option causes mpd to adjust incoming and outgoing TCP SYN segments so that the requested maximum segment size is not greater than the amount 
						allowed by the interface MTU. This is necessary in many setups to avoid problems caused by routers that drop ICMP Datagram Too Big messages. Without these messages,
						the originating machine sends data, it passes the rogue router then hits a machine that has an MTU that is not big enough for the data. Because the IP Don't Fragment option is set,
						this machine sends an ICMP Datagram Too Big message back to the originator and drops the packet. The rogue router drops the ICMP message and the originator never 
						gets to discover that it must reduce the fragment size or drop the IP Don't Fragment option from its outgoing data.</span>
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell">ShortSeq</td>
					<td width="78%" class="vtable">
						<input type="checkbox" value="on" id="shortseq" name="shortseq" <?php if (isset($pconfig['shortseq'])) echo "checked"; ?>>&nbsp;Enable shortseq.
						<br/> <span class="vexpl">This option is only meaningful if multi-link PPP is negotiated. It proscribes shorter multi-link fragment headers, saving two bytes on every frame.</span>
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell">ACFComp</td>
					<td width="78%" class="vtable">
						<input type="checkbox" value="on" id="acfcomp" name="acfcomp" <?php if (isset($pconfig['acfcomp'])) echo "checked"; ?>>&nbsp;Enable acfcomp.
						<br/> <span class="vexpl">Address and control field compression. This option only applies to asynchronous link types. It saves two bytes per frame.</span>
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell">ProtoComp</td>
					<td width="78%" class="vtable">
						<input type="checkbox" value="on" id="protocomp" name="protocomp" <?php if (isset($pconfig['protocomp'])) echo "checked"; ?>>&nbsp;Enable protocomp(compression).
						<br/> <span class="vexpl">Protocol field compression. This option saves one byte per frame for most frames.</span>
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