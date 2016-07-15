<?php
/*
 * easyrule.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * Originally Sponsored By Anathematic @ pfSense Forums
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-firewall-easyrule
##|*NAME=Firewall: Easy Rule add/status
##|*DESCR=Allow access to the 'Firewall: Easy Rule' add/status page.
##|*MATCH=easyrule.php*
##|-PRIV

$pgtitle = gettext("Firewall: EasyRule");
require_once("guiconfig.inc");
require_once("easyrule.inc");
require_once("filter.inc");
require_once("shaper.inc");

$retval = 0;
$message = "";
$specialsrcdst = explode(" ", "any pptp pppoe l2tp openvpn");

if ($_GET && isset($_GET['action'])) {
	switch ($_GET['action']) {
		case 'block':
			/* Check that we have a valid host */
			easyrule_parse_block($_GET['int'], $_GET['src'], $_GET['ipproto']);
			break;
		case 'pass':
			easyrule_parse_pass($_GET['int'], $_GET['proto'], $_GET['src'], $_GET['dst'], $_GET['dstport'], $_GET['ipproto']);
			break;
	}
}

if (stristr($retval, "error") == true) {
	$message = $retval;
}

include("head.inc");
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
<?php
if ($input_errors) {
	print_input_errors($input_errors);
}

if ($message) {
?>
<br />
<?=gettext("Message"); ?>: <?=$message;?>
<br />
<?php
} else {
	print_info_box(
		gettext('This is the Easy Rule status page, mainly used to display errors when adding rules.') . ' ' .
		gettext('There apparently was not an error, and this page was navigated to directly without any instructions for what it should do.') .
		'<br /><br />' .
		gettext('This page is meant to be called from the block/pass buttons on the Firewall Logs page') .
		', <a href="status_logs_filter.php">' . gettext("Status") . ' &gt; ' . gettext('System Logs') . ', ' . gettext('Firewall Tab') . '</a>.<br />');
}
?>
		</td>
	</tr>
</table>
<?php include("foot.inc"); ?>
