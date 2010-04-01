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
$closehead = false;
$pgtitle = array("Interfaces","MLPPP","Edit");
include("head.inc");
$types = array("select" => "Select", "ppp" => "PPP", "pppoe" => "PPPoE", "pptp" => "PPTP"/*,  "l2tp" => "L2TP", "tcp" => "TCP", "udp" => "UDP", "ng" => "Netgraph" */); 


?>

<script type="text/javascript">
	function updateType(t){
		switch(t) {
	<?php
		/* OK, so this is sick using php to generate javascript, but it needed to be done */
		foreach ($types as $key => $val) {
			echo "		case \"{$key}\": {\n";
			$t = $types;
			foreach ($t as $k => $v) {
				if ($k != $key) {
					echo "				$('{$k}').hide();\n";
				}
				
			}
			if ($key == "ppp"){
					echo "				$('serialports').show();\n";
					echo "				$('ports').hide();\n";
			} else {
					if ($key == "select") {
						echo "				$('ports').hide();\n";
						echo "				$('serialports').hide();\n";
					}
					else {
						echo "				$('ports').show();\n";
						echo "				$('serialports').hide();\n";
					}
			}
			echo "			}\n";
		}
	?>
		}
		$(t).show();
	}

	function show_allcfg(obj) {
		if (obj.checked)
			$('allcfg').show();
		else
			$('allcfg').hide();
	}
	
	function show_periodic_reset(obj) {
		if (obj.checked)
			$('presetwrap').show();
		else
			$('presetwrap').hide();
	}

	function show_mon_config() {
		document.getElementById("showmonbox").innerHTML='';
		aodiv = document.getElementById('showmon');
		aodiv.style.display = "block";
	}

	function openwindow(url) {
		var oWin = window.open(url,"pfSensePop","width=620,height=400,top=150,left=150");
		if (oWin==null || typeof(oWin)=="undefined") 
			return false;
		else 
			return true;
	}
	function prefill_att() {
		$('initstr').value = "Q0V1E1S0=0&C1&D2+FCLASS=0";
		$('apn').value = "ISP.CINGULAR";
		$('apnum').value = "1";
		$('phone').value = "*99#";
		$('username').value = "att";
		$('password').value = "att";
	}
	function prefill_sprint() {
		$('initstr').value = "E1Q0";
		$('apn').value = "";
		$('apnum').value = "";
		$('phone').value = "#777";
		$('username').value = "sprint";
		$('password').value = "sprint";
	}
	function prefill_vzw() {
		$('initstr').value = "E1Q0s7=60";
		$('apn').value = "";
		$('apnum').value = "";
		$('phone').value = "#777";
		$('username').value = "123@vzw3g.com";
		$('password').value = "vzw";
	}
	var currentSwap = false;
	function swapOptions(){

				

				document.getElementById("postMoreOptions").style.display = currentSwap ? "" : "none";

				if (typeof(document.forms.postmodify) != "undefined")
					document.forms.postmodify.additional_options.value = currentSwap ? "1" : "0";

				currentSwap = !currentSwap;
	}
