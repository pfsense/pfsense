<?php
/* $Id$ */
/*
	services_igmpproxy.php
	
	Copyright (C) 2009 Ermal Luçi
	Copyright (C) 2004 Scott Ullrich
	All rights reserved.

	originially part of m0n0wall (http://m0n0.ch/wall)
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
	pfSense_MODULE:	dyndns
*/

##|+PRIV
##|*IDENT=page-services-igmpproxy
##|*NAME=Services: Igmpproxy page
##|*DESCR=Allow access to the 'Services: Igmpproxy' page.
##|*MATCH=services_igmpproxy.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['igmpproxy']['igmpentry']))
	$config['igmpproxy']['igmpentry'] = array();

//igmpproxy_sort();
$a_igmpproxy = &$config['igmpproxy']['igmpentry'];

if ($_POST) {

	$pconfig = $_POST;

	$retval = 0;
	/* reload all components that use igmpproxy */
	$retval = services_igmpproxy_configure();

	if(stristr($retval, "error") <> true)
	    $savemsg = get_std_save_message($retval);
	else
	    $savemsg = $retval;

	clear_subsystem_dirty('igmpproxy');
}

if ($_GET['act'] == "del") {
	if ($a_igmpproxy[$_GET['id']]) {
		unset($a_igmpproxy[$_GET['id']]);
		write_config();
		mark_subsystem_dirty('igmpproxy');
		header("Location: services_igmpproxy.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"),gettext("IGMP Proxy"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="services_igmpproxy.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('igmpproxy')): ?><p>
<?php print_info_box_np(gettext("The IGMP entry list has been changed") . ".<br>" . gettext("You must apply the changes in order for them to take effect."));?>
<?php endif; ?>

<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
  <td width="15%" class="listhdrr"><?=gettext("Name");?></td>
  <td width="10%" class="listhdrr"><?=gettext("Type");?></td>
  <td width="25%" class="listhdrr"><?=gettext("Values");?></td>
  <td width="25%" class="listhdr"><?=gettext("Description");?></td>
  <td width="5%" class="list">
    <table border="0" cellspacing="0" cellpadding="1">
      <tr>
	<td valign="middle" width="17">&nbsp;</td>
        <td valign="middle"><a href="services_igmpproxy_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add a new igmpentry");?>"></a></td>
      </tr>
    </table>
  </td>
</tr>
	  <?php $i = 0; foreach ($a_igmpproxy as $igmpentry): ?>
<tr>
  <td class="listlr" ondblclick="document.location='services_igmpproxy_edit.php?id=<?=$i;?>';">
    <?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($igmpentry['ifname']));?>
  </td>
  <td class="listlr" ondblclick="document.location='services_igmpproxy_edit.php?id=<?=$i;?>';">
    <?=htmlspecialchars($igmpentry['type']);?>
  </td>
  <td class="listr" ondblclick="document.location='services_igmpproxy_edit.php?id=<?=$i;?>';">
      <?php
	$addresses = implode(", ", array_slice(explode(" ", $igmpentry['address']), 0, 10));
	echo $addresses;
	if(count($addresses) < 10) {
		echo " ";
	} else {
		echo "...";
	}
    ?>
  </td>
  <td class="listbg" ondblclick="document.location='services_igmpproxy_edit.php?id=<?=$i;?>';">
    <?=htmlspecialchars($igmpentry['descr']);?>&nbsp;
  </td>
  <td valign="middle" nowrap class="list">
    <table border="0" cellspacing="0" cellpadding="1">
      <tr>
        <td valign="middle"><a href="services_igmpproxy_edit.php?id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" title="<?=gettext("edit igmpentry"); ?>"></a></td>
        <td><a href="services_igmpproxy.php?act=del&id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this igmpentry? All elements that still use it will become invalid (e.g. filter rules)!");?>')"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="<?=gettext("delete igmpentry");?>"></a></td>
      </tr>
    </table>
  </td>
</tr>
	  <?php $i++; endforeach; ?>
<tr>
  <td class="list" colspan="4"></td>
  <td class="list">
    <table border="0" cellspacing="0" cellpadding="1">
      <tr>
	<td valign="middle" width="17">&nbsp;</td>
        <td valign="middle"><a href="services_igmpproxy_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add a new igmpentry");?>"></a></td>
        </td>
      </tr>
    </table>
  </td>
</tr>
  <tr>
    <td colspan="2" width="78%">
	<br />
      <input id="submit" name="submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
	<br />
    </td>
  </tr>
<tr>
  <td class="tabcont" colspan="4">
   <p><span class="vexpl"><span class="red"><strong><?=gettext("Note:");?><br></strong></span><?=gettext("Please add the interface for upstream, the allowed subnets, and the downstream interfaces you would like the proxy to allow. Only one 'upstream' interface can be configured.");?></span></p>
  </td>
</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
