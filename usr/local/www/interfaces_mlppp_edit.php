<?php
/* $Id$ */
/*
	interfaces_mlppp_edit.php
	part of m0n0wall (http://m0n0.ch/wall)

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
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-interfaces-mlppp-edit
##|*NAME=Interfaces: mlppp: Edit page
##|*DESCR=Allow access to the 'Interfaces: mlppp: Edit' page.
##|*MATCH=interfaces_mlppp_edit.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['mlppps']['mlppp']))
	$config['mlppps']['mlppp'] = array();

$a_mlppps = &$config['mlppps']['mlppp'];

$portlist = get_interface_list();
$types = array("pppoe" => "PPPoE", "pptp" => "PPTP",  "l2tp" => "LT2TP", "tcp" => "TCP", "udp" => "UDP", "ng" => "Netgraph"  /* , "carpdev-dhcp" => "CarpDev"*/); 

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_mlppps[$id]) {
	$pconfig['if'] = $a_mlppps[$id]['if'];
	$pconfig['mlpppif'] = $a_mlppps[$id]['mlpppif'];
	$pconfig['username'] = $a_mlppps[$id]['username'];
	$pconfig['password'] = $a_mlppps[$id]['password'];
	$pconfig['service'] = $a_mlppps[$id]['service'];
	$pconfig['ondemand'] = $a_mlppps[$id]['ondemand'];
	$pconfig['timeout'] = $a_mlppps[$id]['timeout'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "if tag");
	$reqdfieldsn = explode(",", "Parent interface,mlppp tag");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if ($_POST['tag'] && (!is_numericint($_POST['tag']) || ($_POST['tag'] < '1') || ($_POST['tag'] > '4094'))) {
		$input_errors[] = "The mlppp tag must be an integer between 1 and 4094.";
	}

	foreach ($a_mlppps as $mlppp) {
		if (isset($id) && ($a_mlppps[$id]) && ($a_mlppps[$id] === $mlppp))
			continue;

		if (($mlppp['if'] == $_POST['if']) && ($mlppp['tag'] == $_POST['tag'])) {
			$input_errors[] = "A mlppp with the tag {$mlppp['tag']} is already defined on this interface.";
			break;
		}
	}
	if (is_array($config['qinqs']['qinqentry'])) {
		foreach ($config['qinqs']['qinqentry'] as $qinq)
			if ($qinq['tag'] == $_POST['tag'] && $qinq['if'] == $_POST['if'])
				$input_errors[] = "A QinQ mlppp exists with this tag please remove it to use this tag with.";
	}

	if (!$input_errors) {
		$mlppp = array();
		$mlppp['if'] = $_POST['if'];
		$mlppp['tag'] = $_POST['tag'];
		$mlppp['descr'] = $_POST['descr'];
		$mlppp['mlpppif'] = "{$_POST['if']}_mlppp{$_POST['tag']}";

		$mlppp['mlpppif'] = interface_mlppp_configure($mlppp);
                if ($mlppp['mlpppif'] == "" || !stristr($mlppp['mlpppif'], "mlppp"))
                        $input_errors[] = "Error occured creating interface, please retry.";
                else {
                        if (isset($id) && $a_mlppps[$id])
                                $a_mlppps[$id] = $mlppp;
                        else
                                $a_mlppps[] = $mlppp;

                        write_config();

			$confif = convert_real_interface_to_friendly_interface_name($mlppp['mlpppif']);
			if ($confif <> "")
				interface_configure($confif);
				
			header("Location: interfaces_mlppp.php");
			exit;
		}
	}
}

