<?php
/*
	services_captiveportal_vouchers.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2007 Marcel Wiget <mwiget@mac.com>
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
##|*IDENT=page-services-captiveportal-vouchers
##|*NAME=Services: Captive portal Vouchers
##|*DESCR=Allow access to the 'Services: Captive portal Vouchers' page.
##|*MATCH=services_captiveportal_vouchers.php*
##|-PRIV

if ($_POST['postafterlogin']) {
	$nocsrf= true;
}

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

if ($_REQUEST['generatekey']) {
	exec("/usr/bin/openssl genrsa 64 > /tmp/key64.private");
	exec("/usr/bin/openssl rsa -pubout < /tmp/key64.private > /tmp/key64.public");
	$privatekey = str_replace("\n", "\\n", file_get_contents("/tmp/key64.private"));
	$publickey = str_replace("\n", "\\n", file_get_contents("/tmp/key64.public"));
	exec("rm /tmp/key64.private /tmp/key64.public");
	print json_encode(['public' => $publickey, 'private' => $privatekey]);
	exit;
}

if (!is_array($config['captiveportal'])) {
	$config['captiveportal'] = array();
}
$a_cp =& $config['captiveportal'];

if (!is_array($config['voucher'])) {
	$config['voucher'] = array();
}

if (empty($a_cp[$cpzone])) {
	log_error("Submission on captiveportal page with unknown zone parameter: " . htmlspecialchars($cpzone));
	header("Location: services_captiveportal_zones.php");
	exit;
}

$pgtitle = array(gettext("Services"), gettext("Captive Portal"), "Zone " . $a_cp[$cpzone]['zone'], gettext("Vouchers"));
$shortcut_section = "captiveportal-vouchers";

if (!is_array($config['voucher'][$cpzone]['roll'])) {
	$config['voucher'][$cpzone]['roll'] = array();
}
if (!isset($config['voucher'][$cpzone]['charset'])) {
	$config['voucher'][$cpzone]['charset'] = '2345678abcdefhijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
}
if (!isset($config['voucher'][$cpzone]['rollbits'])) {
	$config['voucher'][$cpzone]['rollbits'] = 16;
}
if (!isset($config['voucher'][$cpzone]['ticketbits'])) {
	$config['voucher'][$cpzone]['ticketbits'] = 10;
}
if (!isset($config['voucher'][$cpzone]['checksumbits'])) {
	$config['voucher'][$cpzone]['checksumbits'] = 5;
}
if (!isset($config['voucher'][$cpzone]['magic'])) {
	$config['voucher'][$cpzone]['magic'] = rand();	 // anything slightly random will do
}
if (!isset($config['voucher'][$cpzone]['exponent'])) {
	while (true) {
		while (($exponent = rand()) % 30000 < 5000) {
			continue;
		}
		$exponent = ($exponent * 2) + 1; // Make it odd number
		if ($exponent <= 65537) {
			break;
		}
	}

	$config['voucher'][$cpzone]['exponent'] = $exponent;
	unset($exponent);
}

if (!isset($config['voucher'][$cpzone]['publickey'])) {
	/* generate a random 64 bit RSA key pair using the voucher binary */
	$fd = popen("/usr/local/bin/voucher -g 64 -e " . $config['voucher'][$cpzone]['exponent'], "r");
	if ($fd !== false) {
		$output = fread($fd, 16384);
		pclose($fd);
		list($privkey, $pubkey) = explode("\0", $output);
		$config['voucher'][$cpzone]['publickey'] = base64_encode($pubkey);
		$config['voucher'][$cpzone]['privatekey'] = base64_encode($privkey);
	}
}

// Check for invalid or expired vouchers
if (!isset($config['voucher'][$cpzone]['descrmsgnoaccess'])) {
	$config['voucher'][$cpzone]['descrmsgnoaccess'] = gettext("Voucher invalid");
}
if (!isset($config['voucher'][$cpzone]['descrmsgexpired'])) {
	$config['voucher'][$cpzone]['descrmsgexpired'] = gettext("Voucher expired");
}

$a_roll = &$config['voucher'][$cpzone]['roll'];

