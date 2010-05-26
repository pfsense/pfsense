<?php
/* $Id$ */
/*
	interfaces_ppps_edit.php
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
##|*IDENT=page-interfaces-ppps-edit
##|*NAME=Interfaces: PPPs: Edit page
##|*DESCR=Allow access to the 'Interfaces: PPPs: Edit' page.
##|*MATCH=interfaces_ppps_edit.php*
##|-PRIV

require("guiconfig.inc");
require("functions.inc");

define("CRON_PPPOE_CMD_FILE", "{$g['varetc_path']}/pppoe_restart_");
define("CRON_MONTHLY_PATTERN", "0 0 1 * *");
define("CRON_WEEKLY_PATTERN", "0 0 * * 0");
define("CRON_DAILY_PATTERN", "0 0 * * *");
define("CRON_HOURLY_PATTERN", "0 * * * *");

function getMPDCRONSettings($ptpid_) {
	global $config;
	if (is_array($config['cron']['item'])) {
		for ($i = 0; $i < count($config['cron']['item']); $i++) {
			$item = $config['cron']['item'][$i];
			if (strpos($item['command'], CRON_PPPOE_CMD_FILE.$ptpid_) !== false) {
				return array("ID" => $i, "ITEM" => $item);
			}
		}
	}
	return NULL;
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
	$pconfig['interfaces'] = $a_ppps[$id]['ports'];
	$pconfig['username'] = $a_ppps[$id]['username'];
	$pconfig['password'] = base64_decode($a_ppps[$id]['password']);
	if (isset($a_ppps[$id]['defaultgw']))
		$pconfig['defaultgw'] = true;
	if (isset($a_ppps[$id]['ondemand']))
		$pconfig['ondemand'] = true;
	$pconfig['idletimeout'] = $a_ppps[$id]['idletimeout'];
	$pconfig['uptime'] = $a_ppps[$id]['uptime'];
	$pconfig['descr'] = $a_ppps[$id]['descr'];
	$pconfig['bandwidth'] = explode(",",$a_ppps[$id]['bandwidth']);
	$pconfig['mtu'] = explode(",",$a_ppps[$id]['mtu']);
	$pconfig['mru'] = explode(",",$a_ppps[$id]['mru']);
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
		$pconfig['initstr'] = base64_decode($a_ppps[$id]['initstr']);
		$pconfig['simpin'] = $a_ppps[$id]['simpin'];
		$pconfig['pin-wait'] = $a_ppps[$id]['pin-wait'];
		$pconfig['apn'] = $a_ppps[$id]['apn'];
		$pconfig['apnum'] = $a_ppps[$id]['apnum'];
		$pconfig['phone'] = $a_ppps[$id]['phone'];
		$pconfig['connect-timeout'] = $a_ppps[$id]['connect-timeout'];
		$pconfig['localip'] = explode(",",$a_ppps[$id]['localip']);
		$pconfig['gateway'] = explode(",",$a_ppps[$id]['gateway']);
	}
	if ($a_ppps[$id]['type'] == "pptp") {
		$pconfig['localip'] = explode(",",$a_ppps[$id]['localip']);
		$pconfig['subnet'] = explode(",",$a_ppps[$id]['subnet']);
		$pconfig['gateway'] = explode(",",$a_ppps[$id]['gateway']);
	}
	if ($a_ppps[$id]['type'] == "pppoe") {
		$pconfig['provider'] = $a_ppps[$id]['provider'];
		/* ================================================ */
		/* = force a connection reset at a specific time? = */
		/* ================================================ */
		
		if (isset($a_ppps[$id]['pppoe-reset-type'])) {
			$pconfig['pppoe-reset-type'] = $a_ppps[$id]['pppoe-reset-type'];
			$itemhash = getMPDCRONSettings($a_ppps[$id]['ptpid']);
			$cronitem = $itemhash['ITEM'];
			if (isset($cronitem)) {
				$resetTime = "{$cronitem['minute']} {$cronitem['hour']} {$cronitem['mday']} {$cronitem['month']} {$cronitem['wday']}";
			} else {
				$resetTime = NULL;
			}
			
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
	
	/* okay first of all, cause we are just hiding the PPPoE HTML
	 * fields releated to PPPoE resets, we are going to unset $_POST
	 * vars, if the reset feature should not be used. Otherwise the
	 * data validation procedure below, may trigger a false error
	 * message.
	 */
	if (empty($_POST['pppoe-reset-type'])) {               
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
	if ($_POST['type'] == "ppp" && count($_POST['interfaces']) > 1)
		$input_errors[] = "Multilink connections (MLPPP) using the PPP link type is not currently supported. Please select only one Link Interface.";
	if (($_POST['provider'] && !is_domain($_POST['provider']))) 
		$input_errors[] = "The service name contains invalid characters.";
	if (($_POST['idletimeout'] != "") && !is_numericint($_POST['idletimeout'])) 
		$input_errors[] = "The idle timeout value must be an integer.";
	if ($_POST['pppoe-reset-type'] == "custom" && $_POST['pppoe_resethour'] <> "" && !is_numericint($_POST['pppoe_resethour']) && 
		$_POST['pppoe_resethour'] >= 0 && $_POST['pppoe_resethour'] <=23) 
		$input_errors[] = gettext("A valid PPPoE reset hour must be specified (0-23).");
	if ($_POST['pppoe-reset-type'] == "custom" && $_POST['pppoe_resetminute'] <> "" && !is_numericint($_POST['pppoe_resetminute']) && 
		$_POST['pppoe_resetminute'] >= 0 && $_POST['pppoe_resetminute'] <=59) 
		$input_errors[] = gettext("A valid PPPoE reset minute must be specified (0-59).");
	if ($_POST['pppoe-reset-type'] == "custom" && $_POST['pppoe_resetdate'] <> "" && !is_numeric(str_replace("/", "", $_POST['pppoe_resetdate']))) 
		$input_errors[] = gettext("A valid PPPoE reset date must be specified (mm/dd/yyyy).");
	if ($_POST['pppoe-reset-type'] == "custom" && $_POST['pppoe_resetdate'] <> "" && is_numeric(str_replace("/", "", $_POST['pppoe_resetdate']))){
		$date_nums = explode("/",$_POST['pppoe_resetdate']);
		if ($date_nums[0] < 1 || $date_nums[0] > 12)
			$input_errors[] = gettext("A valid PPPoE reset month must be specified (1-12) in the Custom PPPoE Periodic reset fields.");
		if ($date_nums[1] < 1 || $date_nums[1] > 31)
			$input_errors[] = gettext("A valid PPPoE reset day of month must be specified (1-31) in the Custom PPPoE Periodic reset fields. No checks are done on valid # of days per month");
		if ($date_nums[2] < date("Y"))
			$input_errors[] = gettext("A valid PPPoE reset year must be specified. Don't select a year in the past!");
	}
	
	foreach($_POST['interfaces'] as $iface){
		if ($_POST['localip'][$iface] && !is_ipaddr($_POST['localip'][$iface]))
			$input_errors[] = "A valid local IP address must be specified for {$iface}.";
/*		if (($_POST['subnet'][$iface] && !is_numeric($_POST['subnet'][$iface]))) 
			$input_errors[] = "A valid PPTP subnet bit count must be specified for {$iface}.";
*/
		if (($_POST['gateway'][$iface] && !is_ipaddr($_POST['gateway'][$iface]))) 
			$input_errors[] = "A valid gateway (remote IP) address must be specified for {$iface}.";
		if (($_POST['bandwidth'][$iface] != "") && !is_numericint($_POST['bandwidth'][$iface])) 
			$input_errors[] = "The bandwidth value for {$iface} must be an integer.";
		if ($_POST['mtu'][$iface] && ($_POST['mtu'][$iface] < 576)) 
			$input_errors[] = "The MTU for {$iface} must be greater than 576 bytes.";
		if ($_POST['mru'][$iface] && ($_POST['mru'][$iface] < 576)) 
			$input_errors[] = "The MRU for {$iface} must be greater than 576 bytes.";
	}

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
		$ppp['ptpid'] = $_POST['ptpid'];
		$ppp['type'] = $_POST['type'];
		$ppp['ports'] = implode(',',$_POST['interfaces']);
		$ppp['username'] = $_POST['username'];
		$ppp['password'] = base64_encode($_POST['password']);
		$ppp['defaultgw'] = $_POST['defaultgw'] ? true : false;
		$ppp['ondemand'] = $_POST['ondemand'] ? true : false;
		if (!empty($_POST['idletimeout']))
			$ppp['idletimeout'] = $_POST['idletimeout'];
		else
			unset($ppp['idletimeout']);
		$ppp['uptime'] = $_POST['uptime'] ? true : false;
		if (!empty($_POST['descr']))
			$ppp['descr'] = $_POST['descr'];
		else
			unset($ppp['descr']);

		switch($_POST['type']) {
			case "ppp":
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
				foreach($_POST['interfaces'] as $iface){
					if (isset($_POST['localip'][$iface]))
						$localip_array[] = $_POST['localip'][$iface];
					if (isset($_POST['gateway'][$iface]))
						$gateway_array[] = $_POST['gateway'][$iface];
				}
				if (count($localip_array))
					$ppp['localip'] = implode(',',$localip_array);
				else
					unset($ppp['localip']);
				if (count($gateway_array))
					$ppp['gateway'] = implode(',',$gateway_array);
				else
					unset($ppp['gateway']);
				if (!empty($_POST['connect-timeout']))
					$ppp['connect-timeout'] = $_POST['connect-timeout'];
				else
					unset($ppp['connect-timeout']);
				break;
			case "pppoe":
				if (!empty($_POST['provider']))
					$ppp['provider'] = $_POST['provider'];
				else
					unset($ppp['provider']);
				if (!empty($_POST['pppoe-reset-type']))
					$ppp['pppoe-reset-type'] = $_POST['pppoe-reset-type'];
				else
					unset($ppp['pppoe-reset-type']);
				
				break;
			case "pptp":
				foreach($_POST['interfaces'] as $iface){
					if (isset($_POST['localip'][$iface]))
						$localip_array[] = $_POST['localip'][$iface];
					if (isset($_POST['gateway'][$iface]))
						$gateway_array[] = $_POST['gateway'][$iface];
					if (isset($_POST['subnet'][$iface]))
						$subnet_array[] = $_POST['subnet'][$iface];
				}
				if (count($localip_array))
					$ppp['localip'] = implode(',',$localip_array);
				else
					unset($ppp['localip']);
				if (count($gateway_array))
					$ppp['gateway'] = implode(',',$gateway_array);
				else
					unset($ppp['gateway']);
				if (count($subnet_array))
					$ppp['subnet'] = implode(',',$subnet_array);
				else
					unset($ppp['subnet']);
				break;
			default:
				break;
			
		}
		
		$ppp['shortseq'] = $_POST['shortseq'] ? true : false;
		$ppp['acfcomp'] = $_POST['acfcomp'] ? true : false;
		$ppp['protocomp'] = $_POST['protocomp'] ? true : false;
		$ppp['vjcomp'] = $_POST['vjcomp'] ? true : false;
		$ppp['tcpmssfix'] = $_POST['tcpmssfix'] ? true : false;
/*		
		$bw_array = array();
		$mtu_array = array();
		$mru_array = array();
		$mrru_array = array();
*/		
		foreach($_POST['interfaces'] as $iface){
			if (isset($_POST['bandwidth'][$iface]))
				$bw_array[] = $_POST['bandwidth'][$iface];
			if (isset($_POST['mtu'][$iface]))
				$mtu_array[] = $_POST['mtu'][$iface];
			if (isset($_POST['mru'][$iface]))
				$mru_array[] = $_POST['mru'][$iface];
			if (isset($_POST['mrru'][$iface]))
				$mrru_array[] = $_POST['mrru'][$iface];
		}
		if (count($bw_array))
			$ppp['bandwidth'] = implode(',', $bw_array);
		else 
			unset($ppp['bandwidth']);
			
		if (count($mtu_array))
			$ppp['mtu'] = implode(',', $mtu_array);
		else 
			unset($ppp['mtu']);
			
		if (count($mru_array))
			$ppp['mru'] = implode(',', $mru_array);
		else 
			unset($ppp['mru']);
		/* handle_pppoe_reset is called here because if user changes Link Type from PPPoE to another type we 
		must be able to clear the config data in the <cron> section of config.xml if it exists
		*/
		handle_pppoe_reset();
		
        $iflist = get_configured_interface_list();
        foreach ($iflist as $if) {
        	if ($config['interfaces'][$if]['ptpid'] == $_POST['ptpid']){
				$thisif = $if;
				break;
			}
		}
		if (isset($id) && $a_ppps[$id])
			$a_ppps[$id] = $ppp;
		else
			$a_ppps[] = $ppp;
			
		write_config();
		configure_cron();
		
		if (isset($thisif)){
			interface_ppps_configure($thisif);
		}
		header("Location: interfaces_ppps.php");
		exit;
	}
} // end if($_POST)

