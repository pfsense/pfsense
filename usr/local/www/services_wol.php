<?php
/* $Id$ */
/*
	services_wol.php
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
	pfSense_BUILDER_BINARIES:	/usr/local/bin/wol	
	pfSense_MODULE:	wol
*/

##|+PRIV
##|*IDENT=page-services-wakeonlan
##|*NAME=Services: Wake on LAN page
##|*DESCR=Allow access to the 'Services: Wake on LAN' page.
##|*MATCH=services_wol.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['wol']['wolentry'])) {
	$config['wol']['wolentry'] = array();
}
$a_wol = &$config['wol']['wolentry'];

if($_GET['wakeall'] <> "") {
	$i = 0;
	$savemsg = "";
	foreach ($a_wol as $wolent) {
		$mac = $wolent['mac'];
		$if = $wolent['interface'];
		$description = $wolent['descr'];
		$ipaddr = get_interface_ip($if);
		if (!is_ipaddr($ipaddr))
			continue;
		$bcip = gen_subnet_max($ipaddr, get_interface_subnet($if));
		/* Execute wol command and check return code. */
		if (!mwexec("/usr/local/bin/wol -i {$bcip} {$mac}"))
			$savemsg .= sprintf(gettext('Sent magic packet to %1$s (%2$s)%3$s'),$mac, $description, ".<br>");
		else
			$savemsg .= sprintf(gettext('Please check the %1$ssystem log%2$s, the wol command for %3$s (%4$s) did not complete successfully%5$s'),'<a href="/diag_logs.php">','</a>',$description,$mac,".<br>");
	}
}

if ($_POST || $_GET['mac']) {
	unset($input_errors);

	if ($_GET['mac']) {
        	/* normalize MAC addresses - lowercase and convert Windows-ized hyphenated MACs to colon delimited */
        	$_GET['mac'] = strtolower(str_replace("-", ":", $_GET['mac']));
		$mac = $_GET['mac'];
		$if = $_GET['if'];
	} else {
        	/* normalize MAC addresses - lowercase and convert Windows-ized hyphenated MACs to colon delimited */
        	$_POST['mac'] = strtolower(str_replace("-", ":", $_POST['mac']));
		$mac = $_POST['mac'];
		$if = $_POST['interface'];
	}

	/* input validation */
	if (!$mac || !is_macaddr($mac))
		$input_errors[] = gettext("A valid MAC address must be specified.");
	if (!$if)
		$input_errors[] = gettext("A valid interface must be specified.");

	if (!$input_errors) {
		/* determine broadcast address */
		$ipaddr = get_interface_ip($if);
		if (!is_ipaddr($ipaddr))
			$input_errors[] = gettext("A valid ip could not be found!");
		else {
			$bcip = gen_subnet_max($ipaddr, get_interface_subnet($if));
			/* Execute wol command and check return code. */
			if(!mwexec("/usr/local/bin/wol -i {$bcip} " . escapeshellarg($mac)))
				$savemsg .= sprintf(gettext("Sent magic packet to %s."),$mac);
			else
				$savemsg .= sprintf(gettext('Please check the %1$ssystem log%2$s, the wol command for %3$s did not complete successfully%4$s'),'<a href="/diag_logs.php">', '</a>', $mac, ".<br>");
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_wol[$_GET['id']]) {
		unset($a_wol[$_GET['id']]);
		write_config();
		header("Location: services_wol.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"),gettext("Wake on LAN"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
			<form action="services_wol.php" method="post" name="iform" id="iform">
			  <table width="100%" border="0" cellpadding="6" cellspacing="0">
			<tr>
				<td colspan="2" valign="top" class="listtopic"><?=gettext("Wake on LAN");?></td>
			</tr>
			  <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Interface");?></td>
                  <td width="78%" class="vtable">
		     		<select name="interface" class="formselect">
                      <?php 
						$interfaces = get_configured_interface_with_descr();
					  	foreach ($interfaces as $iface => $ifacename): ?>
                      	<option value="<?=$iface;?>" <?php if (!link_interface_to_bridge($iface) && $iface == $if) echo "selected"; ?>>
                      <?=htmlspecialchars($ifacename);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br>
                    <span class="vexpl"><?=gettext("Choose which interface the host to be woken up is connected to.");?></span></td>
                </tr>
                <tr>
				  <td width="22%" valign="top" class="vncellreq"><?=gettext("MAC address");?></td>
				  <td width="78%" class="vtable">
                      <input name="mac" type="text" class="formfld unknown" id="mac" size="20" value="<?=htmlspecialchars($mac);?>">
                      <br>
                      <?=gettext("Enter a MAC address ");?><span class="vexpl"> <?=gettext("in the following format: xx:xx:xx:xx:xx:xx");?></span></td></tr>
				<tr>
				  <td width="22%" valign="top">&nbsp;</td>
				  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Send");?>">
				</td>
				</tr>
			</table>
			&nbsp;<br>
			<?=gettext("Wake all clients at once: ");?><a href="services_wol.php?wakeall=true"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_wol_all.gif" width="17" height="17" border="0"></a><p/>
			<?=gettext("Or Click the MAC address to wake up an individual device:");?>
			<table width="100%" border="0" cellpadding="0" cellspacing="0" class="tabcont">
                <tr>
                  <td width="15%" class="listhdrr"><?=gettext("Interface");?></td>
                  <td width="25%" class="listhdrr"><?=gettext("MAC address");?></td>
                  <td width="50%" class="listhdr"><?=gettext("Description");?></td>
                  <td width="10%" class="list">
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
			<td valign="middle" width="17"></td>
                        <td valign="middle"><a href="services_wol_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
		  </td>
		</tr>
			  <?php $i = 0; foreach ($a_wol as $wolent): ?>
                <tr>
                  <td class="listlr" ondblclick="document.location='services_wol_edit.php?id=<?=$i;?>';">
                    <?=convert_friendly_interface_to_friendly_descr($wolent['interface']);?>
                  </td>
                  <td class="listr" ondblclick="document.location='services_wol_edit.php?id=<?=$i;?>';">
                    <a href="?mac=<?=$wolent['mac'];?>&if=<?=$wolent['interface'];?>"><?=strtolower($wolent['mac']);?></a>
                  </td>
                  <td class="listbg" ondblclick="document.location='services_wol_edit.php?id=<?=$i;?>';">
                    <?=htmlspecialchars($wolent['descr']);?>
                  </td>
                  <td valign="middle" nowrap class="list">
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
                        <td valign="middle"><a href="services_wol_edit.php?id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a></td>
                        <td valign="middle"><a href="services_wol.php?act=del&id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this entry?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
                  </td>
			</tr>
	        <?php $i++; endforeach; ?>
                <tr>
                  <td class="list" colspan="3"></td>
                  <td class="list">
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
			<td valign="middle" width="17"></td>
                        <td valign="middle"><a href="services_wol_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
                  </td>
                  			
			</tr>
              </table>
			<span class="vexpl">
					<span class="red">
						<strong>
							<?=gettext("Note:");?><br>
            			</strong>
					</span><?=gettext("This service can be used to wake up (power on) computers by sending special"); ?> &quot;<?=gettext("Magic Packets"); ?>&quot;. <?=gettext("The NIC in the computer that is to be woken up must support Wake on LAN and has to be configured properly (WOL cable, BIOS settings). ");?>
			</span>

</form>
<?php include("fend.inc"); ?>
</body>
</html>