if ($_GET['act'] == "del") {
	$id = $_GET['id'];
	if ($a_roll[$id]) {
		$roll = $a_roll[$id]['number'];
		$voucherlck = lock("voucher{$cpzone}");
		unset($a_roll[$id]);
		voucher_unlink_db($roll);
		unlock($voucherlck);
		write_config();
	}
	header("Location: services_captiveportal_vouchers.php?zone={$cpzone}");
	exit;
} else if ($_GET['act'] == "csv") {
	/* print all vouchers of the selected roll */
	$privkey = base64_decode($config['voucher'][$cpzone]['privatekey']);
	if (strstr($privkey, "BEGIN RSA PRIVATE KEY")) {
		$fd = fopen("{$g['varetc_path']}/voucher_{$cpzone}.private", "w");
		if (!$fd) {
			$input_errors[] = gettext("Cannot write private key file") . ".\n";
		} else {
			chmod("{$g['varetc_path']}/voucher_{$cpzone}.private", 0600);
			fwrite($fd, $privkey);
			fclose($fd);
			$a_voucher = &$config['voucher'][$cpzone]['roll'];
			$id = $_GET['id'];
			if (isset($id) && $a_voucher[$id]) {
				$number = $a_voucher[$id]['number'];
				$count = $a_voucher[$id]['count'];
				header("Content-Type: application/octet-stream");
				header("Content-Disposition: attachment; filename=vouchers_{$cpzone}_roll{$number}.csv");
				if (file_exists("{$g['varetc_path']}/voucher_{$cpzone}.cfg")) {
					system("/usr/local/bin/voucher -c {$g['varetc_path']}/voucher_{$cpzone}.cfg -p {$g['varetc_path']}/voucher_{$cpzone}.private $number $count");
				}
				@unlink("{$g['varetc_path']}/voucher_{$cpzone}.private");
			} else {
				header("Location: services_captiveportal_vouchers.php?zone={$cpzone}");
			}
			exit;
		}
	} else {
		$input_errors[] = gettext("Need private RSA key to print vouchers") . "\n";
	}
}

$pconfig['enable'] = isset($config['voucher'][$cpzone]['enable']);
$pconfig['charset'] = $config['voucher'][$cpzone]['charset'];
$pconfig['rollbits'] = $config['voucher'][$cpzone]['rollbits'];
$pconfig['ticketbits'] = $config['voucher'][$cpzone]['ticketbits'];
$pconfig['checksumbits'] = $config['voucher'][$cpzone]['checksumbits'];
$pconfig['magic'] = $config['voucher'][$cpzone]['magic'];
$pconfig['exponent'] = $config['voucher'][$cpzone]['exponent'];
$pconfig['publickey'] = base64_decode($config['voucher'][$cpzone]['publickey']);
$pconfig['privatekey'] = base64_decode($config['voucher'][$cpzone]['privatekey']);
$pconfig['msgnoaccess'] = $config['voucher'][$cpzone]['descrmsgnoaccess'];
$pconfig['msgexpired'] = $config['voucher'][$cpzone]['descrmsgexpired'];
$pconfig['vouchersyncdbip'] = $config['voucher'][$cpzone]['vouchersyncdbip'];
$pconfig['vouchersyncport'] = $config['voucher'][$cpzone]['vouchersyncport'];
$pconfig['vouchersyncpass'] = $config['voucher'][$cpzone]['vouchersyncpass'];
$pconfig['vouchersyncusername'] = $config['voucher'][$cpzone]['vouchersyncusername'];

