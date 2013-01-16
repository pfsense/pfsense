<?php 
/*
	services_captiveportal_mac_edit.php
	part of m0n0wall (http://m0n0.ch/wall)
	
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
	pfSense_MODULE:	captiveportal
*/

##|+PRIV
##|*IDENT=page-services-captiveportal-editmacaddresses
##|*NAME=Services: Captive portal: Edit MAC Addresses page
##|*DESCR=Allow access to the 'Services: Captive portal: Edit MAC Addresses' page.
##|*MATCH=services_captiveportal_mac_edit.php*
##|-PRIV

function passthrumacscmp($a, $b) {
	return strcmp($a['mac'], $b['mac']);
}

function passthrumacs_sort() {
        global $config, $cpzone;

        usort($config['captiveportal'][$cpzone]['passthrumac'],"passthrumacscmp");
}

require("guiconfig.inc");
require("functions.inc");
require("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

$pgtitle = array(gettext("Services"),gettext("Captive portal"),gettext("Edit pass-through MAC address"));
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

if (!is_array($a_cp[$cpzone]['passthrumac']))
	$a_cp[$cpzone]['passthrumac'] = array();
$a_passthrumacs = &$a_cp[$cpzone]['passthrumac'];

if (isset($id) && $a_passthrumacs[$id]) {
	$pconfig['mac'] = $a_passthrumacs[$id]['mac'];
	$pconfig['bw_up'] = $a_passthrumacs[$id]['bw_up'];
	$pconfig['bw_down'] = $a_passthrumacs[$id]['bw_down'];
	$pconfig['descr'] = $a_passthrumacs[$id]['descr'];
	$pconfig['username'] = $a_passthrumacs[$id]['username'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "mac");
	$reqdfieldsn = array(gettext("MAC address"));
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
	
	$_POST['mac'] = str_replace("-", ":", $_POST['mac']);
	
	if (($_POST['mac'] && !is_macaddr($_POST['mac']))) {
		$input_errors[] = sprintf("%s. [%s]", gettext("A valid MAC address must be specified"), $_POST['mac']);
	}
	if ($_POST['bw_up'] && !is_numeric($_POST['bw_up']))
		$input_errors[] = gettext("Upload speed needs to be an integer");
	if ($_POST['bw_down'] && !is_numeric($_POST['bw_down']))
		$input_errors[] = gettext("Download speed needs to be an integer");

	foreach ($a_passthrumacs as $macent) {
		if (isset($id) && ($a_passthrumacs[$id]) && ($a_passthrumacs[$id] === $macent))
			continue;
		
		if ($macent['mac'] == $_POST['mac']){
			$input_errors[] = sprintf("[%s] %s.", $_POST['mac'], gettext("already allowed"));
			break;
		}	
	}

	if (!$input_errors) {
		$mac = array();
		$mac['mac'] = $_POST['mac'];
		if ($_POST['bw_up'])
			$mac['bw_up'] = $_POST['bw_up'];
		if ($_POST['bw_down'])
			$mac['bw_down'] = $_POST['bw_down'];
		if ($_POST['username'])
			$mac['username'] = $_POST['username'];
		
		$mac['descr'] = $_POST['descr'];

		if (isset($id) && $a_passthrumacs[$id]) {
			$oldmac = $a_passthrumacs[$id]['mac'];
			$a_passthrumacs[$id] = $mac;
		} else {
			$oldmac = $mac['mac'];
			$a_passthrumacs[] = $mac;
		}
		passthrumacs_sort();
		
		write_config();

		if (isset($config['captiveportal'][$cpzone]['enable'])) {
			$ruleno = captiveportal_get_ipfw_passthru_ruleno($oldmac);
			if ($ruleno) {
				captiveportal_free_ipfw_ruleno($ruleno);
				$pipeno = captiveportal_get_dn_passthru_ruleno($oldmac);
				if ($pipeno) {
					captiveportal_free_dn_ruleno($pipeno);
					$rules .= "pipe delete {$pipeno}\n";
					++$pipeno;
					$rules .= "pipe delete {$pipeno}\n";
				}
				$rules = "delete {$ruleno}\n";
				$rules .= "delete " . ++$ruleno . "\n";
			}
			
			$rules .= captiveportal_passthrumac_configure_entry($mac);
			file_put_contents("{$g['tmp_path']}/{$cpzone}_tmpmacedit{$id}", $rules);
			captiveportal_ipfw_set_context($cpzone);
			mwexec("/sbin/ipfw -q {$g['tmp_path']}/{$cpzone}_tmpmacedit{$id}");
			@unlink("{$g['tmp_path']}/{$cpzone}_tmpmacedit{$id}");
		}

		header("Location: services_captiveportal_mac.php?zone={$cpzone}");
		exit;
	}
}
include("head.inc");
?>
<?php include("fbegin.inc"); ?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="services_captiveportal_mac_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
                        <td colspan="2" valign="top" class="listtopic"><?=gettext("Edit Pass-through MAC address");?></td>
                </tr>
		<tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("MAC address"); ?></td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="mac" type="text" class="formfld unknown" id="mac" size="17" value="<?=htmlspecialchars($pconfig['mac']);?>">
                    <?php
                        $ip = getenv('REMOTE_ADDR');
                        $mac = `/usr/sbin/arp -an | grep {$ip} | cut -d" " -f4`;
                        $mac = str_replace("\n","",$mac);
                    ?>
                    <a OnClick="document.forms[0].mac.value='<?=$mac?>';" href="#"><?=gettext("Copy my MAC address");?></a>
                    <br> 
                    <span class="vexpl"><?=gettext("MAC address (6 hex octets separated by colons)"); ?></span></td>
                </tr>
		<tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
                  <td width="78%" class="vtable"> 
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
                    <br> <span class="vexpl"><?=gettext("You may enter a description here " .
                    "for your reference (not parsed)"); ?>.</span></td>
                </tr>
		<tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Bandwidth up"); ?></td>
                  <td width="78%" class="vtable"> 
                    <input name="bw_up" type="text" class="formfld unknown" id="bw_up" size="10" value="<?=htmlspecialchars($pconfig['bw_up']);?>">
                    <br> <span class="vexpl"><?=gettext("Enter a upload limit to be enforced on this MAC address in Kbit/s"); ?></span></td>
                </tr>
		<tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Bandwidth down"); ?></td>
                  <td width="78%" class="vtable"> 
                    <input name="bw_down" type="text" class="formfld unknown" id="bw_down" size="10" value="<?=htmlspecialchars($pconfig['bw_down']);?>">
                    <br> <span class="vexpl"><?=gettext("Enter a download limit to be enforced on this MAC address in Kbit/s"); ?></span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>">
                    <input name="zone" type="hidden" value="<?=htmlspecialchars($cpzone);?>">
                    <?php if (isset($id) && $a_passthrumacs[$id]): ?>
                    <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>">
                    <?php endif; ?>
		    <?php if (isset($pconfig['username']) && $pconfig['username']): ?>
                    <input name="username" type="hidden" value="<?=htmlspecialchars($pconfig['username']);?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
