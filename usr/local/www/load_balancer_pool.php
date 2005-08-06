#!/usr/local/bin/php
<?php
/* $Id */
/*
	load_balancer_pool.php
	part of pfSense (http://www.pfsense.com/)

	Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
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

if (!is_array($config['load_balancer']['lbpool'])) {
	$config['load_balancer']['lbpool'] = array();
}
$a_pool = &$config['load_balancer']['lbpool'];

if ($_POST) {
	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			config_lock();
			$retval |= filter_configure();
			config_unlock();
		}
		$savemsg = get_std_save_message($retval);
		//unlink_if_exists($d_poolconfdirty_path);
	}
}

if ($_GET['act'] == "del") {
	if ($a_pool[$_GET['id']]) {
		/* make sure no virtual servers reference this entry */
		if (is_array($config['load_balancer']['virtual_server'])) {
			foreach ($config['load_balancer']['virtual_server'] as $vs) {
				if ($vs['pool'] == $a_pool[$_GET['id']]['name']) {
					$input_errors[] = "This entry cannot be deleted because it is still referenced by at least one virtual server.";
					break;
				}
			}
		}

		if (!$input_errors) {
			unset($a_pool[$_GET['id']]);
			write_config();
			touch($d_poolconfdirty_path);
			header("Location: load_balancer_pool.php");
			exit;
		}
	}
}

$pgtitle = "Load Balancer: Pool";
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<form action="load_balancer_pool.php" method="post">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_vipconfdirty_path)): ?><p>
<?php print_info_box_np("The load balancer configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
  <?php
        /* active tabs */
        $tab_array = array();
        $tab_array[] = array("Pools", true, "load_balancer_pool.php");
        $tab_array[] = array("Virtual Servers", false, "load_balancer_virtual_server.php");
        display_top_tabs($tab_array);
  ?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="10%" class="listhdrr">Name</td>
                  <td width="25%" class="listhdrr">Servers</td>
                  <td width="10%" class="listhdrr">Port</td>
                  <td width="15%" class="listhdrr">Monitor</td>
                  <td width="30%" class="listhdr">Description</td>
                  <td width="10%" class="list"></td>
				</tr>
			  <?php $i = 0; foreach ($a_pool as $vipent): ?>
                <tr>
                  <td class="listlr" ondblclick="document.location='load_balancer_pool_edit.php?id=<?=$i;?>';">
				<?=$vipent['name'];?>
                  </td>
                  <td class="listlr" align="center" ondblclick="document.location='load_balancer_pool_edit.php?id=<?=$i;?>';">
			  <?php foreach ($vipent['servers'] as $server): ?>
				<?=$server;?><br>
			<?php endforeach; ?>
                  </td>
                  <td class="listlr" ondblclick="document.location='load_balancer_pool_edit.php?id=<?=$i;?>';">
				<?=$vipent['port'];?>
                  </td>
                  <td class="listlr" ondblclick="document.location='load_balancer_pool_edit.php?id=<?=$i;?>';">
				<?=$vipent['monitor'];?>
                  </td>
                  <td class="listbg" ondblclick="document.location='load_balancer_pool_edit.php?id=<?=$i;?>';">
				<font color="#FFFFFF"><?=$vipent['desc'];?></font>
                  </td>
                  <td class="list" nowrap>
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
                        <td valign="middle"><a href="load_balancer_pool_edit.php?id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a></td>
                        <td valign="middle"><a href="load_balancer_pool.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this entry?')"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <?php $i++; endforeach; ?>
                <tr>
                  <td class="list" colspan="5"></td>
                  <td class="list">
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
                        <td valign="middle"><a href="load_balancer_pool_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
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
