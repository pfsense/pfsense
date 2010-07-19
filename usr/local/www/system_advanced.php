<?php
/* $Id$ */
/*
		system_advanced.php
        part of pfSense
        Copyright (C) 2005-2007 Scott Ullrich

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
$pconfig['rfc959workaround'] = $config['system']['rfc959workaround'];
$pconfig['scrubnodf'] = $config['system']['scrubnodf'];
$pconfig['ipv6nat_enable'] = isset($config['diag']['ipv6nat']['enable']);
$pconfig['ipv6nat_ipaddr'] = $config['diag']['ipv6nat']['ipaddr'];
$pconfig['ipv6allow'] = isset($config['system']['ipv6allow']);
$pconfig['cert'] = base64_decode($config['system']['webgui']['certificate']);
$pconfig['key'] = base64_decode($config['system']['webgui']['private-key']);
$pconfig['disableconsolemenu'] = isset($config['system']['disableconsolemenu']);
$pconfig['harddiskstandby'] = $config['system']['harddiskstandby'];
$pconfig['noantilockout'] = isset($config['system']['webgui']['noantilockout']);
$pconfig['filteringbridge_enable'] = isset($config['bridge']['filteringbridge']);
$pconfig['tcpidletimeout'] = $config['filter']['tcpidletimeout'];
$pconfig['maximumstates'] = $config['system']['maximumstates'];
$pconfig['disablerendevouz'] = $config['system']['disablerendevouz'];
$pconfig['enableserial'] = $config['system']['enableserial'];
$pconfig['disablefirmwarecheck'] = isset($config['system']['disablefirmwarecheck']);
$pconfig['preferoldsa_enable'] = isset($config['ipsec']['preferoldsa']);
$pconfig['enablesshd'] = $config['system']['enablesshd'];
$pconfig['sshport'] = $config['system']['ssh']['port'];
$pconfig['sshdkeyonly'] = $config['system']['ssh']['sshdkeyonly'];
$pconfig['authorizedkeys'] = base64_decode($config['system']['ssh']['authorizedkeys']);
$pconfig['sharednet'] = $config['system']['sharednet'];
$pconfig['polling_enable'] = isset($config['system']['polling']);
$pconfig['bypassstaticroutes'] = isset($config['filter']['bypassstaticroutes']);
$pconfig['disablereplyto'] = isset($config['filter']['disablereplyto']);
$pconfig['disablenatreflection'] = $config['system']['disablenatreflection'];
$pconfig['disablechecksumoffloading'] = isset($config['system']['disablechecksumoffloading']);
$pconfig['disableglxsb'] = isset($config['system']['disableglxsb']);
$pconfig['disablescrub'] = isset($config['system']['disablescrub']);
$pconfig['shapertype']             = $config['system']['shapertype'];
$pconfig['lb_use_sticky'] = isset($config['system']['lb_use_sticky']);
$pconfig['disablevpnrules'] = isset($config['system']['disablevpnrules']);
$pconfig['nodnsrebindcheck'] = isset($config['system']['webgui']['nodnsrebindcheck']);

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
	if ($_POST['sshport'] <> "") {
		if( ! is_port($_POST['sshport'])) {
			$input_errors[] = "You must specify a valid port number";
		}
	}
	if($_POST['sshdkeyonly'] == "yes") {
		$config['system']['ssh']['sshdkeyonly'] = "enabled";
	} else {
		unset($config['system']['ssh']['sshdkeyonly']);
	}		
	$config['system']['ssh']['authorizedkeys'] = base64_encode($_POST['authorizedkeys']);

}

if ($_POST) {
    ob_flush();
    flush();
	if (!$input_errors) {
		if($_POST['disablefilter'] == "yes") {
			$config['system']['disablefilter'] = "enabled";
		} else {
			unset($config['system']['disablefilter']);
		}
		if($_POST['disablevpnrules'] == "yes") {
			$config['system']['disablevpnrules'] = true;
		} else {
			unset($config['system']['disablevpnrules']);
		}
		if($_POST['enablesshd'] == "yes") {
			$config['system']['enablesshd'] = "enabled";
			touch("{$g['tmp_path']}/start_sshd");
		} else {
			unset($config['system']['enablesshd']);
			mwexec("/usr/bin/killall sshd");
		}
		$oldsshport = $config['system']['ssh']['port'];
		$config['system']['ssh']['port'] = $_POST['sshport'];

		if($_POST['polling_enable'] == "yes") {
			$config['system']['polling'] = true;
			setup_polling();
		} else {
			unset($config['system']['polling']);
			setup_polling();
		}

		if ($_POST['nodnsrebindcheck'] == "yes")
			$config['system']['webgui']['nodnsrebindcheck'] = true;
		else
			unset($config['system']['webgui']['nodnsrebindcheck']);

		if($_POST['lb_use_sticky'] == "yes") {
			$config['system']['lb_use_sticky'] = true;
                        touch("/var/etc/use_pf_pool__stickyaddr");
                } else {
			unset($config['system']['lb_use_sticky']);
                        unlink_if_exists("/var/etc/use_pf_pool__stickyaddr");
                }

		if($config['interfaces']['wan']['ipaddr'] == "pppoe") 
			unset($config['system']['lb_use_sticky']);

		if($_POST['sharednet'] == "yes") {
			$config['system']['sharednet'] = true;
			system_disable_arp_wrong_if();
		} else {
			unset($config['system']['sharednet']);
			system_enable_arp_wrong_if();
		}

		if($_POST['scrubnodf'] == "yes")
			$config['system']['scrubnodf'] = "enabled";
		else
			unset($config['system']['scrubnodf']);

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
		if($_POST['ipv6allow'] == "yes") {
			$config['system']['ipv6allow'] = true;
		} else {
			unset($config['system']['ipv6allow']);
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
		$config['system']['maximumstates'] = $_POST['maximumstates'];

		if($_POST['enablesshd'] == "yes") {
			$config['system']['enablesshd'] = $_POST['enablesshd'];
		} else {
			unset($config['system']['enablesshd']);
		}

		if($_POST['disablechecksumoffloading'] == "yes") {
			$config['system']['disablechecksumoffloading'] = $_POST['disablechecksumoffloading'];
                        setup_microcode();
		} else {
			unset($config['system']['disablechecksumoffloading']);
                        setup_microcode();
		}
                
                if($_POST['disableglxsb'] == "yes") {
			$config['system']['disableglxsb'] = $_POST['disableglxsb'];
                        setup_glxsb();
		} else {
			unset($config['system']['disableglxsb']);
                        setup_glxsb();
		}

		if($_POST['disablescrub'] == "yes") {
			$config['system']['disablescrub'] = $_POST['disablescrub'];
		} else {
			unset($config['system']['disablescrub']);
		}

		if($_POST['disablenatreflection'] == "yes") {
			$config['system']['disablenatreflection'] = $_POST['disablenatreflection'];
		} else {
			unset($config['system']['disablenatreflection']);
		}
                
                if($_POST['disablereplyto'] == "yes") {
			$config['filter']['disablereplyto'] = $_POST['disablereplyto'];
		} else {
			unset($config['filter']['disablereplyto']);
		}

		// Traffic shaper
		$config['system']['shapertype'] = $_POST['shapertype'];
		
		$config['ipsec']['preferoldsa'] = $_POST['preferoldsa_enable'] ? true : false;
		$config['bridge']['filteringbridge'] = $_POST['filteringbridge_enable'] ? true : false;
		$config['filter']['bypassstaticroutes'] = $_POST['bypassstaticroutes'] ? true : false;

		write_config();

		$retval = 0;
		config_lock();
		$retval = filter_configure();
		if(stristr($retval, "error") <> true)
		    $savemsg = get_std_save_message($retval);
		else
		    $savemsg = $retval;

		config_unlock();

		conf_mount_rw();

		setup_serial_port();

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
				<br>
				<span class="vexpl">Note:  This will disable the internal video card/keyboard</span>
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
		<?php endif; ?>
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
			<td width="22%" valign="top" class="vncell">&nbsp;</td>
			<td width="78%" class="vtable">
				<input name="sshdkeyonly" type="checkbox" id="sshdkeyonly" value="yes" <?php if (isset($pconfig['sshdkeyonly'])) echo "checked"; ?> onclick="enable_change(false)" />
				<strong>Disable Password login for Secure Shell (KEY only)</strong>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">SSH port</td>
			<td width="78%" class="vtable">
				<input name="sshport" type="text" id="sshport" value="<?php echo $pconfig['sshport']; ?>" onclick="enable_change(false)" />
				<br />
				<span class="vexpl">Note:  Leave this blank for the default of 22</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Authorized keys");?></td>
			<td width="78%" class="vtable">
				<textarea name="authorizedkeys" cols="65" rows="7" id="authorizedkeys" class="formfld_cert"><?=htmlspecialchars($pconfig['authorizedkeys']);?></textarea>
				<br />
				Paste an authorized keys file here.
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
			<td colspan="2" valign="top" class="listtopic">IPv6</td>
		</tr>
       		<tr>
			<td width="22%" valign="top" class="vncell">Allow IPv6</td>
			<td width="78%" class="vtable">
				<input name="ipv6allow" type="checkbox" id="ipv6allow" value="yes" <?php if ($pconfig['ipv6allow']) echo "checked"; ?> onclick="enable_change(false)" />
				<strong>Allow IPv6 traffic</strong>
				<br /> <br />
				All IPv6 traffic will be blocked unless this box is checked.
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">IPv6 tunneling</td>
			<td width="78%" class="vtable">
				<input name="ipv6nat_enable" type="checkbox" id="ipv6nat_enable" value="yes" <?php if ($pconfig['ipv6nat_enable']) echo "checked"; ?> onclick="enable_change(false)" />
				<strong>NAT encapsulated IPv6 packets (IP protocol 41/RFC2893) to:</strong>
				<br /> <br />
				<input name="ipv6nat_ipaddr" type="text" class="formfld" id="ipv6nat_ipaddr" size="20" value="<?=htmlspecialchars($pconfig['ipv6nat_ipaddr']);?>" />
				&nbsp;(IP address)
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
			<td colspan="2" valign="top" class="listtopic">Filtering Bridge</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">&nbsp;</td>
			<td width="78%" class="vtable">
				<strong>Enable filtering bridge</strong>
				<span class="vexpl">
					<br>
					This setting no longer exists as it is unnecessary. Filtering
					occurs on the member interfaces of the bridge and cannot be
					disabled. 
				</span>
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
			<td colspan="2" valign="top" class="listtopic">Load Balancing</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell">Load Balancing</td>
			<td width="78%" class="vtable">
				<?php if($config['interfaces']['wan']['ipaddr'] == "pppoe"): ?>
					Sticky connections are disabled due to PPPoE being enabled.
				<?php else: ?>
					<input name="lb_use_sticky" type="checkbox" id="lb_use_sticky" value="yes" <?php if ($pconfig['lb_use_sticky']) echo "checked=\"checked\""; ?> />
				<?php endif; ?>
				<strong>Use sticky connections</strong>
				<br />
				<span class="vexpl">
				Successive connections will be redirected to the servers in a round-robin manner with connections from the same source being sent to the same web server. This "sticky connection" will exist as long as there are states that refer to this connection. Once the states expire, so will the sticky connection. Further connections from that host will be redirected to the next web server in the round robin.
				</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				<input type="submit" class="formbtn" value="<?=gettext("Save");?>" onclick="enable_change(true)" />
			</td>
		</tr>
		<tr>
			<td colspan="2" class="list" height="12">&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2" valign="top" class="listtopic">Miscellaneous</td>
		</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">DNS Rebind Check</td>
				<td width="78%" class="vtable">
					<input name="nodnsrebindcheck" type="checkbox" id="nodnsrebindcheck" value="yes" <?php if ($pconfig['nodnsrebindcheck']) echo "checked"; ?> />
					<strong>Disable webConfigurator DNS Rebinding Checks</strong>
					<br/>
					When this is unchecked, access to the webConfigurator 
					is protected against <a href=\"http://en.wikipedia.org/wiki/DNS_rebinding\">DNS Rebinding attacks</a>
					Check this box to disable this protection if you find that it interferes with
					webConfigurator access in certain corner cases.
				</td>
			</tr>
			<tr>
			  <td width="22%" valign="top" class="vncell">Device polling</td>
			  <td width="78%" class="vtable">
			    <input name="polling_enable" type="checkbox" id="polling_enable" value="yes" <?php if ($pconfig['polling_enable']) echo "checked"; ?>>
			    <strong>Use device polling</strong>
				<br>
				Device polling is a technique that lets the system periodically poll network devices for new data instead of relying on interrupts. This prevents your webGUI, SSH, etc. from being inaccessible due to interrupt floods when under extreme load. Generally this is not recommended.
				Not all NICs support polling; see the <?=$g['product_name'];?> homepage for a list of supported cards.
			  </td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">Console menu </td>
				<td width="78%" class="vtable">
					<input name="disableconsolemenu" type="checkbox" id="disableconsolemenu" value="yes" <?php if ($pconfig['disableconsolemenu']) echo "checked"; ?>  />
					<strong>Password protect the console menu</strong>
					<br />
					<span class="vexpl">Changes to this option will take effect after a reboot.</span>
				</td>
			</tr>
<?php if($g['platform'] == "pfSenseDISABLED"): ?>
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
<?php endif; ?>
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
				<td width="22%" valign="top" class="vncell">Static route filtering</td>
				<td width="78%" class="vtable">
					<input name="bypassstaticroutes" type="checkbox" id="bypassstaticroutes" value="yes" <?php if ($pconfig['bypassstaticroutes']) echo "checked"; ?> />
					<strong>Bypass firewall rules for traffic on the same interface</strong>
					<br />
					This option only applies if you have defined one or more static routes. If it is enabled, traffic that enters and
	 				leaves through the same interface will not be checked by the firewall. This may be desirable in some situations where
					multiple subnets are connected to the same interface.
					<br />
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
				<td width="22%" valign="top" class="vncell">FTP RFC 959 data port violation workaround</td>
				<td width="78%" class="vtable">
					<input name="rfc959workaround" type="checkbox" id="rfc959workaround" value="yes" <?php if (isset($config['system']['rfc959workaround'])) echo "checked"; ?> onclick="enable_change(false)" />
					<strong class="vexpl">Workaround for sites that violate RFC 959 which specifies that the data connection be sourced from the command port - 1 (typically port 20).  This workaround doesn't expose you to any extra risk as the firewall will still only allow connections on a port that the ftp-proxy is listening on.</strong>
					<br />
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">Clear DF bit instead of dropping</td>
				<td width="78%" class="vtable">
					<input name="scrubnodf" type="checkbox" id="scrubnodf" value="yes" <?php if (isset($config['system']['scrubnodf'])) echo "checked"; ?> onclick="enable_change(false)" />
					<strong class="vexpl">Workaround for operating systems that generate fragmented packets with the don't fragment (DF) bit set.  Linux NFS is known to do this.  This will cause the filter to not drop such packets but instead clear the don't fragment bit.  The filter will also randomize the IP identification field of outgoing packets with this option on, to compensate for operating systems that set the DF bit but set a zero IP identification header field.</strong>
					<br />
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
					<textarea cols="60" rows="2" id="info" name="info"style="padding:5px; border:1px dashed #990000; background-color: #ffffff; color: #000000; font-size: 8pt;"></textarea>
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
					<strong>Disable all packet filtering.</strong>
					<br />
					<span class="vexpl">Note:  This converts <?=$g['product_name'];?> into a routing only platform!<br>
					                    Note:  This will turn off NAT!
					</span>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">Disable Firewall Scrub</td>
				<td width="78%" class="vtable">
					<input name="disablescrub" type="checkbox" id="disablescrub" value="yes" <?php if (isset($config['system']['disablescrub'])) echo "checked"; ?> onclick="enable_change(false)" />
					<strong>Disables the PF scrubbing option which can sometimes interfere with NFS and PPTP traffic.</strong>
					<br/>
					Click <a href='http://www.openbsd.org/faq/pf/scrub.html' target='_new'>here</a> for more information.
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
				<td width="22%" valign="top" class="vncell">Disable Auto-added VPN rules</td>
				<td width="78%" class="vtable">
					<input name="disablevpnrules" type="checkbox" id="disablevpnrules" value="yes" <?php if (isset($config['system']['disablevpnrules'])) echo "checked"; ?> onclick="enable_change(false)" />
					<strong>Disable all auto-added VPN rules.</strong>
					<br />
					<span class="vexpl">Note:  This disables automatically added rules for IPsec, PPTP, and OpenVPN. 
					</span>
				</td>
			</tr>
	                <tr>
				<td width="22%" valign="top" class="vncell">Disable reply-to</td>
				<td width="78%" class="vtable">
					<input name="disablereplyto" type="checkbox" id="disablereplyto" value="yes" <?php if ($pconfig['disablereplyto']) echo "checked"; ?> />
					<strong>Disable reply-to on WAN rules</strong>
					<br />
					With Multi-WAN you generally want to ensure traffic leaves the same interface it arrives on, hence reply-to is added automatically by default.
	                                When using bridging, you must disable this behavior if the WAN gateway IP is different from the gateway IP of the hosts behind the bridged interface. 
					<br />
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top">&nbsp;</td>
				<td width="78%"><input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)" /></td>
			</tr>
			<tr>
				<td colspan="2" class="list" height="12">&nbsp;</td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="listtopic">Network Address Translation</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">Disable NAT Reflection</td>
				<td width="78%" class="vtable">
					<input name="disablenatreflection" type="checkbox" id="disablenatreflection" value="yes" <?php if (isset($config['system']['disablenatreflection'])) echo "checked"; ?> onclick="enable_change(false)" />
					<strong>Disables the automatic creation of NAT redirect rules for access to your public IP addresses from within your internal networks.  Note: Reflection only works on port forward type items  and does not work for large ranges > 500 ports.</strong>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top">&nbsp;</td>
				<td width="78%"><input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)" /></td>
			</tr>
			<tr>
				<td colspan="2" class="list" height="12">&nbsp;</td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="listtopic">Hardware Options</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">Disable Hardware Checksum Offloading</td>
				<td width="78%" class="vtable">
					<input name="disablechecksumoffloading" type="checkbox" id="disablechecksumoffloading" value="yes" <?php if (isset($config['system']['disablechecksumoffloading'])) echo "checked"; ?> onclick="enable_change(false)" />
					<strong>Checking this option will disable hardware checksum offloading.  Checksum offloading is broken in some hardware, particularly some Realtek cards. Rarely, drivers may have problems with checksum offloading and some specific NICs.</strong>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell">Disable glxsb loading</td>
				<td width="78%" class="vtable">
					<input name="disableglxsb" type="checkbox" id="disableglxsb" value="yes" <?php if (isset($config['system']['disableglxsb'])) echo "checked"; ?> onclick="enable_change(false)" />
					<span class="vexpl"><strong>Checking this option will disable loading of the glxsb driver.</strong></span>
					<br>
					<span>The glxsb crypto accelerator is found on some Geode platforms (PC Engines ALIX among others).  When using a better crypto card such as a Hifn, you will want to disable the glxsb. <strong>If this device is currently in use, YOU MUST REBOOT for it to be unloaded.</strong></span>
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
//-->
</script>

<?php include("fend.inc"); ?>

</body>
</html>

<?php

if($_POST['cert'] || $_POST['key']) {
	if (($config['system']['webgui']['certificate'] != $oldcert)
			|| ($config['system']['webgui']['private-key'] != $oldkey)) {
	    ob_flush();
	    flush();
	    log_error("webConfigurator certificates have changed.  Restarting webConfigurator.");
	    sleep(1);
		touch("/tmp/restart_webgui");
	}
}

?>