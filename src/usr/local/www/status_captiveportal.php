<?php
/*
 * status_captiveportal.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "This product includes software developed by the pfSense Project
 *    for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 * 4. The names "pfSense" and "pfSense Project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. For written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. Products derived from this software may not be called "pfSense"
 *    nor may "pfSense" appear in their names without prior written
 *    permission of the Electric Sheep Fencing, LLC.
 *
 * 6. Redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "This product includes software developed by the pfSense Project
 * for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 * THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 * ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
 */

##|+PRIV
##|*IDENT=page-status-captiveportal
##|*NAME=Status: Captive Portal
##|*DESCR=Allow access to the 'Status: Captive Portal' page.
##|*MATCH=status_captiveportal.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("captiveportal.inc");

function print_details($cpent) {
	global $config, $cpzone, $cpzoneid;

	printf("<a data-toggle=\"popover\" data-trigger=\"hover focus\" title=\"%s\" data-content=\" ", gettext("Session details"));

	/* print the duration of the session */
	$session_time = time() - $cpent[0];
	printf(gettext("Session duration: %s") . "<br>", convert_seconds_to_dhms($session_time));

	/* print the time left before session timeout or session terminate time or the closer of the two if both are set */
	if (!empty($cpent[7]) && !empty($cpent[9])) {
		$session_time_left = min($cpent[0] + $cpent[7] - time(),$cpent[9] - time());
		printf(gettext("Session time left: %s") . "<br>", convert_seconds_to_dhms($session_time_left));
	} elseif (!empty($cpent[7]) && empty($cpent[9])) {
		$session_time_left = $cpent[0] + $cpent[7] - time();
		printf(gettext("Session time left: %s") . "<br>", convert_seconds_to_dhms($session_time_left));
	} elseif (empty($cpent[7]) && !empty($cpent[9])) {
		$session_time_left = $cpent[9] - time();
		printf(gettext("Session time left: %s") . "<br>", convert_seconds_to_dhms($session_time_left));
	}

	/* print idle time and time left before disconnection if idle timeout is set */
	if ($_GET['showact']) {
		$last_act = captiveportal_get_last_activity($cpent[2], $cpent[3]);

		/* if the user never sent traffic, set last activity time to the login time */
		$last_act = $last_act ? $last_act : $cpent[0];

		$idle_time = time() - $last_act;
		printf(gettext("Idle time: %s") . "<br>", convert_seconds_to_dhms($idle_time));

		if (!empty($cpent[8])) {
			$idle_time_left = $last_act + $cpent[8] - time();
			printf(gettext("Idle time left: %s") . "<br>", convert_seconds_to_dhms($idle_time_left));
		}
	}

	/* print bytes sent and received, invert the values if reverse accounting is enabled */
	$volume = getVolume($cpent[2], $cpent[3]);
	$reverse = isset($config['captiveportal'][$cpzone]['reverseacct']) ? true : false;
	if ($reverse) {
		printf(gettext("Bytes sent: %s") . "<br>" . gettext("Bytes received: %s") . "\" data-html=\"true\">", format_bytes($volume['output_bytes']), format_bytes($volume['input_bytes']));
	} else {
		printf(gettext("Bytes sent: %s") . "<br>" . gettext("Bytes received: %s") . "\" data-html=\"true\">", format_bytes($volume['input_bytes']), format_bytes($volume['output_bytes']));
	}

	/* print username */
	printf("%s</a>", htmlspecialchars($cpent[4]));
}

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
	captiveportal_disconnect_client($_GET['id'], 6);
	/* keep displaying last activity times */
	if ($_GET['showact']) {
		header("Location: status_captiveportal.php?zone={$cpzone}&showact=1");
	} else {
		header("Location: status_captiveportal.php?zone={$cpzone}");
	}
	exit;
}

if ($_GET['deleteall'] && !empty($cpzone) && isset($cpzoneid)) {
	captiveportal_disconnect_all();
	header("Location: status_captiveportal.php?zone={$cpzone}");
	exit;
}

$pgtitle = array(gettext("Status"), gettext("Captive Portal"));
$pglinks = array("", "status_captiveportal.php");

