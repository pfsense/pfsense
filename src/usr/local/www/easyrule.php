<?php
/*
 * easyrule.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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

require_once("guiconfig.inc");
require_once("easyrule.inc");
require_once("filter.inc");
require_once("shaper.inc");

$retval = 0;
$message = "";
$confirmed = isset($_POST['confirmed']) && $_POST['confirmed'] == 'true';

/* $specialsrcdst must be a defined global for functions being called. */
global $specialsrcdst;
$specialsrcdst = explode(" ", "any pppoe l2tp openvpn");

if ($_POST && $confirmed && isset($_POST['action'])) {
	switch ($_POST['action']) {
		case 'block':
			/* Check that we have a valid host */
			$message = easyrule_parse_block($_POST['int'], $_POST['src'], $_POST['ipproto']);
			break;
		case 'pass':
			$message = easyrule_parse_pass($_POST['int'], $_POST['proto'], $_POST['src'], $_POST['dst'], $_POST['dstport'], $_POST['ipproto']);
			break;
		default:
			$message = gettext("Invalid action specified.");
	}
}

if (stristr($retval, "error") == true) {
	$message = $retval;
}

$pgtitle = array(gettext("Firewall"), gettext("Easy Rule"));
include("head.inc");
if ($input_errors) {
	print_input_errors($input_errors);
}
?>
<form action="easyrule.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title">
				<?=gettext("Confirmation Required to Add Easy Rule");?>
			</h2>
		</div>
		<div class="panel-body">
			<div class="content">
<?php
if (!$confirmed && !empty($_REQUEST['action'])) { ?>
	<?php if ($_GET['action'] == 'block'): ?>
				<b><?=gettext("Rule Type")?>:</b> <?=htmlspecialchars(ucfirst(gettext($_GET['action'])))?>
				<br/><b><?=gettext("Interface")?>:</b> <?=htmlspecialchars(strtoupper($_GET['int']))?>
				<input type="hidden" name="int" value="<?=htmlspecialchars($_GET['int'])?>" />
				<br/><b><?= gettext("Source") ?>:</b> <?=htmlspecialchars($_GET['src'])?>
				<input type="hidden" name="src" value="<?=htmlspecialchars($_GET['src'])?>" />
				<br/><b><?=gettext("IP Protocol")?>:</b> <?=htmlspecialchars(ucfirst($_GET['ipproto']))?>
				<input type="hidden" name="ipproto" value="<?=htmlspecialchars($_GET['ipproto'])?>" />
	<?php elseif ($_GET['action'] == 'pass'): ?>
				<b><?=gettext("Rule Type")?>:</b> <?=htmlspecialchars(ucfirst(gettext($_GET['action'])))?>
				<br/><b><?=gettext("Interface")?>:</b> <?=htmlspecialchars(strtoupper($_GET['int']))?>
				<input type="hidden" name="int" value="<?=htmlspecialchars($_GET['int'])?>" />
				<br/><b><?=gettext("Protocol")?>:</b> <?=htmlspecialchars(strtoupper($_GET['proto']))?>
				<input type="hidden" name="proto" value="<?=htmlspecialchars($_GET['proto'])?>" />
				<br/><b><?=gettext("Source")?>:</b> <?=htmlspecialchars($_GET['src'])?>
				<input type="hidden" name="src" value="<?=htmlspecialchars($_GET['src'])?>" />
				<br/><b><?=gettext("Destination")?>:</b> <?=htmlspecialchars($_GET['dst'])?>
				<input type="hidden" name="dst" value="<?=htmlspecialchars($_GET['dst'])?>" />
				<br/><b><?=gettext("Destination Port")?>:</b> <?=htmlspecialchars($_GET['dstport'])?>
				<input type="hidden" name="dstport" value="<?=htmlspecialchars($_GET['dstport'])?>" />
				<br/><b><?=gettext("IP Protocol")?>:</b> <?=htmlspecialchars(ucfirst($_GET['ipproto']))?>
				<input type="hidden" name="ipproto" value="<?=htmlspecialchars($_GET['ipproto'])?>" />
	<?php	else:
			$message = gettext("Invalid action specified.");
		endif; ?>
				<br/><br/>
	<?php if (empty($message)): ?>
				<input type="hidden" name="action" value="<?=htmlspecialchars($_GET['action'])?>" />
				<input type="hidden" name="confirmed" value="true" />
				<button type="submit" class="btn btn-success" name="erconfirm" id="erconfirm" value="<?=gettext("Confirm")?>">
					<i class="fa fa-check icon-embed-btn"></i>
					<?=gettext("Confirm")?>
				</button>
	<?php endif;
}

if ($message) {
	print_info_box($message);
} elseif (empty($_REQUEST['action'])) {
	print_info_box(
		gettext('This is the Easy Rule status page, mainly used to display errors when adding rules.') . ' ' .
		gettext('There apparently was not an error, and this page was navigated to directly without any instructions for what it should do.') .
		'<br /><br />' .
		gettext('This page is meant to be called from the block/pass buttons on the Firewall Logs page') .
		', <a href="status_logs_filter.php">' . gettext("Status") . ' &gt; ' . gettext('System Logs') . ', ' . gettext('Firewall Tab') . '</a>.<br />');
}
?>
			</div>
		</div>
	</div>
</form>
<?php include("foot.inc"); ?>