function handle_pppoe_reset() {
	global $_POST, $config, $g;

	if (!is_array($config['cron']['item'])) 
		$config['cron']['item'] = array(); 
	$itemhash = getMPDCRONSettings($_POST['ptpid']);
	$item = $itemhash['ITEM'];
	
	/* reset cron items if necessary and return*/
	if (empty($_POST['pppoe-reset-type'])) {
		if (isset($item))
			unset($config['cron']['item'][$itemhash['ID']]);
		sigkillbypid("{$g['varrun_path']}/cron.pid", "HUP");
		return;
	}

	if (empty($item)) 
		$item = array();
	if (isset($_POST['pppoe-reset-type']) && $_POST['pppoe-reset-type'] == "custom") {
		$item['minute'] = $_POST['pppoe_resetminute'];
		$item['hour'] = $_POST['pppoe_resethour'];
		if (isset($_POST['pppoe_resetdate']) && $_POST['pppoe_resetdate'] <> "") {
			$date = explode("/", $_POST['pppoe_resetdate']);
			$item['mday'] = $date[1];
			$item['month'] = $date[0];
		} else {
			$item['mday'] = "*";
			$item['month'] = "*";
		}
		$item['wday'] = "*";
		$item['who'] = "root";
		$item['command'] = CRON_PPPOE_CMD_FILE.$_POST['ptpid'];
	} else if (isset($_POST['pppoe-reset-type']) && $_POST['pppoe-reset-type'] == "preset") {
		switch ($_POST['pppoe_pr_preset_val']) {
			case "monthly":
				$item['minute'] = "0";
				$item['hour'] = "0";
				$item['mday'] = "1";
				$item['month'] = "*";
				$item['wday'] = "*";
				$item['who'] = "root";
				$item['command'] = CRON_PPPOE_CMD_FILE.$_POST['ptpid'];
				break;
	        case "weekly":
				$item['minute'] = "0";
				$item['hour'] = "0";
				$item['mday'] = "*";
				$item['month'] = "*";
				$item['wday'] = "0";
				$item['who'] = "root";
				$item['command'] = CRON_PPPOE_CMD_FILE.$_POST['ptpid'];
				break;
			case "daily":
				$item['minute'] = "0";
				$item['hour'] = "0";
				$item['mday'] = "*";
				$item['month'] = "*";
				$item['wday'] = "*";
				$item['who'] = "root";
				$item['command'] = CRON_PPPOE_CMD_FILE.$_POST['ptpid'];
				break;
			case "hourly":
				$item['minute'] = "0";
				$item['hour'] = "*";
				$item['mday'] = "*";
				$item['month'] = "*";
				$item['wday'] = "*";
				$item['who'] = "root";
				$item['command'] = CRON_PPPOE_CMD_FILE.$_POST['ptpid'];
				break;
		} // end switch
	}// end if
	if (isset($itemhash['ID'])) 
		$config['cron']['item'][$itemhash['ID']] = $item;
	else 
		$config['cron']['item'][] = $item;
}