if (!empty($cpzone)) {
	$cpdb = captiveportal_read_db();

	$pgtitle[] = htmlspecialchars($a_cp[$cpzone]['zone']);
	$pglinks[] = "status_captiveportal.php?zone=" . $cpzone;

	if (isset($config['voucher'][$cpzone]['enable'])) {
		$pgtitle[] = gettext("Active Users");
		$pglinks[] = "status_captiveportal.php?zone=" . $cpzone;
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
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("IP address")?></th>
<?php
	if (!isset($config['captiveportal'][$cpzone]['nomacfilter'])):
?>
					<th><?=gettext("MAC address")?></th>
<?php
	endif;
?>
					<th><?=gettext("Username")?></th>
					<th><?=gettext("Session start")?></th>
<?php
	if ($_GET['showact']):
?>
					<th><?=gettext("Last activity")?></th>
<?php
	endif;
?>
					<th data-sortable="false"><?=gettext("Actions")?></th>
				</tr>
			</thead>
			<tbody>
<?php

	foreach ($cpdb as $cpent): ?>
				<tr>
					<td><?=htmlspecialchars($cpent[2])?></td>
<?php
		if (!isset($config['captiveportal'][$cpzone]['nomacfilter'])) {
?>
					<td>
<?php
			$mac=trim($cpent[3]);
			if (!empty($mac)) {
				$mac_hi = strtoupper($mac[0] . $mac[1] . $mac[3] . $mac[4] . $mac[6] . $mac[7]);
				print htmlentities($mac);
				if (isset($mac_man[$mac_hi])) {
					print "<br /><font size=\"-2\"><i>" . htmlspecialchars($mac_man[$mac_hi]) . "</i></font>";
				}
			}
?>
					</td>
<?php
		}
?>
					<td><?php print_details($cpent); ?></td>
<?php
		if ($_GET['showact']):
			$last_act = captiveportal_get_last_activity($cpent[2], $cpent[3]);
			/* if the user never sent traffic, set last activity time to the login time */
			$last_act = $last_act ? $last_act : $cpent[0];
?>
					<td><?=htmlspecialchars(date("m/d/Y H:i:s", $cpent[0]))?></td>
					<td>
<?php
			echo htmlspecialchars(date("m/d/Y H:i:s", $last_act));
?>
					</td>
<?php
		else:
?>
					<td><?=htmlspecialchars(date("m/d/Y H:i:s", $cpent[0]))?></td>
<?php
		endif;
?>
					<td>
						<a href="?zone=<?=htmlspecialchars($cpzone)?>&amp;showact=<?=htmlspecialchars($_GET['showact'])?>&amp;act=del&amp;id=<?=htmlspecialchars($cpent[5])?>"><i class="fa fa-trash" title="<?=gettext("Disconnect this User")?>"></i></a>
					</td>
				</tr>
<?php
	endforeach;
?>
			</tbody>
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

<nav class="action-buttons">
<?php
if (!empty($cpzone)):
	if ($_GET['showact']): ?>
	<a href="status_captiveportal.php?zone=<?=htmlspecialchars($cpzone)?>&amp;showact=0" role="button" class="btn btn-info" title="<?=gettext("Don't show last activity")?>">
		<i class="fa fa-minus-circle icon-embed-btn"></i>
		<?=gettext("Hide Last Activity")?>
	</a>
<?php
	else:
?>
	<a href="status_captiveportal.php?zone=<?=htmlspecialchars($cpzone)?>&amp;showact=1" role="button" class="btn btn-info" title="<?=gettext("Show last activity")?>">
		<i class="fa fa-plus-circle icon-embed-btn"></i>
		<?=gettext("Show Last Activity")?>
	</a>
<?php
	endif;
?>
	<a href="status_captiveportal.php?zone=<?=htmlspecialchars($cpzone)?>&amp;deleteall=1" role="button" class="btn btn-danger" title="<?=gettext("Disconnect all active users")?>">
		<i class="fa fa-trash icon-embed-btn"></i>
		<?=gettext("Disconnect All Users")?>
	</a>
<?php
endif;
?>
</nav>
<?php include("foot.inc");
