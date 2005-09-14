#!/usr/local/bin/php
<?php
/* $Id$ */
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
$pconfig['ipv6nat_enable'] = isset($config['diag']['ipv6nat']['enable']);
$pconfig['ipv6nat_ipaddr'] = $config['diag']['ipv6nat']['ipaddr'];
$pconfig['cert'] = base64_decode($config['system']['webgui']['certificate']);
$pconfig['key'] = base64_decode($config['system']['webgui']['private-key']);
$pconfig['disableconsolemenu'] = isset($config['system']['disableconsolemenu']);
$pconfig['harddiskstandby'] = $config['system']['harddiskstandby'];
$pconfig['noantilockout'] = isset($config['system']['webgui']['noantilockout']);
$pconfig['tcpidletimeout'] = $config['filter']['tcpidletimeout'];
$pconfig['schedulertype'] = $config['system']['schedulertype'];
$pconfig['maximumstates'] = $config['system']['maximumstates'];
$pconfig['theme'] = $config['system']['theme'];
$pconfig['disablerendevouz'] = $config['system']['disablerendevouz'];
$pconfig['enableserial'] = $config['system']['enableserial'];
$pconfig['disablefirmwarecheck'] = isset($config['system']['disablefirmwarecheck']);
$pconfig['preferoldsa_enable'] = isset($config['ipsec']['preferoldsa']);
$pconfig['enablesshd'] = $config['system']['enablesshd'];
$pconfig['sharednet'] = $config['system']['sharednet'];

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
	if ($_POST['altpkgconfigurl'])
		if ($_POST['pkgconfig_base_url'] == "" || $_POST['pkgconfig_filename'] == "")
		$input_errors[] = "You must specifiy and base URL and a filename before using an alternate pkg_config.xml.";
	}
	if ($_POST['maximumstates'] <> "") {
		if ($_POST['maximumstates'] < 1000)
			$input_errors[] = "States must be above 1000 and below 100000000";
		if ($_POST['maximumstates'] > 100000000)
			$input_errors[] = "States must be above 1000 and below 100000000";
	}
	
	if (!$input_errors) {
		if($_POST['disablefilter'] == "yes") {
			$config['system']['disablefilter'] = "enabled";
		} else {
			unset($config['system']['disablefilter']);
		}
		if($_POST['enablesshd'] == "yes") {
			$config['system']['enablesshd'] = "enabled";
		} else {
			unset($config['system']['enablesshd']);
		}		

		if($_POST['sharednet'] == "yes") {
			$config['system']['sharednet'] = true;
			system_disable_arp_wrong_if();
		} else {
			unset($config['system']['sharednet']);
			system_enable_arp_wrong_if();
		}		

		if($_POST['disableftpproxy'] == "yes") {
			$config['system']['disableftpproxy'] = "enabled";
			unset($config['system']['rfc959workaround']);
			system_start_ftp_helpers();
		} else {
			unset($config['system']['disableftpproxy']);
			system_start_ftp_helpers();
		}
		if($_POST['rfc959workaround'] == "yes")
			$config['system']['rfc959workaround'] = "enabled";
		else
			unset($config['system']['rfc959workaround']);

		if($_POST['ipv6nat_enable'] == "yes") {
			$config['diag']['ipv6nat']['enable'] = true;
			$config['diag']['ipv6nat']['ipaddr'] = $_POST['ipv6nat_ipaddr'];
		} else {
			unset($config['diag']['ipv6nat']['enable']);
			unset($config['diag']['ipv6nat']['ipaddr']);
		}
		$oldcert = $config['system']['webgui']['certificate'];
		$oldkey = $config['system']['webgui']['private-key'];
		$config['system']['webgui']['certificate'] = base64_encode($_POST['cert']);
		$config['system']['webgui']['private-key'] = base64_encode($_POST['key']);
		if($_POST['disableconsolemenu'] == "yes") {
			$config['system']['disableconsolemenu'] = true;
			auto_login(true);
		} else {
			unset($config['system']['disableconsolemenu']);
			auto_login(false);
		}
		unset($config['system']['webgui']['expanddiags']);
		$config['system']['optimization'] = $_POST['optimization'];
		
		if($_POST['disablefirmwarecheck'] == "yes")
			$config['system']['disablefirmwarecheck'] = true;
		else
			unset($config['system']['disablefirmwarecheck']);

		if ($_POST['enableserial'] == "yes")
			$config['system']['enableserial'] = true;
		else
			unset($config['system']['enableserial']);

		if($_POST['harddiskstandby'] <> "") {
			$config['system']['harddiskstandby'] = $_POST['harddiskstandby'];
			system_set_harddisk_standby();
		} else
			unset($config['system']['harddiskstandby']);

		if ($_POST['noantilockout'] == "yes")
			$config['system']['webgui']['noantilockout'] = true;
		else
			unset($config['system']['webgui']['noantilockout']);

		/* Firewall and ALTQ options */
		$config['system']['schedulertype'] = $_POST['schedulertype'];
		$config['system']['maximumstates'] = $_POST['maximumstates'];

		if($_POST['enablesshd'] == "yes") {
			$config['system']['enablesshd'] = $_POST['enablesshd'];
		} else {
			unset($config['system']['enablesshd']);
		}

                $config['ipsec']['preferoldsa'] = $_POST['preferoldsa_enable'] ? true : false;
	
		/* pfSense themes */
		$config['theme'] = $_POST['theme'];

		write_config();

		if (($config['system']['webgui']['certificate'] != $oldcert)
				|| ($config['system']['webgui']['private-key'] != $oldkey)) {
			system_webgui_start();
		}

			
		$retval = 0;
		config_lock();
		$retval = filter_configure();
		if(stristr($retval, "error") <> true)
		    $savemsg = get_std_save_message($retval);
		else
		    $savemsg = $retval;
		$retval |= interfaces_optional_configure();
		config_unlock();
		
		$etc_ttys  = return_filename_as_array("/etc/ttys");
		$boot_loader_rc = return_filename_as_array("/boot/loader.rc");
		
		conf_mount_rw();
		
		$fout = fopen("/etc/ttys","w");
		if(!$fout) {
			echo "Cannot open /etc/ttys for writing.  Floppy inserted?\n";	
		} else {		
			foreach($etc_ttys as $tty) {
				if(stristr($tty,"ttyv0") <> true) {
					fwrite($fout, $tty . "\n");				
				}
			}
			if(isset($pconfig['enableserial']))
				fwrite($fout, "ttyv0\t\"/usr/libexec/getty Pc\"\tcons25\t\ton\tsecure\n");
			fclose($fout);		
		}
		
		$fout = fopen("/boot/loader.rc","w");
		if(!is_array($boot_loader_rc))
			$boot_loader_rc = array();
		foreach($boot_loader_rc as $blrc) {
			if(stristr($blrc,"comconsole") <> true) {
				fwrite($fout, $blrc . "\n");				
			}
		}
		if(isset($pconfig['enableserial']))
			fwrite($fout, "set console=comconsole\n");
		fclose($fout);
		
		mwexec("/etc/sshd");
		
		conf_mount_ro();
	}
}

