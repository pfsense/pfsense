<?php
/*
	status_captiveportal.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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
##|*IDENT=page-status-captiveportal
##|*NAME=Status: Captive portal
##|*DESCR=Allow access to the 'Status: Captive portal' page.
##|*MATCH=status_captiveportal.php*
##|-PRIV

require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

$cpzone = $_GET['zone'];
if (isset($_POST['zone'])) {
	$cpzone = $_POST['zone'];
}
$cpzone = strtolower($cpzone);

if (!is_array($config['captiveportal'])) {
	$config['captiveportal'] = array();
}
$a_cp =& $config['captiveportal'];

if (count($a_cp) == 1) {
	$cpzone = current(array_keys($a_cp));
}

/* If the zone does not exist, do not display the invalid zone */
if (!array_key_exists($cpzone, $a_cp)) {
	$cpzone = "";
}

if (isset($cpzone) && !empty($cpzone) && isset($a_cp[$cpzone]['zoneid'])) {
	$cpzoneid = $a_cp[$cpzone]['zoneid'];
}

if ($_GET['act'] == "del" && !empty($cpzone) && isset($cpzoneid) && isset($_GET['id'])) {
	captiveportal_disconnect_client($_GET['id']);
	header("Location: status_captiveportal.php?zone={$cpzone}");
	exit;
}

function clientcmp($a, $b) {
	global $order;
	return strcmp($a[$order], $b[$order]);
}

if (!empty($cpzone)) {
	$cpdb = captiveportal_read_db();

	if ($_GET['order']) {
		if ($_GET['order'] == "ip") {
			$order = 2;
		} else if ($_GET['order'] == "mac") {
			$order = 3;
		} else if ($_GET['order'] == "user") {
			$order = 4;
		} else if ($_GET['order'] == "lastact") {
			$order = 5;
		} else {
			$order = 0;
		}
		usort($cpdb, "clientcmp");
	}
}
$pgtitle = array(gettext("Status"), gettext("Captive Portal"));

if (!empty($cpzone)) {
	$pgtitle[] = $a_cp[$cpzone]['zone'];

	if (isset($config['voucher'][$cpzone]['enable'])) {
		$pgtitle[] = gettext("Active Users");
	}
}
$shortcut_section = "captiveportal";

include("head.inc");

if (!empty($cpzone) && isset($config['voucher'][$cpzone]['enable'])):
	$tab_array = array();
	$tab_array[] = array(gettext("Active Users"), true, "status_captiveportal.php?zone=" . htmlspecialchars($cpzone));
	$tab_array[] = array(gettext("Active Vouchers"), false, "status_captiveportal_vouchers.php?zone=" . htmlspecialchars($cpzone));
	$tab_array[] = array(gettext("Voucher Rolls"), false, "status_captiveportal_voucher_rolls.php?zone=" . htmlspecialchars($cpzone));
	$tab_array[] = array(gettext("Test Vouchers"), false, "status_captiveportal_test.php?zone=" . htmlspecialchars($cpzone));
	$tab_array[] = array(gettext("Expire Vouchers"), false, "status_captiveportal_expire.php?zone=" . htmlspecialchars($cpzone));
	display_top_tabs($tab_array);
endif;

// Load MAC-Manufacturer table
$mac_man = load_mac_manufacturer_table();

if (count($a_cp) > 1) {
	$form = new Form(false);

	$section = new Form_Section('Captive Portal Zone');

	$zonelist = array("" => 'None');

	foreach ($a_cp as $cpkey => $cp) {
		$zonelist[$cpkey] = $cp['zone'];
	}

	$section->addInput(new Form_Select(
		'zone',
		'Display Zone',
		$cpzone,
		$zonelist
	))->setOnchange('this.form.submit()');

	$form->add($section);

	print($form);
}

