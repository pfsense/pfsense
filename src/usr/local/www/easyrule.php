<?php
/*
 * easyrule.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * Originally Sponsored By Anathematic @ pfSense Forums
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
