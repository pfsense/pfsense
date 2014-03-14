<?php
/*
	easyrule.php

	Copyright (C) 2009-2010 Jim Pingle (jpingle@gmail.com)
	Originally Sponsored By Anathematic @ pfSense Forums
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
	pfSense_MODULE:	filter
*/

##|+PRIV
##|*IDENT=page-firewall-easyrule
##|*NAME=Firewall: Easy Rule add/status page
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

if(stristr($retval, "error") == true)
    $message = $retval;

include("head.inc"); ?>
<body link="#000000" vlink="#000000" alink="#000000">
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
<?php if ($input_errors) print_input_errors($input_errors); ?>

<?php if ($message) { ?>
<br />
<?=gettext("Message"); ?>: <?php echo $message; ?>
<br />
<?php } else { ?>
<?=gettext("This is the Easy Rule status page, mainly used to display errors when adding rules. " .
"If you are seeing this, there apparently was not an error, and you navigated to the " .
"page directly without telling it what to do"); ?>.<br /><br />
<?=gettext("This page is meant to be called from the block/pass buttons on the Firewall Logs page"); ?>, <a href="diag_logs_filter.php"><?=gettext("Status"); ?> &gt; <?=gettext("System Logs, " .
"Firewall Tab"); ?></a>.
<br />
<?php } ?>
</td></tr></table>
<?php include("fend.inc"); ?>
