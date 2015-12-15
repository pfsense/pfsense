<?php
/*
	easyrule.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Originally Sponsored By Anathematic @ pfSense Forums
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
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

include("head.inc"); ?>

include("fbegin.inc");
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
<?php
if ($input_errors) {
	print_input_errors($input_errors);
}

if ($message) { ?>
<br />
<?=gettext("Message"); ?>: <?php echo $message; ?>
<br />
<?php } else { ?>
<?=gettext("This is the Easy Rule status page, mainly used to display errors when adding rules. " .
"If you are seeing this, there apparently was not an error, and you navigated to the " .
"page directly without telling it what to do"); ?>.<br /><br />
<?=gettext("This page is meant to be called from the block/pass buttons on the Firewall Logs page"); ?>, <a href="status_logs_filter.php"><?=gettext("Status"); ?> &gt; <?=gettext("System Logs, " .
"Firewall Tab"); ?></a>.
<br />
<?php } ?>
</td></tr></table>
<?php include("foot.inc"); ?>
