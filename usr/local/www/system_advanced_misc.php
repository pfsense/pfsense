<?php
/* $Id$ */
/*
	system_advanced_misc.php
	part of pfSense
	Copyright (C) 2005-2007 Scott Ullrich

	Copyright (C) 2008 Shrew Soft Inc

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
/*
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-system-advanced-misc
##|*NAME=System: Advanced: Miscellaneous page
##|*DESCR=Allow access to the 'System: Advanced: Miscellaneous' page.
##|*MATCH=system_advanced.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("ipsec.inc");
require_once("vpn.inc");

$pconfig['proxyurl'] = $config['system']['proxyurl'];
$pconfig['proxyport'] = $config['system']['proxyport'];
$pconfig['proxyuser'] = $config['system']['proxyuser'];
$pconfig['proxypass'] = $config['system']['proxypass'];
$pconfig['harddiskstandby'] = $config['system']['harddiskstandby'];
$pconfig['lb_use_sticky'] = isset($config['system']['lb_use_sticky']);
$pconfig['gw_switch_default'] = isset($config['system']['gw_switch_default']);
$pconfig['preferoldsa_enable'] = isset($config['ipsec']['preferoldsa']);
$pconfig['racoondebug_enable'] = isset($config['ipsec']['racoondebug']);
$pconfig['maxmss_enable'] = isset($config['system']['maxmss_enable']);
$pconfig['maxmss'] = $config['system']['maxmss'];
$pconfig['powerd_enable'] = isset($config['system']['powerd_enable']);
$pconfig['glxsb_enable'] = isset($config['system']['glxsb_enable']);
$pconfig['schedule_states'] = isset($config['system']['schedule_states']);
$pconfig['kill_states'] = isset($config['system']['kill_states']);

if ($_POST) {

    unset($input_errors);
    $pconfig = $_POST;

	ob_flush();
	flush();

	if (!$input_errors) {

		if($_POST['harddiskstandby'] <> "") {
			$config['system']['harddiskstandby'] = $_POST['harddiskstandby'];
			system_set_harddisk_standby();
		} else
			unset($config['system']['harddiskstandby']);

		if($_POST['proxyurl'] <> "")
			$config['system']['proxyurl'] = $_POST['proxyurl'];
		else
			unset($config['system']['proxyurl']);

		if($_POST['proxyport'] <> "")
			$config['system']['proxyport'] = $_POST['proxyport'];
		else
			unset($config['system']['proxyport']);

		if($_POST['proxyuser'] <> "")
			$config['system']['proxyuser'] = $_POST['proxyuser'];
		else
			unset($config['system']['proxyuser']);

		if($_POST['proxypass'] <> "")
			$config['system']['proxypass'] = $_POST['proxypass'];
		else
			unset($config['system']['proxypass']);

		if($_POST['lb_use_sticky'] == "yes")
			$config['system']['lb_use_sticky'] = true;
		else
			unset($config['system']['lb_use_sticky']);

		if($_POST['gw_switch_default'] == "yes")
			$config['system']['gw_switch_default'] = true;
		else
			unset($config['system']['gw_switch_default']);

		if($_POST['preferoldsa_enable'] == "yes")
			$config['ipsec']['preferoldsa'] = true;
		elseif (isset($config['ipsec']['preferoldsa']))
			unset($config['ipsec']['preferoldsa']);

		$need_racoon_restart = false;
		if($_POST['racoondebug_enable'] == "yes") {
			if (!isset($config['ipsec']['racoondebug'])) {
				$config['ipsec']['racoondebug'] = true;
				$need_racoon_restart = true;
			}
		} else {
			if (isset($config['ipsec']['racoondebug'])) {
				unset($config['ipsec']['racoondebug']);
				$need_racoon_restart = true;
			}
		}

		if($_POST['maxmss_enable'] == "yes") {
                        $config['system']['maxmss_enable'] = true;
			$config['system']['maxmss'] = $_POST['maxmss'];
                } else {
                        unset($config['system']['maxmss_enable']);
                        unset($config['system']['maxmss']);
		}

		if($_POST['powerd_enable'] == "yes")
                        $config['system']['powerd_enable'] = true;
                else
                        unset($config['system']['powerd_enable']);

		if($_POST['glxsb_enable'] == "yes")
                        $config['system']['glxsb_enable'] = true;
                else
                        unset($config['system']['glxsb_enable']);

		if($_POST['schedule_states'] == "yes")
                        $config['system']['schedule_states'] = true;
                else
                        unset($config['system']['schedule_states']);

		if($_POST['kill_states'] == "yes")
                        $config['system']['kill_states'] = true;
                else
                        unset($config['system']['kill_states']);

		write_config();

		$retval = 0;
		system_resolvconf_generate(true);
		$retval = filter_configure();
		if(stristr($retval, "error") <> true)
		    $savemsg = get_std_save_message(gettext($retval));
		else
		    $savemsg = gettext($retval);
		
		activate_powerd();
		load_glxsb();
		vpn_ipsec_configure_preferoldsa();
		if ($need_racoon_restart)
			vpn_ipsec_force_reload();
	}
}

$pgtitle = array(gettext("System"),gettext("Advanced: Miscellaneous"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
	include("fbegin.inc");
	if ($input_errors)
		print_input_errors($input_errors);
	if ($savemsg)
		print_info_box($savemsg);
?>
<script type="text/javascript" >
function maxmss_checked(obj) {
	if (obj.checked)
		jQuery('#maxmss').attr('disabled',false);
	else
		jQuery('#maxmss').attr('disabled','true');
}
</script>
	<form action="system_advanced_misc.php" method="post" name="iform" id="iform">
		<table width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr>
				<td>
					<?php
						$tab_array = array();
						$tab_array[] = array(gettext("Admin Access"), false, "system_advanced_admin.php");
						$tab_array[] = array(gettext("Firewall / NAT"), false, "system_advanced_firewall.php");
						$tab_array[] = array(gettext("Networking"), false, "system_advanced_network.php");
						$tab_array[] = array(gettext("Miscellaneous"), true, "system_advanced_misc.php");
						$tab_array[] = array(gettext("System Tunables"), false, "system_advanced_sysctl.php");
						$tab_array[] = array(gettext("Notifications"), false, "system_advanced_notifications.php");
						display_top_tabs($tab_array);
					?>
				</td>
			</tr>
			<tr>
				<td id="mainarea">
					<div class="tabcont">
						<span class="vexpl">
							<span class="red">
								<strong><?=gettext("NOTE:"); ?>&nbsp</strong>
							</span>
							<?=gettext("The options on this page are intended for use by advanced users only."); ?>
							<br/>
						</span>
						<br/>
						<table width="100%" border="0" cellpadding="6" cellspacing="0">
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("Proxy support"); ?></td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Proxy URL"); ?></td>
								<td width="78%" class="vtable">
									<input name="proxyurl" id="proxyurl" value="<?php if ($pconfig['proxyurl'] <> "") echo $pconfig['proxyurl']; ?>" class="formfld unknown">
									<br />
									<?php printf(gettext("Proxy url for allowing %s to use this proxy to connect outside."),$g['product']); ?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Proxy Port"); ?></td>
								<td width="78%" class="vtable">
									<input name="proxyport" id="proxyport" value="<?php if ($pconfig['proxyport'] <> "") echo $pconfig['proxyport']; ?>" class="formfld unknown">
									<br />
									<?php printf(gettext("Proxy url for allowing %s to use this proxy port to connect outside. Default is 8080 for http protocol or ssl for 443."),$g['product']); ?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Proxy Username"); ?></td>
								<td width="78%" class="vtable">
									<input name="proxyuser" id="proxyuser" value="<?php if ($pconfig['proxyuser'] <> "") echo $pconfig['proxyuser']; ?>" class="formfld unknown">
									<br />
									<?php printf(gettext("Proxy username for allowing %s to use this proxy to connect outside"),$g['product']); ?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Proxy Pass"); ?></td>
								<td width="78%" class="vtable">
									<input type="password" name="proxypass" id="proxypass" value="<?php if ($pconfig['proxypass'] <> "") echo $pconfig['proxypass']; ?>" class="formfld unknown">
									<br />
									<?php printf(gettext("Proxy password for allowing %s to use this proxy to connect outside"),$g['product']); ?>
								</td>
							</tr>
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("Load Balancing"); ?></td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Load Balancing"); ?></td>
								<td width="78%" class="vtable">
									<input name="lb_use_sticky" type="checkbox" id="lb_use_sticky" value="yes" <?php if ($pconfig['lb_use_sticky']) echo "checked=\"checked\""; ?> />
									<strong><?=gettext("Use sticky connections"); ?></strong><br/>
									<?=gettext("Successive connections will be redirected to the servers " .
									"in a round-robin manner with connections from the same " .
									"source being sent to the same web server. This 'sticky " .
									"connection' will exist as long as there are states that " .
									"refer to this connection. Once the states expire, so will " .
									"the sticky connection. Further connections from that host " .
									"will be redirected to the next web server in the round " .
									"robin."); ?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Load Balancing"); ?></td>
								<td width="78%" class="vtable">
									<input name="gw_switch_default" type="checkbox" id="gw_switch_default" value="yes" <?php if ($pconfig['gw_switch_default']) echo "checked=\"checked\""; ?> />
									<strong><?=gettext("Allow default gateway switching"); ?></strong><br/>
									<?=gettext("If the link where the default gateway resides fails " .
									"switch the default gateway to another available one."); ?>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("Power savings"); ?></td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("PowerD"); ?></td>
								<td width="78%" class="vtable">
									<input name="powerd_enable" type="checkbox" id="powerd_enable" value="yes" <?php if ($pconfig['powerd_enable']) echo "checked"; ?> />
									<strong><?=gettext("Use PowerD"); ?></strong><br/>
									<br />
								     <?=gettext("The powerd utility monitors the system state and sets various power control " .
								     "options accordingly. It offers three modes (maximum, minimum, and " .
								     "adaptive) that can be individually selected while on AC power or batteries. " . 
								     "The modes maximum, minimum, and adaptive may be abbreviated max, " .
								     "min, adp.   Maximum mode chooses the highest performance values.  Minimum " .
								     "mode selects the lowest performance values to get the most power savings. " .
								     "Adaptive mode attempts to strike a balance by degrading performance when " .
								     "the system appears idle and increasing it when the system is busy.  It " .
								     "offers a good balance between a small performance loss for greatly " .
								     "increased power savings.  The default mode for {$g['product_name']} is adaptive."); ?>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("glxsb Crypto Acceleration"); ?></td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("glxsb"); ?></td>
								<td width="78%" class="vtable">
									<input name="glxsb_enable" type="checkbox" id="glxsb_enable" value="yes" <?php if ($pconfig['glxsb_enable']) echo "checked"; ?> />
									<strong><?=gettext("Use glxsb"); ?></strong><br/>
									<br />
								     <?=gettext("The AMD Geode LX Security Block will accelerate some cryptographic functions " .
								     "on systems which have the chip. Do not enable this option if you have a " .
								     "Hifn cryptographic acceleration card, as this will take precedence and the " .
								     "Hifn card will not be used. Acceleration should be automatic for IPsec " .
								     "when using Rijndael (AES). OpenVPN should be set for AES-128-CBC."); ?>
								     <br/><br/>
								     <?=gettext("If you do not have a glxsb chip in your system, this option will have no " .
								     "effect. To unload the module, uncheck this option and then reboot."); ?>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("IP Security"); ?></td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Security Associations"); ?></td>
								<td width="78%" class="vtable">
									<input name="preferoldsa_enable" type="checkbox" id="preferoldsa_enable" value="yes" <?php if ($pconfig['preferoldsa_enable']) echo "checked"; ?> />
									<strong><?=gettext("Prefer older IPsec SAs"); ?></strong>
									<br />
									<?=gettext("By default, if several SAs match, the newest one is " .
									"preferred if it's at least 30 seconds old. Select this " .
									"option to always prefer old SAs over new ones."); ?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("IPsec Debug"); ?></td>
								<td width="78%" class="vtable">
									<input name="racoondebug_enable" type="checkbox" id="racoondebug_enable" value="yes" <?php if ($pconfig['racoondebug_enable']) echo "checked"; ?> />
									<strong><?=gettext("Start racoon in debug mode"); ?></strong>
									<br />
									<?=gettext("Launches racoon in debug mode so that more verbose logs " .
									"will be generated to aid in troubleshooting."); ?><br/>
									<?=gettext("NOTE: Changing this setting will restart racoon."); ?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Maximum MSS"); ?></td>
								<td width="78%" class="vtable">
									<input name="maxmss_enable" type="checkbox" id="maxmss_enable" value="yes" <?php if ($pconfig['maxmss_enable'] == true) echo "checked"; ?> onClick="maxmss_checked(this)" />
									<strong><?=gettext("Enable MSS clamping on VPN traffic"); ?></strong>
									<br />
									<input name="maxmss" id="maxmss" value="<?php if ($pconfig['maxmss'] <> "") echo $pconfig['maxmss']; else "1400"; ?>" class="formfld unknown" <?php if ($pconfig['maxmss_enable'] == false) echo "disabled"; ?>>
									<br />
									<?=gettext("Enable MSS clamping on TCP flows over VPN. " .
									"This helps overcome problems with PMTUD on IPsec VPN links. If left blank, the default value is 1400 bytes. "); ?>
								</td>
							</tr>
                                                        <tr>
                                                                <td colspan="2" class="list" height="12">&nbsp;</td>
                                                        </tr>
                                                        <tr>
                                                                <td colspan="2" valign="top" class="listtopic"><?=gettext("Schedules"); ?></td>
                                                        </tr>
                                                        <tr>
                                                                <td width="22%" valign="top" class="vncell"><?=gettext("Schedule States"); ?></td>
                                                                <td width="78%" class="vtable">
                                                                        <input name="schedule_states" type="checkbox" id="schedule_states" value="yes" <?php if ($pconfig['schedule_states']) echo "checked"; ?> />
                                                                        <br />
									<?=gettext("By default schedules clear the states of existing connections when expiry time has come. ".
									"This option allows to override this setting by not clearing states for existing connections."); ?>
                                                                </td>
                                                        </tr>
                                                        <tr>
                                                                <td colspan="2" class="list" height="12">&nbsp;</td>
                                                        </tr>
                                                        <tr>
                                                                <td colspan="2" valign="top" class="listtopic"><?=gettext("Gateway Monitoring"); ?></td>
                                                        </tr>
                                                        <tr>
                                                                <td width="22%" valign="top" class="vncell"><?=gettext("States"); ?></td>
                                                                <td width="78%" class="vtable">
                                                                        <input name="kill_states" type="checkbox" id="kill_states" value="yes" <?php if ($pconfig['kill_states']) echo "checked"; ?> />
                                                                        <br />
									<?=gettext("By default the monitoring process will flush states for a gateway that goes down. ".
									"This option allows to override this setting by not clearing states for existing connections."); ?>
                                                                </td>
                                                        </tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<?php if($g['platform'] == "pfSenseDISABLED"): ?>
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("Hardware Settings"); ?></td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Hard disk standby time "); ?></td>
								<td width="78%" class="vtable">
									<select name="harddiskstandby" class="formselect">
										<?php
										 	## Values from ATA-2 http://www.t13.org/project/d0948r3-ATA-2.pdf (Page 66)
											$sbvals = explode(" ", "0.5,6 1,12 2,24 3,36 4,48 5,60 7.5,90 10,120 15,180 20,240 30,241 60,242");
										?>
										<option value="" <?php if(!$pconfig['harddiskstandby']) echo('selected');?>><?=gettext("Always on"); ?></option>
										<?php
											foreach ($sbvals as $sbval):
												list($min,$val) = explode(",", $sbval);
										?>
										<option value="<?=$val;?>" <?php if($pconfig['harddiskstandby'] == $val) echo('selected');?>><?=$min;?> <?=gettext("minutes"); ?></option>
										<?php endforeach; ?>
									</select>
									<br/>
									<?=gettext("Puts the hard disk into standby mode when the selected amount of time after the last ".
									"access has elapsed."); ?> <em><?=gettext("Do not set this for CF cards."); ?></em>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<?php endif; ?>

							<tr>
								<td width="22%" valign="top">&nbsp;</td>
								<td width="78%">
									<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
								</td>
							</tr>
						</table>
					</div>
				</td>
			</tr>
		</table>
	</form>

<?php include("fend.inc"); ?>
</body>
</html>

