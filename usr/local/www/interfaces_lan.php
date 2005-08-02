#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	interfaces_lan.php
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

require("guiconfig.inc");

$lancfg = &$config['interfaces']['lan'];
$pconfig['ipaddr'] = $lancfg['ipaddr'];
$pconfig['subnet'] = $lancfg['subnet'];
$pconfig['bridge'] = $optcfg['bridge'];
$pconfig['bandwidth'] = $lancfg['bandwidth'];
$pconfig['bandwidthtype'] = $lancfg['bandwidthtype'];

/* Wireless interface? */
if (isset($lancfg['wireless'])) {
	require("interfaces_wlan.inc");
	wireless_config_init();
}

if ($_POST) {

	if ($_POST['bridge']) {
		/* double bridging? */
		for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
			if ($i != $index) {
				if ($config['interfaces']['opt' . $i]['bridge'] == $_POST['bridge']) {
					$input_errors[] = "Optional interface {$i} " .
						"({$config['interfaces']['opt' . $i]['descr']}) is already bridged to " .
						"the specified interface.";
				} else if ($config['interfaces']['opt' . $i]['bridge'] == "opt{$index}") {
					$input_errors[] = "Optional interface {$i} " .
						"({$config['interfaces']['opt' . $i]['descr']}) is already bridged to " .
						"this interface.";
				}
			}
		}
		if ($config['interfaces'][$_POST['bridge']]['bridge']) {
			$input_errors[] = "The specified interface is already bridged to " .
				"another interface.";
		}
		/* captive portal on? */
		if (isset($config['captiveportal']['enable'])) {
			$input_errors[] = "Interfaces cannot be bridged while the captive portal is enabled.";
		}
	} else {

		unset($input_errors);
		$pconfig = $_POST;
		$changedesc = "LAN Interface: ";
	
		/* input validation */
		$reqdfields = explode(" ", "ipaddr subnet");
		$reqdfieldsn = explode(",", "IP address,Subnet bit count");
	
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
		if (($_POST['ipaddr'] && !is_ipaddr($_POST['ipaddr']))) {
			$input_errors[] = "A valid IP address must be specified.";
		}
		if (($_POST['subnet'] && !is_numeric($_POST['subnet']))) {
			$input_errors[] = "A valid subnet bit count must be specified.";
		}
	
		/* Wireless interface? */
		if (isset($lancfg['wireless'])) {
			$wi_input_errors = wireless_config_post();
			if ($wi_input_errors) {
				$input_errors = array_merge($input_errors, $wi_input_errors);
			}
		}
	
		if (!$input_errors) {
			$optcfg['bridge'] = $_POST['bridge'];
			if (($lancfg['ipaddr'] != $_POST['ipaddr']) || ($lancfg['subnet'] != $_POST['subnet'])) {
				update_if_changed("IP Address", &$lancfg['ipaddr'], $_POST['ipaddr']);
				update_if_changed("subnet", &$lancfg['subnet'], $_POST['subnet']);
	
				/* We'll need to reboot after this */
				touch($d_sysrebootreqd_path);
			}
	
			if($_POST['bandwidth'] <> "" and $_POST['bandwidthtype'] <> "") {
				update_if_changed("bandwidth", &$lancfg['bandwidth'], $_POST['bandwidth']);
				update_if_changed("bandwidth type", &$lancfg['bandwidthtype'], $_POST['bandwidthtype']);
			} else {
				unset($lancfg['bandwidth']);
				unset($lancfg['bandwidthtype']);
			}
	
			$dhcpd_was_enabled = 0;
			if (isset($config['dhcpd']['enable'])) {
				unset($config['dhcpd']['enable']);
				$dhcpd_was_enabled = 1;
				$changedesc .= " DHCP disabled";
			}
	
			write_config($changedesc);
	
					
			if ($dhcpd_was_enabled)
				$savemsg .= "<br>Note that the DHCP server has been disabled.<br>Please review its configuration " .
					"and enable it again prior to rebooting.";
			else
				$savemsg = "The changes have been applied.  You may need to correct the web browsers ip address.";
		}
	}
}


$pgtitle = "Interfaces: LAN";
include("head.inc");

?>
<script type="text/javascript" language="javascript" src="ip_helper.js">
</script>

