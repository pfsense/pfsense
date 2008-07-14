<?php
/* $Id$ */
/*
	interfaces_wan.php
        Copyright (C) 2007 Scott Ullrich
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

define("CRON_MONTHLY_PATTERN", "0 0 1 * *");
define("CRON_WEEKLY_PATTERN", "0 0 * * 0");
define("CRON_DAILY_PATTERN", "0 0 * * *");
define("CRON_HOURLY_PATTERN", "0 * * * *");
define("CRON_PPPOE_CMD_FILE", "/etc/pppoerestart");
define("CRON_PPPOE_CMD", "#!/bin/sh\necho '<?php require(\"interfaces.inc\"); interfaces_wan_pppoe_restart($if); services_dyndns_reset($if); ?>' | /usr/local/bin/php -q");

function getMPDCRONSettings() {
  global $config;

  if (is_array($config['cron']['item'])) {
    for ($i = 0; $i < count($config['cron']['item']); $i++) {
      $item =& $config['cron']['item'][$i];

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

require("guiconfig.inc");

unset($if);
if ($_GET['if'])
        $if = $_GET['if'];
else if ($_POST['if'])
        $if = $_POST['if'];

if (!$if)
        $if = "wan";

if (!is_array($config['gateways']['gateway_item']))
	$config['gateways']['gateway_item'] = array();
$a_gateways = &$config['gateways']['gateway_item'];

$wancfg = &$config['interfaces'][$if];

$pconfig['username'] = $wancfg['username'];
$pconfig['password'] = $wancfg['password'];
$pconfig['provider'] = $wancfg['provider'];
$pconfig['pppoe_dialondemand'] = isset($wancfg['ondemand']);
$pconfig['pppoe_idletimeout'] = $wancfg['timeout'];

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

    /* just initialize $pconfig['pppoe_resetdate'] if the
     * coresponding item contains appropriate numeric values.
     */
    if ($resetTime_a[2] <> "*" && $resetTime_a[3] <> "*") {
      $pconfig['pppoe_resetdate'] = "{$resetTime_a[3]}/{$resetTime_a[2]}/" . date("Y");
    }
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

$pconfig['pptp_username'] = $wancfg['username'];
$pconfig['pptp_password'] = $wancfg['password'];
$pconfig['pptp_local'] = $wancfg['local'];
$pconfig['pptp_subnet'] = $wancfg['subnet'];
$pconfig['pptp_remote'] = $wancfg['remote'];
$pconfig['pptp_dialondemand'] = isset($wancfg['ondemand']);
$pconfig['pptp_idletimeout'] = $wancfg['timeout'];

$pconfig['disableftpproxy'] = isset($wancfg['disableftpproxy']);

$pconfig['dhcphostname'] = $wancfg['dhcphostname'];
$pconfig['alias-address'] = $wancfg['alias-address'];
$pconfig['alias-subnet'] = $wancfg['alias-subnet'];
$pconfig['descr'] = remove_bad_chars($wancfg['descr']);
$pconfig['enable'] = isset($wancfg['enable']);