if ($_POST) {
	unset($input_errors);

	if ($_POST['postafterlogin']) {
		voucher_expire($_POST['voucher_expire']);
		exit;
	}

	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable'] == "yes") {
		if (!$_POST['vouchersyncusername']) {
			$reqdfields = explode(" ", "charset rollbits ticketbits checksumbits publickey magic");
			$reqdfieldsn = array(gettext("charset"), gettext("rollbits"), gettext("ticketbits"), gettext("checksumbits"), gettext("publickey"), gettext("magic"));
		} else {
			$reqdfields = explode(" ", "vouchersyncdbip vouchersyncport vouchersyncpass vouchersyncusername");
			$reqdfieldsn = array(gettext("Synchronize Voucher Database IP"), gettext("Sync port"), gettext("Sync password"), gettext("Sync username"));
		}

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
	}

	if (!$_POST['vouchersyncusername']) {
		// Check for form errors
		if ($_POST['charset'] && (strlen($_POST['charset'] < 2))) {
			$input_errors[] = gettext("Need at least 2 characters to create vouchers.");
		}
		if ($_POST['charset'] && (strpos($_POST['charset'], "\"") > 0)) {
			$input_errors[] = gettext("Double quotes aren't allowed.");
		}
		if ($_POST['charset'] && (strpos($_POST['charset'], ",") > 0)) {
			$input_errors[] = "',' " . gettext("aren't allowed.");
		}
		if ($_POST['rollbits'] && (!is_numeric($_POST['rollbits']) || ($_POST['rollbits'] < 1) || ($_POST['rollbits'] > 31))) {
			$input_errors[] = gettext("# of Bits to store Roll Id needs to be between 1..31.");
		}
		if ($_POST['ticketbits'] && (!is_numeric($_POST['ticketbits']) || ($_POST['ticketbits'] < 1) || ($_POST['ticketbits'] > 16))) {
			$input_errors[] = gettext("# of Bits to store Ticket Id needs to be between 1..16.");
		}
		if ($_POST['checksumbits'] && (!is_numeric($_POST['checksumbits']) || ($_POST['checksumbits'] < 1) || ($_POST['checksumbits'] > 31))) {
			$input_errors[] = gettext("# of Bits to store checksum needs to be between 1..31.");
		}
		if ($_POST['publickey'] && (!strstr($_POST['publickey'], "BEGIN PUBLIC KEY"))) {
			$input_errors[] = gettext("This doesn't look like an RSA Public key.");
		}
		if ($_POST['privatekey'] && (!strstr($_POST['privatekey'], "BEGIN RSA PRIVATE KEY"))) {
			$input_errors[] = gettext("This doesn't look like an RSA Private key.");
		}
		if ($_POST['vouchersyncdbip'] && (is_ipaddr_configured($_POST['vouchersyncdbip']))) {
			$input_errors[] = gettext("You cannot sync the voucher database to this host (itself).");
		}
		if ($_POST['vouchersyncpass'] != $_POST['vouchersyncpass_confirm']) {
			$input_errors[] = gettext("Password and confirmed password must match.");
		}
	}

	if (!$input_errors) {
		if (empty($config['voucher'][$cpzone])) {
			$newvoucher = array();
		} else {
			$newvoucher = $config['voucher'][$cpzone];
		}
		if ($_POST['enable'] == "yes") {
			$newvoucher['enable'] = true;
		} else {
			unset($newvoucher['enable']);
		}
		if (empty($_POST['vouchersyncusername'])) {
			unset($newvoucher['vouchersyncdbip']);
			unset($newvoucher['vouchersyncport']);
			unset($newvoucher['vouchersyncusername']);
			unset($newvoucher['vouchersyncpass']);
			$newvoucher['charset'] = $_POST['charset'];
			$newvoucher['rollbits'] = $_POST['rollbits'];
			$newvoucher['ticketbits'] = $_POST['ticketbits'];
			$newvoucher['checksumbits'] = $_POST['checksumbits'];
			$newvoucher['magic'] = $_POST['magic'];
			$newvoucher['exponent'] = $_POST['exponent'];
			$newvoucher['publickey'] = base64_encode($_POST['publickey']);
			$newvoucher['privatekey'] = base64_encode($_POST['privatekey']);
			$newvoucher['descrmsgnoaccess'] = $_POST['msgnoaccess'];
			$newvoucher['descrmsgexpired'] = $_POST['msgexpired'];
			$config['voucher'][$cpzone] = $newvoucher;
			write_config();
			voucher_configure_zone();
		} else {
			$newvoucher['vouchersyncdbip'] = $_POST['vouchersyncdbip'];
			$newvoucher['vouchersyncport'] = $_POST['vouchersyncport'];
			$newvoucher['vouchersyncusername'] = $_POST['vouchersyncusername'];
			if ($_POST['vouchersyncpass'] != DMYPWD ) {
				$newvoucher['vouchersyncpass'] = $_POST['vouchersyncpass'];
			} else {
				$newvoucher['vouchersyncpass'] = $config['voucher'][$cpzone]['vouchersyncpass'];
			}
			if ($newvoucher['vouchersyncpass'] && $newvoucher['vouchersyncusername'] &&
			    $newvoucher['vouchersyncport'] && $newvoucher['vouchersyncdbip']) {
				// Synchronize the voucher DB from the master node
				require_once("xmlrpc.inc");

				$protocol = "http";
				if (is_array($config['system']) && is_array($config['system']['webgui']) && !empty($config['system']['webgui']['protocol']) &&
				    $config['system']['webgui']['protocol'] == "https") {
					$protocol = "https";
				}
				if ($protocol == "https" || $newvoucher['vouchersyncport'] == "443") {
					$url = "https://{$newvoucher['vouchersyncdbip']}";
				} else {
					$url = "http://{$newvoucher['vouchersyncdbip']}";
				}

				$execcmd = <<<EOF
				\$toreturn = array();
				\$toreturn['voucher'] = \$config['voucher']['$cpzone'];
				unset(\$toreturn['vouchersyncport'], \$toreturn['vouchersyncpass'], \$toreturn['vouchersyncusername'], \$toreturn['vouchersyncdbip']);

EOF;

				/* assemble xmlrpc payload */
				$params = array(
					XML_RPC_encode($newvoucher['vouchersyncpass']),
					XML_RPC_encode($execcmd)
				);
				$port = $newvoucher['vouchersyncport'];
				log_error("voucher XMLRPC sync data {$url}:{$port}.");
				$msg = new XML_RPC_Message('pfsense.exec_php', $params);
				$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
				$cli->setCredentials($newvoucher['vouchersyncusername'], $newvoucher['vouchersyncpass']);
				$resp = $cli->send($msg, "250");
				if (!is_object($resp)) {
					$error = "A communications error occurred while attempting CaptivePortalVoucherSync XMLRPC sync with {$url}:{$port} (pfsense.exec_php).";
					log_error($error);
					file_notice("CaptivePortalVoucherSync", $error, "Communications error occurred", "");
					$input_errors[] = $error;
				} elseif ($resp->faultCode()) {
					$cli->setDebug(1);
					$resp = $cli->send($msg, "250");
					$error = "An error code was received while attempting CaptivePortalVoucherSync XMLRPC sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
					log_error($error);
					file_notice("CaptivePortalVoucherSync", $error, "Error code received", "");
					$input_errors[] = $error;
				} else {
					log_error("The Captive Portal voucher database has been synchronized with {$url}:{$port} (pfsense.exec_php).");
				}
				if (!$input_errors) {
					$toreturn = XML_RPC_Decode($resp->value());
					if (!is_array($toreturn)) {
						if ($toreturn == "Authentication failed") {
							$input_errors[] = "Could not synchronize the voucher database: Authentication Failed.";
						}
					} else {
						// If we received back the voucher roll and other information then store it.
						if ($toreturn['voucher']['roll']) {
							$newvoucher['roll'] = $toreturn['voucher']['roll'];
						}
						if ($toreturn['voucher']['rollbits']) {
							$newvoucher['rollbits'] = $toreturn['voucher']['rollbits'];
						}
						if ($toreturn['voucher']['ticketbits']) {
							$newvoucher['ticketbits'] = $toreturn['voucher']['ticketbits'];
						}
						if ($toreturn['voucher']['checksumbits']) {
							$newvoucher['checksumbits'] = $toreturn['voucher']['checksumbits'];
						}
						if ($toreturn['voucher']['magic']) {
							$newvoucher['magic'] = $toreturn['voucher']['magic'];
						}
						if ($toreturn['voucher']['exponent']) {
							$newvoucher['exponent'] = $toreturn['voucher']['exponent'];
						}
						if ($toreturn['voucher']['publickey']) {
							$newvoucher['publickey'] = $toreturn['voucher']['publickey'];
						}
						if ($toreturn['voucher']['privatekey']) {
							$newvoucher['privatekey'] = $toreturn['voucher']['privatekey'];
						}
						if ($toreturn['voucher']['descrmsgnoaccess']) {
							$newvoucher['descrmsgnoaccess'] = $toreturn['voucher']['descrmsgnoaccess'];
						}
						if ($toreturn['voucher']['descrmsgexpired']) {
							$newvoucher['descrmsgexpired'] = $toreturn['voucher']['descrmsgexpired'];
						}
						$savemsg = gettext("Voucher database has been synchronized from {$url}:{$port}");

						$config['voucher'][$cpzone] = $newvoucher;
						write_config();
						voucher_configure_zone(true);
					}
				}
			}
		}

		if (!$input_errors) {
			header("Location: services_captiveportal_vouchers.php?zone={$cpzone}");
			exit;
		}
	}
}
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg. 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("Configuration"), false, "services_captiveportal.php?zone={$cpzone}");
$tab_array[] = array(gettext("MAC"), false, "services_captiveportal_mac.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed IP Addresses"), false, "services_captiveportal_ip.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed Hostnames"), false, "services_captiveportal_hostname.php?zone={$cpzone}");
$tab_array[] = array(gettext("Vouchers"), true, "services_captiveportal_vouchers.php?zone={$cpzone}");
$tab_array[] = array(gettext("File Manager"), false, "services_captiveportal_filemanager.php?zone={$cpzone}");
display_top_tabs($tab_array, true);

