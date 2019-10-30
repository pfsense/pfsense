<?php
/*
 * services_captiveportal_vouchers.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2007 Marcel Wiget <mwiget@mac.com>
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
##|*IDENT=page-services-captiveportal-vouchers
##|*NAME=Services: Captive Portal Vouchers
##|*DESCR=Allow access to the 'Services: Captive Portal Vouchers' page.
##|*MATCH=services_captiveportal_vouchers.php*
##|-PRIV

if ($_POST['postafterlogin']) {
	$nocsrf= true;
}

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("captiveportal.inc");
require_once("voucher.inc");

$cpzone = strtolower(htmlspecialchars($_REQUEST['zone']));

if ($_REQUEST['generatekey']) {
	include_once("phpseclib/Math/BigInteger.php");
	include_once("phpseclib/Crypt/Hash.php");
	include_once("phpseclib/Crypt/RSA.php");

	$rsa = new phpseclib\Crypt\RSA();
	$key = $rsa->createKey(64);
	$privatekey = $key["privatekey"];
	$publickey = $key["publickey"];

	print json_encode(['public' => $publickey, 'private' => $privatekey]);
	exit;
}

if (empty($cpzone)) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

init_config_arr(array('captiveportal'));
init_config_arr(array('voucher', $cpzone, 'roll'));
$a_cp = &$config['captiveportal'];

if (empty($a_cp[$cpzone])) {
	log_error(sprintf(gettext("Submission on captiveportal page with unknown zone parameter: %s"), htmlspecialchars($cpzone)));
	header("Location: services_captiveportal_zones.php");
	exit;
}

$pgtitle = array(gettext("Services"), gettext("Captive Portal"), $a_cp[$cpzone]['zone'], gettext("Vouchers"));
$pglinks = array("", "services_captiveportal_zones.php", "services_captiveportal.php?zone=" . $cpzone, "@self");
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

if ($_POST['act'] == "del") {
	$id = $_POST['id'];
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
} else if ($_REQUEST['act'] == "csv") {
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
			$id = $_REQUEST['id'];
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

if ($_POST['save']) {
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
		if ($_POST['charset'] && (strlen($_POST['charset']) < 2)) {
			$input_errors[] = gettext("Need at least 2 characters to create vouchers.");
		}
		if ($_POST['charset'] && (strpos($_POST['charset'], "\"") > 0)) {
			$input_errors[] = gettext("Double quotes aren't allowed.");
		}
		if ($_POST['charset'] && (strpos($_POST['charset'], ",") > 0)) {
			$input_errors[] = gettext("',' aren't allowed.");
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
			$input_errors[] = gettext("The voucher database cannot be sync'd to this host (itself).");
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
			// Refresh captiveportal login to show voucher changes
			captiveportal_configure_zone($config['captiveportal'][$cpzone]);
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
				$execcmd = <<<EOF
				global \$config;
				\$toreturn = array();
				\$toreturn['voucher'] = \$config['voucher']['$cpzone'];
				unset(\$toreturn['vouchersyncport'], \$toreturn['vouchersyncpass'], \$toreturn['vouchersyncusername'], \$toreturn['vouchersyncdbip']);

EOF;
				require_once("xmlrpc_client.inc");
				$rpc_client = new pfsense_xmlrpc_client();
				$rpc_client->setConnectionData(
						$newvoucher['vouchersyncdbip'], $newvoucher['vouchersyncport'],
						$newvoucher['vouchersyncusername'], $newvoucher['vouchersyncpass']);
				$rpc_client->set_noticefile("CaptivePortalVoucherSync");
				$resp = $rpc_client->xmlrpc_exec_php($execcmd);
				if ($resp == null) {
					$input_errors[] = $rpc_client->get_error();
				}

				if (!$input_errors) {
					if (is_array($resp)) {
						log_error(sprintf(gettext("The Captive Portal voucher database has been synchronized with %s (pfsense.exec_php)."), $url));
						// If we received back the voucher roll and other information then store it.
						if ($resp['voucher']['roll']) {
							$newvoucher['roll'] = $resp['voucher']['roll'];
						}
						if ($resp['voucher']['rollbits']) {
							$newvoucher['rollbits'] = $resp['voucher']['rollbits'];
						}
						if ($resp['voucher']['ticketbits']) {
							$newvoucher['ticketbits'] = $resp['voucher']['ticketbits'];
						}
						if ($resp['voucher']['checksumbits']) {
							$newvoucher['checksumbits'] = $resp['voucher']['checksumbits'];
						}
						if ($resp['voucher']['magic']) {
							$newvoucher['magic'] = $resp['voucher']['magic'];
						}
						if ($resp['voucher']['exponent']) {
							$newvoucher['exponent'] = $resp['voucher']['exponent'];
						}
						if ($resp['voucher']['publickey']) {
							$newvoucher['publickey'] = $resp['voucher']['publickey'];
						}
						if ($resp['voucher']['privatekey']) {
							$newvoucher['privatekey'] = $resp['voucher']['privatekey'];
						}
						if ($resp['voucher']['descrmsgnoaccess']) {
							$newvoucher['descrmsgnoaccess'] = $resp['voucher']['descrmsgnoaccess'];
						}
						if ($resp['voucher']['descrmsgexpired']) {
							$newvoucher['descrmsgexpired'] = $resp['voucher']['descrmsgexpired'];
						}
						$savemsg = sprintf(gettext('Voucher database has been synchronized from %1$s'), $url);

						$config['voucher'][$cpzone] = $newvoucher;
						write_config();
						voucher_configure_zone(true);
						// Refresh captiveportal login to show voucher changes
						captiveportal_configure_zone($config['captiveportal'][$cpzone]);
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
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("Configuration"), false, "services_captiveportal.php?zone={$cpzone}");
$tab_array[] = array(gettext("MACs"), false, "services_captiveportal_mac.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed IP Addresses"), false, "services_captiveportal_ip.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed Hostnames"), false, "services_captiveportal_hostname.php?zone={$cpzone}");
$tab_array[] = array(gettext("Vouchers"), true, "services_captiveportal_vouchers.php?zone={$cpzone}");
$tab_array[] = array(gettext("File Manager"), false, "services_captiveportal_filemanager.php?zone={$cpzone}");
display_top_tabs($tab_array, true);

// We draw a simple table first, then present the controls to work with it
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Voucher Rolls");?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Roll #")?></th>
						<th><?=gettext("Minutes/Ticket")?></th>
						<th><?=gettext("# of Tickets")?></th>
						<th><?=gettext("Comment")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody>
<?php
$i = 0;
foreach ($a_roll as $rollent):
?>
					<tr>
						<td><?=htmlspecialchars($rollent['number']); ?></td>
						<td><?=htmlspecialchars($rollent['minutes'])?></td>
						<td><?=htmlspecialchars($rollent['count'])?></td>
						<td><?=htmlspecialchars($rollent['descr']); ?></td>
						<td>
							<!-- These buttons are hidden/shown on checking the 'enable' checkbox -->
							<a class="fa fa-pencil"		title="<?=gettext("Edit voucher roll"); ?>" href="services_captiveportal_vouchers_edit.php?zone=<?=$cpzone?>&amp;id=<?=$i; ?>"></a>
							<a class="fa fa-trash"		title="<?=gettext("Delete voucher roll")?>" href="services_captiveportal_vouchers.php?zone=<?=$cpzone?>&amp;act=del&amp;id=<?=$i; ?>" usepost></a>
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

$section = new Form_Section('Create, Generate and Activate Rolls with Vouchers');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable the creation, generation and activation of rolls with vouchers',
	$pconfig['enable']
	));

$form->add($section);

$section = new Form_Section('Create, Generate and Activate Rolls with Vouchers');
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

$section = new Form_Section('Voucher Database Synchronization');
$section->addClass('rolledit');

$section->addInput(new Form_IpAddress(
	'vouchersyncdbip',
	'Synchronize Voucher Database IP',
	$pconfig['vouchersyncdbip']
))->setHelp('IP address of master nodes webConfigurator to synchronize voucher database and used vouchers from.%1$s' .
			'NOTE: this should be setup on the slave nodes and not the primary node!', '<br />');

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
	$pconfig['vouchersyncusername'],
	['autocomplete' => 'new-password']
))->setHelp('This is the username of the master voucher nodes webConfigurator.');

$section->addPassword(new Form_Input(
	'vouchersyncpass',
	'Voucher sync password',
	'password',
	$pconfig['vouchersyncpass']
))->setHelp('This is the password of the master voucher nodes webConfigurator.');

$form->addGlobal(new Form_Input(
	'zone',
	null,
	'hidden',
	$cpzone
));

$form->addGlobal(new Form_Input(
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

	var generateButton = $('<a class="btn btn-xs btn-warning"><i class="fa fa-refresh icon-embed-btn"></i><?=gettext("Generate new keys");?></a>');
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
