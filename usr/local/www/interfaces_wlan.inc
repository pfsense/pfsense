<?php 
/*
	interfaces_wlan.inc
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

function wireless_config_init() {
	global $optcfg, $pconfig;
	
	$pconfig['mode'] = $optcfg['wireless']['mode'];
	$pconfig['ssid'] = $optcfg['wireless']['ssid'];
	$pconfig['stationname'] = $optcfg['wireless']['stationname'];
	$pconfig['channel'] = $optcfg['wireless']['channel'];
	$pconfig['wep_enable'] = isset($optcfg['wireless']['wep']['enable']);
	
	if (is_array($optcfg['wireless']['wep']['key'])) {
		$i = 1;
		foreach ($optcfg['wireless']['wep']['key'] as $wepkey) {
			$pconfig['key' . $i] = $wepkey['value'];
			if (isset($wepkey['txkey']))
				$pconfig['txkey'] = $i;
			$i++;
		}
		if (!isset($wepkey['txkey']))
			$pconfig['txkey'] = 1;
	}
}

function wireless_config_post() {
	global $optcfg, $pconfig;

	unset($input_errors);

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = explode(" ", "mode ssid channel");
		$reqdfieldsn = explode(",", "Mode,SSID,Channel");
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
		
		if (!$input_errors) {
			/* bridge check (hostap only!) */
			if ($pconfig['bridge'] && ($pconfig['mode'] != "hostap"))
				$input_errors[] = "Bridging a wireless interface is only possible in hostap mode.";
		}
	}

	if (!$input_errors) {
	
		$optcfg['wireless']['mode'] = $_POST['mode'];
		$optcfg['wireless']['ssid'] = $_POST['ssid'];
		$optcfg['wireless']['stationname'] = $_POST['stationname'];
		$optcfg['wireless']['channel'] = $_POST['channel'];
		$optcfg['wireless']['wep']['enable'] = $_POST['wep_enable'] ? true : false;
		
		$optcfg['wireless']['wep']['key'] = array();
		for ($i = 1; $i <= 4; $i++) {
			if ($_POST['key' . $i]) {
				$newkey = array();
				$newkey['value'] = $_POST['key' . $i];
				if ($_POST['txkey'] == $i)
					$newkey['txkey'] = true;
				$optcfg['wireless']['wep']['key'][] = $newkey;
			}
		}
	}
	
	return $input_errors;
}

function wireless_config_print() {
	global $optcfg, $pconfig;
?>
                <tr> 
                  <td colspan="2" valign="top" height="16"></td>
				</tr>
                <tr> 
                  <td colspan="2" valign="top" class="vnsepcell">Wireless configuration</td>
				</tr>
                <tr> 
                  <td valign="top" class="vncellreq">Mode</td>
                  <td class="vtable"> <select name="mode" class="formfld" id="mode">
                      <?php 
						$opts = array();
						if (strstr($optcfg['if'], "wi"))
							$opts[] = "hostap";
						$opts[] = "BSS";
						$opts[] = "IBSS";
				foreach ($opts as $opt): ?>
                      <option <?php if ($opt == $pconfig['mode']) echo "selected";?>> 
                      <?=htmlspecialchars($opt);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br>
                    Note: IBSS mode is sometimes also called &quot;ad-hoc&quot; 
                    mode;<br>
                    BSS mode is also known as &quot;infrastructure&quot; mode</td>
				</tr>
                <tr> 
                  <td valign="top" class="vncellreq">SSID</td>
                  <td class="vtable"><input name="ssid" type="text" class="formfld" id="ssid" size="20" value="<?=htmlspecialchars($pconfig['ssid']);?>">
                  </td>
                </tr>
                <tr> 
                  <td valign="top" class="vncellreq">Channel</td>
                  <td class="vtable"><select name="channel" class="formfld" id="channel">
                      <?php
					  for ($i = 0; $i <= 14; $i++): ?>
                      <option <?php if ($i == $pconfig['channel']) echo "selected";?>>
                      <? echo($i==0 ? "Auto" : $i) ?>
                      </option>
                      <?php endfor; ?>
                    </select></td>
                </tr>
                <tr> 
                  <td valign="top" class="vncell">Station name</td>
                  <td class="vtable"><input name="stationname" type="text" class="formfld" id="stationname" size="20" value="<?=htmlspecialchars($pconfig['stationname']);?>"> 
                    <br>
                    Hint: this field can usually be left blank</td>
                </tr>
                <tr> 
                  <td valign="top" class="vncell">WEP</td>
                  <td class="vtable"> <input name="wep_enable" type="checkbox" id="wep_enable" value="yes" <?php if ($pconfig['wep_enable'] == "yes") echo "checked"; ?>> 
                    <strong>Enable WEP</strong><br>
                    &nbsp; <table border="0" cellspacing="0" cellpadding="0">
                      <tr> 
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;TX key&nbsp;</td>
                      </tr>
                      <tr> 
                        <td>Key 1:&nbsp;&nbsp;</td>
                        <td> <input name="key1" type="text" class="formfld" id="key1" size="30" value="<?=htmlspecialchars($pconfig['key1']);?>"></td>
                        <td align="center"> <input name="txkey" type="radio" value="1" <?php if ($pconfig['txkey'] == 1) echo "checked";?>> 
                        </td>
                      </tr>
                      <tr> 
                        <td>Key 2:&nbsp;&nbsp;</td>
                        <td> <input name="key2" type="text" class="formfld" id="key2" size="30" value="<?=htmlspecialchars($pconfig['key2']);?>"></td>
                        <td align="center"> <input name="txkey" type="radio" value="2" <?php if ($pconfig['txkey'] == 2) echo "checked";?>></td>
                      </tr>
                      <tr> 
                        <td>Key 3:&nbsp;&nbsp;</td>
                        <td> <input name="key3" type="text" class="formfld" id="key3" size="30" value="<?=htmlspecialchars($pconfig['key3']);?>"></td>
                        <td align="center"> <input name="txkey" type="radio" value="3" <?php if ($pconfig['txkey'] == 3) echo "checked";?>></td>
                      </tr>
                      <tr> 
                        <td>Key 4:&nbsp;&nbsp;</td>
                        <td> <input name="key4" type="text" class="formfld" id="key4" size="30" value="<?=htmlspecialchars($pconfig['key4']);?>"></td>
                        <td align="center"> <input name="txkey" type="radio" value="4" <?php if ($pconfig['txkey'] == 4) echo "checked";?>></td>
                      </tr>
                    </table>
                    <br>
                    40 (64) bit keys may be entered as 5 ASCII characters or 10 
                    hex digits preceded by '0x'.<br>
                    104 (128) bit keys may be entered as 13 ASCII characters or 
                    26 hex digits preceded by '0x'.</td>
                </tr>
<?php } ?>