$pgtitle = array("Interfaces","MLPPP","Edit");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="interfaces_mlppp_edit.php" method="post" name="iform" id="iform">
              <table id="interfacetable" width="100%" border="0" cellpadding="6" cellspacing="0">
              	<tr>
					<td colspan="2" valign="top" class="listtopic">MLPPP configuration</td>
				</tr>
				<tr>
                  <td width="22%" valign="top" class="vncellreq">Member interfaces</td>
                  <td width="78%" class="vtable">
				  <select name="members[]" multiple="true" class="formselect" size="5">
                      <?php

						foreach ($portlist as $ifn => $ifinfo)
						if (is_jumbo_capable($ifn)) {
							echo "<option value=\"{$ifn}\"";
							if ($ifn == $pconfig['if'])
								echo "selected";
							echo ">";
							echo htmlspecialchars($ifn . " (" . $ifinfo['mac'] . ")");
							echo "</option>";
						}
		      		?>
                    </select>
				<br/><span class="vexpl">Interfaces participating in the multilink connection.</span>
				</td>
            	</tr>
            	<tr>
				<td valign="middle" class="vncell"><strong>Type</strong></td>
				<td class="vtable"> 
					<select name="type" onChange="updateType(this.value);" class="formselect" id="type">
					<?php 
						foreach ($types as $key => $opt) { 
							echo "<option onClick=\"updateType('{$key}');\"";
							if ($key == $pconfig['type']) 
								echo " selected";
							echo " value=\"{$key}\" >" . htmlspecialchars($opt);
							echo "</option>";
					    } 
					?>
					</select>
				</td>
			</tr>
				<tr>
                  <td valign="top" class="vncell">Username</td>
                  <td class="vtable">
                    <input name="username" type="text" class="formfld unknown" id="username" size="10" value="<?=htmlspecialchars($pconfig['username']);?>">
                  </td>
			    </tr>
			    <tr>
                  <td valign="top" class="vncell">Password</td>
                  <td class="vtable">
                    <input name="password" type="text" class="formfld unknown" id="password" size="2" value="<?=htmlspecialchars($pconfig['password']);?>">
                  </td>
			    </tr>
			    <tr>
                  <td valign="top" class="vncell">Service</td>
                  <td class="vtable">
                    <input name="service" type="text" class="formfld unknown" id="service" size="6" value="<?=htmlspecialchars($pconfig['service']);?>">
                  </td>
			    </tr>
			    <tr>
                  <td valign="top" class="vncell">Bandwidth</td>
                  <td class="vtable">
                    <input name="bandwidth" type="text" class="formfld unknown" id="bandwidth" size="6" value="<?=htmlspecialchars($pconfig['bandwidth']);?>">&nbsp;(bits/sec)
                    <br> <span class="vexpl">Set Bandwidth for each link if links have different bandwidths, otherwise, leave blank.</span>
                  </td>
			    </tr>
			    <tr>
                  <td valign="top" class="vncell">Link MTU</td>
                  <td class="vtable">
                    <input name="bandwidth" type="text" class="formfld unknown" id="bandwidth" size="6" value="<?=htmlspecialchars($pconfig['bandwidth']);?>">
                    <br> <span class="vexpl">Set Bandwidth for each link if links have different bandwidths, otherwise, leave blank.</span>
                  </td>
			    </tr>
			    <tr>
                  <td valign="top" class="vncell">Link MRU</td>
                  <td class="vtable">
                    <input name="bandwidth" type="text" class="formfld unknown" id="bandwidth" size="6" value="<?=htmlspecialchars($pconfig['bandwidth']);?>">
                    <br> <span class="vexpl">Set Bandwidth for each link if links have different bandwidths, otherwise, leave blank.</span>
                  </td>
			    </tr>
			    <tr>
				<tr>
            	<td valign="top" class="vncell">Dial 	On Demand</td>
                  <td class="vtable">
                    <input type="checkbox" value="on" id="ondemand" name="ondemand" <?php if (isset($pconfig['ondemand'])) echo "checked"; ?>> Enable Dial-on-Demand mode
                    <br> <span class="vexpl">This option causes the interface to operate in dial-on-demand mode, allowing you to have a virtual full time connection. 
                    The interface is configured, but the actual connection of the link is delayed until qualifying outgoing traffic is detected. </span>
                  </td>
			    </tr>
			    <tr>
                  <td valign="top" class="vncell">Idle Timeout</td>
                  <td class="vtable">
                    <input name="timeout" type="text" class="formfld unknown" id="timeout" size="6" value="<?=htmlspecialchars($pconfig['timeout']);?>">
                    <br> <span class="vexpl">Idle Timeout goes with the OnDemand selection above. If OnDemand is not checked this is ignored.</span>
                  </td>
			    </tr>
				<tr>
                  <td width="22%" valign="top" class="vncell">Description</td>
                  <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
                    <br> <span class="vexpl">You may enter a description here for your reference (not parsed).</span>
                  </td>
                </tr>
				<td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
		    <input type="hidden" name="mlpppif" value="<?=$pconfig['mlpppif']; ?>">
                    <input name="Submit" type="submit" class="formbtn" value="Save"> <input type="button" value="Cancel" onclick="history.back()">
                    <?php if (isset($id) && $a_mlppps[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
