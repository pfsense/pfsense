<?php 
/*
	services_captiveportal_hostname_edit.php
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
	pfSense_MODULE:	captiveportal
*/

##|+PRIV
##|*IDENT=page-services-captiveportal-editallowedhostnames
##|*NAME=Services: Captive portal: Edit Allowed Hostnames page
##|*DESCR=Allow access to the 'Services: Captive portal: Edit Allowed Hostnames' page.
##|*MATCH=services_captiveportal_hostname_edit.php*
##|-PRIV

function allowedhostnamescmp($a, $b) {
	return strcmp($a['hostname'], $b['hostname']);
}

function allowedhostnames_sort() {
	global $g, $config, $cpzone;
	usort($config['captiveportal'][$cpzone]['allowedhostname'],"allowedhostname");
}

require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

$pgtitle = array(gettext("Services"),gettext("Captive portal"),gettext("Edit allowed Hostname"));
$shortcut_section = "captiveportal";

$cpzone = $_GET['zone'];
if (isset($_POST['zone']))
        $cpzone = $_POST['zone'];

if (empty($cpzone) || empty($config['captiveportal'][$cpzone])) {
        header("Location: services_captiveportal_zones.php");
        exit;
}

if (!is_array($config['captiveportal']))
        $config['captiveportal'] = array();
$a_cp =& $config['captiveportal'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (!is_array($a_cp[$cpzone]['allowedhostname']))
	$a_cp[$cpzone]['allowedhostname'] = array();
$a_allowedhostnames = &$a_cp[$cpzone]['allowedhostname'];

if (isset($id) && $a_allowedhostnames[$id]) {
	$pconfig['zone'] = $a_allowedhostnames[$id]['zone'];
	$pconfig['hostname'] = $a_allowedhostnames[$id]['hostname'];
	$pconfig['sn'] = $a_allowedhostnames[$id]['sn'];
	$pconfig['dir'] = $a_allowedhostnames[$id]['dir'];
	$pconfig['bw_up'] = $a_allowedhostnames[$id]['bw_up'];
	$pconfig['bw_down'] = $a_allowedhostnames[$id]['bw_down'];
	$pconfig['descr'] = $a_allowedhostnames[$id]['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "hostname");
	$reqdfieldsn = array(gettext("Allowed Hostname"));
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (($_POST['hostname'] && !is_hostname($_POST['hostname']))) 
		$input_errors[] = sprintf(gettext("A valid Hostname must be specified. [%s]"), $_POST['hostname']);

	if ($_POST['bw_up'] && !is_numeric($_POST['bw_up']))
		$input_errors[] = gettext("Upload speed needs to be an integer");
	if ($_POST['bw_down'] && !is_numeric($_POST['bw_down']))
		$input_errors[] = gettext("Download speed needs to be an integer");

	foreach ($a_allowedhostnames as $ipent) {
		if (isset($id) && ($a_allowedhostnames[$id]) && ($a_allowedhostnames[$id] === $ipent))
			continue;
		
		if ($ipent['hostname'] == $_POST['hostname']){
			$input_errors[] = sprintf("[%s] %s.", $_POST['hostname'], gettext("already allowed")) ;
			break ;
		}	
	}

	if (!$input_errors) {
		$ip = array();
		$ip['hostname'] = $_POST['hostname'];
		$ip['sn'] = $_POST['sn'];
		$ip['dir'] = $_POST['dir'];
		$ip['descr'] = $_POST['descr'];
		if ($_POST['bw_up'])
			$ip['bw_up'] = $_POST['bw_up'];
		if ($_POST['bw_down'])
			$ip['bw_down'] = $_POST['bw_down'];
		if (isset($id) && $a_allowedhostnames[$id])
			$a_allowedhostnames[$id] = $ip;
		else
			$a_allowedhostnames[] = $ip;

		allowedhostnames_sort();
		
		write_config();

		$rules = captiveportal_allowedhostname_configure();
		@file_put_contents("{$g['tmp_path']}/hostname_rules", $rules);
		mwexec("/sbin/ipfw -x {$cpzone} {$g['tmp_path']}/hostname_rules");
		unset($rules);
		
		header("Location: services_captiveportal_hostname.php?zone={$cpzone}");
		exit;
	}
}

include("head.inc");

?>
<?php include("fbegin.inc"); ?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php if ($input_errors) print_input_errors($input_errors); ?>
		<form action="services_captiveportal_hostname_edit.php" method="post" name="iform" id="iform">
		<table width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Direction"); ?></td>
			<td width="78%" class="vtable"> 
			<select name="dir" class="formfld">
		<?php 
			$dirs = array(gettext("Both"),gettext("From"),gettext("To")) ;
			foreach ($dirs as $dir): ?>
				<option value="<?=strtolower($dir);?>" <?php if (strtolower($dir) == strtolower($pconfig['dir'])) echo "selected";?> >
				<?=htmlspecialchars($dir);?>
				</option>
		<?php endforeach; ?>
			</select>
			<br> 
			<span class="vexpl"><?=gettext("Use"); ?> <em><?=gettext("From"); ?></em> <?=gettext("to always allow an Hostname through the captive portal (without authentication)"); ?>. 
				<?=gettext("Use"); ?> <em><?=gettext("To"); ?></em> <?=gettext("to allow access from all clients (even non-authenticated ones) behind the portal to this Hostname"); ?>.</span></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Hostname"); ?></td>
			<td width="78%" class="vtable"> 
				<?=$mandfldhtml;?><input name="hostname" type="text" class="formfld unknown" id="hostname" size="17" value="<?=htmlspecialchars($pconfig['hostname']);?>">
			<br> 
			<span class="vexpl"><?=gettext("Hostname");?>.</span></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
			<td width="78%" class="vtable"> 
			<input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
			<br> <span class="vexpl"><?=gettext("You may enter a description here for your reference (not parsed)"); ?>.</span></td>
			</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Bandwidth up"); ?></td>
			<td width="78%" class="vtable">
			<input name="bw_up" type="text" class="formfld unknown" id="bw_up" size="10" value="<?=htmlspecialchars($pconfig['bw_up']);?>">
			<br> <span class="vexpl"><?=gettext("Enter a upload limit to be enforced on this Hostname in Kbit/s"); ?></span></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Bandwidth down"); ?></td>
			<td width="78%" class="vtable">
			<input name="bw_down" type="text" class="formfld unknown" id="bw_down" size="10" value="<?=htmlspecialchars($pconfig['bw_down']);?>">
			<br> <span class="vexpl"><?=gettext("Enter a download limit to be enforced on this Hostname in Kbit/s"); ?></span></td>
		</tr>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%"> 
				<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>">
				<input name="zone" type="hidden" value="<?=htmlspecialchars($cpzone);?>">
				<?php if (isset($id) && $a_allowedhostnames[$id]): ?>
					<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>">
				<?php endif; ?>
			</td>
		</tr>
	</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
