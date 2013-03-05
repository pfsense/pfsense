<?php 
/*
	captive_portal_status.widget.php
	Copyright (C) 2007 Sam Wenham
	All rights reserved.

	status_captiveportal.php
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

$nocsrf = true;

require_once("globals.inc");
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("captiveportal.inc");

?>

<?php

if (($_GET['act'] == "del") && (!empty($_GET['zone']))) {
	$cpzone = $_GET['zone'];
	captiveportal_disconnect_client($_GET['id']);
}

flush();

function clientcmp($a, $b) {
	global $order;
	return strcmp($a[$order], $b[$order]);
}

if (!is_array($config['captiveportal']))
        $config['captiveportal'] = array();
$a_cp =& $config['captiveportal'];

$cpdb_all = array();

foreach ($a_cp as $cpzone => $cp) {
	$cpdb = captiveportal_read_db();
	foreach ($cpdb as $cpent) {
		$cpent[10] = $cpzone;
		if ($_GET['showact'])
			$cpent[11] = captiveportal_get_last_activity($cpent[2]);
		$cpdb_all[] = $cpent;
	}
}

if ($_GET['order']) {
	if ($_GET['order'] == "ip")
		$order = 2;
	else if ($_GET['order'] == "mac")
		$order = 3;
	else if ($_GET['order'] == "user")
               	$order = 4;
	else if ($_GET['order'] == "lastact")
		$order = 5;
	else if ($_GET['order'] == "zone")
		$order = 10;
	else
		$order = 0;
	usort($cpdb_all, "clientcmp");
}
?>
<table class="sortable" name="sortabletable" id="sortabletable" width="100%" border="0" cellpadding="0" cellspacing="0" summary="captive portal status">
  <tr>
    <td class="listhdrr"><a href="?order=ip&amp;showact=<?=$_GET['showact'];?>">IP address</a></td>
    <td class="listhdrr"><a href="?order=mac&amp;showact=<?=$_GET['showact'];?>">MAC address</a></td>
    <td class="listhdrr"><a href="?order=user&amp;showact=<?=$_GET['showact'];?>"><?=gettext("Username");?></a></td>
	<?php if ($_GET['showact']): ?>
    <td class="listhdrr"><a href="?order=start&amp;showact=<?=$_GET['showact'];?>"><?=gettext("Session start");?></a></td>
    <td class="listhdrr"><a href="?order=start&amp;showact=<?=$_GET['showact'];?>"><?=gettext("Last activity");?></a></td>
	<?php endif; ?>
  </tr>
<?php foreach ($cpdb_all as $cpent): ?>
  <tr>
    <td class="listlr"><?=$cpent[2];?></td>
    <td class="listr"><?=$cpent[3];?>&nbsp;</td>
    <td class="listr"><?=$cpent[4];?>&nbsp;</td>
	<?php if ($_GET['showact']): ?>
    <td class="listr"><?=htmlspecialchars(date("m/d/Y H:i:s", $cpent[0]));?></td>
    <td class="listr"><?php if ($cpent[11] && ($cpent[11] > 0)) echo htmlspecialchars(date("m/d/Y H:i:s", $cpent[11]));?></td>
	<?php endif; ?>
	<td valign="middle" class="list nowrap">
	<a href="?order=<?=$_GET['order'];?>&amp;showact=<?=$_GET['showact'];?>&amp;act=del&amp;zone=<?=$cpent[10];?>&amp;id=<?=$cpent[5];?>" onclick="return confirm('Do you really want to disconnect this client?')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" alt="x" /></a></td>
  </tr>
<?php endforeach; ?>
</table>