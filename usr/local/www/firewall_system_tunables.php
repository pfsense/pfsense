<?php
/* $Id$ */
/*
	firewall_virtual_ip.php
	part of pfSense (http://www.pfsense.com/)
	Copyright (C) 2004-2005 Scott Ullrich <geekgod@pfsense.com>.
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

##|+PRIV
##|*IDENT=page-firewall-system-tunables
##|*NAME=Firewall: System: Tunables page
##|*DESCR=Allow access to the 'Firewall: System: Tunables' page.
##|*MATCH=firewall_system_tunables.php*
##|-PRIV


$pgtitle = array("Firewall","System","Tunables");

require("guiconfig.inc");

if (!is_array($config['sysctl']['item'])) {
	$config['sysctl']['item'] = array();
}
$a_tunable = &$config['sysctl']['item'];

if ($_POST) {
	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		$savemsg = get_std_save_message($retval);
		unlink_if_exists($d_sysctldirty_path);
	}
}

if ($_GET['act'] == "del") {
	if ($a_tunable[$_GET['id']]) {
		/* if this is an AJAX caller then handle via JSON */
		if(isAjax() && is_array($input_errors)) {
			input_errors2Ajax($input_errors);
			exit;
		}

		if (!$input_errors) {
			unset($a_tunable[$_GET['id']]);
			write_config();
			touch($d_sysctldirty_path);
			pfSenseHeader("firewall_system_tunables.php");
			exit;
		}
	}
}

include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>
<form action="firewall_system_tunables.php" method="post">
<div id="inputerrors"></div>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_sysctldirty_path)): ?><p>
<?php print_info_box_np("The firewall tunables have changed.  You must apply the configuration to take affect.");?><br />
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="20%" class="listhdrr">Tunable Name</td>
                  <td width="60%" class="listhdrr">Description</td>                 
                  <td width="20%" class="listhdrr">Value</td>
				</tr>
			  <?php $i = 0; foreach ($config['sysctl']['item'] as $tunable): ?>
                <tr>
                  <td class="listlr" ondblclick="document.location='firewall_system_tunables_edit.php?id=<?=$i;?>';">
                  <?php echo $tunable['tunable']; ?>
                  </td>
                  <td class="listlr" align="left" ondblclick="document.location='firewall_system_tunables_edit.php?id=<?=$i;?>';">
                  <?php echo $tunable['desc']; ?>
                  </td>
                  <td class="listlr" align="left" ondblclick="document.location='firewall_system_tunables_edit.php?id=<?=$i;?>';">
                  <?php echo $tunable['value']; ?>
                  </td>
                  <td class="list" nowrap>
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
                        <td valign="middle"><a href="firewall_system_tunables_edit.php?id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" alt="" /></a></td>
                        <td valign="middle"><a href="firewall_system_tunables.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('Do you really want to delete this entry?')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" alt="" /></a></td>
                      </tr>
                    </table>
                  </td>                  
                <?php $i++; endforeach; ?>
                <tr>
                  <td class="list" colspan="3"></td>
                  <td class="list">
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
                        <td valign="middle"><a href="firewall_system_tunables_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="" /></a></td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
	   </div>
	</table>
            </form>
<?php include("fend.inc"); ?>
</body>
</html>
