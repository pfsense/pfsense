<?php
/* $Id$ */
/*
	interfaces_bridge_edit.php

	Copyright (C) 2008 Ermal Luçi
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
##|*IDENT=page-interfaces-bridge-edit
##|*NAME=Interfaces: Bridge edit page
##|*DESCR=Allow access to the 'Interfaces: Bridge : Edit' page.
##|*MATCH=interfaces_bridge_edit.php*
##|-PRIV

require("guiconfig.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/interfaces_bridge.php');

if (!is_array($config['bridges']['bridged']))
	$config['bridges']['bridged'] = array();

$a_bridges = &$config['bridges']['bridged'];

$ifacelist = get_configured_interface_with_descr();
foreach ($ifacelist as $bif => $bdescr) {
	if (substr(get_real_interface($bif), 0, 3) == "gre")
		unset($ifacelist[$bif]);
}

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_bridges[$id]) {
	$pconfig['enablestp'] = isset($a_bridges[$id]['enablestp']);
	$pconfig['descr'] = $a_bridges[$id]['descr'];
	$pconfig['bridgeif'] = $a_bridges[$id]['bridgeif'];
	$pconfig['members'] = $a_bridges[$id]['members'];
	$pconfig['maxaddr'] = $a_bridges[$id]['maxaddr'];
	$pconfig['timeout'] = $a_bridges[$id]['timeout'];
	if ($a_bridges[$id]['static'])
		$pconfig['static'] = $a_bridges[$id]['static'];
	if ($a_bridges[$id]['private'])
		$pconfig['private'] = $a_bridges[$id]['private'];
	if (isset($a_bridges[$id]['stp']))
		$pconfig['stp'] = $a_bridges[$id]['stp'];
	$pconfig['maxage'] = $a_bridges[$id]['maxage'];
	$pconfig['fwdelay'] = $a_bridges[$id]['fwdelay'];
	$pconfig['hellotime'] = $a_bridges[$id]['hellotime'];
	$pconfig['priority'] = $a_bridges[$id]['priority'];
	$pconfig['proto'] = $a_bridges[$id]['proto'];
	$pconfig['holdcnt'] = $a_bridges[$id]['holdcnt'];
	if (!empty($a_bridges[$id]['ifpriority'])) {
		$pconfig['ifpriority'] = explode(",", $a_bridges[$id]['ifpriority']);
		$ifpriority = array();
		foreach ($pconfig['ifpriority'] as $cfg) {
			list ($key, $value)  = explode(":", $cfg);
			$embprioritycfg[$key] = $value;
			foreach ($embprioritycfg as $key => $value) {
				$ifpriority[$key] = $value;
			}
		}
		$pconfig['ifpriority'] = $ifpriority;
	}
	if (!empty($a_bridges[$id]['ifpathcost'])) {
		$pconfig['ifpathcost'] = explode(",", $a_bridges[$id]['ifpathcost']);
		$ifpathcost = array();
		foreach ($pconfig['ifpathcost'] as $cfg) {
			list ($key, $value)  = explode(":", $cfg);
			$embpathcfg[$key] = $value;
			foreach ($embpathcfg as $key => $value) {
				$ifpathcost[$key] = $value;
			}
		}
		$pconfig['ifpathcost'] = $ifpathcost;
	}
	$pconfig['span'] = $a_bridges[$id]['span'];
	if (isset($a_bridges[$id]['edge']))
		$pconfig['edge'] = $a_bridges[$id]['edge'];
	if (isset($a_bridges[$id]['autoedge']))
		$pconfig['autoedge'] = $a_bridges[$id]['autoedge'];
	if (isset($a_bridges[$id]['ptp']))
		$pconfig['ptp'] = $a_bridges[$id]['ptp'];
	if (isset($a_bridges[$id]['autoptp']))
		$pconfig['autoptp'] = $a_bridges[$id]['autoptp'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "members");
	$reqdfieldsn = array(gettext("Member Interfaces"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['maxage'] && !is_numeric($_POST['maxage']))
		$input_errors[] = gettext("Maxage needs to be an integer between 6 and 40.");
	if ($_POST['maxaddr'] && !is_numeric($_POST['maxaddr']))
		$input_errors[] = gettext("Maxaddr needs to be an integer.");
	if ($_POST['timeout'] && !is_numeric($_POST['timeout']))
		$input_errors[] = gettext("Timeout needs to be an integer.");
	if ($_POST['fwdelay'] && !is_numeric($_POST['fwdelay']))
		$input_errors[] = gettext("Forward Delay needs to be an integer between 4 and 30.");
	if ($_POST['hellotime'] && !is_numeric($_POST['hellotime']))
		$input_errors[] = gettext("Hello time for STP needs to be an integer between 1 and 2.");
	if ($_POST['priority'] && !is_numeric($_POST['priority']))
		$input_errors[] = gettext("Priority for STP needs to be an integer between 0 and 61440.");
	if ($_POST['holdcnt'] && !is_numeric($_POST['holdcnt']))
		$input_errors[] = gettext("Transmit Hold Count for STP needs to be an integer between 1 and 10.");
	foreach ($ifacelist as $ifn => $ifdescr) {
		if ($_POST[$ifn] <> "" && !is_numeric($_POST[$ifn]))
			$input_errors[] = "{$ifdescr} " . gettext("interface priority for STP needs to be an integer between 0 and 240.");
	}
	$i = 0;
	foreach ($ifacelist as $ifn => $ifdescr) {
		if ($_POST["{$ifn}{$i}"] <> "" && !is_numeric($_POST["{$ifn}{$i}"]))
			$input_errors[] = "{$ifdescr} " . gettext("interface path cost for STP needs to be an integer between 1 and 200000000.");
		$i++;
	}

	if (!is_array($_POST['members']) || count($_POST['members']) < 2)
		$input_errors[] = gettext("You must select at least 2 member interfaces for a bridge.");

	if (is_array($_POST['members'])) {
		foreach($_POST['members'] as $ifmembers) {
			if (empty($config['interfaces'][$ifmembers]))
				$input_errors[] = gettext("A member interface passed does not exist in configuration");
			if (is_array($config['interfaces'][$ifmembers]['wireless']) &&
				$config['interfaces'][$ifmembers]['wireless']['mode'] != "hostap")
				$input_errors[] = gettext("Bridging a wireless interface is only possible in hostap mode.");
			if ($_POST['span'] != "none" && $_POST['span'] == $ifmembers)
				$input_errors[] = gettext("Span interface cannot be part of the bridge. Remove the span interface from bridge members to continue.");
		}
	}

	if (!$input_errors) {
		$bridge = array();
		$bridge['members'] = implode(',', $_POST['members']);
		$bridge['enablestp'] = $_POST['enablestp'] ? true : false;
		$bridge['descr'] = $_POST['descr'];
		$bridge['maxaddr'] = $_POST['maxaddr'];
		$bridge['timeout'] = $_POST['timeout'];
		if ($_POST['static'])
			$bridge['static'] = implode(',', $_POST['static']);
		if ($_POST['private'])
			$bridge['private'] = implode(',', $_POST['private']);
		if (isset($_POST['stp']))
			$bridge['stp'] = implode(',', $_POST['stp']);
		$bridge['maxage'] = $_POST['maxage'];
		$bridge['fwdelay'] = $_POST['fwdelay'];
		$bridge['hellotime'] = $_POST['hellotime'];
		$bridge['priority'] = $_POST['priority'];
		$bridge['proto'] = $_POST['proto'];
		$bridge['holdcnt'] = $_POST['holdcnt'];
		$i = 0;
		$ifpriority = "";
		$ifpathcost = "";
		foreach ($ifacelist as $ifn => $ifdescr) {
			if ($_POST[$ifn] <> "") {
				if ($i > 0)
					$ifpriority .= ",";
				$ifpriority .= $ifn.":".$_POST[$ifn];
			}
			if ($_POST["{$ifn}0"] <> "") {
				if ($i > 0)
					$ifpathcost .= ",";
				$ifpathcost .= $ifn.":".$_POST["{$ifn}0"];
			}
			$i++;
		}
		$bridge['ifpriority'] = $ifpriority;
		$bridge['ifpathcost'] = $ifpathcost;

		if ($_POST['span'] != "none")
			$bridge['span'] = $_POST['span'];
		else
			unset($bridge['span']);
		if (isset($_POST['edge']))
			$bridge['edge'] = implode(',', $_POST['edge']);
		if (isset($_POST['autoedge']))
			$bridge['autoedge'] = implode(',', $_POST['autoedge']);
		if (isset($_POST['ptp']))
			$bridge['ptp'] = implode(',', $_POST['ptp']);
		if (isset($_POST['autoptp']))
			$bridge['autoptp'] = implode(',', $_POST['autoptp']);

		$bridge['bridgeif'] = $_POST['bridgeif'];
		interface_bridge_configure($bridge);
		if ($bridge['bridgeif'] == "" || !stristr($bridge['bridgeif'], "bridge"))
			$input_errors[] = gettext("Error occurred creating interface, please retry.");
		else {
			if (isset($id) && $a_bridges[$id])
				$a_bridges[$id] = $bridge;
			else
				$a_bridges[] = $bridge;

			write_config();

			$confif = convert_real_interface_to_friendly_interface_name($bridge['bridgeif']);
			if ($confif <> "")
				interface_configure($confif);

			header("Location: interfaces_bridge.php");
			exit;
		}
	}
}

$pgtitle = array(gettext("Interfaces"),gettext("Bridge"),gettext("Edit"));
$shortcut_section = "interfaces";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script type="text/javascript">
//<![CDATA[
function show_source_port_range() {
        document.getElementById("sprtable").style.display = 'none';
        document.getElementById("sprtable1").style.display = '';
        document.getElementById("sprtable2").style.display = '';
        document.getElementById("sprtable3").style.display = '';
        document.getElementById("sprtable4").style.display = '';
        document.getElementById("sprtable5").style.display = '';
        document.getElementById("sprtable6").style.display = '';
        document.getElementById("sprtable7").style.display = '';
        document.getElementById("sprtable8").style.display = '';
        document.getElementById("sprtable9").style.display = '';
        document.getElementById("sprtable10").style.display = '';
}
//]]>
</script>

<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="interfaces_bridge_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0" summary="interfaces bridge edit">
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Bridge configuration"); ?></td>
				</tr>
				<tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Member interfaces"); ?></td>
                  <td width="78%" class="vtable">
				  <select name="members[]" multiple="multiple" class="formselect" size="3">
                      <?php
						$members_array = explode(',', $pconfig['members']);
						foreach ($ifacelist as $ifn => $ifinfo) {
							echo "<option value=\"{$ifn}\"";
							if (in_array($ifn, $members_array))
								echo " selected=\"selected\"";
							echo ">{$ifinfo}</option>";
						}
						unset($members_array);
				?>
                    </select>
			<br />
			<span class="vexpl"><?=gettext("Interfaces participating in the bridge."); ?></span>
			</td>
            </tr>
			<tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
                  <td width="78%" class="vtable">
				  <input type="text" name="descr" id="descr" class="formfld unknown" size="50" value="<?=htmlspecialchars($pconfig['descr']);?>" />
					</td>
				</tr>
            <tr id="sprtable">
                <td></td>
                <td>
                <p><input type="button" onclick="show_source_port_range()" value="<?=gettext("Show advanced options"); ?>" /></p>
                </td>
			</tr>
                <tr style="display:none" id="sprtable1">
                  <td valign="top" class="vncell" align="center"><?=gettext("RSTP/STP"); ?>  </td>
                  <td class="vtable">
					<input type="checkbox" name="enablestp" id="enablestp" <?php if ($pconfig['enablestp']) echo "checked=\"checked\"";?> />
					<span class="vexpl"><strong><?=gettext("Enable spanning tree options for this bridge."); ?> </strong></span>
					<br /><br />
					<table id="stpoptions" border="0" cellpadding="6" cellspacing="0" summary="protocol">
					<tr><td valign="top" class="vncell" width="20%"><?=gettext("Protocol"); ?></td>
					<td class="vtable" width="80%">
					<select name="proto" id="proto">
						<?php
							foreach (array("rstp", "stp") as $proto) {
								echo "<option value=\"{$proto}\"";
								if ($pconfig['proto'] == $proto)
									echo " selected=\"selected\"";
								echo ">".strtoupper($proto)."</option>";
							}
						?>
					</select>
                    <br />
                    <span class="vexpl"><?=gettext("Protocol used for spanning tree."); ?> </span></td>
					</tr>
					<tr> <td valign="top" class="vncell" width="20%"><?=gettext("STP interfaces"); ?></td>
					<td class="vtable" width="80%">
					<select name="stp[]" class="formselect" multiple="multiple" size="3">
						<?php
							foreach ($ifacelist as $ifn => $ifdescr) {
								echo "<option value=\"{$ifn}\"";
								if (stristr($pconfig['stp'], $ifn))
									echo " selected=\"selected\"";
								echo ">{$ifdescr}</option>";
							}
						?>
					</select>
					<br />
					<span class="vexpl" >
	     <?=gettext("Enable Spanning Tree Protocol on interface.  The if_bridge(4) " .
	     "driver has support for the IEEE 802.1D Spanning Tree Protocol " .
	     "(STP).  STP is used to detect and remove loops in a " .
	     "network topology."); ?>
					</span>
					</td></tr>
					<tr><td valign="top" class="vncell" width="20%"><?=gettext("Valid time"); ?></td>
					<td class="vtable" width="80%">
					<input name="maxage" type="text" class="formfld unkown" id="maxage" size="8" value="<?=htmlspecialchars($pconfig['maxage']);?>" /> <?=gettext("seconds"); ?>
					<br />
					<span class="vexpl">
	     <?=gettext("Set the time that a Spanning Tree Protocol configuration is " .
	     "valid.  The default is 20 seconds.  The minimum is 6 seconds and " .
	     "the maximum is 40 seconds."); ?>
					</span>
					</td></tr>
					<tr><td valign="top" class="vncell" width="20%"><?=gettext("Forward time"); ?> </td>
					<td class="vtable" width="80%">
					<input name="fwdelay" type="text" class="formfld unkown" id="fwdelay" size="8" value="<?=htmlspecialchars($pconfig['fwdelay']);?>" /> <?=gettext("seconds"); ?>
					<br />
					<span class="vexpl">
	     <?=gettext("Set the time that must pass before an interface begins forwarding " .
	     "packets when Spanning Tree is enabled.  The default is 15 seconds.  The minimum is 4 seconds and the maximum is 30 seconds."); ?>
					</span>
					</td></tr>
					<tr><td valign="top" class="vncell" width="20%"><?=gettext("Hello time"); ?></td>
					<td class="vtable" width="80%">
					<input name="hellotime" type="text" class="formfld unkown" size="8" id="hellotime" value="<?=htmlspecialchars($pconfig['hellotime']);?>" /> <?=gettext("seconds"); ?>
					<br />
					<span class="vexpl">
	     <?=gettext("Set the time between broadcasting of Spanning Tree Protocol configuration messages.  The hello time may only be changed when " .
	     "operating in legacy STP mode.  The default is 2 seconds.  The minimum is 1 second and the maximum is 2 seconds."); ?>
					</span>
					</td></tr>
					<tr><td valign="top" class="vncell" width="20%"><?=gettext("Priority"); ?></td>
					<td class="vtable" width="80%">
					<input name="priority" type="text" class="formfld unkown" id="priority" value="<?=htmlspecialchars($pconfig['priority']);?>" />
					<br />
					<span class="vexpl">
	     <?=gettext("Set the bridge priority for Spanning Tree.  The default is 32768. " .
	     "The minimum is 0 and the maximum is 61440."); ?>
					</span>
					</td></tr>
					<tr><td valign="top" class="vncell" width="20%"><?=gettext("Hold count"); ?></td>
					<td class="vtable" width="80%">
					<input name="holdcnt" type="text" class="formfld unkown" id="holdcnt" value="<?=htmlspecialchars($pconfig['holdcnt']);?>" />
					<br />
					<span class="vexpl">
	     <?=gettext("Set the transmit hold count for Spanning Tree.  This is the number" .
	     " of packets transmitted before being rate limited.  The " .
	     "default is 6.  The minimum is 1 and the maximum is 10."); ?>
					</span>
					</td></tr>
					<tr><td valign="top" class="vncell" width="20%"><?=gettext("Priority"); ?></td>
					<td class="vtable" width="80%">
					<table summary="priority">
					<?php foreach ($ifacelist as $ifn => $ifdescr)
							echo "<tr><td>{$ifdescr}</td><td><input size=\"5\" name=\"{$ifn}\" type=\"text\" class=\"formfld unkown\" id=\"{$ifn}\" value=\"{$ifpriority[$ifn]}\" /></td></tr>";
					?>
					<tr><td></td></tr>
					</table>
					<br />
					<span class="vexpl" >
	     <?=gettext("Set the Spanning Tree priority of interface to value.  The " .
	     "default is 128.  The minimum is 0 and the maximum is 240.  Increments of 16."); ?>
					</span>
					</td></tr>
					<tr><td valign="top" class="vncell" width="20%"><?=gettext("Path cost"); ?></td>
					<td class="vtable" width="80%">
					<table summary="path cost">
					<?php $i = 0; foreach ($ifacelist as $ifn => $ifdescr)
							echo "<tr><td>{$ifdescr}</td><td><input size=\"8\" name=\"{$ifn}{$i}\" type=\"text\" class=\"formfld unkown\" id=\"{$ifn}{$i}\" value=\"{$ifpathcost[$ifn]}\" /></td></tr>";
					?>
					<tr><td></td></tr>
					</table>
					<br />
					<span class="vexpl" >
	     <?=gettext("Set the Spanning Tree path cost of interface to value.  The " .
	     "default is calculated from the link speed.  To change a previously selected path cost back to automatic, set the cost to 0. ".
	     "The minimum is 1 and the maximum is 200000000."); ?>
					</span>
					</td></tr>

			    </table>
				</td></tr>
                <tr style="display:none" id="sprtable2">
                  <td valign="top" class="vncell"><?=gettext("Cache size"); ?></td>
					<td class="vtable">
						<input name="maxaddr" size="10" type="text" class="formfld unkown" id="maxaddr" value="<?=htmlspecialchars($pconfig['maxaddr']);?>" /> <?=gettext("entries"); ?>
					<br /><span class="vexpl">
<?=gettext("Set the size of the bridge address cache to size.	The default is " .
	     ".100 entries."); ?>
					</span>
					</td>
				</tr>
                <tr style="display:none" id="sprtable3">
                  <td valign="top" class="vncell"><?=gettext("Cache entry expire time"); ?></td>
				  <td>
					<input name="timeout" type="text" class="formfld unkown" id="timeout" size="10" value="<?=htmlspecialchars($pconfig['timeout']);?>" /> <?=gettext("seconds"); ?>
					<br /><span class="vexpl">
	     <?=gettext("Set the timeout of address cache entries to this number of seconds.  If " .
	     "seconds is zero, then address cache entries will not be expired. " .
	     "The default is 240 seconds."); ?>
					</span>
					</td>
				</tr>
                <tr style="display:none" id="sprtable4">
                  <td valign="top" class="vncell"><?=gettext("Span port"); ?></td>
					<td class="vtable">
				  	<select name="span" class="formselect" id="span">
						<option value="none" selected="selected"><?=gettext("None"); ?></option>
						<?php
							foreach ($ifacelist as $ifn => $ifdescr) {
								echo "<option value=\"{$ifn}\"";
								if ($ifn == $pconfig['span'])
									echo " selected=\"selected\"";
								echo ">{$ifdescr}</option>";
							}
						?>
					</select>
					<br /><span class="vexpl">
	     <?=gettext("Add the interface named by interface as a span port on the " .
	     "bridge.  Span ports transmit a copy of every frame received by " .
	     "the bridge.  This is most useful for snooping a bridged network " .
	     "passively on another host connected to one of the span ports of " .
	     "the bridge."); ?>
					</span>
		<p class="vexpl"><span class="red"><strong>
					 <?=gettext("Note:"); ?><br />
                                  </strong></span>
                 <?=gettext("The span interface cannot be part of the bridge member interfaces."); ?>
                                        </p>
					</td>
				</tr>
                <tr style="display:none" id="sprtable5">
                  <td valign="top" class="vncell"><?=gettext("Edge ports"); ?></td>
                  <td class="vtable">
					<select name="edge[]" class="formselect" multiple="multiple" size="3">
						<?php
							foreach ($ifacelist as $ifn => $ifdescr) {
								echo "<option value=\"{$ifn}\"";
								if (stristr($pconfig['edge'], $ifn))
									echo " selected=\"selected\"";
								echo ">{$ifdescr}</option>";
							}
						?>
					</select>
                    <br />
                    <span class="vexpl">
	     <?=gettext("Set interface as an edge port.  An edge port connects directly to " .
	     "end stations and cannot create bridging loops in the network; this " .
	     "allows it to transition straight to forwarding."); ?>
					</span></td>
			    </tr>
                <tr style="display:none" id="sprtable6">
                  <td valign="top" class="vncell"><?=gettext("Auto Edge ports"); ?></td>
                  <td class="vtable">
					<select name="autoedge[]" class="formselect" multiple="multiple" size="3">
						<?php
							foreach ($ifacelist as $ifn => $ifdescr) {
								echo "<option value=\"{$ifn}\"";
								if (stristr($pconfig['autoedge'], $ifn))
									echo " selected=\"selected\"";
								echo ">{$ifdescr}</option>";
							}
						?>
					</select>
                    <br />
                    <span class="vexpl">
	     <?=gettext("Allow interface to automatically detect edge status.  This is the " .
	     "default for all interfaces added to a bridge."); ?></span>
		 <p class="vexpl"><span class="red"><strong>
				  <?=gettext("Note:"); ?><br />
				  </strong></span>
		 <?=gettext("This will disable the autoedge status of interfaces."); ?>
					</p></td>
			    </tr>
                <tr style="display:none" id="sprtable7">
                  <td valign="top" class="vncell"><?=gettext("PTP ports"); ?></td>
                  <td class="vtable">
					<select name="ptp[]" class="formselect" multiple="multiple" size="3">
						<?php
							foreach ($ifacelist as $ifn => $ifdescr) {
								echo "<option value=\"{$ifn}\"";
								if (stristr($pconfig['ptp'], $ifn))
									echo " selected=\"selected\"";
								echo ">{$ifdescr}</option>";
							}
						?>
					</select>
                    <br />
                    <span class="vexpl">
	     <?=gettext("Set the interface as a point-to-point link.  This is required for " .
	     "straight transitions to forwarding and should be enabled on a " .
	     "direct link to another RSTP-capable switch."); ?>
					</span></td>
			    </tr>
                <tr style="display:none" id="sprtable8">
                  <td valign="top" class="vncell"><?=gettext("Auto PTP ports"); ?></td>
                  <td class="vtable">
					<select name="autoptp[]" class="formselect" multiple="multiple" size="3">
						<?php
							foreach ($ifacelist as $ifn => $ifdescr) {
								echo "<option value=\"{$ifn}\"";
								if (stristr($pconfig['autoptp'], $ifn))
									echo " selected=\"selected\"";
								echo ">{$ifdescr}</option>";
							}
						?>
					</select>
                    <br />
                    <span class="vexpl">
	     <?=gettext("Automatically detect the point-to-point status on interface by " .
	     "checking the full duplex link status.  This is the default for " .
	     "interfaces added to the bridge."); ?></span>
				 <p class="vexpl"><span class="red"><strong>
				  <?=gettext("Note:"); ?><br />
				  </strong></span>
		 <?=gettext("The interfaces selected here will be removed from default autoedge status."); ?>
					</p></td>
			    </tr>
                <tr style="display:none" id="sprtable9">
                  <td valign="top" class="vncell"><?=gettext("Sticky ports"); ?></td>
                  <td class="vtable">
					<select name="static[]" class="formselect" multiple="multiple" size="3">
						<?php
							foreach ($ifacelist as $ifn => $ifdescr) {
								echo "<option value=\"{$ifn}\"";
								if (stristr($pconfig['static'], $ifn))
									echo " selected=\"selected\"";
								echo ">{$ifdescr}</option>";
							}
						?>
					</select>
                    <br />
                    <span class="vexpl">
	     <?=gettext("Mark an interface as a \"sticky\" interface.  Dynamically learned " .
	     "address entries are treated as static once entered into the " .
	     "cache.  Sticky entries are never aged out of the cache or " .
	     "replaced, even if the address is seen on a different interface."); ?>
					</span></td>
			    </tr>
                <tr style="display:none" id="sprtable10">
                  <td valign="top" class="vncell"><?=gettext("Private ports"); ?></td>
                  <td class="vtable">
					<select name="private[]" class="formselect" multiple="multiple" size="3">
						<?php
							foreach ($ifacelist as $ifn => $ifdescr) {
								echo "<option value=\"{$ifn}\"";
								if (stristr($pconfig['private'], $ifn))
									echo " selected=\"selected\"";
								echo ">{$ifdescr}</option>";
							}
						?>
					</select>
                    <br />
                    <span class="vexpl">
	     <?=gettext("Mark an interface as a \"private\" interface.  A private interface does not forward any traffic to any other port that is also " .
	     "a private interface."); ?>
					</span></td>
			    </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
		    <input type="hidden" name="bridgeif" value="<?=htmlspecialchars($pconfig['bridgeif']); ?>" />
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" />
                    <input type="button" class="formbtn" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=$referer;?>'" />
                    <?php if (isset($id) && $a_bridges[$id]): ?>
                    <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