$pgtitle = "System: Advanced functions";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php include("fbegin.inc"); ?>

<p class="pgtitle"><?=$pgtitle?></p>

<form action="system_advanced.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<p><span class="vexpl"><span class="red"><strong>Note: </strong></span>the options on this page are intended for use by advanced users only.</span></p>
<br />

<table width="100%" border="0" cellpadding="6" cellspacing="0">
	<tbody>
		<?php if($g['platform'] == "pfSense" || $g['platform'] == "cdrom"): ?>
		<tr>
			<td colspan="2" valign="top" class="listtopic">Enable Serial Console</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">&nbsp;</td>
			<td width="78%" class="vtable">
				<input name="enableserial" type="checkbox" id="enableserial" value="yes" <?php if (isset($pconfig['enableserial'])) echo "checked"; ?> onclick="enable_change(false)" />
				<strong>This will enable the first serial port with 9600/8/N/1</strong>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%"><input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)" /></td>
		</tr>
		</tr>
		<tr>
			<td colspan="2" class="list" height="12"></td>
		</tr>		
		<?php endif ?>
		<tr>
			<td colspan="2" valign="top" class="listtopic">Secure Shell</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">&nbsp;</td>
			<td width="78%" class="vtable">
				<input name="enablesshd" type="checkbox" id="enablesshd" value="yes" <?php if (isset($pconfig['enablesshd'])) echo "checked"; ?> onclick="enable_change(false)" />
				<strong>Enable Secure Shell</strong>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)" />
			</td>
		</tr>
		<tr>
			<td colspan="2" class="list" height="12">&nbsp;</td>
		</tr>		
		<tr>
			<td colspan="2" valign="top" class="listtopic">Shared Physical Network</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">&nbsp;</td>
			<td width="78%" class="vtable">
				<input name="sharednet" type="checkbox" id="sharednet" value="yes" <?php if (isset($pconfig['sharednet'])) echo "checked"; ?> onclick="enable_change(false)" />
				<strong>This will suppress ARP messages when interfaces share the same physical network</strong>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)" />
			</td>
		</tr>
		<tr>
			<td colspan="2" class="list" height="12">&nbsp;</td>
		</tr>	
		<tr>
			<td colspan="2" valign="top" class="listtopic">Theme</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">&nbsp;</td>
			<td width="78%" class="vtable">
			    <select name="theme">
