#!/usr/local/bin/php
<?php
/*
	system_advanced.php
        part of pfSense
        Copyright (C) 2005 Scott Ullrich

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

require("guiconfig.inc");

$pconfig['disablefilter'] = $config['system']['disablefilter'];
$pconfig['disableftpproxy'] = $config['system']['disableftpproxy'];
$pconfig['rfc959workaround'] = $config['system']['rfc959workaround'];
$pconfig['filteringbridge_enable'] = isset($config['bridge']['filteringbridge']);
$pconfig['ipv6nat_enable'] = isset($config['diag']['ipv6nat']['enable']);
$pconfig['ipv6nat_ipaddr'] = $config['diag']['ipv6nat']['ipaddr'];
$pconfig['cert'] = base64_decode($config['system']['webgui']['certificate']);
$pconfig['key'] = base64_decode($config['system']['webgui']['private-key']);
$pconfig['disableconsolemenu'] = isset($config['system']['disableconsolemenu']);
$pconfig['disablefirmwarecheck'] = isset($config['system']['disablefirmwarecheck']);
$pconfig['altfirmwareurl'] = $config['system']['altfirmwareurl'];
$pconfig['firmwareurl'] = $config['system']['firmwareurl'];
$pconfig['firmwarename'] = $config['system']['firmwarename'];
$pconfig['expanddiags'] = isset($config['system']['webgui']['expanddiags']);
if ($g['platform'] == "generic-pc")
	$pconfig['harddiskstandby'] = $config['system']['harddiskstandby'];
$pconfig['noantilockout'] = isset($config['system']['webgui']['noantilockout']);
$pconfig['tcpidletimeout'] = $config['filter']['tcpidletimeout'];

$pconfig['schedulertype'] = $config['system']['schedulertype'];
$pconfig['maximumstates'] = $config['system']['maximumstates'];
$pconfig['disablerendevouz'] = $config['system']['disablerendevouz'];

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['ipv6nat_enable'] && !is_ipaddr($_POST['ipv6nat_ipaddr'])) {
		$input_errors[] = "You must specify an IP address to NAT IPv6 packets.";
	}
	if ($_POST['maximumstates'] && !is_numericint($_POST['maximumstates'])) {
		$input_errors[] = "The Firewall Maximum States value must be an integer.";
	}
	if ($_POST['tcpidletimeout'] && !is_numericint($_POST['tcpidletimeout'])) {
		$input_errors[] = "The TCP idle timeout must be an integer.";
	}
	if (($_POST['cert'] && !$_POST['key']) || ($_POST['key'] && !$_POST['cert'])) {
		$input_errors[] = "Certificate and key must always be specified together.";
	} else if ($_POST['cert'] && $_POST['key']) {
		if (!strstr($_POST['cert'], "BEGIN CERTIFICATE") || !strstr($_POST['cert'], "END CERTIFICATE"))
			$input_errors[] = "This certificate does not appear to be valid.";
		if (!strstr($_POST['key'], "BEGIN RSA PRIVATE KEY") || !strstr($_POST['key'], "END RSA PRIVATE KEY"))
			$input_errors[] = "This key does not appear to be valid.";
	if ($_POST['altfirmwareurl'])
		if ($_POST['firmwareurl'] == "" || $_POST['firmwarename'] == "")
		$input_errors[] = "You must specify a base URL and a filename for the alternate firmware.";
	}

	if (!$input_errors) {
		if($_POST['disablefilter'] == "yes") {
			$config['system']['disablefilter'] = "enabled";
		} else {
			unset($config['system']['disablefilter']);
		}
		if($_POST['disableftpproxy'] == "yes") {
			$config['system']['disableftpproxy'] = "enabled";
			unset($config['system']['rfc959workaround']);
		} else {
			unset($config['system']['disableftpproxy']);
		}
		if($_POST['rfc959workaround'] == "yes") {
			$config['system']['rfc959workaround'] = "enabled";
		}
		$config['bridge']['filteringbridge'] = $_POST['filteringbridge_enable'] ? true : false;
		$config['diag']['ipv6nat']['enable'] = $_POST['ipv6nat_enable'] ? true : false;
		$config['diag']['ipv6nat']['ipaddr'] = $_POST['ipv6nat_ipaddr'];
		$oldcert = $config['system']['webgui']['certificate'];
		$oldkey = $config['system']['webgui']['private-key'];
		$config['system']['webgui']['certificate'] = base64_encode($_POST['cert']);
		$config['system']['webgui']['private-key'] = base64_encode($_POST['key']);
		$config['system']['disableconsolemenu'] = $_POST['disableconsolemenu'] ? true : false;
		$config['system']['disablefirmwarecheck'] = $_POST['disablefirmwarecheck'] ? true : false;
		$config['system']['altfirmwareurl'] = $_POST['altfirmwareurl'] ? true : false;
		if ($config['system']['altfirmwareurl']) {
			$config['system']['firmwareurl'] = $_POST['firmwareurl'];
			$config['system']['firmwarename'] = $_POST['firmwarename'];
		} else {
			unset($config['system']['firmwareurl']);
			unset($config['system']['firmwarename']);
		}
		$config['system']['webgui']['expanddiags'] = $_POST['expanddiags'] ? true : false;
		$config['system']['optimization'] = $_POST['optimization'];
		$config['system']['disablerendevouz'] = $_POST['disablerendevouz'];

		if ($g['platform'] == "generic-pc") {
			$oldharddiskstandby = $config['system']['harddiskstandby'];
			$config['system']['harddiskstandby'] = $_POST['harddiskstandby'];
		}
		$config['system']['webgui']['noantilockout'] = $_POST['noantilockout'] ? true : false;

		/* Firewall and ALTQ options */
		$config['system']['schedulertype'] = $_POST['schedulertype'];
		$config['system']['maximumstates'] = $_POST['maximumstates'];

		write_config();

		if (($config['system']['webgui']['certificate'] != $oldcert)
				|| ($config['system']['webgui']['private-key'] != $oldkey)) {
			touch($d_sysrebootreqd_path);
		} else if (($g['platform'] == "generic-pc") && ($config['system']['harddiskstandby'] != $oldharddiskstandby)) {
			if (!$config['system']['harddiskstandby']) {
				// Reboot needed to deactivate standby due to a stupid ATA-protocol
				touch($d_sysrebootreqd_path);
				unset($config['system']['harddiskstandby']);
			} else {
				// No need to set the standby-time if a reboot is needed anyway
				system_set_harddisk_standby();
			}
		}

		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			config_lock();
			$retval = filter_configure();
			if(stristr($retval, "error") <> true)
			    $savemsg = get_std_save_message($retval);
			else
			    $savemsg = $retval;
			$retval |= interfaces_optional_configure();
			config_unlock();
		}
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("System: Advanced functions");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
<script language="JavaScript">
<!--
function enable_change(enable_over) {
	if (document.iform.ipv6nat_enable.checked || enable_over) {
		document.iform.ipv6nat_ipaddr.disabled = 0;
		document.iform.schedulertype.disabled = 0;
	} else {
		document.iform.ipv6nat_ipaddr.disabled = 1;
	}
}
function enable_altfirmwareurl(enable_over) {
        if (document.iform.altfirmwareurl.checked || enable_over) {
                document.iform.firmwareurl.disabled = 0;
                document.iform.firmwarename.disabled = 0;
        } else {
                document.iform.firmwareurl.disabled = 1;
                document.iform.firmwarename.disabled = 1;
        }
}

