<?php 
/*
	services_captiveportal_ip_edit.php
	Copyright (C) 2011 Scott Ullrich <sullrich@gmail.com>
	All rights reserved.

	Originally part of m0n0wall (http://m0n0.ch/wall)	
	Copyright (C) 2004 Dinesh Nair <dinesh@alphaque.com>
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
	pfSense_BUILDER_BINARIES:	/sbin/ipfw
	pfSense_MODULE: captiveportal
*/

##|+PRIV
##|*IDENT=page-services-captiveportal-editallowedips
##|*NAME=Services: Captive portal: Edit Allowed IPs page
##|*DESCR=Allow access to the 'Services: Captive portal: Edit Allowed IPs' page.
##|*MATCH=services_captiveportal_ip_edit.php*
##|-PRIV

function allowedipscmp($a, $b) {
	return strcmp($a['ip'], $b['ip']);
}

function allowedips_sort() {
	global $g, $config, $cpzone;

	usort($config['captiveportal'][$cpzone]['allowedip'],"allowedipscmp");
}

require("guiconfig.inc");
require("functions.inc");
require("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

$pgtitle = array(gettext("Services"),gettext("Captive portal"),gettext("Edit allowed IP address"));
$shortcut_section = "captiveportal";

$cpzone = $_GET['zone'];
if (isset($_POST['zone']))
        $cpzone = $_POST['zone'];
                        
if (empty($cpzone)) {
        header("Location: services_captiveportal_zones.php");
        exit;
}

if (!is_array($config['captiveportal']))
        $config['captiveportal'] = array();
$a_cp =& $config['captiveportal'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (!is_array($config['captiveportal'][$cpzone]['allowedip']))
	$config['captiveportal'][$cpzone]['allowedip'] = array();
$a_allowedips =& $config['captiveportal'][$cpzone]['allowedip'];

if (isset($id) && $a_allowedips[$id]) {
	$pconfig['ip'] = $a_allowedips[$id]['ip'];
	$pconfig['sn'] = $a_allowedips[$id]['sn'];
	$pconfig['dir'] = $a_allowedips[$id]['dir'];
	$pconfig['bw_up'] = $a_allowedips[$id]['bw_up'];
	$pconfig['bw_down'] = $a_allowedips[$id]['bw_down'];
	$pconfig['descr'] = $a_allowedips[$id]['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "ip");
	$reqdfieldsn = array(gettext("Allowed IP address"));
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (($_POST['ip'] && !is_ipaddr($_POST['ip']))) 
		$input_errors[] = sprintf(gettext("A valid IP address must be specified. [%s]"), $_POST['ip']);
	
	if ($_POST['bw_up'] && !is_numeric($_POST['bw_up']))
		$input_errors[] = gettext("Upload speed needs to be an integer");

	if ($_POST['bw_down'] && !is_numeric($_POST['bw_down']))
		$input_errors[] = gettext("Download speed needs to be an integer");

	foreach ($a_allowedips as $ipent) {
		if (isset($id) && ($a_allowedips[$id]) && ($a_allowedips[$id] === $ipent))
			continue;
		
		if ($ipent['ip'] == $_POST['ip']){
			$input_errors[] = sprintf("[%s] %s.", $_POST['ip'], gettext("already allowed")) ;
			break ;
		}	
	}

	if (!$input_errors) {
		$ip = array();
		$ip['ip'] = $_POST['ip'];
		$ip['sn'] = $_POST['sn'];
		$ip['dir'] = $_POST['dir'];
		$ip['descr'] = $_POST['descr'];
		if ($_POST['bw_up'])
			$ip['bw_up'] = $_POST['bw_up'];
		if ($_POST['bw_down'])
			$ip['bw_down'] = $_POST['bw_down'];
		if (isset($id) && $a_allowedips[$id]) {
			$oldip = $a_allowedips[$id]['ip'];
			if (!empty($a_allowedips[$id]['sn']))
				$oldip .= "/{$a_allowedips[$id]['sn']}";
			$a_allowedips[$id] = $ip;
		} else {
			$oldip = $ip['ip'];
			if (!empty($ip['sn']))
				$oldip .= "/{$ip['sn']}";
			$a_allowedips[] = $ip;
		}
		allowedips_sort();
		
		write_config();

		if (isset($a_cp[$cpzone]['enable']) && is_module_loaded("ipfw.ko")) {
			$rules = "";
			for ($i = 3; $i < 10; $i++)
				$rules .= "table {$i} delete {$oldip}\n";
			$rules .= captiveportal_allowedip_configure_entry($ip);
			file_put_contents("{$g['tmp_path']}/{$cpzone}_allowedip_tmp{$id}", $rules);
			captiveportal_ipfw_set_context($cpzone);
			mwexec("/sbin/ipfw -q {$g['tmp_path']}/{$cpzone}_allowedip_tmp{$id}");
			@unlink("{$g['tmp_path']}/{$cpzone}_allowedip_tmp{$id}");
		}
		
		header("Location: services_captiveportal_ip.php?zone={$cpzone}");
		exit;
	}
}

include("head.inc");

?>
<?php include("fbegin.inc"); ?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php if ($input_errors) print_input_errors($input_errors); ?>
		<form action="services_captiveportal_ip_edit.php" method="post" name="iform" id="iform">
		<table width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
                        <td colspan="2" valign="top" class="listtopic"><?=gettext("Edit allowed ip rule");?></td>
                </tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Direction"); ?></td>
			<td width="78%" class="vtable"> 
			<select name="dir" class="formfld">
		<?php 
			$dirs = array(gettext("Both"),gettext("From"),gettext("To")) ;
			foreach ($dirs as $dir): 
		?>
				<option value="<?=strtolower($dir);?>" <?php if (strtolower($dir) == strtolower($pconfig['dir'])) echo "selected";?> >
				<?=htmlspecialchars($dir);?>
				</option>
		<?php endforeach; ?>
			</select>
			<br> 
			<span class="vexpl"><?=gettext("Use"); ?> <em><?=gettext("From"); ?></em> <?=gettext("to always allow an IP address through the captive portal (without authentication)"); ?>. 
			<?=gettext("Use"); ?> <em><?=gettext("To"); ?></em> <?=gettext("to allow access from all clients (even non-authenticated ones) behind the portal to this IP address"); ?>.</span></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("IP address"); ?></td>
			<td width="78%" class="vtable"> 
				<?=$mandfldhtml;?><input name="ip" type="text" class="formfld unknown" id="ip" size="17" value="<?=htmlspecialchars($pconfig['ip']);?>">
				/<select name='sn' class="formselect" id='sn'>
				<?php for ($i = 32; $i >= 1; $i--): ?>
					<option value="<?=$i;?>" <?php if ($i == $pconfig['sn']) echo "selected"; ?>><?=$i;?></option>
				<?php endfor; ?>
				</select>
				<br> 
				<span class="vexpl"><?=gettext("IP address and subnet mask. Use /32 for a single IP");?>.</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
			<td width="78%" class="vtable"> 
				<input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
				<br> <span class="vexpl"><?=gettext("You may enter a description here for your reference (not parsed)"); ?>.</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Bandwidth up"); ?></td>
			<td width="78%" class="vtable">
			<input name="bw_up" type="text" class="formfld unknown" id="bw_up" size="10" value="<?=htmlspecialchars($pconfig['bw_up']);?>">
			<br> <span class="vexpl"><?=gettext("Enter a upload limit to be enforced on this IP address in Kbit/s"); ?></span>
		</td>
		</tr>
		<tr>
		 <td width="22%" valign="top" class="vncell"><?=gettext("Bandwidth down"); ?></td>
		 <td width="78%" class="vtable">
			<input name="bw_down" type="text" class="formfld unknown" id="bw_down" size="10" value="<?=htmlspecialchars($pconfig['bw_down']);?>">
			<br> <span class="vexpl"><?=gettext("Enter a download limit to be enforced on this IP address in Kbit/s"); ?></span>
		</td>
		</tr>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%"> 
				<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>">
				<input name="zone" type="hidden" value="<?=htmlspecialchars($cpzone);?>">
				<?php if (isset($id) && $a_allowedips[$id]): ?>
					<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>">
				<?php endif; ?>
			</td>
		</tr>
	  </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
