<?php
/*
	easyrule.php

	Copyright (C) 2009 Jim Pingle (jpingle@gmail.com)
	Sponsored By Anathematic @ pfSense Forums
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

$pgtitle = "Status : EasyRule";
require_once("guiconfig.inc");
require_once("easyrule.inc");
$retval = 0;
$message = "";

if ($_GET && isset($_GET['action'])) {
	switch ($_GET['action']) {
		case 'block':
			/* Check that we have a valid host */
			if (isset($_GET['src']) && isset($_GET['int'])) {
				if (!is_ipaddr($_GET['src'])) {
					$message .= "Tried to block invalid IP: " . htmlspecialchars($_GET['src']) . "<br/>";
					break;
				}
				$_GET['int'] = easyrule_find_rule_interface($_GET['int']);
				if ($_GET['int'] === false) {
					$message .= "Invalid interface for block rule: " . htmlspecialchars($_GET['int']) . "<br/>";
					break;
				}
				if (easyrule_block_host_add($_GET['src'], $_GET['int'])) {
					/* shouldn't get here, the function will redirect */
					$message .= "Host added successfully" . "<br/>";
				} else {
					$message .= "Failed to create block rule, alias, or add host." . "<br/>";
				}
			} else {
				$message .= "Tried to block but had no host IP or interface<br/>";
			}
			break;
		case 'pass':
			/* Check for valid int, srchost, dsthost, dstport, and proto */
			if (isset($_GET['int']) && isset($_GET['proto']) && isset($_GET['src']) && isset($_GET['dst'])) {
				$_GET['int'] = easyrule_find_rule_interface($_GET['int']);
				if ($_GET['int'] === false) {
					$message .= "Invalid interface for pass rule: " . htmlspecialchars($_GET['int']) . "<br/>";
					break;
				}
				if (getprotobyname($_GET['proto']) == -1) {
					$message .= "Invalid protocol for pass rule: " . htmlspecialchars($_GET['proto']) . "<br/>";
					break;
				}
				if (!is_ipaddr($_GET['src'])) {
					$message .= "Tried to pass invalid source IP: " . htmlspecialchars($_GET['src']) . "<br/>";
					break;
				}
				if (!is_ipaddr($_GET['dst'])) {
					$message .= "Tried to pass invalid destination IP: " . htmlspecialchars($_GET['dst']) . "<br/>";
					break;
				}
				if (($_GET['proto'] != 'icmp') && !isset($_GET['dstport'])) {
					$message .= "Missing destination port: " . htmlspecialchars($_GET['dstport']) . "<br/>";
					break;
				}
				if ($_GET['proto'] == 'icmp') {
					$_GET['dstport'] = 0;
				}
				if (!is_numeric($_GET['dstport']) || ($_GET['dstport'] < 0) || ($_GET['dstport'] > 65536)) {
					$message .= "Tried to pass invalid destination port: " . htmlspecialchars($_GET['dstport']) . "<br/>";
					break;
				}
				/* Should have valid input... */
				if (easyrule_pass_rule_add($_GET['int'], $_GET['proto'], $_GET['src'], $_GET['dst'], $_GET['dstport'])) {
					/* Shouldn't get here, the function should redirect. */
					$message .= "Successfully added pass rule!" . "<br/>";
				} else {
					$message .= "Failed to add pass rule." . "<br/>";
				}
			} else {
				$message = "Missing parameters for pass rule";
				break;
			}
			break;
	}
}

if(stristr($retval, "error") == true)
    $message = $retval;

include("head.inc"); ?>
<body link="#000000" vlink="#000000" alink="#000000">
<? include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
<?php if ($input_errors) print_input_errors($input_errors); ?>

<?php if ($message) { ?>
<br/>
Message: <?php echo $message; ?>
<br/>
<? } else { ?>
This is the Easy Rule status page, mainly used to display errors when adding rules. 
If you are seeing this, there apparently was not an error, and you navigated to the
page directly without telling it what to do.<br/><br/>
This page is meant to be called from the block/pass buttons on the Firewall Logs page, <a href="http://192.168.56.101/diag_logs_filter.php">Status &gt; System Logs,
Firewall Tab</a>.
<br />      
<? } ?>
</td></tr></table>
<?php include("fend.inc"); ?>