<?php
				$files = return_dir_as_array("/usr/local/www/themes/");
				foreach($files as $f) {
					if ( (substr($f, 0, 1) == "_") && !isset($config['system']['developer']) ) continue;
					if($f == "CVS") continue;
					$selected = "";
					if($f == $config['theme'])
						$selected = " SELECTED";
					if($config['theme'] == "" and $f == "pfsense")
						$selceted = " SELECTED";
					echo "\t\t\t\t\t"."<option{$selected}>{$f}</option>\n";
				}
?>
				</select>
				<strong>This will change the look and feel of pfSense</strong>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)" />
			</td>
		</tr>
		<tr>
			<td colspan="2" class="list" height="12">&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2" valign="top" class="listtopic">IPv6 tunneling</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">&nbsp;</td>
			<td width="78%" class="vtable">
				<input name="ipv6nat_enable" type="checkbox" id="ipv6nat_enable" value="yes" <?php if ($pconfig['ipv6nat_enable']) echo "checked"; ?> onclick="enable_change(false)" />
				<strong>NAT encapsulated IPv6 packets (IP protocol 41/RFC2893) to:</strong>
				<br /> <br />
				<input name="ipv6nat_ipaddr" type="text" class="formfld" id="ipv6nat_ipaddr" size="20" value="<?=htmlspecialchars($pconfig['ipv6nat_ipaddr']);?>" />
				&nbsp;(IP address)<span class="vexpl"><br /> Don't forget to add a firewall rule to permit IPv6 packets!</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)" />
			</td>
		</tr>
		<tr>
			<td colspan="2" class="list" height="12">&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2" valign="top" class="listtopic">webGUI SSL certificate/key</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Certificate</td>
			<td width="78%" class="vtable">
				<textarea name="cert" cols="65" rows="7" id="cert" class="formpre"><?=htmlspecialchars($pconfig['cert']);?></textarea>
				<br />
				Paste a signed certificate in X.509 PEM format here. <a href="javascript:if(openwindow('system_advanced_create_certs.php') == false) alert('Popup blocker detected.  Action aborted.');" >Create</a> certificates automatically.
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Key</td>
			<td width="78%" class="vtable">
				<textarea name="key" cols="65" rows="7" id="key" class="formpre"><?=htmlspecialchars($pconfig['key']);?></textarea>
				<br />
				Paste an RSA private key in PEM format here.
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)" />
			</td>
		</tr>
		<tr>
			<td colspan="2" class="list" height="12">&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2" valign="top" class="listtopic">Miscellaneous</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Console menu </td>
			<td width="78%" class="vtable">
				<input name="disableconsolemenu" type="checkbox" id="disableconsolemenu" value="yes" <?php if ($pconfig['disableconsolemenu']) echo "checked"; ?>  />
				<strong>Disable console menu</strong>
				<br />
				<span class="vexpl">Changes to this option will take effect after a reboot.</span>
			</td>
		</tr>
		<tr>
			<td valign="top" class="vncell">Firmware version check</td>
			<td class="vtable">
				<input name="disablefirmwarecheck" type="checkbox" id="disablefirmwarecheck" value="yes" <?php if ($pconfig['disablefirmwarecheck']) echo "checked"; ?>  />
				<strong>Disable firmware version check</strong>
				<br />
				<span class="vexpl">This will cause pfSense not to check for newer firmware versions when the <a href="system_firmware.php">System: Firmware</a> page is viewed.</span>
			</td>
		</tr>		
		<tr>
			<td width="22%" valign="top" class="vncell">Hard disk standby time </td>
			<td width="78%" class="vtable">
				<select name="harddiskstandby" class="formfld">