</script>
</head>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="interfaces_mlppp_edit.php" method="post" name="iform" id="iform">
              <table id="interfacetable" width="100%" border="0" cellpadding="6" cellspacing="0">
              	<tr>
					<td colspan="2" valign="top" class="listtopic">MLPPP configuration</td>
				</tr>
				<tr>
					<td valign="middle" class="vncell"><strong>Link Type</strong></td>
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
				<tr style="display:none;" name="ports" id="ports" >
					<td width="22%" valign="top" class="vncellreq">Member interface(s)</td>
					<td width="78%" class="vtable">
						<select name="members[]" multiple="true" class="formselect">
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
            	<tr style="display:none;" name="serialports" id="serialports">
					<td width="22%" valign="top" class="vncellreq">Member interface(s)</td>
					<td width="78%" class="vtable">
						<select name="serport" id="serport" multiple="true" class="formselect">
						<?php
							$serportlist = glob("/dev/cua*");
							$modems = glob("/dev/modem*");
							$serportlist = array_merge($serportlist, $modems);
							foreach ($serportlist as $port) {
								if(preg_match("/\.(lock|init)$/", $port))
									continue;
								echo "<option value=\"".trim($port)."\"";
								if ($pconfig['port'] == $port)
									echo "selected";
								echo ">{$port}</option>";
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
					<td width="22%" valign="top" class="vncell">Gateway</td>
					<td width="78%" class="vtable">
						<input type="checkbox" value="on" id="defaultgw" name="defaultgw" <?php if (isset($pconfig['defaultgw'])) echo "checked"; ?>>This link will be used as the default gateway.
					</td>
				</tr>
				<tr>
            	<td valign="top" class="vncell">Dial On Demand</td>
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
                <tr>
					<td colspan="2" valign="top" height="16"></td>
				</tr>

			    <tr style="display:none;" name="select" id="select">
				</tr>
				<tr style="display:none;" name="ppp" id="ppp">
					<td colspan="2" style="padding:0px;">
						<table width="100%" border="0" cellpadding="6" cellspacing="0">
							<tr>
								<td colspan="2" valign="top" class="listtopic">PPP configuration</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Pre-fill Settings</td>
								<td width="78%" class="vtable">
								<a href='#' onClick='javascript:prefill_att();'>ATT</A>
								<a href='#' onClick='javascript:prefill_sprint();'>Sprint</A>
								<a href='#' onClick='javascript:prefill_vzw();'>Verizon</A>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Init String</td>
								<td width="78%" class="vtable">
									<input type="text" size="40" class="formfld unknown" id="initstr" name="initstr"><?=htmlspecialchars($pconfig['initstr']);?>
									<br><span class="vexpl">Note: Enter the modem initialization string here. Do NOT include the "AT" string at the beginning of the command.</span>
								</td>
							</tr>
							<tr>
							  <td width="22%" valign="top" class="vncell">Sim PIN</td>
							  <td width="78%" class="vtable">
								<input name="simpin" type="text" class="formfld unknown" id="simpin" size="12" value="<?=htmlspecialchars($pconfig['simpin']);?>">
							</td>
							</tr>
							<tr>
							  <td width="22%" valign="top" class="vncell">Sim PIN wait</td>
							  <td width="78%" class="vtable">
								<input name="pin-wait" type="text" class="formfld unknown" id="pin-wait" size="2" value="<?=htmlspecialchars($pconfig['pin-wait']);?>">
								<br><span class="vexpl">Note: Time to wait for SIM to discover network after PIN is sent to SIM (seconds).</span>
							</td>
							</tr>
							<tr>
							  <td width="22%" valign="top" class="vncell">Access Point Name (APN)</td>
							  <td width="78%" class="vtable">
								<input name="apn" type="text" class="formfld unknown" id="apn" size="40" value="<?=htmlspecialchars($pconfig['apn']);?>">
							</td>
							</tr>
							<tr>
							  <td width="22%" valign="top" class="vncell">APN number (optional)</td>
							  <td width="78%" class="vtable">
								<input name="apnum" type="text" class="formfld unknown" id="apnum" size="2" value="<?=htmlspecialchars($pconfig['apnum']);?>">
								<br><span class="vexpl">Note: Defaults to 1 if you set APN above. Ignored if you set no APN above.</span>
							</td>
							</tr>
							<tr>
							  <td width="22%" valign="top" class="vncell">Phone Number</td>
							  <td width="78%" class="vtable">
								<input name="phone" type="text" class="formfld unknown" id="phone" size="40" value="<?=htmlspecialchars($pconfig['phone']);?>">
								<br><span class="vexpl">Note: Typically (*99# or *99***# or *99***1#) for GSM networks and *777 for CDMA networks</span>
							  </td>
							</tr>
							<tr>
							<tr>
							  <td width="22%" valign="top" class="vncell">Local IP</td>
							  <td width="78%" class="vtable">
								<input name="localip" type="text" class="formfld unknown" id="localip" size="40" value="<?=htmlspecialchars($pconfig['localip']);?>">
								<br><span class="vexpl">Note: Enter your IP address here if it is not automatically assigned.</span>
							  </td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Remote IP (Gateway)</td>
								<td width="78%" class="vtable">
									<input name="gateway" type="text" class="formfld unknown" id="gateway" size="40" value="<?=htmlspecialchars($pconfig['gateway']);?>">
									<br><span class="vexpl">Note: Enter the remote IP here if not automatically assigned. This is where the packets will be routed.</span>
								</td>
							</tr>
							<tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Connection Timeout</td>
								<td width="78%" class="vtable">
									<input name="connect-timeout" type="text" class="formfld unknown" id="connect-timeout" size="2" value="<?=htmlspecialchars($pconfig['connect-timeout']);?>">
									<br><span class="vexpl">Note: Enter timeout in seconds for connection to be established (sec.) Default is 45 sec.</span>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr style="display:none;" name="pppoe" id="pppoe">
					<td colspan="2" style="padding:0px;">
						<table width="100%" border="0" cellpadding="6" cellspacing="0">
							<tr>
								<td colspan="2" valign="top" class="listtopic">PPPoE configuration</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell">Service name</td>
								<td width="78%" class="vtable"><input name="provider" type="text" class="formfld unknown" id="provider" size="20" value="<?=htmlspecialchars($pconfig['provider']);?>">
									<br> <span class="vexpl">Hint: this field can usually be left empty</span>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				
				<tr style="display:none;" name="pptp" id="pptp">
					<td colspan="2" style="padding:0px;">
						<table width="100%" border="0" cellpadding="6" cellspacing="0">
							<tr>
								<td colspan="2" valign="top" class="listtopic">PPTP configuration</td>
							</tr>
							<tr>
								<td width="22%" width="100" valign="top" class="vncellreq">Local IP address</td>
								<td width="78%" class="vtable"> 
									<input name="pptp_local" type="text" class="formfld unknown" id="pptp_local" size="20"  value="<?=htmlspecialchars($pconfig['pptp_local']);?>">
									/
									<select name="pptp_subnet" class="formselect" id="pptp_subnet">
									<?php for ($i = 31; $i > 0; $i--): ?>
										<option value="<?=$i;?>" <?php if ($i == $pconfig['pptp_subnet']) echo "selected"; ?>>
											<?=$i;?>
										</option>
									<?php endfor; ?>
									</select>
								</td>
							</tr>
							<tr>
								<td width="22%" width="100" valign="top" class="vncellreq">Remote IP address</td>
								<td width="78%" class="vtable">
									<input name="pptp_remote" type="text" class="formfld unknown" id="pptp_remote" size="20" value="<?=htmlspecialchars($pconfig['pptp_remote']);?>">
								</td>
							</tr>
						</table>
					</td>
				</tr>
			    <tr>
					<td colspan="2" valign="top" height="16"></td>
				</tr>
				<tr>
					<td colspan="2" valign="top" class="listtopic">Advanced Options <a href="javascript:swapOptions();" class="navlnk">-</a></td>
				</tr>
				<tr name="advanced" id="postMoreOptions">
					<td colspan="2" style="padding:0px;">
						<table width="100%" border="0" cellpadding="6" cellspacing="0">
							<tr>
								<td width="22%" width="100" valign="top" class="vncell">Bandwidth</td>
								<td class="vtable">
								<input name="bandwidth" type="text" class="formfld unknown" id="bandwidth" size="6" value="<?=htmlspecialchars($pconfig['bandwidth']);?>">&nbsp;(bits/sec)
								<br> <span class="vexpl">Set Bandwidth for each link if links have different bandwidths, otherwise, leave blank.</span>
							  </td>
							</tr>
							<tr>
							  <td width="22%" width="100" valign="top" class="vncell">Link MTU</td>
							  <td class="vtable">
								<input name="bandwidth" type="text" class="formfld unknown" id="bandwidth" size="6" value="<?=htmlspecialchars($pconfig['bandwidth']);?>">
								<br> <span class="vexpl">Set Bandwidth for each link if links have different bandwidths, otherwise, leave blank.</span>
							  </td>
							</tr>
							<tr>
							  <td width="22%" width="100" valign="top" class="vncell">Link MRU</td>
							  <td class="vtable">
								<input name="bandwidth" type="text" class="formfld unknown" id="bandwidth" size="6" value="<?=htmlspecialchars($pconfig['bandwidth']);?>">
								<br> <span class="vexpl">Set Bandwidth for each link if links have different bandwidths, otherwise, leave blank.</span>
							  </td>
							</tr>
						</table>
					</td>
				</tr>
			    
                <tr>
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