if (is_array($config['aliases']['alias']))
foreach($config['aliases']['alias'] as $alias)
        if($alias['name'] == $wancfg['descr'])
                $input_errors[] = gettext("Sorry, an alias with the name {$wancfg['descr']} a
lready exists.");

if ($wancfg['ipaddr'] == "dhcp") {
	$pconfig['type'] = "DHCP";
} else if ($wancfg['ipaddr'] == "carpdev-dhcp") {
	$pconfig['type'] = "CarpDEV-DHCP";
	$pconfig['ipaddr'] = "";	    
} else if ($wancfg['ipaddr'] == "pppoe") {
	$pconfig['type'] = "PPPoE";
} else if ($wancfg['ipaddr'] == "pptp") {
	$pconfig['type'] = "PPTP";
} else {
	$pconfig['type'] = "Static";
	$pconfig['ipaddr'] = $wancfg['ipaddr'];
	$pconfig['subnet'] = $wancfg['subnet'];
	$pconfig['gateway'] = $wancfg['gateway'];
	$pconfig['pointtopoint'] = $wancfg['pointtopoint'];
}

$pconfig['blockpriv'] = isset($wancfg['blockpriv']);
$pconfig['blockbogons'] = isset($wancfg['blockbogons']);
$pconfig['spoofmac'] = $wancfg['spoofmac'];
$pconfig['mtu'] = $wancfg['mtu'];

/* Wireless interface? */
if (isset($wancfg['wireless'])) {
	require("interfaces_wlan.inc");
	wireless_config_init();
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;
  
	/* filter out spaces from descriptions  */
        $_POST['descr'] = remove_bad_chars($_POST['descr']);

	if ($_POST['enable'] || $if == "wan") {
		/* optional interface if list */
                $iflist = get_configured_interface_with_descr(true);

                /* description unique? */
                foreach ($iflist as $ifent => $ifdescr) {
                        if ($if != $ifent && $ifdescr == $_POST['descr'])
                                $input_errors[] = "An interface with the specified descriptio
n already exists.";
                }

	/* okay first of all, cause we are just hidding the PPPoE HTML
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
		unlink_if_exists(CRON_PPPOE_CMD_FILE);
	}

	if($_POST['gateway'] and $pconfig['gateway'] <> $_POST['gateway']) {
		/* enumerate slbd gateways and make sure we are not creating a route loop */
		if(is_array($config['load_balancer']['lbpool'])) {
			foreach($config['load_balancer']['lbpool'] as $lbpool) {
				if($lbpool['type'] == "gateway") {
				    foreach ((array) $lbpool['servers'] as $server) {
			            $svr = split("\|", $server);
			            if($svr[1] == $pconfig['gateway'])  {
			            		$_POST['gateway']  = $pconfig['gateway'];
			            		$input_errors[] = "Cannot change {$svr[1]} gateway.  It is currently referenced by the load balancer pools.";
			            }
					}
				}
			}
			foreach($config['filter']['rule'] as $rule) {
				if($rule['gateway'] == $pconfig['gateway']) {
	            		$_POST['gateway']  = $pconfig['gateway'];
	            		$input_errors[] = "Cannot change {$svr[1]} gateway.  It is currently referenced by the filter rules via policy based routing.";
				}
			}
		}
	}

	/* input validation */
	if ($_POST['type'] == "Static") {
		if ($if == "wan") {
			$reqdfields = explode(" ", "ipaddr subnet gateway");
			$reqdfieldsn = explode(",", "IP address,Subnet bit count,Gateway");
		} else  {
			$reqdfields = explode(" ", "ipaddr subnet");
                        $reqdfieldsn = explode(",", "IP address,Subnet bit count");
		}
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	} else if ($_POST['type'] == "PPPoE") {
		if ($_POST['pppoe_dialondemand']) {
			$reqdfields = explode(" ", "username password pppoe_dialondemand pppoe_idletimeout");
			$reqdfieldsn = explode(",", "PPPoE username,PPPoE password,Dial on demand,Idle timeout value");
		} else {
			$reqdfields = explode(" ", "username password");
			$reqdfieldsn = explode(",", "PPPoE username,PPPoE password");
		}
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	} else if ($_POST['type'] == "PPTP") {
		if ($_POST['pptp_dialondemand']) {
			$reqdfields = explode(" ", "pptp_username pptp_password pptp_local pptp_subnet pptp_remote pptp_dialondemand pptp_idletimeout");
			$reqdfieldsn = explode(",", "PPTP username,PPTP password,PPTP local IP address,PPTP subnet,PPTP remote IP address,Dial on demand,Idle timeout value");
		} else {
			$reqdfields = explode(" ", "pptp_username pptp_password pptp_local pptp_subnet pptp_remote");
			$reqdfieldsn = explode(",", "PPTP username,PPTP password,PPTP local IP address,PPTP subnet,PPTP remote IP address");
		}
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	}

	/* normalize MAC addresses - lowercase and convert Windows-ized hyphenated MACs to colon delimited */
	$_POST['spoofmac'] = strtolower(str_replace("-", ":", $_POST['spoofmac']));

	if (($_POST['ipaddr'] && !is_ipaddr($_POST['ipaddr']))) {
		$input_errors[] = "A valid IP address must be specified.";
	}
	if (($_POST['subnet'] && !is_numeric($_POST['subnet']))) {
		$input_errors[] = "A valid subnet bit count must be specified.";
	}
	if (($_POST['alias-address'] && !is_ipaddr($_POST['alias-address']))) {
		$input_errors[] = "A valid alias IP address must be specified.";
	}
	if (($_POST['alias-subnet'] && !is_numeric($_POST['alias-subnet']))) {
		$input_errors[] = "A valid alias subnet bit count must be specified.";
	}
	if ($_POST['gateway']) {
		$match = false;
		foreach($a_gateways as $gateway) {
			if(in_array($_POST['gateway'], $gateway)) {
				$match = true;
			}
		}
		if(!$match)
			$input_errors[] = "A valid gateway must be specified.";
	}
	if (($_POST['pointtopoint'] && !is_ipaddr($_POST['pointtopoint']))) {
		$input_errors[] = "A valid point-to-point IP address must be specified.";
	}
	if (($_POST['provider'] && !is_domain($_POST['provider']))) {
		$input_errors[] = "The service name contains invalid characters.";
	}
	if (($_POST['pppoe_idletimeout'] != "") && !is_numericint($_POST['pppoe_idletimeout'])) {
		$input_errors[] = "The idle timeout value must be an integer.";
	}
	if ($_POST['pppoe_resethour'] <> "" && !is_numericint($_POST['pppoe_resethour']) && 
		$_POST['pppoe_resethour'] >= 0 && $_POST['pppoe_resethour'] <=23) {
		$input_errors[] = gettext("A valid PPPoE reset hour must be specified (0-23).");
	}
	if ($_POST['pppoe_resetminute'] <> "" && !is_numericint($_POST['pppoe_resetminute']) && 
		$_POST['pppoe_resetminute'] >= 0 && $_POST['pppoe_resetminute'] <=59) {
		$input_errors[] = gettext("A valid PPPoE reset minute must be specified (0-59).");
	}
	if ($_POST['pppoe_resetdate'] <> "" && !is_numeric(str_replace("/", "", $_POST['pppoe_resetdate']))) {
		$input_errors[] = gettext("A valid PPPoE reset date must be specified (mm/dd/yyyy).");
	}
	if (($_POST['pptp_local'] && !is_ipaddr($_POST['pptp_local']))) {
		$input_errors[] = "A valid PPTP local IP address must be specified.";
	}
	if (($_POST['pptp_subnet'] && !is_numeric($_POST['pptp_subnet']))) {
		$input_errors[] = "A valid PPTP subnet bit count must be specified.";
	}
	if (($_POST['pptp_remote'] && !is_ipaddr($_POST['pptp_remote']))) {
		$input_errors[] = "A valid PPTP remote IP address must be specified.";
	}
	if (($_POST['pptp_idletimeout'] != "") && !is_numericint($_POST['pptp_idletimeout'])) {
		$input_errors[] = "The idle timeout value must be an integer.";
	}
	if (($_POST['spoofmac'] && !is_macaddr($_POST['spoofmac']))) {
		$input_errors[] = "A valid MAC address must be specified.";
	}
	if ($_POST['mtu'] && (($_POST['mtu'] < 576) || ($_POST['mtu'] > 1500))) {
		$input_errors[] = "The MTU must be between 576 and 1500 bytes.";
	}
	}

	/* Wireless interface? */
	if (isset($wancfg['wireless'])) {
		$wi_input_errors = wireless_config_post();
		if ($wi_input_errors) {
			$input_errors = array_merge($input_errors, $wi_input_errors);
		}
	}

	if (!$input_errors) {

		$bridge = discover_bridge($wancfg['if'], filter_translate_type_to_real_interface($wancfg['bridge']));
		if($bridge <> "-1") {
			destroy_bridge($bridge);
		}

		unset($wancfg['ipaddr']);
		unset($wancfg['subnet']);
		unset($wancfg['gateway']);
		unset($wancfg['pointtopoint']);
		unset($wancfg['dhcphostname']);
		unset($wancfg['username']);
		unset($wancfg['password']);
		unset($wancfg['provider']);
		unset($wancfg['ondemand']);
		unset($wancfg['timeout']);
		unset($wancfg['pppoe']['pppoe-reset-type']);
		unset($wancfg['local']);
		unset($wancfg['subnet']);
		unset($wancfg['remote']);
		unset($wancfg['disableftpproxy']);

		/* per interface pftpx helper */
		if($_POST['disableftpproxy'] == "yes") {
			$wancfg['disableftpproxy'] = true;
			system_start_ftp_helpers();
		} else {
			system_start_ftp_helpers();
		}

		$wancfg['descr'] = remove_bad_chars($_POST['descr']);
		$wancfg['enable'] = $if == "wan" ? true : $_POST['enable'] ? true : false;

		if ($_POST['type'] == "Static") {
			$wancfg['ipaddr'] = $_POST['ipaddr'];
			$wancfg['subnet'] = $_POST['subnet'];
			$wancfg['gateway'] = $_POST['gateway'];
			if (isset($wancfg['ispointtopoint']))
				$wancfg['pointtopoint'] = $_POST['pointtopoint'];
		} else if ($_POST['type'] == "DHCP") {
			$wancfg['ipaddr'] = "dhcp";
			$wancfg['dhcphostname'] = $_POST['dhcphostname'];
			$wancfg['alias-address'] = $_POST['alias-address'];
			$wancfg['alias-subnet'] = $_POST['alias-subnet'];
		} else if ($_POST['type'] == "CarpDEV-DHCP") {
			$wancfg['ipaddr'] = "carpdev-dhcp";
			$wancfg['dhcphostname'] = $_POST['dhcphostname'];
			$wancfg['alias-address'] = $_POST['alias-address'];
			$wancfg['alias-subnet'] = $_POST['alias-subnet'];			
		} else if ($_POST['type'] == "PPPoE") {
			$wancfg['ipaddr'] = "pppoe";
			$wancfg['username'] = $_POST['username'];
			$wancfg['password'] = $_POST['password'];
			$wancfg['provider'] = $_POST['provider'];
			$wancfg['ondemand'] = $_POST['pppoe_dialondemand'] ? true : false;
			$wancfg['timeout'] = $_POST['pppoe_idletimeout'];
      
			/* perform a periodic reset? */
			if (isset($_POST['pppoe_preset'])) {
				if (! is_array($config['cron']['item'])) { $config['cron']['item'] = array(); }

					$itemhash = getMPDCRONSettings();
					$item = $itemhash['ITEM'];

					if (empty($item)) {
						$item = array();
					}

					if (isset($_POST['pppoe_pr_type']) && $_POST['pppoe_pr_type'] == "custom") {
						$wancfg['pppoe']['pppoe-reset-type'] = "custom";
						$pconfig['pppoe_pr_custom'] = true;

						$item['minute'] = $_POST['pppoe_resetminute'];
						$item['hour'] = $_POST['pppoe_resethour'];

						if (isset($_POST['pppoe_resetdate']) && 
							$_POST['pppoe_resetdate'] <> "" && 
							strlen($_POST['pppoe_resetdate']) == 10) {
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
						$wancfg['pppoe']['pppoe-reset-type'] = "preset";
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
				if (isset($itemhash['ID'])) {
					$config['cron']['item'][$itemhash['ID']] = $item;
				} else {
					$config['cron']['item'][] = $item;
				}
			} // end if
		} else if ($_POST['type'] == "PPTP") {
			$wancfg['ipaddr'] = "pptp";
			$wancfg['username'] = $_POST['pptp_username'];
			$wancfg['password'] = $_POST['pptp_password'];
			$wancfg['local'] = $_POST['pptp_local'];
			$wancfg['subnet'] = $_POST['pptp_subnet'];
			$wancfg['remote'] = $_POST['pptp_remote'];
			$wancfg['ondemand'] = $_POST['pptp_dialondemand'] ? true : false;
			$wancfg['timeout'] = $_POST['pptp_idletimeout'];
		}
    
		/* reset cron items if necessary */
		if (empty($_POST['pppoe_preset'])) {
			/* test whether a cron item exists and unset() it if necessary */
			$itemhash = getMPDCRONSettings();
			$item = $itemhash['ITEM'];
			if (isset($item)) { unset($config['cron']['item'][$itemhash['ID']]); }
		}

		if($_POST['blockpriv'] == "yes")
			$wancfg['blockpriv'] = true;
		else
			unset($wancfg['blockpriv']);

		if($_POST['blockbogons'] == "yes")
			$wancfg['blockbogons'] = true;
		else
			unset($wancfg['blockbogons']);

		$wancfg['spoofmac'] = $_POST['spoofmac'];
		$wancfg['mtu'] = $_POST['mtu'];

		write_config();
    
		/* finally install the pppoerestart file */
		if (isset($_POST['pppoe_preset'])) {
		config_lock();
		conf_mount_rw();
      
		if (! file_exists(CRON_PPPOE_CMD_FILE)) {
			file_put_contents(CRON_PPPOE_CMD_FILE, CRON_PPPOE_CMD);
			chmod(CRON_PPPOE_CMD_FILE, 0700);
		}
      
		/* regenerate cron settings/crontab file */
		configure_cron();
		sigkillbypid("{$g['varrun_path']}/cron.pid", "HUP");
      
		conf_mount_ro();
		config_unlock();
		}

		$retval = 0;
		$savemsg = get_std_save_message($retval);
	}
}

$pgtitle = array("Interfaces", $wancfg['descr']);
$closehead = false;
include("head.inc");

?>

<script type="text/javascript" src="/javascript/numericupdown/js/numericupdown.js"></script>
<link href="/javascript/numericupdown/css/numericupdown.css" rel="stylesheet" type="text/css" />

<script type="text/javascript" src="/javascript/datepicker/js/datepicker.js"></script>
<link href="/javascript/datepicker/css/datepicker.css" rel="stylesheet" type="text/css" />

<script type="text/javascript" src="/javascript/scriptaculous/prototype.js"></script>
<script type="text/javascript" src="/javascript/scriptaculous/scriptaculous.js"></script>

<script type="text/javascript">
<!--
function enable_change(enable_change) {
	if (document.iform.pppoe_dialondemand.checked || enable_change) {
		document.iform.pppoe_idletimeout.disabled = 0;
	} else {
		document.iform.pppoe_idletimeout.disabled = 1;
	}
}

function enable_change_pptp(enable_change_pptp) {
	if (document.iform.pptp_dialondemand.checked || enable_change_pptp) {
		document.iform.pptp_idletimeout.disabled = 0;
		document.iform.pptp_local.disabled = 0;
		document.iform.pptp_remote.disabled = 0;
	} else {
		document.iform.pptp_idletimeout.disabled = 1;
	}
}

function type_change(enable_change,enable_change_pptp) {
	switch (document.iform.type.selectedIndex) {
		case 0:
			document.iform.username.disabled = 1;
			document.iform.password.disabled = 1;
			document.iform.provider.disabled = 1;
			document.iform.pppoe_dialondemand.disabled = 1;
			document.iform.pppoe_idletimeout.disabled = 1;
			document.iform.pppoe_preset.disabled = 1;
			document.iform.pppoe_preset.checked = 0;
			Effect.Fade('presetwrap', { duration: 1.0 });
			document.iform.ipaddr.disabled = 0;
			document.iform.subnet.disabled = 0;
			document.iform.gateway.disabled = 0;
			document.iform.pptp_username.disabled = 1;
			document.iform.pptp_password.disabled = 1;
			document.iform.pptp_local.disabled = 1;
			document.iform.pptp_subnet.disabled = 1;
			document.iform.pptp_remote.disabled = 1;
			document.iform.pptp_dialondemand.disabled = 1;
			document.iform.pptp_idletimeout.disabled = 1;
			document.iform.dhcphostname.disabled = 1;
			break;
		case 1:
			document.iform.username.disabled = 1;
			document.iform.password.disabled = 1;
			document.iform.provider.disabled = 1;
			document.iform.pppoe_dialondemand.disabled = 1;
			document.iform.pppoe_idletimeout.disabled = 1;
			document.iform.pppoe_preset.disabled = 1;
			document.iform.pppoe_preset.checked = 0;
			Effect.Fade('presetwrap', { duration: 1.0 });
			document.iform.ipaddr.disabled = 1;
			document.iform.subnet.disabled = 1;
			document.iform.gateway.disabled = 1;
			document.iform.pptp_username.disabled = 1;
			document.iform.pptp_password.disabled = 1;
			document.iform.pptp_local.disabled = 1;
			document.iform.pptp_subnet.disabled = 1;
			document.iform.pptp_remote.disabled = 1;
			document.iform.pptp_dialondemand.disabled = 1;
			document.iform.pptp_idletimeout.disabled = 1;
			document.iform.dhcphostname.disabled = 0;
			break;
		case 2:
			document.iform.username.disabled = 0;
			document.iform.password.disabled = 0;
			document.iform.provider.disabled = 0;
			document.iform.pppoe_dialondemand.disabled = 0;
			if (document.iform.pppoe_dialondemand.checked || enable_change) {
				document.iform.pppoe_idletimeout.disabled = 0;
			} else {
				document.iform.pppoe_idletimeout.disabled = 1;
			}
			document.iform.pppoe_preset.disabled = 0;
			document.iform.ipaddr.disabled = 1;
			document.iform.subnet.disabled = 1;
			document.iform.gateway.disabled = 1;
			document.iform.pptp_username.disabled = 1;
			document.iform.pptp_password.disabled = 1;
			document.iform.pptp_local.disabled = 1;
			document.iform.pptp_subnet.disabled = 1;
			document.iform.pptp_remote.disabled = 1;
			document.iform.pptp_dialondemand.disabled = 1;
			document.iform.pptp_idletimeout.disabled = 1;
			document.iform.dhcphostname.disabled = 1;
			break;
		case 3:
			document.iform.username.disabled = 1;
			document.iform.password.disabled = 1;
			document.iform.provider.disabled = 1;
			document.iform.pppoe_dialondemand.disabled = 1;
			document.iform.pppoe_idletimeout.disabled = 1;
			document.iform.pppoe_preset.disabled = 1;
			document.iform.pppoe_preset.checked = 0;
			Effect.Fade('presetwrap', { duration: 1.0 });			
			document.iform.ipaddr.disabled = 1;
			document.iform.subnet.disabled = 1;
			document.iform.gateway.disabled = 1;
			document.iform.pptp_username.disabled = 0;
			document.iform.pptp_password.disabled = 0;
			document.iform.pptp_local.disabled = 0;
			document.iform.pptp_subnet.disabled = 0;
			document.iform.pptp_remote.disabled = 0;
			document.iform.pptp_dialondemand.disabled = 0;
			if (document.iform.pptp_dialondemand.checked || enable_change_pptp) {
				document.iform.pptp_idletimeout.disabled = 0;
			} else {
				document.iform.pptp_idletimeout.disabled = 1;
			}
			document.iform.dhcphostname.disabled = 1;
			break;
		case 4:
			document.iform.username.disabled = 1;
			document.iform.password.disabled = 1;
			document.iform.provider.disabled = 1;
			document.iform.pppoe_dialondemand.disabled = 1;
			document.iform.pppoe_idletimeout.disabled = 1;
			document.iform.pppoe_preset.disabled = 1;
			document.iform.pppoe_preset.checked = 0;
			Effect.Fade('presetwrap', { duration: 1.0 });
			document.iform.ipaddr.disabled = 1;
			document.iform.subnet.disabled = 1;
			document.iform.gateway.disabled = 1;
			document.iform.pptp_username.disabled = 1;
			document.iform.pptp_password.disabled = 1;
			document.iform.pptp_local.disabled = 1;
			document.iform.pptp_subnet.disabled = 1;
			document.iform.pptp_remote.disabled = 1;
			document.iform.pptp_dialondemand.disabled = 1;
			document.iform.pptp_idletimeout.disabled = 1;
			document.iform.dhcphostname.disabled = 1;
			break;
		case 5:
			document.iform.username.disabled = 1;
			document.iform.password.disabled = 1;
			document.iform.provider.disabled = 1;
			document.iform.pppoe_dialondemand.disabled = 1;
			document.iform.pppoe_idletimeout.disabled = 1;
			document.iform.pppoe_preset.disabled = 1;
			document.iform.pppoe_preset.checked = 0;
			Effect.Fade('presetwrap', { duration: 1.0 });
			document.iform.ipaddr.disabled = 1;
			document.iform.subnet.disabled = 1;
			document.iform.gateway.disabled = 1;
			document.iform.pptp_username.disabled = 1;
			document.iform.pptp_password.disabled = 1;
			document.iform.pptp_local.disabled = 1;
			document.iform.pptp_subnet.disabled = 1;
			document.iform.pptp_remote.disabled = 1;
			document.iform.pptp_dialondemand.disabled = 1;
			document.iform.pptp_idletimeout.disabled = 1;
			document.iform.dhcphostname.disabled = 1;
			break;

	}
}

function show_mon_config() {
	document.getElementById("showmonbox").innerHTML='';
	aodiv = document.getElementById('showmon');
	aodiv.style.display = "block";
}

//-->
</script>
</head>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
            <form action="interfaces_wan.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
                  <td colspan="2" valign="top" class="listtopic">General configuration</td>
                </tr>
<?php if ($if != "wan"): ?>
                <tr>
                  <td width="22%" valign="top" class="vtable">&nbsp;</td>
                  <td width="78%" class="vtable">
                        <input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable']) echo "checked"; ?> onClick="enable_change(false)">
                    <strong>Enable Interface <?=$if;?> interface</strong></td>
                </tr>
<?php endif; ?>
                <tr>
                  <td width="22%" valign="top" class="vncell">Description</td>
                  <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="
30" value="<?=htmlspecialchars($pconfig['descr']);?>">
                                        <br> <span class="vexpl">Enter a description (name) f
or the interface here.</span>
                  </td>
                </tr>
                <tr>
                  <td valign="middle" class="vncell"><strong>Type</strong></td>
                  <td class="vtable"> <select name="type" class="formselect" id="type" onchange="type_change()">
                      <?php $opts = split(" ", "Static DHCP PPPoE PPTP"); // CarpDEV-DHCP");
					  foreach ($opts as $opt): ?>
	                      <option <?php if ($opt == $pconfig['type']) echo "selected";?>>
	                      <?=htmlspecialchars($opt);?>
	                      </option>
                      <?php endforeach; ?>
                    </select></td>
                </tr>
                <tr>
                  <td valign="top" class="vncell">MAC address</td>
                  <td class="vtable"> <input name="spoofmac" type="text" class="formfld unknown" id="spoofmac" size="30" value="<?=htmlspecialchars($pconfig['spoofmac']);?>">
		    <?php
			$ip = getenv('REMOTE_ADDR');
			$mac = `/usr/sbin/arp -an | grep {$ip} | cut -d" " -f4`;
			$mac = str_replace("\n","",$mac);
		    ?>
		    <a OnClick="document.forms[0].spoofmac.value='<?=$mac?>';" href="#">Copy my MAC address</a>
		    <br>
                    This field can be used to modify (&quot;spoof&quot;) the MAC
                    address of the WAN interface<br>
                    (may be required with some cable connections)<br>
                    Enter a MAC address in the following format: xx:xx:xx:xx:xx:xx
                    or leave blank</td>
                </tr>
                <tr>
                  <td valign="top" class="vncell">MTU</td>
                  <td class="vtable"> <input name="mtu" type="text" class="formfld unknown" id="mtu" size="8" value="<?=htmlspecialchars($pconfig['mtu']);?>">
                    <br>
                    If you enter a value in this field, then MSS clamping for
                    TCP connections to the value entered above minus 40 (TCP/IP
                    header size) will be in effect. If you leave this field blank,
                    an MTU of 1492 bytes for PPPoE and 1500 bytes for all other
                    connection types will be assumed.</td>
                </tr>
                <tr>
                  <td colspan="2" valign="top" height="16"></td>
                </tr>
                <tr>
                  <td colspan="2" valign="top" class="listtopic">Static IP configuration</td>
                </tr>
                <tr>
                  <td width="100" valign="top" class="vncellreq">IP address</td>
                  <td class="vtable"> <input name="ipaddr" type="text" class="formfld unknown" id="ipaddr" size="20" value="<?=htmlspecialchars($pconfig['ipaddr']);?>">
                    /
                    <select name="subnet" class="formselect" id="subnet">
			<?php
			for ($i = 32; $i > 0; $i--) {
				if($i <> 31) {
					echo "<option value=\"{$i}\" ";
					if ($i == $pconfig['subnet']) echo "selected";
					echo ">" . $i . "</option>";
				}
			}
			?>
                    </select></td>
                </tr><?php if (isset($wancfg['ispointtopoint'])): ?>
                <tr>
                  <td valign="top" class="vncellreq">Point-to-point IP address </td>
                  <td class="vtable">
                    <input name="pointtopoint" type="text" class="formfld unknown" id="pointtopoint" size="20" value="<?=htmlspecialchars($pconfig['pointtopoint']);?>">
                  </td>
                </tr><?php endif; ?>
                <tr>
                  <td valign="top" class="vncellreq">Gateway</td>
                  <td class="vtable"><select name="gateway" class="formselect" id="gateway">
			<?php
			if(count($a_gateways) > 0) {
				foreach ($a_gateways as $gateway) {
					if($gateway['interface'] == $if) {
			?>
					<option value="<?=$gateway['name'];?>" <?php if ($gateway['name'] == $pconfig['gateway']) echo "selected"; ?>>
					<?=htmlspecialchars($gateway['name']);?>
					</option>
			<?php
					}
				}
			}
			?>
			</select>Select a existing Gateway from the list or add one on the <a href="/system_gateways.php">Gateways</a> page<br>
                  </td>
                </tr>
                <tr>
                  <td colspan="2" valign="top" height="16"></td>
                </tr>
                <tr>
                  <td colspan="2" valign="top" class="listtopic">DHCP client configuration</td>
                </tr>
                <tr>
                  <td valign="top" class="vncell">Hostname</td>
                  <td class="vtable"> <input name="dhcphostname" type="text" class="formfld unknown" id="dhcphostname" size="40" value="<?=htmlspecialchars($pconfig['dhcphostname']);?>">
                    <br>
                    The value in this field is sent as the DHCP client identifier
                    and hostname when requesting a DHCP lease. Some ISPs may require
                    this (for client identification).</td>
                </tr>
                <tr>
                  <td width="100" valign="top" class="vncellreq">Alias IP address</td>
                  <td class="vtable"> <input name="alias-address" type="text" class="formfld unknown" id="alias-address" size="20" value="<?=htmlspecialchars($pconfig['alias-address']);?>">
                    <select name="alias-subnet" class="formselect" id="alias-subnet">
			<?php
			for ($i = 32; $i > 0; $i--) {
				if($i <> 31) {
					echo "<option value=\"{$i}\" ";
					if ($i == $pconfig['alias-subnet']) echo "selected";
					echo ">" . $i . "</option>";
				}
			}
			?>
                    </select>
                    The value in this field is used as a fixed alias IP address by the
		    DHCP client.</td>
                </tr>
                <tr>
                  <td colspan="2" valign="top" height="16"></td>
                </tr>
                <tr>
                  <td colspan="2" valign="top" class="listtopic">PPPoE configuration</td>
                </tr>
                <tr>
                  <td valign="top" class="vncellreq">Username</td>
                  <td class="vtable"><input name="username" type="text" class="formfld user" id="username" size="20" value="<?=htmlspecialchars($pconfig['username']);?>">
                  </td>
                </tr>
                <tr>
                  <td valign="top" class="vncellreq">Password</td>
                  <td class="vtable"><input name="password" type="password" class="formfld pwd" id="password" size="20" value="<?=htmlspecialchars($pconfig['password']);?>">
                  </td>
                </tr>
                <tr>
                  <td valign="top" class="vncell">Service name</td>
                  <td class="vtable"><input name="provider" type="text" class="formfld unknown" id="provider" size="20" value="<?=htmlspecialchars($pconfig['provider']);?>">
                    <br> <span class="vexpl">Hint: this field can usually be left
                    empty</span></td>
                </tr>
                <tr>
                  <td valign="top" class="vncell">Dial on demand</td>
                  <td class="vtable"><input name="pppoe_dialondemand" type="checkbox" id="pppoe_dialondemand" value="enable" <?php if ($pconfig['pppoe_dialondemand']) echo "checked"; ?> onClick="enable_change(false)" >
                    <strong>Enable Dial-On-Demand mode</strong><br>
		    This option causes the interface to operate in dial-on-demand mode, allowing you to have a <i>virtual full time</i> connection. The interface is configured, but the actual connection of the link is delayed until qualifying outgoing traffic is detected.</td>
                </tr>
                <tr>
                  <td valign="top" class="vncell">Idle timeout</td>
                  <td class="vtable">
                    <input name="pppoe_idletimeout" type="text" class="formfld unknown" id="pppoe_idletimeout" size="8" value="<?=htmlspecialchars($pconfig['pppoe_idletimeout']);?>"> 
seconds<br>If no qualifying outgoing packets are transmitted for the specified number of seconds, the connection is brought down. An idle timeout of zero disables this feature.</td>
                </tr>
                <tr>
                  <td valign="top" class="vncell"><?=gettext("Periodic reset");?></td>
                  <td class="vtable">
                    <input name="pppoe_preset" type="checkbox" id="pppoe_preset" value="yes" <?php if ($pconfig['pppoe_preset']) echo "checked=\"checked\""; ?> onclick="Effect.toggle('presetwrap', 'appear', { duration: 1.0 });" />
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
                            <input name="pppoe_pr_type" type="radio" id="pppoe_pr_custom" value="custom" <?php if ($pconfig['pppoe_pr_custom']) echo "checked=\"checked\""; ?> onclick="if (this.checked) { Effect.Appear('pppoecustomwrap', { duration: 1.0 }); Effect.Fade('pppoepresetwrap', { duration: 1.0 }); }" /> 
                            <?= gettext("provide a custom reset time"); ?>
                            <br />
                            <input name="pppoe_pr_type" type="radio" id="pppoe_pr_preset" value="preset" <?php if ($pconfig['pppoe_pr_preset']) echo "checked=\"checked\""; ?> onclick="if (this.checked) { Effect.Appear('pppoepresetwrap', { duration: 1.0 }); Effect.Fade('pppoecustomwrap', { duration: 1.0 }); }" /> 
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
                <tr>
                  <td colspan="2" valign="top" height="16"></td>
                </tr>
                <tr>
                  <td colspan="2" valign="top" class="listtopic">PPTP configuration</td>
                </tr>
                <tr>
                  <td valign="top" class="vncellreq">Username</td>
                  <td class="vtable"><input name="pptp_username" type="text" class="formfld user" id="pptp_username" size="20" value="<?=htmlspecialchars($pconfig['pptp_username']);?>">
                  </td>
                </tr>
                <tr>
                  <td valign="top" class="vncellreq">Password</td>
                  <td class="vtable"><input name="pptp_password" type="text" class="formfld pwd" id="pptp_password" size="20" 
value="<?=htmlspecialchars($pconfig['pptp_password']);?>">
                  </td>
                </tr>
                <tr>
                  <td width="100" valign="top" class="vncellreq">Local IP address</td>
                  <td class="vtable"> <input name="pptp_local" type="text" class="formfld unknown" id="pptp_local" size="20" 
value="<?=htmlspecialchars($pconfig['pptp_local']);?>">
                    /
                    <select name="pptp_subnet" class="formselect" id="pptp_subnet">
                      <?php for ($i = 31; $i > 0; $i--): ?>
                      <option value="<?=$i;?>" <?php if ($i == $pconfig['pptp_subnet']) echo "selected"; ?>>
                      <?=$i;?>
                      </option>
                      <?php endfor; ?>
                    </select></td>
                </tr>
                <tr>
                  <td width="100" valign="top" class="vncellreq">Remote IP address</td>
                  <td class="vtable"> <input name="pptp_remote" type="text" class="formfld unknown" id="pptp_remote" size="20" value="<?=htmlspecialchars($pconfig['pptp_remote']);?>">
                  </td>
                </tr>
                <tr>
                  <td valign="top" class="vncell">Dial on demand</td>
                  <td class="vtable"><input name="pptp_dialondemand" type="checkbox" id="pptp_dialondemand" value="enable" <?php if ($pconfig['pptp_dialondemand']) echo "checked"; ?> onClick="enable_change_pptp(false)" >
                    <strong>Enable Dial-On-Demand mode</strong><br>
		    This option causes the interface to operate in dial-on-demand mode, allowing you to have a <i>virtual full time</i> connection. The interface is configured, but the actual connection of the link is delayed until qualifying outgoing traffic is detected.</td>
                </tr>
                <tr>
                  <td valign="top" class="vncell">Idle timeout</td>
                  <td class="vtable">
                    <input name="pptp_idletimeout" type="text" class="formfld unknown" id="pptp_idletimeout" size="8" value="<?=htmlspecialchars($pconfig['pptp_idletimeout']);?>"> 
seconds<br>If no qualifying outgoing packets are transmitted for the specified number of seconds, the connection is brought down. An idle timeout of zero disables this feature.</td>
                </tr>
                <tr>
                  <td colspan="2" valign="top" height="16"></td>
                </tr>
                <tr>
                  <td colspan="2" valign="top" class="listtopic">Other</td>
                </tr>
		<tr>
			<td width="22%" valign="top" class="vncell">FTP Helper</td>
			<td width="78%" class="vtable">
				<input name="disableftpproxy" type="checkbox" id="disableftpproxy" value="yes" <?php if ($pconfig['disableftpproxy']) echo "checked"; ?> onclick="enable_change(false)" />
				<strong>Disable the userland FTP-Proxy application</strong>
				<br />
			</td>
		</tr>
		        <?php
				/* Wireless interface? */
				if (isset($wancfg['wireless']))
					wireless_config_print();
			?>
                <tr>
                  <td height="16" colspan="2" valign="top"></td>
                </tr>
                <tr>
                  <td valign="middle">&nbsp;</td>
                  <td class="vtable"><a name="rfc1918"></a> <input name="blockpriv" type="checkbox" id="blockpriv" value="yes" <?php if ($pconfig['blockpriv']) echo "checked"; ?>>
                    <strong>Block private networks</strong><br>
                    When set, this option blocks traffic from IP addresses that
                    are reserved for private<br>
                    networks as per RFC 1918 (10/8, 172.16/12, 192.168/16) as
                    well as loopback addresses<br>
                    (127/8). You should generally leave this option turned on,
                    unless your WAN network<br>
                    lies in such a private address space, too.</td>
                </tr>
                <tr>
                  <td valign="middle">&nbsp;</td>
                  <td class="vtable"> <input name="blockbogons" type="checkbox" id="blockbogons" value="yes" <?php if ($pconfig['blockbogons']) echo "checked"; ?>>
                    <strong>Block bogon networks</strong><br>
                    When set, this option blocks traffic from IP addresses that
                    are reserved (but not RFC 1918) or not yet assigned by IANA.<br>
                    Bogons are prefixes that should never appear in the Internet routing table, and obviously should not appear as the source address in any packets you receive.</td>
		</tr>

                <tr>
                  <td width="100" valign="top">&nbsp;</td>
                  <td> &nbsp;<br> <input name="Submit" type="submit" class="formbtn" value="Save" onClick="enable_change_pptp(true)&&enable_change(true)">
			<input name="if" type="hidden" id="if" value="<?=$if;?>">
                  </td>
                </tr>
              </table>
</form>
<script language="JavaScript">
<!--
type_change();
//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>


<?php

if ($_POST) {

	if (!$input_errors) {

		unlink_if_exists("{$g['tmp_path']}/config.cache");

		ob_flush();
		flush();
		sleep(1);

		interfaces_wan_configure($if);

		reset_carp();

		/* sync filter configuration */
		filter_configure();

 		/* set up static routes */
		system_routing_configure();

	}
}

?>
