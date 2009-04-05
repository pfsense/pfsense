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

##|+PRIV
##|*IDENT=page-system-advanced-misc
##|*NAME=System: Advanced: Miscellaneous page
##|*DESCR=Allow access to the 'System: Advanced: Miscellaneous' page.
##|*MATCH=system_advanced.php*
##|-PRIV


require("guiconfig.inc");

$pconfig['harddiskstandby'] = $config['system']['harddiskstandby'];
$pconfig['lb_use_sticky'] = isset($config['system']['lb_use_sticky']);
$pconfig['preferoldsa_enable'] = isset($config['ipsec']['preferoldsa']);
$pconfig['powerd_enable'] = isset($config['system']['powerd_enable']);

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

		if($_POST['lb_use_sticky'] == "yes")
			$config['system']['lb_use_sticky'] = true;
		else
			unset($config['system']['lb_use_sticky']);

		$config['ipsec']['preferoldsa'] = $_POST['preferoldsa_enable'] ? true : false;
		$config['system']['powerd_enable'] = $_POST['powerd_enable'] ? true : false;

		write_config();

		$retval = 0;
		config_lock();
		$retval = filter_configure();
		if(stristr($retval, "error") <> true)
		    $savemsg = get_std_save_message($retval);
		else
		    $savemsg = $retval;
		config_unlock();
		
		activate_powerd();
	}
}

$pgtitle = array("System","Advanced: Miscellaneous");
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
	<form action="system_advanced_misc.php" method="post" name="iform" id="iform">
		<table width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr>
				<td>
					<?php
						$tab_array = array();
						$tab_array[] = array("Admin Access", false, "system_advanced_admin.php");
						$tab_array[] = array("Firewall / NAT", false, "system_advanced_firewall.php");
						$tab_array[] = array("Networking", false, "system_advanced_network.php");
						$tab_array[] = array("Miscellaneous", true, "system_advanced_misc.php");
						$tab_array[] = array("System Tunables", false, "system_advanced_sysctl.php");
						display_top_tabs($tab_array);
					?>
				</td>
			</tr>
			<tr>
				<td id="mainarea">
					<div class="tabcont">
						<span class="vexpl">
							<span class="red">
								<strong>NOTE:&nbsp</strong>
							</span>
							The options on this page are intended for use by advanced users only.
							<br/>
						</span>
						<br/>
						<table width="100%" border="0" cellpadding="6" cellspacing="0">
							<tr>
								<td colspan="2" valign="top" class="listtopic">Load Balancing</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Load Balancing</td>
								<td width="78%" class="vtable">
									<input name="lb_use_sticky" type="checkbox" id="lb_use_sticky" value="yes" <?php if ($pconfig['lb_use_sticky']) echo "checked=\"checked\""; ?> />
									<strong>Use sticky connections</strong><br/>
									Successive connections will be redirected to the servers
									in a round-robin manner with connections from the same
									source being sent to the same web server. This "sticky
									connection" will exist as long as there are states that
									refer to this connection. Once the states expire, so will
									the sticky connection. Further connections from that host
									will be redirected to the next web server in the round
									robin.
								</td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<tr>
								<td colspan="2" valign="top" class="listtopic">Power savings</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">PowerD</td>
								<td width="78%" class="vtable">
									<input name="powerd_enable" type="checkbox" id="powerd_enable" value="yes" <?php if ($pconfig['powerd_enable']) echo "checked"; ?> />
									<br />
								     The powerd utility monitors the system state and sets various power con-
								     trol options accordingly.	It offers three modes (maximum, minimum, and
								     adaptive) that can be individually selected while on AC power or batter-
								     ies.  The modes maximum, minimum, and adaptive may be abbreviated max,
								     min, adp.   Maximum mode chooses the highest performance values.  Minimum 
								     mode selects the lowest performance values to get the most power savings.
								     Adaptive mode attempts to strike a balance by degrading performance when
								     the system appears idle and increasing it when the system is busy.  It
								     offers a good balance between a small performance loss for greatly
								     increased power savings.  The default mode for pfSense is adaptive.
								</td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<tr>
								<td colspan="2" valign="top" class="listtopic">IP Security</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Security Assocications</td>
								<td width="78%" class="vtable">
									<input name="preferoldsa_enable" type="checkbox" id="preferoldsa_enable" value="yes" <?php if ($pconfig['preferoldsa_enable']) echo "checked"; ?> />
									<strong>Prefer older IPsec SAs</strong>
									<br />
									By default, if several SAs match, the newest one is
									preferred if it's at least 30 seconds old. Select this
									option to always prefer old SAs over new ones.
								</td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<?php if($g['platform'] == "pfSenseDISABLED"): ?>
							<tr>
								<td colspan="2" valign="top" class="listtopic">Hardware Settings</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Hard disk standby time </td>
								<td width="78%" class="vtable">
									<select name="harddiskstandby" class="formselect">
										<?php
										 	## Values from ATA-2 http://www.t13.org/project/d0948r3-ATA-2.pdf (Page 66)
											$sbvals = explode(" ", "0.5,6 1,12 2,24 3,36 4,48 5,60 7.5,90 10,120 15,180 20,240 30,241 60,242");
										?>
										<option value="" <?php if(!$pconfig['harddiskstandby']) echo('selected');?>>Always on</option>
										<?php
											foreach ($sbvals as $sbval):
												list($min,$val) = explode(",", $sbval);
										?>
										<option value="<?=$val;?>" <?php if($pconfig['harddiskstandby'] == $val) echo('selected');?>><?=$min;?> minutes</option>
										<?php endforeach; ?>
									</select>
									<br/>
									Puts the hard disk into standby mode when the selected amount of time after the last
									access has elapsed. <em>Do not set this for CF cards.</em>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<?php endif; ?>

							<tr>
								<td width="22%" valign="top">&nbsp;</td>
								<td width="78%">
									<input name="Submit" type="submit" class="formbtn" value="Save" />
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