// We draw a simple table first, then present the controls to work with it
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title">Voucher Rolls</h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><?=gettext("Roll")?> #</th>
						<th><?=gettext("Minutes/Ticket")?></th>
						<th># <?=gettext("of Tickets")?></th>
						<th><?=gettext("Comment")?></th>
						<th><?=gettext("Action")?></th>
					</tr>
				</thead>
				<tbody>
<?php
$i = 0;
foreach ($a_roll as $rollent):
?>
					<tr>
						<td>
							<?=htmlspecialchars($rollent['number']); ?>&nbsp;
						</td>
						<td>
							<?=htmlspecialchars($rollent['minutes'])?>&nbsp;
						</td>
						<td>
							<?=htmlspecialchars($rollent['count'])?>&nbsp;
						</td>
						<td>
							<?=htmlspecialchars($rollent['descr']); ?>&nbsp;
						</td>
						<td>
							<!-- These buttons are hidden/shown on checking the 'enable' checkbox -->
							<a class="fa fa-pencil"		title="<?=gettext("Edit voucher roll"); ?>" href="services_captiveportal_vouchers_edit.php?zone=<?=$cpzone?>&amp;id=<?=$i; ?>"></a>
							<a class="fa fa-trash"		title="<?=gettext("Delete voucher roll")?>" href="services_captiveportal_vouchers.php?zone=<?=$cpzone?>&amp;act=del&amp;id=<?=$i; ?>"></a>
							<a class="fa fa-file-excel-o"	title="<?=gettext("Export vouchers for this roll to a .csv file")?>" href="services_captiveportal_vouchers.php?zone=<?=$cpzone?>&amp;act=csv&amp;id=<?=$i; ?>"></a>
						</td>
					</tr>
