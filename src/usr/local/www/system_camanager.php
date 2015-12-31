<?php
/*
	system_camanager.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *  Copyright (c)  2008 Shrew Soft Inc.
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
##|*IDENT=page-system-camanager
##|*NAME=System: CA Manager
##|*DESCR=Allow access to the 'System: CA Manager' page.
##|*MATCH=system_camanager.php*
##|-PRIV

require("guiconfig.inc");
require_once("certs.inc");

$ca_methods = array(
	"existing" => gettext("Import an existing Certificate Authority"),
	"internal" => gettext("Create an internal Certificate Authority"),
	"intermediate" => gettext("Create an intermediate Certificate Authority"));

$ca_keylens = array("512", "1024", "2048", "4096");
$openssl_digest_algs = array("sha1", "sha224", "sha256", "sha384", "sha512");

$pgtitle = array(gettext("System"), gettext("Certificate Manager"), gettext("CAs"));

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (!is_array($config['ca'])) {
	$config['ca'] = array();
}

$a_ca =& $config['ca'];

if (!is_array($config['cert'])) {
	$config['cert'] = array();
}

$a_cert =& $config['cert'];

if (!is_array($config['crl'])) {
	$config['crl'] = array();
}

$a_crl =& $config['crl'];

$act = $_GET['act'];
if ($_POST['act']) {
	$act = $_POST['act'];
}

if ($act == "del") {

	if (!isset($a_ca[$id])) {
		pfSenseHeader("system_camanager.php");
		exit;
	}

	$index = count($a_cert) - 1;
	for (;$index >= 0; $index--) {
		if ($a_cert[$index]['caref'] == $a_ca[$id]['refid']) {
			unset($a_cert[$index]);
		}
	}

	$index = count($a_crl) - 1;
	for (;$index >= 0; $index--) {
		if ($a_crl[$index]['caref'] == $a_ca[$id]['refid']) {
			unset($a_crl[$index]);
		}
	}

	$name = $a_ca[$id]['descr'];
	unset($a_ca[$id]);
	write_config();
	$savemsg = sprintf(gettext("Certificate Authority %s and its CRLs (if any) successfully deleted"), htmlspecialchars($name)) . "<br />";
	pfSenseHeader("system_camanager.php");
	exit;
}

if ($act == "edit") {
	if (!$a_ca[$id]) {
		pfSenseHeader("system_camanager.php");
		exit;
	}
	$pconfig['descr']  = $a_ca[$id]['descr'];
	$pconfig['refid']  = $a_ca[$id]['refid'];
	$pconfig['cert']   = base64_decode($a_ca[$id]['crt']);
	$pconfig['serial'] = $a_ca[$id]['serial'];
	if (!empty($a_ca[$id]['prv'])) {
		$pconfig['key'] = base64_decode($a_ca[$id]['prv']);
	}
}

if ($act == "new") {
	$pconfig['method'] = $_GET['method'];
	$pconfig['keylen'] = "2048";
	$pconfig['digest_alg'] = "sha256";
	$pconfig['lifetime'] = "3650";
	$pconfig['dn_commonname'] = "internal-ca";
}

if ($act == "exp") {

	if (!$a_ca[$id]) {
		pfSenseHeader("system_camanager.php");
		exit;
	}

	$exp_name = urlencode("{$a_ca[$id]['descr']}.crt");
	$exp_data = base64_decode($a_ca[$id]['crt']);
	$exp_size = strlen($exp_data);

	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename={$exp_name}");
	header("Content-Length: $exp_size");
	echo $exp_data;
	exit;
}

if ($act == "expkey") {

	if (!$a_ca[$id]) {
		pfSenseHeader("system_camanager.php");
		exit;
	}

	$exp_name = urlencode("{$a_ca[$id]['descr']}.key");
	$exp_data = base64_decode($a_ca[$id]['prv']);
	$exp_size = strlen($exp_data);

	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename={$exp_name}");
	header("Content-Length: $exp_size");
	echo $exp_data;
	exit;
}

if ($_POST) {

	unset($input_errors);
	$input_errors = array();
	$pconfig = $_POST;

	/* input validation */
	if ($pconfig['method'] == "existing") {
		$reqdfields = explode(" ", "descr cert");
		$reqdfieldsn = array(
			gettext("Descriptive name"),
			gettext("Certificate data"));
		if ($_POST['cert'] && (!strstr($_POST['cert'], "BEGIN CERTIFICATE") || !strstr($_POST['cert'], "END CERTIFICATE"))) {
			$input_errors[] = gettext("This certificate does not appear to be valid.");
		}
		if ($_POST['key'] && strstr($_POST['key'], "ENCRYPTED")) {
			$input_errors[] = gettext("Encrypted private keys are not yet supported.");
		}
	}
	if ($pconfig['method'] == "internal") {
		$reqdfields = explode(" ",
			"descr keylen lifetime dn_country dn_state dn_city ".
			"dn_organization dn_email dn_commonname");
		$reqdfieldsn = array(
			gettext("Descriptive name"),
			gettext("Key length"),
			gettext("Lifetime"),
			gettext("Distinguished name Country Code"),
			gettext("Distinguished name State or Province"),
			gettext("Distinguished name City"),
			gettext("Distinguished name Organization"),
			gettext("Distinguished name Email Address"),
			gettext("Distinguished name Common Name"));
	}
	if ($pconfig['method'] == "intermediate") {
		$reqdfields = explode(" ",
			"descr caref keylen lifetime dn_country dn_state dn_city ".
			"dn_organization dn_email dn_commonname");
		$reqdfieldsn = array(
			gettext("Descriptive name"),
			gettext("Signing Certificate Authority"),
			gettext("Key length"),
			gettext("Lifetime"),
			gettext("Distinguished name Country Code"),
			gettext("Distinguished name State or Province"),
			gettext("Distinguished name City"),
			gettext("Distinguished name Organization"),
			gettext("Distinguished name Email Address"),
			gettext("Distinguished name Common Name"));
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
	if ($pconfig['method'] != "existing") {
		/* Make sure we do not have invalid characters in the fields for the certificate */
		if (preg_match("/[\?\>\<\&\/\\\"\']/", $_POST['descr'])) {
			array_push($input_errors, "The field 'Descriptive Name' contains invalid characters.");
		}

		for ($i = 0; $i < count($reqdfields); $i++) {
			if ($reqdfields[$i] == 'dn_email') {
				if (preg_match("/[\!\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $_POST["dn_email"])) {
					array_push($input_errors, "The field 'Distinguished name Email Address' contains invalid characters.");
				}
			} else if ($reqdfields[$i] == 'dn_commonname') {
				if (preg_match("/[\!\@\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $_POST["dn_commonname"])) {
					array_push($input_errors, "The field 'Distinguished name Common Name' contains invalid characters.");
				}
			} else if (($reqdfields[$i] != "descr") && preg_match("/[\!\@\#\$\%\^\(\)\~\?\>\<\&\/\\\,\.\"\']/", $_POST["$reqdfields[$i]"])) {
				array_push($input_errors, "The field '" . $reqdfieldsn[$i] . "' contains invalid characters.");
			}
		}
		if (!in_array($_POST["keylen"], $ca_keylens)) {
			array_push($input_errors, gettext("Please select a valid Key Length."));
		}
		if (!in_array($_POST["digest_alg"], $openssl_digest_algs)) {
			array_push($input_errors, gettext("Please select a valid Digest Algorithm."));
		}
	}

	/* if this is an AJAX caller then handle via JSON */
	if (isAjax() && is_array($input_errors)) {
		input_errors2Ajax($input_errors);
		exit;
	}

	/* save modifications */
	if (!$input_errors) {
		$ca = array();
		if (!isset($pconfig['refid']) || empty($pconfig['refid'])) {
			$ca['refid'] = uniqid();
		} else {
			$ca['refid'] = $pconfig['refid'];
		}

		if (isset($id) && $a_ca[$id]) {
			$ca = $a_ca[$id];
		}

		$ca['descr'] = $pconfig['descr'];

		if ($act == "edit") {
			$ca['descr']  = $pconfig['descr'];
			$ca['refid']  = $pconfig['refid'];
			$ca['serial'] = $pconfig['serial'];
			$ca['crt']	  = base64_encode($pconfig['cert']);
			if (!empty($pconfig['key'])) {
				$ca['prv']	  = base64_encode($pconfig['key']);
			}
		} else {
			$old_err_level = error_reporting(0); /* otherwise openssl_ functions throw warnings directly to a page screwing menu tab */
			if ($pconfig['method'] == "existing") {
				ca_import($ca, $pconfig['cert'], $pconfig['key'], $pconfig['serial']);
			} else if ($pconfig['method'] == "internal") {
				$dn = array(
					'countryName' => $pconfig['dn_country'],
					'stateOrProvinceName' => $pconfig['dn_state'],
					'localityName' => $pconfig['dn_city'],
					'organizationName' => $pconfig['dn_organization'],
					'emailAddress' => $pconfig['dn_email'],
					'commonName' => $pconfig['dn_commonname']);
				if (!ca_create($ca, $pconfig['keylen'], $pconfig['lifetime'], $dn, $pconfig['digest_alg'])) {
					while ($ssl_err = openssl_error_string()) {
						$input_errors = array();
						array_push($input_errors, "openssl library returns: " . $ssl_err);
					}
				}
			} else if ($pconfig['method'] == "intermediate") {
				$dn = array(
					'countryName' => $pconfig['dn_country'],
					'stateOrProvinceName' => $pconfig['dn_state'],
					'localityName' => $pconfig['dn_city'],
					'organizationName' => $pconfig['dn_organization'],
					'emailAddress' => $pconfig['dn_email'],
					'commonName' => $pconfig['dn_commonname']);

				if (!ca_inter_create($ca, $pconfig['keylen'], $pconfig['lifetime'], $dn, $pconfig['caref'], $pconfig['digest_alg'])) {
					while ($ssl_err = openssl_error_string()) {
						$input_errors = array();
						array_push($input_errors, "openssl library returns: " . $ssl_err);
					}
				}
			}
			error_reporting($old_err_level);
		}

		if (isset($id) && $a_ca[$id]) {
			$a_ca[$id] = $ca;
		} else {
			$a_ca[] = $ca;
		}

		if (!$input_errors) {
			write_config();
		}

		pfSenseHeader("system_camanager.php");
	}
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

// Load valid country codes
$dn_cc = array();
if (file_exists("/etc/ca_countries")) {
	$dn_cc_file=file("/etc/ca_countries");
	foreach ($dn_cc_file as $line) {
		if (preg_match('/^(\S*)\s(.*)$/', $line, $matches)) {
			$dn_cc[$matches[1]] = $matches[1];
		}
	}
}

$tab_array = array();
$tab_array[] = array(gettext("CAs"), true, "system_camanager.php");
$tab_array[] = array(gettext("Certificates"), false, "system_certmanager.php");
$tab_array[] = array(gettext("Certificate Revocation"), false, "system_crlmanager.php");
display_top_tabs($tab_array);

if (!($act == "new" || $act == "edit" || $act == gettext("Save") || $input_errors)) {
?>
<div class="table-responsive">
<table class="table table-striped table-hover">
	<thead>
		<tr>
			<th><?=gettext("Name")?></th>
			<th><?=gettext("Internal")?></th>
			<th><?=gettext("Issuer")?></th>
			<th><?=gettext("Certificates")?></th>
			<th><?=gettext("Distinguished Name")?></th>
			<th><?=gettext("Actions")?></th>
		</tr>
	</thead>
	<tbody>
<?php
foreach ($a_ca as $i => $ca):
	$name = htmlspecialchars($ca['descr']);
	$subj = cert_get_subject($ca['crt']);
	$issuer = cert_get_issuer($ca['crt']);
	list($startdate, $enddate) = cert_get_dates($ca['crt']);
	if ($subj == $issuer) {
		$issuer_name = gettext("self-signed");
	} else {
		$issuer_name = gettext("external");
	}
	$subj = htmlspecialchars($subj);
	$issuer = htmlspecialchars($issuer);
	$certcount = 0;

	$issuer_ca = lookup_ca($ca['caref']);
	if ($issuer_ca) {
		$issuer_name = $issuer_ca['descr'];
	}

	// TODO : Need gray certificate icon
	$internal = (!!$ca['prv']);

	foreach ($a_cert as $cert) {
		if ($cert['caref'] == $ca['refid']) {
			$certcount++;
		}
	}

	foreach ($a_ca as $cert) {
		if ($cert['caref'] == $ca['refid']) {
			$certcount++;
		}
	}
?>
		<tr>
			<td><?=$name?></td>
			<td><?=$internal?></td>
			<td><i><?=$issuer_name?></i></td>
			<td><?=$certcount?></td>
			<td>
				<?=$subj?>
				<br />
				<small>
					<?=gettext("Valid From")?>: <b><?=$startdate ?></b><br /><?=gettext("Valid Until")?>: <b><?=$enddate ?></b>
				</small>
			</td>
			<td>
				<a class="fa fa-pencil"	title="<?=gettext("Edit")?>"	href="system_camanager.php?act=edit&amp;id=<?=$i?>"></a>
				<a class="fa fa-sign-in"	title="<?=gettext("Export")?>"	href="system_camanager.php?act=exp&amp;id=<?=$i?>"></a>
			<?php if ($ca['prv']): ?>
				<a class="fa fa-key"	title="<?=gettext("Export key")?>"	href="system_camanager.php?act=expkey&amp;id=<?=$i?>"></a>
			<?php endif?>
				<a class="fa fa-trash" 	title="<?=gettext("Delete")?>"	href="system_camanager.php?act=del&amp;id=<?=$i?>"></a>
			</td>
		</tr>
<?php endforeach; ?>
	</tbody>
</table>
</div>

<nav class="action-buttons">
	<a href="?act=new" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>
<?
	include("foot.inc");
	exit;
}

$form = new Form;
//$form->setAction('system_camanager.php?act=edit');
if (isset($id) && $a_ca[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

if ($act == "edit") {
	$form->addGlobal(new Form_Input(
		'refid',
		null,
		'hidden',
		$pconfig['refid']
	));
}

$section = new Form_Section('Create / edit CA');

$section->addInput(new Form_Input(
	'descr',
	'Descriptive name',
	'text',
	$pconfig['descr']
));

if (!isset($id) || $act == "edit") {
	$section->addInput(new Form_Select(
		'method',
		'Method',
		$pconfig['method'],
		$ca_methods
	))->toggles();
}

$form->add($section);

$section = new Form_Section('Existing Certificate Authority');
$section->addClass('toggle-existing collapse');

$section->addInput(new Form_Textarea(
	'cert',
	'Certificate data',
	$pconfig['cert']
))->setHelp('Paste a certificate in X.509 PEM format here.');

$section->addInput(new Form_Textarea(
	'key',
	'Certificate Private Key (optional)',
	$pconfig['key']
))->setHelp('Paste the private key for the above certificate here. This is '.
	'optional in most cases, but required if you need to generate a '.
	'Certificate Revocation List (CRL).');

$section->addInput(new Form_Input(
	'serial',
	'Serial for next certificate',
	'number',
	$pconfig['serial']
))->setHelp('Enter a decimal number to be used as the serial number for the next '.
	'certificate to be created using this CA.');

$form->add($section);

$section = new Form_Section('Internal Certificate Authority');
$section->addClass('toggle-internal', 'toggle-intermediate', 'collapse');

$allCas = array();
foreach ($a_ca as $ca) {
	if (!$ca['prv']) {
			continue;
	}

	$allCas[ $ca['refid'] ] = $ca['descr'];
}

$group = new Form_Group('Signing Certificate Authority');
$group->addClass('toggle-intermediate', 'collapse');
$group->add(new Form_Select(
	'caref',
	null,
	$pconfig['caref'],
	$allCas
));
$section->add($group);

$section->addInput(new Form_Select(
	'keylen',
	'Key length (bits)',
	$pconfig['keylen'],
	array_combine($ca_keylens, $ca_keylens)
));

$section->addInput(new Form_Select(
	'digest_alg',
	'Digest Algorithm',
	$pconfig['digest_alg'],
	array_combine($openssl_digest_algs, $openssl_digest_algs)
))->setHelp('NOTE: It is recommended to use an algorithm stronger than SHA1 '.
	'when possible.');

$section->addInput(new Form_Input(
	'lifetime',
	'Lifetime (days)',
	'number',
	$pconfig['lifetime']
));

$section->addInput(new Form_Select(
	'dn_country',
	'Country Code',
	$pconfig['dn_country'],
	$dn_cc
));

$section->addInput(new Form_Input(
	'dn_state',
	'State or Province',
	'text',
	$pconfig['dn_state'],
	['placeholder' => 'e.g. Texas']
));

$section->addInput(new Form_Input(
	'dn_city',
	'City',
	'text',
	$pconfig['dn_city'],
	['placeholder' => 'e.g. Austin']
));

$section->addInput(new Form_Input(
	'dn_organization',
	'Organization',
	'text',
	$pconfig['dn_organization'],
	['placeholder' => 'e.g. My Company Inc.']
));

$section->addInput(new Form_Input(
	'dn_email',
	'Email Address',
	'email',
	$pconfig['dn_email'],
	['placeholder' => 'e.g. admin@mycompany.com']
));

$section->addInput(new Form_Input(
	'dn_commonname',
	'Common Name',
	'text',
	$pconfig['dn_commonname'],
	['placeholder' => 'e.g. internal-ca']
));

$form->add($section);

print $form;

$internal_ca_count = 0;
foreach ($a_ca as $ca) {
	if ($ca['prv']) {
		$internal_ca_count++;
	}
}

include('foot.inc');
?>