// -->
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<form action="system_advanced.php" method="post" name="iform" id="iform">
<?php include("fbegin.inc"); ?>
      <p class="pgtitle">System: Advanced functions</p>
            <?php if ($input_errors) print_input_errors($input_errors); ?>
            <?php if ($savemsg) print_info_box($savemsg); ?>
            <p><span class="vexpl"><span class="red"><strong>Note: </strong></span>the
              options on this page are intended for use by advanced users only,
              and there's <strong>NO</strong> support for them.</span></p><br>

              <table width="100%" border="0" cellpadding="6" cellspacing="0">

                <tr>
                  <td colspan="2" valign="top" class="listtopic">Disable Rendezvous</td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">&nbsp;</td>
                  <td width="78%" class="vtable">
                    <input name="disablerendevouz" type="checkbox" id="disablerendevouz" value="yes" <?php if ($pconfig['disablerendevouz']) echo "checked"; ?> onclick="enable_change(false)">
                    <strong>Disable the Rendevouz automatic discovery protocol.</strong>
                    </td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)">
                  </td>
                </tr>
                <tr>
                  <td colspan="2" class="list" height="12"></td>
                </tr>

                <tr>
                  <td colspan="2" valign="top" class="listtopic">IPv6 tunneling</td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">&nbsp;</td>
                  <td width="78%" class="vtable">
                    <input name="ipv6nat_enable" type="checkbox" id="ipv6nat_enable" value="yes" <?php if ($pconfig['ipv6nat_enable']) echo "checked"; ?> onclick="enable_change(false)">
                    <strong>NAT encapsulated IPv6 packets (IP protocol 41/RFC2893)
                    to:</strong><br> <br> <input name="ipv6nat_ipaddr" type="text" class="formfld" id="ipv6nat_ipaddr" size="20" value="<?=htmlspecialchars($pconfig['ipv6nat_ipaddr']);?>">
                    &nbsp;(IP address)<span class="vexpl"><br>
                    Don't forget to add a firewall rule to permit IPv6 packets!</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)">
                  </td>
                </tr>
                <tr>
                  <td colspan="2" class="list" height="12"></td>
                </tr>
		<tr>
                  <td colspan="2" valign="top" class="listtopic">Filtering bridge</td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">&nbsp;</td>
                  <td width="78%" class="vtable">
                    <input name="filteringbridge_enable" type="checkbox" id="filteringbridge_enable" value="yes" <?php if ($pconfig['filteringbridge_enable']) echo "checked"; ?>>
                    <strong>Enable filtering bridge</strong><span class="vexpl"><br>
                    This will cause bridged packets to pass through the packet
                    filter in the same way as routed packets do (by default bridged
                    packets are always passed). If you enable this option, you'll
                    have to add filter rules to selectively permit traffic from
                    bridged interfaces.</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)">
                  </td>
                </tr>
                <tr>
                  <td colspan="2" class="list" height="12"></td>
                </tr>
                <tr>
                  <td colspan="2" valign="top" class="listtopic">webGUI SSL certificate/key</td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">Certificate</td>
                  <td width="78%" class="vtable">
                    <textarea name="cert" cols="65" rows="7" id="cert" class="formpre"><?=htmlspecialchars($pconfig['cert']);?></textarea>
                    <br>
                    Paste a signed certificate in X.509 PEM format here. <A target="_new" HREF='system_advanced_create_certs.php'>Create</a> certificates automatically.</td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">Key</td>
                  <td width="78%" class="vtable">
                    <textarea name="key" cols="65" rows="7" id="key" class="formpre"><?=htmlspecialchars($pconfig['key']);?></textarea>
                    <br>
                    Paste an RSA private key in PEM format here.</td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)">
                  </td>
                </tr>
                <tr>
                  <td colspan="2" class="list" height="12"></td>
                </tr>
                <tr>
                  <td colspan="2" valign="top" class="listtopic">Miscellaneous</td>
                </tr>
				<tr>
                  <td width="22%" valign="top" class="vncell">Console menu </td>
                  <td width="78%" class="vtable">
                    <input name="disableconsolemenu" type="checkbox" id="disableconsolemenu" value="yes" <?php if ($pconfig['disableconsolemenu']) echo "checked"; ?>>
                    <strong>Disable console menu</strong><span class="vexpl"><br>
                    Changes to this option will take effect after a reboot.</span></td>
                </tr>
		<tr>
                  <td valign="top" class="vncell">Firmware version check </td>
                  <td class="vtable">
                    <input name="disablefirmwarecheck" type="checkbox" id="disablefirmwarecheck" value="yes" <?php if ($pconfig['disablefirmwarecheck']) echo "checked"; ?>>
                    <strong>Disable firmware version check</strong><span class="vexpl"><br>
    This will cause pfSense not to check for newer firmware versions when the <a href="system_firmware.php">System: Firmware</a> page is viewed.</span></td>
		</tr>
		<tr>
                  <td valign="top" class="vncell">Alternate firmware URL</td>
                  <td class="vtable">
                    <input name="altfirmwareurl" type="checkbox" id="altfirmwareurl" value="yes" onClick="enable_altfirmwareurl()" <?php if (isset($pconfig['altfirmwareurl'])) echo "checked"; ?>> Use a different URL for firmware upgrades<br>
		    <table>
                    <tr><td>Base URL:</td><td><input name="firmwareurl" type="input" id="firmwareurl" size="64" value="<?php if ($pconfig['firmwareurl']) echo $pconfig['firmwareurl']; else echo $g['firmwarebaseurl']; ?>"></td></tr>
                    <tr><td>Filename:</td><td><input name="firmwarename" type="input" id="firmwarename" size="32" value="<?php if ($pconfig['firmwarename']) echo $pconfig['firmwarename']; else echo $g['firmwarefilename']; ?>"></td></tr>
		    </table>
                    <span class="vexpl"><br>
    This is where pfSense will check for newer firmware versions when <a href="system_firmware.php">System: Firmware</a> page is viewed.</span></td>
		</tr>
		<tr>
                  <td width="22%" valign="top" class="vncell">Hard disk standby time </td>
                  <td width="78%" class="vtable">
                    <select name="harddiskstandby" class="formfld">
					<?php
                        /* Values from ATA-2
                           http://www.t13.org/project/d0948r3-ATA-2.pdf
                           Page 66 */
						$sbvals = explode(" ", "0.5,6 1,12 2,24 3,36 4,48 5,60 7.5,90 10,120 15,180 20,240 30,241 60,242");
					?>
                      <option value="" <?php if(!$pconfig['harddiskstandby']) echo('selected');?>>Always on</option>
					<?php
					foreach ($sbvals as $sbval):
						list($min,$val) = explode(",", $sbval); ?>
                      <option value="<?=$val;?>" <?php if($pconfig['harddiskstandby'] == $val) echo('selected');?>><?=$min;?> minutes</option>
					<?php endforeach; ?>
                    </select>
                    <br>
                    Puts the hard disk into standby mode when the selected amount of time after the last
                    access has elapsed. <em>Do not set this for CF cards.</em></td>
				</tr>
				<tr>
                  <td width="22%" valign="top" class="vncell">Navigation</td>
                  <td width="78%" class="vtable">
                    <input name="expanddiags" type="checkbox" id="expanddiags" value="yes" <?php if ($pconfig['expanddiags']) echo "checked"; ?>>
                    <strong>Keep diagnostics in navigation expanded </strong></td>
                </tr>
		<tr>
                  <td width="22%" valign="top" class="vncell">webGUI anti-lockout</td>
                  <td width="78%" class="vtable">
                    <input name="noantilockout" type="checkbox" id="noantilockout" value="yes" <?php if ($pconfig['noantilockout']) echo "checked"; ?>>
                    <strong>Disable webGUI anti-lockout rule</strong><br>
					By default, access to the webGUI on the LAN interface is always permitted, regardless of the user-defined filter rule set. Enable this feature to control webGUI access (make sure to have a filter rule in place that allows you in, or you will lock yourself out!).<br>
					Hint:
					the &quot;set LAN IP address&quot; option in the console menu  resets this setting as well.</td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)">
                  </td>
                </tr>
                <tr>
                  <td colspan="2" class="list" height="12"></td>
                </tr>
                <tr>
                  <td colspan="2" valign="top" class="listtopic">Traffic Shaper and Firewall Advanced</td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">FTP Helper</td>
                  <td width="78%" class="vtable">
                    <input name="disableftpproxy" type="checkbox" id="disableftpproxy" value="yes" <?php if (isset($config['system']['disableftpproxy'])) echo "checked"; ?> onclick="enable_change(false)">
                    <strong class="vexpl">Disable the userland FTP-Proxy application</strong><br>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">FTP RFC 959 data port violation workaround</td>
                  <td width="78%" class="vtable">
                    <input name="rfc959workaround" type="checkbox" id="rfc959workaround" value="yes" <?php if (isset($config['system']['rfc959workaround'])) echo "checked"; ?> onclick="enable_change(false)">
                    <strong class="vexpl">Workaround for sites that violate RFC 959 which specifies that the data connection be sourced from the command port - 1 (typically port 20).  This workaround doesn't expose you to any extra risk as the firewall will still only allow connections on a port that the ftp-proxy is listening on.</strong><br>
                </tr>

		<tr>
		  <td width="22%" valign="top" class="vncell">Traffic Shaper Scheduler</td>
		  <td width="78%" class="vtable">
		    <select id="schedulertype" name="schedulertype" <?= $style ?>>
		    <?php
			    if($pconfig['schedulertype'] == 'priq')
				    echo "<option value=\"priq\">Priority based queueing</option>";
			    if($pconfig['schedulertype'] == 'cbq')
				    echo "<option value=\"cbq\">Class based queueing</option>";
			    if($pconfig['schedulertype'] == 'hfsc')
				    echo "<option value=\"hfsc\">Hierarchical Fair Service Curve queueing</option>";
		    ?>
			    <option value="priq">Priority based queueing</option>
			    <option value="cbq">Class based queueing</option>
			    <option value="hfsc">Hierarchical Fair Service Curve queueing</option>
		    </select>
		    <br> <span class="vexpl"><b>Select which type of queueing you would like to use</b>
		  <?php if (is_array($config['shaper']['queue']) > 0): ?>
			<script language="javascript">
			document.iform.schedulertype.disabled = 1;
			</script>
			<br>
			NOTE: This option is disabled since there are queues defined.
		  <?php endif; ?>
		    </span></td>
		</tr>

		<tr>
                  <td width="22%" valign="top" class="vncell">Firewall Optimization Options</td>
                  <td width="78%" class="vtable">
		    <select name="optimization" id="optimization">
			<option value="normal"<?php if($config['system']['optimization']=="normal") echo " SELECTED"; ?>>normal - as the name says, it's the normal optimization algorithm.</option>
			<option value="high-latency"<?php if($config['system']['optimization']=="high-latency") echo " SELECTED"; ?>>high-latency - used for high latency links, such as satellite links.  Expires idle connections later than default.</option>
			<option value="aggressive"<?php if($config['system']['optimization']=="aggressive") echo " SELECTED"; ?>>aggressive - expires idle connections earlier than default; using less memory and CPU time while possibly dropping some legitimate connections.</option>
			<option value="conservative"<?php if($config['system']['optimization']=="conservative") echo " SELECTED"; ?>>conservative - tries to avoid dropping any legitimate connections at the expense of increased memory usage and CPU utilization.</option>
		    </select>
		    <br> <span class="vexpl"><b>Select which type of state table optimization your would like to use</b></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">Disable Firewall</td>
                  <td width="78%" class="vtable">
                    <input name="disablefilter" type="checkbox" id="disablefilter" value="yes" <?php if (isset($config['system']['disablefilter'])) echo "checked"; ?> onclick="enable_change(false)">
                    <strong>Disable the firewalls filter altogether.</strong><br>
                    <span class="vexpl">NOTE!  This basically converts pfSense into a routing only platform!</span></td>
                </tr>

                <tr>
                  <td width="22%" valign="top" class="vncell">Firewall Maximum States</td>
                  <td width="78%" class="vtable">
                    <input name="maximumstates" type="input" id="maximumstates" value="<?php echo $pconfig['maximumstates']; ?>" onclick="enable_change(false)"><br>
                    <strong>Maximum number of connections to hold in the firewall state table.</strong><br>
                    <span class="vexpl">NOTE!  Leave this blank for the default of 10000</span></td>
                </tr>

                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)">
                  </td>
                </tr>
                <tr>
                  <td colspan="2" class="list" height="12"></td>
                </tr>






              </table>
</form>
            <script language="JavaScript">
<!--
enable_change(false);
enable_altfirmwareurl(false);
//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>