$closehead = false;
$pgtitle = array("Interfaces","PPPs","Edit");
include("head.inc");

$types = array("select" => "Select", "ppp" => "PPP", "pppoe" => "PPPoE", "pptp" => "PPTP"/*,  "l2tp" => "L2TP", "tcp" => "TCP", "udp" => "UDP"*/  ); 

?>
	<script type="text/javascript" src="/javascript/numericupdown/js/numericupdown.js"></script>
	<link href="/javascript/numericupdown/css/numericupdown.css" rel="stylesheet" type="text/css" />
	<script type="text/javascript" src="/javascript/datepicker/js/datepicker.js"></script>
	<link href="/javascript/datepicker/css/datepicker.css" rel="stylesheet" type="text/css"/>
	<script type="text/javascript" >
		document.observe("dom:loaded", function() { updateType(<?php echo "'{$pconfig['type']}'";?>); });
	</script>
</head>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC" >
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
	<form action="interfaces_ppps_edit.php" method="post" name="iform" id="iform">
	  <table id="interfacetable" width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?= gettext("PPPs configuration"); ?></td>
		</tr>
		<tr>
			<td valign="middle" class="vncell"><strong><?= gettext("Link Type"); ?></strong></td>
			<td class="vtable"> 
				<select name="type" onChange="updateType(this.value);" class="formselect" id="type">
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
			<td width="22%" valign="top" class="vncellreq"><?= gettext("Link interface(s)"); ?></td>
			<td width="78%" class="vtable">
				<select valign="top" name="interfaces[]" multiple="true" class="formselect" size="4" onChange="show_hide_linkfields(this.options);">
					<option></option>
				</select>

				<br/><span class="vexpl"><?= gettext("Interfaces or ports participating in the multilink connection."); ?></span>
				<span style="display:none" id="prefil_ppp">
				<p/><?= gettext("Click a link to fill in defaults for these carriers:"); ?>&nbsp;
				<a href='#' onClick='javascript:prefill_att();'>ATT</A>
				<a href='#' onClick='javascript:prefill_sprint();'>Sprint</A>
				<a href='#' onClick='javascript:prefill_vzw();'>Verizon</A>
				</span>
			</td>
		</tr>
		<tr style="display:none" name="portlists" id="portlists">
			<td id="serialports"><?php
				$selected_ports = explode(',',$pconfig['interfaces']);
				$serial = glob("/dev/cua*");
				$modems = glob("/dev/modem*");
				$serialports = array_merge($serial, $modems);
				$serport_count = 0;
				foreach ($serialports as $port) {
					if(preg_match("/\.(lock|init)$/", $port))
						continue;
					$serport_count++;
					echo $port.",".trim($port);
					if (in_array($port,$selected_ports))
						echo ",1|";
					else
						echo ",|";
				}
				echo $serport_count;
			?></td>
			<td id="ports"><?php
				$port_count = 0;
				foreach ($portlist as $ifn => $ifinfo){
				$port_count++;
					echo htmlspecialchars($ifn . " (" . $ifinfo['mac'] . ")") . ",{$ifn}";
					if (in_array($ifn,$selected_ports))
						echo ",1|";
					else
						echo ",|";
				}
				echo $port_count;
				if($serport_count > $port_count)
					$port_count=$serport_count;
			?></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?= gettext("Username"); ?></td>
			<td width="78%" class="vtable">
			<input name="username" type="text" class="formfld user" id="username" size="20" value="<?=htmlspecialchars($pconfig['username']);?>">
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?= gettext("Password"); ?></td>
			<td width="78%" class="vtable">
			<input name="password" type="password" class="formfld pwd" id="password" size="20" value="<?=htmlspecialchars($pconfig['password']);?>">
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?= gettext("Gateway"); ?></td>
			<td width="78%" class="vtable">
				<input type="checkbox" value="on" id="defaultgw" name="defaultgw" <?php if (isset($pconfig['defaultgw'])) echo "checked"; ?>><?= gettext("This link will be used as the default gateway."); ?> 
				<br/><span class="vexpl"><?= gettext("\"Allow DNS Server list to be overridden\" must also be checked in the System -> General Setup menu."); ?>
			</td>
		</tr>
		<tr>
		<td valign="top" class="vncell"><?= gettext("Dial On Demand"); ?></td>
			<td class="vtable">
				<input type="checkbox" value="on" id="ondemand" name="ondemand" <?php if (isset($pconfig['ondemand'])) echo "checked"; ?>> <?= gettext("Enable Dial-on-Demand mode"); ?> 
				<br/> <span class="vexpl"><?= gettext("This option causes the interface to operate in dial-on-demand mode, allowing you to have a virtual full time connection. 
				The interface is configured, but the actual connection of the link is delayed until qualifying outgoing traffic is detected."); ?> </span>
			</td>
		</tr>
		<tr>
			<td valign="top" class="vncell"><?= gettext("Idle Timeout"); ?></td>
			<td class="vtable">
				<input name="idletimeout" type="text" class="formfld unknown" id="idletimeout" size="12" value="<?=htmlspecialchars($pconfig['idletimeout']);?>"> <?= gettext("(seconds) Default is 0, which disables the timeout feature."); ?>
				<br/> <span class="vexpl"><?= gettext("If no incoming or outgoing packets are transmitted for the entered number of seconds the connection is brought down.
				<br/>When the idle timeout occurs, if the dial-on-demand option is enabled, mpd goes back into dial-on-demand mode. Otherwise, the interface is brought down and all associated routes removed."); ?></span>
			</td>
		</tr>
		<tr>
		<td valign="top" class="vncell"><?= gettext("Uptime Logging"); ?></td>
			<td class="vtable">
				<input type="checkbox" value="on" id="uptime" name="uptime" <?php if (isset($pconfig['uptime'])) echo "checked"; ?>> <?= gettext("Enable persistent logging of connection uptime."); ?> 
				<br/> <span class="vexpl"><?= gettext("This option causes cumulative uptime to be recorded and displayed on the Status Interfaces page."); ?></span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?= gettext("Description"); ?></td>
			<td width="78%" class="vtable">
				<input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
				<br/> <span class="vexpl"><?= gettext("You may enter a description here for your reference. Description will appear in the \"Interfaces Assign\" select lists."); ?></span>
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
						<td colspan="2" valign="top" class="listtopic"><?= gettext("PPP configuration"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?= gettext("Init String"); ?></td>
						<td width="78%" class="vtable">
							<input type="text" size="40" class="formfld unknown" id="initstr" name="initstr" value="<?=htmlspecialchars($pconfig['initstr']);?>">
							<br/><span class="vexpl"><?= gettext("Note: Enter the modem initialization string here. Do NOT include the \"AT\" 
							string at the beginning of the command. Many modern USB 3G modems don't need an initialization string."); ?></span>
						</td>
					</tr>
					<tr>
					  <td width="22%" valign="top" class="vncell"><?= gettext("Sim PIN"); ?></td>
					  <td width="78%" class="vtable">
						<input name="simpin" type="text" class="formfld unknown" id="simpin" size="12" value="<?=htmlspecialchars($pconfig['simpin']);?>">
					</td>
					</tr>
					<tr>
					  <td width="22%" valign="top" class="vncell"><?= gettext("Sim PIN wait"); ?></td>
					  <td width="78%" class="vtable">
						<input name="pin-wait" type="text" class="formfld unknown" id="pin-wait" size="2" value="<?=htmlspecialchars($pconfig['pin-wait']);?>">
						<br/><span class="vexpl"><?= gettext("Note: Time to wait for SIM to discover network after PIN is sent to SIM (seconds)."); ?></span>
					</td>
					</tr>
					<tr>
					  <td width="22%" valign="top" class="vncell"><?= gettext("Access Point Name (APN)"); ?></td>
					  <td width="78%" class="vtable">
						<input name="apn" type="text" class="formfld unknown" id="apn" size="40" value="<?=htmlspecialchars($pconfig['apn']);?>">
					</td>
					</tr>
					<tr>
					  <td width="22%" valign="top" class="vncell"><?= gettext("APN number (optional)"); ?></td>
					  <td width="78%" class="vtable">
						<input name="apnum" type="text" class="formfld unknown" id="apnum" size="2" value="<?=htmlspecialchars($pconfig['apnum']);?>">
						<br/><span class="vexpl"><?= gettext("Note: Defaults to 1 if you set APN above. Ignored if you set no APN above."); ?></span>
					</td>
					</tr>
					<tr>
					  <td width="22%" valign="top" class="vncell"><?= gettext("Phone Number"); ?></td>
					  <td width="78%" class="vtable">
						<input name="phone" type="text" class="formfld unknown" id="phone" size="40" value="<?=htmlspecialchars($pconfig['phone']);?>">
						<br/><span class="vexpl"><?= gettext("Note: Typically (*99# or *99***# or *99***1#) for GSM networks and *777 for CDMA networks"); ?></span>
					  </td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?= gettext("Connection Timeout"); ?></td>
						<td width="78%" class="vtable">
							<input name="connect-timeout" type="text" class="formfld unknown" id="connect-timeout" size="2" value="<?=htmlspecialchars($pconfig['connect-timeout']);?>">
							<br/><span class="vexpl"><?= gettext("Note: Enter timeout in seconds for connection to be established (sec.) Default is 45 sec."); ?></span>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr style="display:none" name="pppoe" id="pppoe">
			<td colspan="2" style="padding:0px;">
				<table width="100%" border="0" cellpadding="6" cellspacing="0">
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?= gettext("PPPoE configuration"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?= gettext("Service name"); ?></td>
						<td width="78%" class="vtable"><input name="provider" type="text" class="formfld unknown" id="provider" size="20" value="<?=htmlspecialchars($pconfig['provider']);?>">
							<br/> <span class="vexpl"><?= gettext("Hint: this field can usually be left empty"); ?></span>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Periodic reset");?></td>
						<td width="78%" class="vtable">
							<table id="presetwrap" cellspacing="0" cellpadding="0" width="100%">
								<tr>
									<td align="left" valign="top">
										<p style="margin: 4px; padding: 4px 0 4px 0; width: 94%;">
										<select valign="top" id="reset_type" name="pppoe-reset-type" class="formselect" onChange="show_reset_settings(this.value);">
											<option value = ""><?= gettext("Disabled"); ?></option>
											<option value="custom" <?php if ($pconfig['pppoe-reset-type'] == "custom") echo "selected"; ?>><?= gettext("Custom"); ?></option>
											<option value="preset" <?php if ($pconfig['pppoe-reset-type'] == "preset") echo "selected"; ?>><?= gettext("Pre-Set"); ?></option>
										</select> <?= gettext("Select a reset timing type"); ?>
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
										<?= gettext("If you leave the date field empty, the reset will be executed each day at the time you did specify using the minutes and hour field."); ?>
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
						<td colspan="2" valign="top" class="listtopic"><?= gettext("PPTP configuration"); ?></td>
					</tr>
				</table>
			</td>
		</tr>
		<?php for($j=0; $j < $port_count; $j++) : ?>
		<tr style="display:none" id="ipfields<?=$j;?>">
			<td colspan="2" style="padding:0px;">
				<table width="100%" border="0" cellpadding="6" cellspacing="0">
					<tr>
						<td width="22%" name="<?= gettext("Local IP address"); ?>" id="localiplabel<?=$j;?>" valign="top" class="vncell"></td>
						<td width="78%" class="vtable"> 
							<input name="localip[]" type="text" class="formfld unknown" id="localip<?=$j;?>" size="20"  value="<?=htmlspecialchars($pconfig['localip'][$j]);?>">
							/
							<select style="display:none" name="subnet[]" class="formselect" id="subnet<?=$j;?>">
							<?php for ($i = 31; $i > 0; $i--): ?>
								<option value="<?=$i;?>"<?php if ($i == $pconfig['subnet'][$j]) echo "selected"; ?>><?=$i;?></option>
							<?php endfor; ?>
							</select>
							<br><span class="vexpl"><?= gettext("Note: Local IP/subnet is required for PPTP connections. LocalIP is automatically assigned for PPP links if this field is empty."); ?></span>
						</td>
					</tr>
					<tr>
						<td width="22%" name="<?= gettext("Remote IP (Gateway)"); ?>" id="gatewaylabel<?=$j;?>" valign="top" class="vncell"></td>
						<td width="78%" class="vtable">
							<input name="gateway[]" type="text" class="formfld unknown" id="gateway<?=$j;?>" size="20" value="<?=htmlspecialchars($pconfig['gateway'][$j]);?>">
							<br><span class="vexpl"><?= gettext("Note: This is where the packets will be routed. Remote IP is required for PPTP connections. Remote IP is automatically assigned for PPP links if this field is empty."); ?></span>
						</td>
					</tr>
				</table>
			</td>
		</tr><?php endfor; ?>
		<tr>
			<td colspan="2" valign="top" height="16"></td>
		</tr>
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?= gettext("Advanced Options"); ?></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?= gettext("Compression"); ?></td>
			<td width="78%" class="vtable">
				<input type="checkbox" value="on" id="vjcomp" name="vjcomp" <?php if (isset($pconfig['vjcomp'])) echo "checked"; ?>>&nbsp;<?= gettext("Disable vjcomp(compression) (enabled by default)."); ?>
				<br/> <span class="vexpl">This option enables Van Jacobson TCP header compression, which saves several bytes per TCP data packet. 
				You almost always want this option. This compression ineffective for TCP connections with enabled modern extensions like time 
				stamping or SACK, which modify TCP options between sequential packets.</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?= gettext("TCPmssFix"); ?></td>
			<td width="78%" class="vtable">
				<input type="checkbox" value="on" id="tcpmssfix" name="tcpmssfix" <?php if (isset($pconfig['tcpmssfix'])) echo "checked"; ?>>&nbsp;<?= gettext("Enable tcpmssfix (disabled by default)."); ?>
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
				<input type="checkbox" value="on" id="shortseq" name="shortseq" <?php if (isset($pconfig['shortseq'])) echo "checked"; ?>>&nbsp;<?= gettext("Disable shortseq (enabled by default)."); ?>
				<br/> <span class="vexpl"><?= gettext("This option is only meaningful if multi-link PPP is negotiated. It proscribes shorter multi-link fragment headers, saving two bytes on every frame. 
				It is not necessary to disable this for connections that are not multi-link."); ?></span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">ACFComp</td>
			<td width="78%" class="vtable">
				<input type="checkbox" value="on" id="acfcomp" name="acfcomp" <?php if (isset($pconfig['acfcomp'])) echo "checked"; ?>>&nbsp;<?= gettext("Disable acfcomp(compression) (enabled by default)."); ?>
				<br/> <span class="vexpl"><?= gettext("Address and control field compression. This option only applies to asynchronous link types. It saves two bytes per frame."); ?></span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">ProtoComp</td>
			<td width="78%" class="vtable">
				<input type="checkbox" value="on" id="protocomp" name="protocomp" <?php if (isset($pconfig['protocomp'])) echo "checked"; ?>>&nbsp;<?= gettext("Disable protocomp(compression) (enabled by default)."); ?>
				<br/> <span class="vexpl"><?= gettext("Protocol field compression. This option saves one byte per frame for most frames."); ?></span>
			</td>
		</tr>

		<?php for($i=0; $i < $port_count; $i++) : ?>
		<tr style="display:none" id="link<?=$i;?>">
			<td width="22%" valign="top" id="linklabel<?=$i;?>" class="vncell"> Link Parameters</td>
			<td class="vtable">
				<table name="link_parameters" border="0" cellpadding="6" cellspacing="0">
					<tr>
						<td width="22%" id="bwlabel<?=$i;?>" valign="top"class="vncell"> Bandwidth</td>
						<td width="78%" class="vtable">
						<br/><input name="bandwidth[]" id="bandwidth<?=$i;?>" type="text" class="formfld unknown" size="40" value="<?=htmlspecialchars($pconfig['bandwidth'][$i]);?>">
						<br/> <span class="vexpl">Set Bandwidth for each link ONLY for MLPPP connections and ONLY when links have different bandwidths.</span>
					  </td>
					</tr>
					<tr>
					  <td width="22%" id="mtulabel<?=$i;?>" valign="top" class="vncell"> MTU</td>
					  <td width="78%" class="vtable">
						<input name="mtu[]" id="mtu<?=$i;?>" type="text" class="formfld unknown" size="6" value="<?=htmlspecialchars($pconfig['mtu'][$i]);?>">
						<br> <span class="vexpl">Set MTU for each link if links have different bandwidths, otherwise, mtu will default to 1492.</span>
					  </td>
					</tr>
					<tr>
					  <td width="22%" id="mrulabel<?=$i;?>" valign="top" class="vncell"> MRU</td>
					  <td width="78%" class="vtable">
						<input name="mru[]" id="mru<?=$i;?>" type="text" class="formfld unknown" size="6" value="<?=htmlspecialchars($pconfig['mru'][$i]);?>">
						<br> <span class="vexpl">Set MRU for each link if links have different bandwidths, otherwise, mru will default to 1492.</span>
					  </td>
					</tr>
				</table
			</td>
		</tr><?php endfor; ?>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input name="Submit" type="submit" class="formbtn" value="Save">
				<input type="button" value="Cancel" onclick="history.back()">
				<?php
					if (isset($a_ppps[$id]['ptpid']))
						$ptpid = $a_ppps[$id]['ptpid'];
					else
						$ptpid = uniqid('', true);
				?>
				<input name="ptpid" type="hidden" value="<?=$ptpid;?>">
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