<script language="JavaScript">
<!--
function ipaddr_change() {
	document.iform.subnet.value = gen_bits_lan(document.iform.ipaddr.value);
}
function enable_change(enable_over) {
	var endis;
	endis = !((document.iform.bridge.selectedIndex == 0) || enable_over);
	document.iform.ipaddr.disabled = endis;
	document.iform.subnet.disabled = endis;
}
// -->
</script>


<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
            <form action="interfaces_lan.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
                  <td width="22%" valign="top" class="vncellreq">Bridge with</td>
                  <td width="78%" class="vtable">
			<select name="bridge" class="formfld" id="bridge" onChange="enable_change(false)">
				  	<option <?php if (!$pconfig['bridge']) echo "selected";?> value="">none</option>
                      <?php $opts = array('lan' => "LAN", 'wan' => "WAN");
					  	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
							if ($i != $index)
								$opts['opt' . $i] = "Optional " . $i . " (" .
									$config['interfaces']['opt' . $i]['descr'] . ")";
						}
					foreach ($opts as $opt => $optname): ?>
                      <option <?php if ($opt == $pconfig['bridge']) echo "selected";?> value="<?=htmlspecialchars($opt);?>">
                      <?=htmlspecialchars($optname);?>
                      </option>
                      <?php endforeach; ?>
                    </select> </td>
		</tr>	      
                <tr>
                  <td width="22%" valign="top" class="vncellreq">IP address</td>
                  <td width="78%" class="vtable">
                    <input name="ipaddr" type="text" class="formfld" id="hostname" size="20" value="<?=htmlspecialchars($pconfig['ipaddr']);?>" onchange="ipaddr_change()">
                    /
                    <select name="subnet" class="formfld" id="subnet">
					<?php
					for ($i = 32; $i > 0; $i--) {
						if($i <> 31) {
							echo "<option value=\"{$i}\" ";
							if ($i == $pconfig['subnet']) echo "selected";
							echo ">" . $i . "</option>";
						}
					}
					?>
                    </select></td>
                </tr>
				<?php /* Wireless interface? */
				if (isset($lancfg['wireless']))
					wireless_config_print();
				?>

                <tr>
                  <td colspan="2" valign="top" height="16"></td>
                </tr>

                <tr>
                  <td colspan="2" valign="top" class="vnsepcell">Bandwidth Management (Traffic Shaping)</td>
                </tr>
                <tr>
                  <td valign="top" class="vncell">Interface Bandwidth Speed</td>
                  <td class="vtable"> <input name="bandwidth" type="text" class="formfld" id="bandwidth" size="30" value="<?=htmlspecialchars($pconfig['bandwidth']);?>">
			<select name="bandwidthtype">
				<option value="<?=htmlspecialchars($pconfig['bandwidthtype']);?>"><?=htmlspecialchars($pconfig['bandwidthtype']);?></option>
				<option value="b">bit/s</option>
				<option value="Kb">Kilobit/s</option>
				<option value="Mb">Megabit/s</option>
				<option value="Gb">Gigabit/s</option>
				<option value=""></option>
			</select>
			<br> The bandwidth setting will define the speed of the interface for traffic shaping.
		  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="Save">
                  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"><span class="vexpl"><span class="red"><strong>Warning:<br>
                    </strong></span>after you click &quot;Save&quot;, you will need
                    to do one or more of the following steps before you can
                    access your firewall again:
                    <ul>
                      <li>change the IP address of your computer</li>
                      <li>renew its DHCP lease</li>
                      <li>access the webGUI with the new IP address</li>
                    </ul>
                    </span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"><span class="vexpl"><span class="red"><strong>Note:<br>
                    </strong></span>be sure to add <a href="firewall_rules.php">firewall rules</a> to permit traffic
                    through the interface. You also need firewall rules for an interface in
                    bridged mode as the firewall acts as a filtering bridge.</span></td>
                </tr>		
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>

<?php

if ($_POST) {

	/*   Change these items late in the script
	 *   so the script will fully complete to
         *   the users web browser
	 */

	/* set up LAN interface */
	interfaces_lan_configure();

	interfaces_vlan_configure();
	
	/* setup carp interfaces */
	interfaces_carp_configure();

	/* bring up carp interfaces */
	interfaces_carp_bringup();	
	
}

?>