<?php
	$i++;
endforeach;
?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php

if ($pconfig['enable']) : ?>
	<nav class="action-buttons">
		<a href="services_captiveportal_vouchers_edit.php?zone=<?=$cpzone?>" class="btn btn-success">
			<i class="fa fa-plus icon-embed-btn"></i>
			<?=gettext("Add")?>
		</a>
	</nav>
<?php
endif;

$form = new Form();

$section = new Form_Section('Create, generate and activate Rolls with Vouchers');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable the creation, generation and activation of rolls with vouchers',
	$pconfig['enable']
	));

$form->add($section);

$section = new Form_Section('Create, generate and activate Rolls with Vouchers');
$section->addClass('rolledit');

$section->addInput(new Form_Textarea(
	'publickey',
	'Voucher Public Key',
	$pconfig['publickey']
))->setHelp('Paste an RSA public key (64 Bit or smaller) in PEM format here. This key is used to decrypt vouchers.');

$section->addInput(new Form_Textarea(
	'privatekey',
	'Voucher Private Key',
	$pconfig['privatekey']
))->setHelp('Paste an RSA private key (64 Bit or smaller) in PEM format here. This key is only used to generate encrypted vouchers and doesn\'t need to be available if the vouchers have been generated offline.');

$section->addInput(new Form_Input(
	'charset',
	'Character set',
	'text',
	$pconfig['charset']
))->setHelp('Tickets are generated with the specified character set. It should contain printable characters (numbers, lower case and upper case letters) that are hard to confuse with others. Avoid e.g. 0/O and l/1.');

$section->addInput(new Form_Input(
	'rollbits',
	'# of Roll bits',
	'text',
	$pconfig['rollbits']
))->setHelp('Reserves a range in each voucher to store the Roll # it belongs to. Allowed range: 1..31. Sum of Roll+Ticket+Checksum bits must be one Bit less than the RSA key size.');