<?php
				 	## Values from ATA-2 http://www.t13.org/project/d0948r3-ATA-2.pdf (Page 66)
					$sbvals = explode(" ", "0.5,6 1,12 2,24 3,36 4,48 5,60 7.5,90 10,120 15,180 20,240 30,241 60,242");
?>
					<option value="" <?php if(!$pconfig['harddiskstandby']) echo('selected');?>>Always on</option>
<?php
					foreach ($sbvals as $sbval):
						list($min,$val) = explode(",", $sbval); ?>
					<option value="<?=$val;?>" <?php if($pconfig['harddiskstandby'] == $val) echo('selected');?>><?=$min;?> minutes</option>
<?php 				endforeach; ?>
				</select>
				<br />
				Puts the hard disk into standby mode when the selected amount of time after the last
				access has elapsed. <em>Do not set this for CF cards.</em>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">webGUI anti-lockout</td>
			<td width="78%" class="vtable">
				<input name="noantilockout" type="checkbox" id="noantilockout" value="yes" <?php if ($pconfig['noantilockout']) echo "checked"; ?> />
				<strong>Disable webGUI anti-lockout rule</strong>
				<br />
				By default, access to the webGUI on the LAN interface is always permitted, regardless of the user-defined filter 
				rule set. Enable this feature to control webGUI access (make sure to have a filter rule in place that allows you 
				in, or you will lock yourself out!).
				<br />
				Hint: the &quot;set LAN IP address&quot; option in the console menu  resets this setting as well.
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">IPsec SA preferral</td>
			<td width="78%" class="vtable">
				<input name="preferoldsa_enable" type="checkbox" id="preferoldsa_enable" value="yes" <?php if ($pconfig['preferoldsa_enable']) echo "checked"; ?> />
				<strong>Prefer old IPsec SAs</strong>
				<br />
				By default, if several SAs match, the newest one is preferred if it's at least 30 seconds old.Select this option to always prefer old SAs over new ones.
			</td>
		</tr>		
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)" />
			</td>
		</tr>
		<tr>
			<td colspan="2" class="list" height="12">&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2" valign="top" class="listtopic">Traffic Shaper and Firewall Advanced</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">FTP Helper</td>
			<td width="78%" class="vtable">
				<input name="disableftpproxy" type="checkbox" id="disableftpproxy" value="yes" <?php if (isset($config['system']['disableftpproxy'])) echo "checked"; ?> onclick="enable_change(false)" />
				<strong class="vexpl">Disable the userland FTP-Proxy application</strong>
				<br />
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">FTP RFC 959 data port violation workaround</td>
			<td width="78%" class="vtable">
				<input name="rfc959workaround" type="checkbox" id="rfc959workaround" value="yes" <?php if (isset($config['system']['rfc959workaround'])) echo "checked"; ?> onclick="enable_change(false)" />
				<strong class="vexpl">Workaround for sites that violate RFC 959 which specifies that the data connection be sourced from the command port - 1 (typically port 20).  This workaround doesn't expose you to any extra risk as the firewall will still only allow connections on a port that the ftp-proxy is listening on.</strong>
				<br />
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Traffic Shaper Scheduler</td>
			<td width="78%" class="vtable">
				<select id="schedulertype" name="schedulertype" <?= $style ?>>
					<option value="priq"<?php if($pconfig['schedulertype'] == 'priq') echo " selected"; ?>>Priority based queueing</option>
					<option value="cbq"<?php if($pconfig['schedulertype'] == 'cbq') echo " selected"; ?>>Class based queueing</option>
					<option value="hfsc"<?php if($pconfig['schedulertype'] == 'hfsc') echo " selected"; ?>>Hierarchical Fair Service Curve queueing</option>
				</select>
				<br />
				<span class="vexpl"><b>Select which type of queueing you would like to use</b></span>
				<?php if (is_array($config['shaper']['queue']) > 0): ?>
				<script language="javascript" type="text/javascript">
					document.iform.schedulertype.disabled = 1;
				</script>
				<br />
				NOTE: This option is disabled since there are queues defined.
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Firewall Optimization Options</td>
			<td width="78%" class="vtable">
				<select onChange="update_description(this.selectedIndex);" name="optimization" id="optimization">
					<option value="normal"<?php if($config['system']['optimization']=="normal") echo " selected"; ?>>normal</option>
					<option value="high-latency"<?php if($config['system']['optimization']=="high-latency") echo " selected"; ?>>high-latency</option>
					<option value="aggressive"<?php if($config['system']['optimization']=="aggressive") echo " selected"; ?>>aggressive</option>
					<option value="conservative"<?php if($config['system']['optimization']=="conservative") echo " selected"; ?>>conservative</option>
				</select>
				<br />
				<textarea cols="60" rows="2" id="info" name="info"style="border:1px dashed #000066; background-color: #ffffff; color: #000000; font-size: 8pt;"></textarea>
				<script language="javascript" type="text/javascript">
					update_description(document.forms[0].optimization.selectedIndex);
				</script>
				<br />
				<span class="vexpl"><b>Select which type of state table optimization your would like to use</b></span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Disable Firewall</td>
			<td width="78%" class="vtable">
				<input name="disablefilter" type="checkbox" id="disablefilter" value="yes" <?php if (isset($config['system']['disablefilter'])) echo "checked"; ?> onclick="enable_change(false)" />
				<strong>Disable the firewalls filter altogether.</strong>
				<br />
				<span class="vexpl">Note:  This basically converts pfSense into a routing only platform!</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Firewall Maximum States</td>
			<td width="78%" class="vtable">
				<input name="maximumstates" type="text" id="maximumstates" value="<?php echo $pconfig['maximumstates']; ?>" onclick="enable_change(false)" />
				<br />
				<strong>Maximum number of connections to hold in the firewall state table.</strong>
				<br />
				<span class="vexpl">Note:  Leave this blank for the default of 10000</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%"><input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)" /></td>
		</tr>
		<tr>
			<td colspan="2" class="list" height="12">&nbsp;</td>
		</tr>
	</tbody>
</table>
</form>

<script language="JavaScript" type="text/javascript">
<!--
	enable_change(false);
	//enable_altfirmwareurl(false);
	//enable_altpkgconfigurl(false);
//-->
</script>

<?php include("fend.inc"); ?>

<?php

function auto_login($status) {
	$gettytab = file_get_contents("/etc/gettyab");
	$getty_split = split("\n", $gettytab);
	conf_mount_rw();
	$fd = fopen("/etc/gettytab", "w");
	foreach($getty_split as $gs) {
		if(stristr($gs, "cb:ce:ck:lc") == true) {
			if($status == true) {
				fwrite($fd, "::cb:ce:ck:lc:fd#1000:im=\r\n%s/%m (%h) (%t)\r\n\r\n:sp#1200:\\n");
			} else {
				fwrite($fd, ":al=root:cb:ce:ck:lc:fd#1000:im=\r\n%s/%m (%h) (%t)\r\n\r\n:sp#1200:\\n");
			}
		} else {
			fwrite($fd, "{$gs}\n");
		}
	}
	fclose($fd);
	conf_mount_ro();	
}

?>
</body>
</html>
