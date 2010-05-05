<?php
/* $Id$ */
/*
	system_advanced_firewall.php
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
##|*IDENT=page-system-advanced-firewall
##|*NAME=System: Advanced: Firewall and NAT page
##|*DESCR=Allow access to the 'System: Advanced: Firewall and NAT' page.
##|*MATCH=system_advanced.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

$pconfig['disablefilter'] = $config['system']['disablefilter'];
$pconfig['rfc959workaround'] = $config['system']['rfc959workaround'];
$pconfig['scrubnodf'] = $config['system']['scrubnodf'];
$pconfig['scrubrnid'] = $config['system']['scrubrnid'];
$pconfig['tcpidletimeout'] = $config['filter']['tcpidletimeout'];
$pconfig['optimization'] = $config['filter']['optimization'];
$pconfig['maximumstates'] = $config['system']['maximumstates'];
$pconfig['disablenatreflection'] = $config['system']['disablenatreflection'];
if (!isset($config['system']['enablebinatreflection']))
	$pconfig['disablebinatreflection'] = "yes";
else
	$pconfig['disablebinatreflection'] = "";
$pconfig['bypassstaticroutes'] = isset($config['filter']['bypassstaticroutes']);
$pconfig['disablescrub'] = isset($config['system']['disablescrub']);
$pconfig['tftpinterface'] = $config['system']['tftpinterface']; 

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['maximumstates'] && !is_numericint($_POST['maximumstates'])) {
		$input_errors[] = "The Firewall Maximum States value must be an integer.";
	}
	if ($_POST['tcpidletimeout'] && !is_numericint($_POST['tcpidletimeout'])) {
		$input_errors[] = "The TCP idle timeout must be an integer.";
	}

    ob_flush();
    flush();

	if (!$input_errors) {

		if($_POST['disablefilter'] == "yes")
			$config['system']['disablefilter'] = "enabled";
		else
			unset($config['system']['disablefilter']);

		if($_POST['rfc959workaround'] == "yes")
			$config['system']['rfc959workaround'] = "enabled";
		else
			unset($config['system']['rfc959workaround']);

		if($_POST['scrubnodf'] == "yes")
			$config['system']['scrubnodf'] = "enabled";
		else
			unset($config['system']['scrubnodf']);

		if($_POST['scrubrnid'] == "yes")
                        $config['system']['scrubrnid'] = "enabled";
                else
                        unset($config['system']['scrubrnid']);

		$config['system']['optimization'] = $_POST['optimization'];
		$config['system']['maximumstates'] = $_POST['maximumstates'];

		if($_POST['disablenatreflection'] == "yes")
			$config['system']['disablenatreflection'] = $_POST['disablenatreflection'];
		else
			unset($config['system']['disablenatreflection']);

		if($_POST['disablebinatreflection'] == "yes")
			unset($config['system']['enablebinatreflection']);
		else
			$config['system']['enablebinatreflection'] = "yes";

		if($_POST['bypassstaticroutes'] == "yes")
			$config['filter']['bypassstaticroutes'] = $_POST['bypassstaticroutes'];
		else
			unset($config['filter']['bypassstaticroutes']);

		if($_POST['disablescrub'] == "yes")
			$config['system']['disablescrub'] = $_POST['disablescrub'];
		else
			unset($config['system']['disablescrub']);

		if ($_POST['tftpinterface'])
			$config['system']['tftpinterface'] = implode(",", $_POST['tftpinterface']);
		else
			unset($config['system']['tftpinterface']);
	
		write_config();

		/* 
		 * XXX: This is a kludge here but its the better place than on every filter reload.
		 * NOTE: This is only for setting the ipfw state limits. 
		 */
		if ($_POST['maximumstates'] && is_numeric($_POST['maximumstates']) && is_module_loaded("ipfw.ko"))
			filter_load_ipfw();
			
		$retval = 0;
		$retval = filter_configure();
		if(stristr($retval, "error") <> true)
		    $savemsg = get_std_save_message($retval);
		else
		    $savemsg = $retval;
	}
}

$pgtitle = array("System","Advanced: Firewall and NAT");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>

<script language="JavaScript">
<!--

var descs=new Array(5);
descs[0]="as the name says, it's the normal optimization algorithm";
descs[1]="used for high latency links, such as satellite links.  Expires idle connections later than default";
descs[2]="expires idle connections quicker. More efficient use of CPU and memory but can drop legitimate connections";
descs[3]="tries to avoid dropping any legitimate connections at the expense of increased memory usage and CPU utilization.";

function update_description(itemnum) {
        document.forms[0].info.value=descs[itemnum];

}

//-->
</script>

<?php
	if ($input_errors)
		print_input_errors($input_errors);
	if ($savemsg)
		print_info_box($savemsg);
