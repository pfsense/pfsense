#!/usr/local/bin/php
<?php 
/*
	interfaces_opt.php
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

unset($index);
if ($_GET['index'])
	$index = $_GET['index'];
else if ($_POST['index'])
	$index = $_POST['index'];
	
if (!$index)
	exit;

$optcfg = &$config['interfaces']['opt' . $index];
$pconfig['descr'] = $optcfg['descr'];
$pconfig['bridge'] = $optcfg['bridge'];
$pconfig['ipaddr'] = $optcfg['ipaddr'];
$pconfig['subnet'] = $optcfg['subnet'];
$pconfig['enable'] = isset($optcfg['enable']);

/* Wireless interface? */
if (isset($optcfg['wireless'])) {
	require("interfaces_wlan.inc");
	wireless_config_init();
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable']) {
	
		/* description unique? */
		for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
			if ($i != $index) {
				if ($config['interfaces']['opt' . $i]['descr'] == $_POST['descr']) {
					$input_errors[] = "An interface with the specified description already exists.";
				}
			}
		}
		
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
			$reqdfields = explode(" ", "descr ipaddr subnet");
			$reqdfieldsn = explode(",", "Description,IP address,Subnet bit count");
		
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
		
			if (($_POST['ipaddr'] && !is_ipaddr($_POST['ipaddr']))) {
				$input_errors[] = "A valid IP address must be specified.";
			}
			if (($_POST['subnet'] && !is_numeric($_POST['subnet']))) {
				$input_errors[] = "A valid subnet bit count must be specified.";
			}
		}
	}
	
	/* Wireless interface? */
	if (isset($optcfg['wireless'])) {
		$wi_input_errors = wireless_config_post();
		if ($wi_input_errors) {
			$input_errors = array_merge($input_errors, $wi_input_errors);
		}
	}
	
	if (!$input_errors) {
		$optcfg['descr'] = $_POST['descr'];
		$optcfg['ipaddr'] = $_POST['ipaddr'];
		$optcfg['subnet'] = $_POST['subnet'];
		$optcfg['bridge'] = $_POST['bridge'];
		$optcfg['enable'] = $_POST['enable'] ? true : false;
			
		write_config();
		
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			config_lock();
			$retval = interfaces_optional_configure();
			
			/* is this the captive portal interface? */
			if (isset($config['captiveportal']['enable']) && 
				($config['captiveportal']['interface'] == ('opt' . $index))) {
				captiveportal_configure();
			}
			config_unlock();
		}
		$savemsg = get_std_save_message($retval);
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Interfaces: Optional $index (" . htmlspecialchars($optcfg['descr']) . ")");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
<script language="JavaScript">
<!--
function enable_change(enable_over) {
	if ((document.iform.bridge.selectedIndex == 0) || enable_over) {
		document.iform.ipaddr.disabled = 0;
		document.iform.subnet.disabled = 0;
	} else {
		document.iform.ipaddr.disabled = 1;
		document.iform.subnet.disabled = 1;
	}
}
function gen_bits(ipaddr) {
    if (ipaddr.search(/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/) != -1) {
        var adr = ipaddr.split(/\./);
        if (adr[0] > 255 || adr[1] > 255 || adr[2] > 255 || adr[3] > 255)
            return 0;
        if (adr[0] == 0 && adr[1] == 0 && adr[2] == 0 && adr[3] == 0)
            return 0;
		
		if (adr[0] <= 127)
			return 23;
		else if (adr[0] <= 191)
			return 15;
		else
			return 7;
    }
    else
        return 0;
}
function ipaddr_change() {
	document.iform.subnet.selectedIndex = gen_bits(document.iform.ipaddr.value);
}
//-->
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Interfaces: Optional <?=$index;?> (<?=htmlspecialchars($optcfg['descr']);?>)</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if ($optcfg['if']): ?>
            <form action="interfaces_opt.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td width="22%" valign="top" class="vtable">&nbsp;</td>
                  <td width="78%" class="vtable">
<input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable']) echo "checked"; ?> onClick="enable_change(false)">
                    <strong>Enable Optional <?=$index;?> interface</strong></td>
				</tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">Description</td>
                  <td width="78%" class="vtable"> 
                    <input name="descr" type="text" class="formfld" id="descr" size="30" value="<?=htmlspecialchars($pconfig['descr']);?>">
					<br> <span class="vexpl">Enter a description (name) for the interface here.</span>
				 </td>
				</tr>
                <tr> 
                  <td colspan="2" valign="top" height="16"></td>
				</tr>
				<tr> 
                  <td colspan="2" valign="top" class="vnsepcell">IP configuration</td>
				</tr>
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
                    <input name="ipaddr" type="text" class="formfld" id="ipaddr" size="20" value="<?=htmlspecialchars($pconfig['ipaddr']);?>" onchange="ipaddr_change()">
                    /
                	<select name="subnet" class="formfld" id="subnet">
					<?php for ($i = 31; $i > 0; $i--): ?>
					<option value="<?=$i;?>" <?php if ($i == $pconfig['subnet']) echo "selected"; ?>><?=$i;?></option>
					<?php endfor; ?>
                    </select>
				 </td>
				</tr>
				<?php /* Wireless interface? */
				if (isset($optcfg['wireless']))
					wireless_config_print();
				?>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="index" type="hidden" value="<?=$index;?>"> 
				  <input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)"> 
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"><span class="vexpl"><span class="red"><strong>Note:<br>
                    </strong></span>be sure to add firewall rules to permit traffic 
                    through the interface. Firewall rules for an interface in 
                    bridged mode have no effect on packets to hosts other than 
                    m0n0wall itself, unless &quot;Enable filtering bridge&quot; 
                    is checked on the <a href="system_advanced.php">System: 
                    Advanced functions</a> page.</span></td>
                </tr>
              </table>
</form>
<script language="JavaScript">
<!--
enable_change(false);
//-->
</script>
<?php else: ?>
<p><strong>Optional <?=$index;?> has been disabled because there is no OPT<?=$index;?> interface.</strong></p>
<?php endif; ?>
<?php include("fend.inc"); ?>
</body>
</html>
