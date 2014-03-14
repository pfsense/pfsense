<?php 
/* $Id$ */
/*
	services_wol_edit.php
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
	pfSense_MODULE:	wol
*/

##|+PRIV
##|*IDENT=page-services-wakeonlan-edit
##|*NAME=Services: Wake on LAN: Edit page
##|*DESCR=Allow access to the 'Services: Wake on LAN: Edit' page.
##|*MATCH=services_wol_edit.php*
##|-PRIV

function wolcmp($a, $b) {
	return strcmp($a['descr'], $b['descr']);
}

function wol_sort() {
        global $config;

        usort($config['wol']['wolentry'], "wolcmp");
}

require("guiconfig.inc");

if (!is_array($config['wol']['wolentry'])) {
	$config['wol']['wolentry'] = array();
}
$a_wol = &$config['wol']['wolentry'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_wol[$id]) {
	$pconfig['interface'] = $a_wol[$id]['interface'];
	$pconfig['mac'] = $a_wol[$id]['mac'];
	$pconfig['descr'] = $a_wol[$id]['descr'];
}
else
{
	$pconfig['interface'] = $_GET['if'];
	$pconfig['mac'] = $_GET['mac'];
	$pconfig['descr'] = $_GET['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "interface mac");
	$reqdfieldsn = array(gettext("Interface"),gettext("MAC address"));
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

        /* normalize MAC addresses - lowercase and convert Windows-ized hyphenated MACs to colon delimited */
        $_POST['mac'] = strtolower(str_replace("-", ":", $_POST['mac']));
	
	if (($_POST['mac'] && !is_macaddr($_POST['mac']))) {
		$input_errors[] = gettext("A valid MAC address must be specified.");
	}

	if (!$input_errors) {
		$wolent = array();
		$wolent['interface'] = $_POST['interface'];
		$wolent['mac'] = $_POST['mac'];
		$wolent['descr'] = $_POST['descr'];

		if (isset($id) && $a_wol[$id])
			$a_wol[$id] = $wolent;
		else
			$a_wol[] = $wolent;
		wol_sort();
		
		write_config();
		
		header("Location: services_wol.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"),gettext("Wake on LAN"),gettext("Edit"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="services_wol_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Edit WOL entry");?></td>
				</tr>	
			  <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Interface");?></td>
                  <td width="78%" class="vtable">
<select name="interface" class="formfld">
                      <?php 
					  $interfaces = get_configured_interface_with_descr();
					  foreach ($interfaces as $iface => $ifacename): ?>
                      <option value="<?=$iface;?>" <?php if (!link_interface_to_bridge($iface) && $iface == $pconfig['interface']) echo "selected"; ?>> 
                      <?=htmlspecialchars($ifacename);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br />
                    <span class="vexpl"><?=gettext("Choose which interface this host is connected to.");?></span></td>
                </tr>
				<tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("MAC address");?></td>
                  <td width="78%" class="vtable"> 
                    <input name="mac" type="text" class="formfld" id="mac" size="20" value="<?=htmlspecialchars($pconfig['mac']);?>">
                    <br /> 
                    <span class="vexpl"><?=gettext("Enter a MAC address  in the following format: ".
                    "xx:xx:xx:xx:xx:xx");?><em></em></span></td>
                </tr>
				<tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
                  <td width="78%" class="vtable"> 
                    <input name="descr" type="text" class="formfld" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
                    <br /> <span class="vexpl"><?=gettext("You may enter a description here".
                   " for your reference (not parsed).");?></span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>"> <input class="formbtn" type="button" value="<?=gettext("Cancel");?>" onclick="history.back()">
                    <?php if (isset($id) && $a_wol[$id]): ?>
                    <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