$section->addInput(new Form_Input(
	'ticketbits',
	'# of Ticket bits',
	'text',
	$pconfig['ticketbits']
))->setHelp('Reserves a range in each voucher to store the Ticket# it belongs to. Allowed range: 1..16. ' .
					'Using 16 bits allows a roll to have up to 65535 vouchers. ' .
					'A bit array, stored in RAM and in the config, is used to mark if a voucher has been used. A bit array for 65535 vouchers requires 8 KB of storage. ');

$section->addInput(new Form_Input(
	'checksumbits',
	'# of Checksum bits',
	'text',
	$pconfig['checksumbits']
))->setHelp('Reserves a range in each voucher to store a simple checksum over Roll # and Ticket#. Allowed range is 0..31.');

$section->addInput(new Form_Input(
	'magic',
	'Magic number',
	'text',
	$pconfig['magic']
))->setHelp('Magic number stored in every voucher. Verified during voucher check. ' .
					'Size depends on how many bits are left by Roll+Ticket+Checksum bits. If all bits are used, no magic number will be used and checked.');

$section->addInput(new Form_Input(
	'msgnoaccess',
	'Invalid voucher message',
	'text',
	$pconfig['msgnoaccess']
))->setHelp('Error message displayed for invalid vouchers on captive portal error page ($PORTAL_MESSAGE$).');


$section->addInput(new Form_Input(
	'msgexpired',
	'Expired voucher message',
	'text',
	$pconfig['msgexpired']
))->setHelp('Error message displayed for expired vouchers on captive portal error page ($PORTAL_MESSAGE$).');

$form->add($section);

$section = new Form_Section('Voucher database synchronization');
$section->addClass('rolledit');

$section->addInput(new Form_IpAddress(
	'vouchersyncdbip',
	'Synchronize Voucher Database IP',
	$pconfig['vouchersyncdbip']
))->setHelp('IP address of master nodes webConfigurator to synchronize voucher database and used vouchers from.' . '<br />' .
			'NOTE: this should be setup on the slave nodes and not the primary node!');

$section->addInput(new Form_Input(
	'vouchersyncport',
	'Voucher sync port',
	'text',
	$pconfig['vouchersyncport']
))->setHelp('The port of the master voucher node\'s webConfigurator. Example: 443 ');

$section->addInput(new Form_Input(
	'vouchersyncusername',
	'Voucher sync username',
	'text',
	$pconfig['vouchersyncusername']
))->setHelp('This is the username of the master voucher nodes webConfigurator.');

$section->addPassword(new Form_Input(
	'vouchersyncpass',
	'Voucher sync password',
	'password',
	$pconfig['vouchersyncpass']
))->setHelp('This is the password of the master voucher nodes webConfigurator.');

$section->addInput(new Form_Input(
	'zone',
	null,
	'hidden',
	$cpzone
));

$section->addInput(new Form_Input(
	'exponent',
	null,
	'hidden',
	$pconfig['exponent']
));

$form->add($section);
print($form);
?>
<div class="rolledit">
<?php
	print_info_box(gettext('Changing any Voucher parameter (apart from managing the list of Rolls) on this page will render existing vouchers useless if they were generated with different settings. ' .
							'Specifying the Voucher Database Synchronization options will not record any other value from the other options. They will be retrieved/synced from the master.'), 'info');
?>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Hides all elements of the specified class. This will usually be a section or group
	function hideClass(s_class, hide) {
		if (hide) {
			$('.' + s_class).hide();
		} else {
			$('.' + s_class).show();
		}
	}

	function setShowHide (show) {
		hideClass('rolledit', !show);

		if (show) {
			$('td:nth-child(5),th:nth-child(5)').show();
		} else {
			$('td:nth-child(5),th:nth-child(5)').hide();
		}
	}

	// Show/hide on checkbox change
	$('#enable').click(function() {
		setShowHide($('#enable').is(":checked"));
	})

	// Set initial state
	setShowHide($('#enable').is(":checked"));

	var generateButton = $('<a class="btn btn-xs btn-default">Generate new keys</a>');
	generateButton.on('click', function() {
		$.ajax({
			type: 'get',
			url: 'services_captiveportal_vouchers.php?generatekey=true',
			dataType: 'json',
			success: function(data) {
				$('#publickey').val(data.public.replace(/\\n/g, '\n'));
				$('#privatekey').val(data.private.replace(/\\n/g, '\n'));
			}
		});
	});
	generateButton.appendTo($('#publickey + .help-block')[0]);
});
//]]>
</script>
<?php include("foot.inc");