if (!empty($cpzone)): ?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=sprintf(gettext("Users Logged In (%d)"), count($cpdb))?></h2></div>
	<div class="panel-body table-responsive">

		<table class="table table-striped table-hover table-condensed">
			<tr>
				<th>
					<a href="?zone=<?=htmlspecialchars($cpzone)?>&amp;order=ip&amp;showact=<?=htmlspecialchars($_GET['showact'])?>"><?=gettext("IP address")?></a>
				</th>
				<th>
					<a href="?zone=<?=htmlspecialchars($cpzone)?>&amp;order=mac&amp;showact=<?=htmlspecialchars($_GET['showact'])?>"><?=gettext("MAC address")?></a>
				</th>
				<th>
					<a href="?zone=<?=htmlspecialchars($cpzone)?>&amp;order=user&amp;showact=<?=htmlspecialchars($_GET['showact'])?>"><?=gettext("Username")?></a>
				</th>
				<th>
					<a href="?zone=<?=htmlspecialchars($cpzone)?>&amp;order=start&amp;showact=<?=htmlspecialchars($_GET['showact'])?>"><?=gettext("Session start")?></a>
				</th>

<?php
	if ($_GET['showact']):
?>
				<th>
					<a href="?zone=<?=htmlspecialchars($cpzone)?>&amp;order=lastact&amp;showact=<?=htmlspecialchars($_GET['showact'])?>"><?=gettext("Last activity")?></a>
				</th>
<?php
	endif;
?>
				<th><?=gettext("Actions")?></th>
			</tr>
<?php

	foreach ($cpdb as $cpent): ?>
			<tr>
				<td>
					<?=$cpent[2]?>
				</td>
				<td>
<?php
		$mac=trim($cpent[3]);
		if (!empty($mac)) {
			$mac_hi = strtoupper($mac[0] . $mac[1] . $mac[3] . $mac[4] . $mac[6] . $mac[7]);
			print htmlentities($mac);
			if (isset($mac_man[$mac_hi])) {
				print "<br /><font size=\"-2\"><i>{$mac_man[$mac_hi]}</i></font>";
			}
		}
?>	&nbsp;
				</td>
				<td>
					<?=htmlspecialchars($cpent[4])?>&nbsp;
				</td>
<?php
		if ($_GET['showact']):
			$last_act = captiveportal_get_last_activity($cpent[2], $cpent[3]); ?>
				<td>
					<?=htmlspecialchars(date("m/d/Y H:i:s", $cpent[0]))?>
				</td>
				<td>
<?php
			if ($last_act != 0) {
				echo htmlspecialchars(date("m/d/Y H:i:s", $last_act));
			}
?>
				</td>
<?php
		else:
?>
				<td>
					<?=htmlspecialchars(date("m/d/Y H:i:s", $cpent[0]))?>
				</td>
<?php
		endif;
?>
				<td>
					<a href="?zone=<?=htmlspecialchars($cpzone)?>&amp;order=<?=$_GET['order']?>&amp;showact=<?=htmlspecialchars($_GET['showact'])?>&amp;act=del&amp;id=<?=$cpent[5]?>"><i class="fa fa-trash" title="<?=gettext("Disconnect this User")?>"></i></a>
				</td>
			</tr>
<?php
	endforeach;
?>
		</table>
	</div>
</div>
<?php
else:
	if (empty($a_cp)) {
		// If no zones have been defined
		print_info_box(sprintf(gettext('No Captive Portal zones have been configured. New zones may be added here: %1$sServices > Captive Portal%2$s.'), '<a href="services_captiveportal_zones.php">', '</a>'), 'warning', false);
	}
endif;
?>


<form action="status_captiveportal.php" method="get" style="margin: 14px;">
	<input type="hidden" name="order" value="<?=htmlspecialchars($_GET['order'])?>" />

<?php
if (!empty($cpzone)):
	if ($_GET['showact']): ?>
		<input type="hidden" name="showact" value="0" />
		<button type="submit" class="btn btn-info" value="<?=gettext("Don't show last activity")?>">
			<i class="fa fa-minus-circle icon-embed-btn"></i>
			<?=gettext("Hide Last Activity")?>
		</button>
<?php
	else:
?>
		<input type="hidden" name="showact" value="1" />
		<button type="submit" class="btn btn-info" value="<?=gettext("Show last activity")?>">
			<i class="fa fa-plus-circle icon-embed-btn"></i>
			<?=gettext("Show Last Activity")?>
		</button>
<?php
	endif;
?>
	<input type="hidden" name="zone" value="<?=htmlspecialchars($cpzone)?>" />
<?php
endif;
?>
</form>
<?php include("foot.inc");