?>
	<form action="system_advanced_firewall.php" method="post" name="iform" id="iform">
		<table width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr>
				<td class="tabnavtbl">
					<?php
						$tab_array = array();
						$tab_array[] = array("Admin Access", false, "system_advanced_admin.php");
						$tab_array[] = array("Firewall / NAT", true, "system_advanced_firewall.php");
						$tab_array[] = array("Networking", false, "system_advanced_network.php");
						$tab_array[] = array("Miscellaneous", false, "system_advanced_misc.php");
						$tab_array[] = array("System Tunables", false, "system_advanced_sysctl.php");
						$tab_array[] = array("Notifications", false, "system_advanced_notifications.php");						
						display_top_tabs($tab_array);
					?>
				</ul>
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
								<td colspan="2" valign="top" class="listtopic">Firewall Advanced</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">IP Do-Not-Fragment compatibility</td>
								<td width="78%" class="vtable">
									<input name="scrubnodf" type="checkbox" id="scrubnodf" value="yes" <?php if (isset($config['system']['scrubnodf'])) echo "checked"; ?> />
									<strong>Clear invalid DF bits instead of dropping the packets</strong><br/>
									This allows for communications with hosts that generate fragmented
									packets with the don't fragment (DF) bit set. Linux NFS is known to
									do this. This will cause the filter to not drop such packets but
									instead clear the don't fragment bit. 
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">IP Random id generation</td>
								<td width="78%" class="vtable">
									<input name="scrubrnid" type="checkbox" id="scrubnodf" value="yes" <?php if (isset($config['system']['scrubrnid'])) echo "checked"; ?> />
									<strong>Insert a stronger id into IP header of packets passing through the filter.</strong><br/>
									Replaces the IP identification field of packets with random values to
									compensate for operating systems that use predicatable values.
									This option only applies to packets that are not fragmented after the
									optional packet reassembly.
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
									<br/>
									<textarea readonly="yes" cols="60" rows="1" id="info" name="info"style="padding:5px; border:1px dashed #990000; background-color: #ffffff; color: #000000; font-size: 8pt;"></textarea>
									<script language="javascript" type="text/javascript">
										update_description(document.forms[0].optimization.selectedIndex);
									</script>
									<br/>
									Select the type of state table optimization to use
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Disable Firewall</td>
								<td width="78%" class="vtable">
									<input name="disablefilter" type="checkbox" id="disablefilter" value="yes" <?php if (isset($config['system']['disablefilter'])) echo "checked"; ?> />
									<strong>Disable all packet filtering.</strong>
									<br/>
									<span class="vexpl">Note:  This converts <?= $g['product_name'] ?> into a routing only platform!<br>
				    	                Note:  This will turn off NAT!
									</span>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Disable Firewall Scrub</td>
								<td width="78%" class="vtable">
									<input name="disablescrub" type="checkbox" id="disablescrub" value="yes" <?php if (isset($config['system']['disablescrub'])) echo "checked"; ?> />
									<strong>Disables the PF scrubbing option which can sometimes interfere with NFS and PPTP traffic.</strong>
									<br/>
									Click <a href='http://www.openbsd.org/faq/pf/scrub.html' target='_new'>here</a> for more information.
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Firewall Maximum States</td>
								<td width="78%" class="vtable">
									<input name="maximumstates" type="text" id="maximumstates" value="<?php echo $pconfig['maximumstates']; ?>" />
									<br/>
									<strong>Maximum number of connections to hold in the firewall state table.</strong>
									<br/>
									<span class="vexpl">Note:  Leave this blank for the default.  On your system the default size is: <?= pfsense_default_state_size() ?></span>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Static route filtering</td>
								<td width="78%" class="vtable">
									<input name="bypassstaticroutes" type="checkbox" id="bypassstaticroutes" value="yes" <?php if ($pconfig['bypassstaticroutes']) echo "checked"; ?> />
									<strong>Bypass firewall rules for traffic on the same interface</strong>
									<br/>
									This option only applies if you have defined one or more static routes. If it is enabled, traffic that enters and
					 				leaves through the same interface will not be checked by the firewall. This may be desirable in some situations where
									multiple subnets are connected to the same interface.
									<br/>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<?php if(count($config['interfaces']) > 1): ?>
							<tr>
								<td colspan="2" valign="top" class="listtopic">Network Address Translation</td>
							</tr>		
							<tr>
								<td width="22%" valign="top" class="vncell">Disable NAT Reflection for port forwards</td>
								<td width="78%" class="vtable">
									<input name="disablenatreflection" type="checkbox" id="disablenatreflection" value="yes" <?php if (isset($config['system']['disablenatreflection'])) echo "checked"; ?> />
									<strong>Disables the automatic creation of additional NAT redirect rules for access to port forwards on your public IP addresses from within your internal networks.  Note: Reflection might not fully work in complex routing scenarios.</strong>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Disable NAT Reflection for 1:1 NAT</td>
								<td width="78%" class="vtable">
									<input name="disablebinatreflection" type="checkbox" id="disablebinatreflection" value="yes" <?php if (!isset($config['system']['enablebinatreflection'])) echo "checked"; ?> />
									<strong>Disables the automatic creation of additional NAT 1:1 mappings for access to 1:1 mappings of your public IP addresses from within your internal networks.  Note: Reflection might not fully work in complex routing scenarios.</strong>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">TFTP Proxy</td>
								<td width="78%" class="vtable">
									<select name="tftpinterface[]" multiple="true" class="formselect" size="3">
<?php
                                					$ifdescs = get_configured_interface_with_descr();
                                					foreach ($ifdescs as $ifent => $ifdesc):
?>
										<option value="<?=$ifent;?>" <?php if (stristr($pconfig['tftpinterface'], $ifent)) echo "selected"; ?>><?=gettext($ifdesc);?></option>
<?php                           						endforeach; ?>
                                					</select>
									<strong>Choose the interfaces where you want TFTP proxy helper to be enabled.</strong>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<?php endif; ?>
							<tr>
								<td width="22%" valign="top">&nbsp;</td>
								<td width="78%"><input name="Submit" type="submit" class="formbtn" value="Save" /></td>
							</tr>
						</table>
					</td>
				</tr>
			</div>
		</table>
	</form>

<?php include("fend.inc"); ?>
</body>
</html>

