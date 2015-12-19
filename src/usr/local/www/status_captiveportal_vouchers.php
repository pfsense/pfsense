<?php
/*
	status_captiveportal_vouchers.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2007 Marcel Wiget <mwiget@mac.com>.
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-status-captiveportal-vouchers
##|*NAME=Status: Captive portal Vouchers
##|*DESCR=Allow access to the 'Status: Captive portal Vouchers' page.
##|*MATCH=status_captiveportal_vouchers.php*
##|-PRIV

require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require("captiveportal.inc");
require_once("voucher.inc");

$cpzone = $_GET['zone'];
if (isset($_POST['zone'])) {
	$cpzone = $_POST['zone'];
}

if (empty($cpzone)) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

if (!is_array($config['captiveportal'])) {
	$config['captiveportal'] = array();
}
$a_cp =& $config['captiveportal'];
$pgtitle = array(gettext("Status"), gettext("Captive portal"), gettext("Vouchers"), $a_cp[$cpzone]['zone']);
$shortcut_section = "captiveportal-vouchers";

function clientcmp($a, $b) {
	global $order;
	return strcmp($a[$order], $b[$order]);
}

if (!is_array($config['voucher'][$cpzone]['roll'])) {
	$config['voucher'][$cpzone]['roll'] = array();
}

$a_roll = $config['voucher'][$cpzone]['roll'];

$db = array();

foreach ($a_roll as $rollent) {
	$roll = $rollent['number'];
	$minutes = $rollent['minutes'];

	if (!file_exists("{$g['vardb_path']}/voucher_{$cpzone}_active_$roll.db")) {
		continue;
	}

	$active_vouchers = file("{$g['vardb_path']}/voucher_{$cpzone}_active_$roll.db", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach ($active_vouchers as $voucher => $line) {
		list($voucher, $timestamp, $minutes) = explode(",", $line);
		$remaining = (($timestamp + 60*$minutes) - time());

		if ($remaining > 0) {
			$dbent[0] = $voucher;
			$dbent[1] = $roll;
			$dbent[2] = $timestamp;
			$dbent[3] = intval($remaining/60);
			$dbent[4] = $timestamp + 60*$minutes; // expires at
			$db[] = $dbent;
		}
	}
}

if ($_GET['order']) {
	$order = $_GET['order'];
	usort($db, "clientcmp");
}

include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Active Users"), false, "status_captiveportal.php?zone={$cpzone}");
$tab_array[] = array(gettext("Active Vouchers"), true, "status_captiveportal_vouchers.php?zone={$cpzone}");
$tab_array[] = array(gettext("Voucher Rolls"), false, "status_captiveportal_voucher_rolls.php?zone={$cpzone}");
$tab_array[] = array(gettext("Test Vouchers"), false, "status_captiveportal_test.php?zone={$cpzone}");
$tab_array[] = array(gettext("Expire Vouchers"), false, "status_captiveportal_expire.php?zone={$cpzone}");
display_top_tabs($tab_array);
?>

<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<thead>
			<tr>
				<th><a href="?order=0&amp;showact=<?=htmlspecialchars($_GET['showact'])?>"><?=gettext("Voucher"); ?></a></th>
				<th><a href="?order=1&amp;showact=<?=htmlspecialchars($_GET['showact'])?>"><?=gettext("Roll"); ?></a></th>
				<th><a href="?order=2&amp;showact=<?=htmlspecialchars($_GET['showact'])?>"><?=gettext("Activated at"); ?></a></th>
				<th><a href="?order=3&amp;showact=<?=htmlspecialchars($_GET['showact'])?>"><?=gettext("Expires in"); ?></a></th>
				<th><a href="?order=4&amp;showact=<?=htmlspecialchars($_GET['showact'])?>"><?=gettext("Expires at"); ?></a></th>
			</tr>
		</thead>
		<tbody>
<?php
foreach ($db as $dbent):
?>
			<tr>
				<td><?=$dbent[0]?></td>
				<td><?=$dbent[1]?></td>
				<td><?=htmlspecialchars(date("m/d/Y H:i:s", $dbent[2]))?></td>
				<td><?=$dbent[3]?><?=gettext("min"); ?></td>
				<td><?=htmlspecialchars(date("m/d/Y H:i:s", $dbent[4]))?></td>
			</tr>
<?php
endforeach;
?>
		</tbody>
	</table>
</div>

<?php include("foot.inc");
