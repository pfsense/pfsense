<?php
/*
 * system_certmanager_renew.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Shrew Soft Inc
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
##|*IDENT=page-system-certmanager-renew
##|*NAME=System: Certificate Manager: Renew or Reissue CA/Certificate
##|*DESCR=Allow access to the 'System: Certificate Manager: Renew or Reissue CA/Certificate' page.
##|*MATCH=system_certmanager_renew.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("certs.inc");
require_once("pfsense-utils.inc");

global $cert_strict_values;

init_config_arr(array('ca'));
$a_ca = &$config['ca'];

init_config_arr(array('cert'));
$a_cert = &$config['cert'];

$type = $_REQUEST['type'];

switch ($type) {
	case 'ca':
		$torenew = & lookup_ca($_REQUEST['refid']);
		$returnpage = "system_camanager.php";
		$service_function = 'ca_get_all_services';
		$typestring = gettext("Certificate Authority");
		break;
	case 'cert':
		$torenew = & lookup_cert($_REQUEST['refid']);
		$returnpage = "system_certmanager.php";
		$service_function = 'cert_get_all_services';
		$typestring = gettext("Certificate");
		break;
	default:
		pfSenseHeader("system_certmanager.php");
		exit;
}

if (!$torenew) {
	pfSenseHeader($returnpage);
	exit;
}
$old_serial = cert_get_serial($torenew['crt']);
if ($_POST['renew']) {
	$input_errors = array();
	$old_serial = cert_get_serial($torenew['crt']);
	if (cert_renew($torenew, ($_POST['reusekey'] == "yes"), ($_POST['strictsecurity'] == "yes"))) {
		$new_serial = cert_get_serial($torenew['crt']);
		$message = sprintf(gettext("Renewed %s %s (%s) - Serial %s -> %s"),
					$typestring,
					$torenew['refid'],
					$torenew['descr'],
					$old_serial,
					$new_serial);
		log_error($message);
		write_config($message);
		cert_restart_services($service_function($torenew['refid']));
		pfSenseHeader($returnpage);
		exit;
	} else {
		$input_errors[] = gettext("Error renewing") . " {$typestring}";
	}
}

$pgtitle = array(gettext("System"), gettext("Certificate Manager"), gettext("Renew or Reissue"));
$pglinks = array("", "system_camanager.php", "@self");

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("CAs"), false, "system_camanager.php");
$tab_array[] = array(gettext("Certificates"), false, "system_certmanager.php");
$tab_array[] = array(gettext("Certificate Revocation"), false, "system_crlmanager.php");
$tab_array[] = array(gettext("Renew or Reissue"), true, "system_certmanager_renew.php");
display_top_tabs($tab_array);

$cert_details = openssl_x509_parse(base64_decode($torenew['crt']));
$subj = cert_get_subject($torenew['crt']);
$issuer = cert_get_issuer($torenew['crt']);
if (!empty($torenew['caref'])) {
	$issuer_ca = & lookup_ca($torenew['caref']);
	if ($issuer_ca && !empty($issuer_ca['descr'])) {
		$issuer = "{$issuer_ca['descr']}: {$issuer}";
	}
} elseif ($subj == $issuer) {
	$issuer = "self-signed";
}
$lifetime = (int) round(($cert_details['validTo_time_t'] - $cert_details['validFrom_time_t']) / 86400);
$purpose = cert_get_purpose($torenew['crt']);
$res_key = openssl_pkey_get_private(base64_decode($torenew['prv']));
$key_details = openssl_pkey_get_details($res_key);

print_info_box(gettext('Renewing or reissuing a CA or certificate will replace the old entry.' . ' ' .
		'The old entry will be lost, and cannot be revoked after it has been replaced.' . ' ' .
		'Daemons known to be using this entry or one of its descendents will be restarted after the entry is replaced.'),
		'warning', false);

$form = new Form(false);
$form->setAction('system_certmanager_renew.php');

$section = new Form_Section("Renew or Reissue: " . htmlspecialchars($torenew['descr']));

$section->addInput(new Form_StaticText(
	"Subject",
	htmlspecialchars($subj)
));

$section->addInput(new Form_StaticText(
	"Serial",
	htmlspecialchars($old_serial)
));

if (!empty($cert_details['extensions']['subjectKeyIdentifier'])) {
	$section->addInput(new Form_StaticText(
		'Subject Key ID',
		str_replace("\n", '<br/>', htmlspecialchars($cert_details['extensions']['subjectKeyIdentifier']))
	));
}

if ($type == "cert") {
	$section->addInput(new Form_StaticText(
		'Certificate Type',
		($purpose['server'] == 'Yes') ? gettext('Server') : gettext('User')
	));
}

if (!empty($issuer)) {
	$section->addInput(new Form_StaticText(
		'Issued By',
		htmlspecialchars($issuer)
	));
}

$section->addInput(new Form_Checkbox(
	'reusekey',
	'Reuse Key',
	'Use the existing key',
	true
))->setHelp('Set this option to retain the existing keys when reissuing. Uncheck to generate a new key.');

$section->addInput(new Form_Checkbox(
	'strictsecurity',
	'Strict Security',
	'Enforce strict security parameters',
	false
))->setHelp('Set this option to enforce stricter security requirements, which may alter certain certificate properties such as the digest, key length, and lifetime.' . ' ' .
		'Note that this will override the value of Reuse Key if the old key does not meet the stricter requirements.');

$form->addGlobal(new Form_Input(
	'refid',
	null,
	'hidden',
	$_REQUEST['refid']
));

$form->addGlobal(new Form_Input(
	'type',
	null,
	'hidden',
	$type
));

$form->add($section);

$form->addGlobal(new Form_Button(
	'renew',
	'Renew/Reissue',
	null,
	'fa-repeat'
))->addClass('btn-danger');

print($form);
?>
<div class="panel panel-danger">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Certificate Properties vs Strict Security')?></h2></div>
	<div class="panel-body">
		<div class="container">
			<br/>
			<?=gettext('If Strict Security is checked, weak certificate properties will be replaced as necessary.')?>
			<?=gettext('This table lists the specific changes necessary to meet strict security requirements.')?>
			<br/>
			<br/>
		</div>
		<div class="table-responsive">
		<table class="table table-striped table-hover sortable-theme-bootstrap">
			<thead>
				<tr class="danger">
					<th><?=gettext("Property")?></th>
					<th><?=gettext("Current Value")?></th>
					<th><?=gettext("Secure Value")?></th>
					<th><?=gettext("Would Change")?></th>
				</tr>
			</thead>
			<tbody>
<?php if ($type == "cert"): ?>
				<tr>
					<td><?=gettext("Lifetime")?></td>
					<td><?=$lifetime?> <?=gettext("Days")?>
					</td>
	<?php if ($purpose['server'] == 'Yes'): ?>
					<td><?= $cert_strict_values['max_server_cert_lifetime'] ?> <?=gettext("Days")?></td>
					<td><?= ($lifetime > $cert_strict_values['max_server_cert_lifetime']) ? gettext('Yes') : gettext('No') ?></td>
	<?php else: ?>
					<td>n/a</td>
					<td>No</td>
	<?php endif; ?>
				</tr>
<?php endif; ?>
				<tr>
					<td><?=gettext("Digest")?></td>
					<td><?=htmlspecialchars($cert_details['signatureTypeSN'])?></td>
					<td><?=gettext("SHA-256 or stronger")?></td>
					<td><?=(in_array($cert_details['signatureTypeSN'], $cert_strict_values['digest_blacklist'])) ? gettext('Yes') : gettext('No') ?></td>
				</tr>
<?php if ($key_details['type'] == OPENSSL_KEYTYPE_RSA): ?>
				<tr>
					<td><?=gettext("RSA Key Bits")?></td>
					<td><?=htmlspecialchars($key_details['bits'])?></td>
					<td><?=$cert_strict_values['min_private_key_bits']?></td>
					<td><?=($key_details['bits'] < $cert_strict_values['min_private_key_bits']) ? gettext('Yes') : gettext('No') ?></td>
				</tr>
<?php endif; ?>
			</tbody>
		</table>
		</div>
	</div>
</div>

<?php
include('foot.inc');
