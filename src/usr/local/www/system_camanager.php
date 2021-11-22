<?php
/*
 * system_camanager.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-system-camanager
##|*NAME=System: CA Manager
##|*DESCR=Allow access to the 'System: CA Manager' page.
##|*MATCH=system_camanager.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("certs.inc");
require_once("pfsense-utils.inc");

$ca_methods = array(
	"internal" => gettext("Create an internal Certificate Authority"),
	"existing" => gettext("Import an existing Certificate Authority"),
	"intermediate" => gettext("Create an intermediate Certificate Authority"));

$ca_keylens = array("1024", "2048", "3072", "4096", "6144", "7680", "8192", "15360", "16384");
$ca_keytypes = array("RSA", "ECDSA");
global $openssl_digest_algs;
global $cert_strict_values;
$max_lifetime = cert_get_max_lifetime();
$default_lifetime = min(3650, $max_lifetime);
$openssl_ecnames = cert_build_curve_list();
$class = "success";

init_config_arr(array('ca'));
$a_ca = &$config['ca'];

init_config_arr(array('cert'));
$a_cert = &$config['cert'];

init_config_arr(array('crl'));
$a_crl = &$config['crl'];

$act = $_REQUEST['act'];

if (isset($_REQUEST['id']) && ctype_alnum($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}
if (!empty($id)) {
	$thisca =& lookup_ca($id);
}

/* Actions other than 'new' require an ID.
 * 'del' action must be submitted via POST. */
if ((!empty($act) &&
    ($act != 'new') &&
    !$thisca) ||
    (($act == 'del') && empty($_POST))) {
	pfSenseHeader("system_camanager.php");
	exit;
}

switch ($act) {
	case 'del':
		$name = htmlspecialchars($thisca['descr']);
		if (cert_in_use($id)) {
			$savemsg = sprintf(gettext("Certificate %s is in use and cannot be deleted"), $name);
			$class = "danger";
		} else {
			/* Only remove CA reference when deleting. It can be reconnected if a new matching CA is imported */
			foreach ($a_cert as $cid => $acrt) {
				if ($acrt['caref'] == $thisca['refid']) {
					unset($a_cert[$cid]['caref']);
				}
			}
			/* Remove any CRLs for this CA, there is no way to recover the connection once the CA has been removed. */
			foreach ($a_crl as $cid => $acrl) {
				if ($acrl['caref'] == $thisca['refid']) {
					unset($a_crl[$cid]);
				}
			}
			/* Delete the CA */
			foreach ($a_ca as $cid => $aca) {
				if ($aca['refid'] == $thisca['refid']) {
					unset($a_ca[$cid]);
				}
			}
			$savemsg = sprintf(gettext("Deleted Certificate Authority %s and associated CRLs"), htmlspecialchars($name));
			write_config($savemsg);
			ca_setup_trust_store();
		}
		unset($act);
		break;
	case 'edit':
		/* Editing an existing CA, so populate values. */
		$pconfig['method'] = 'existing';
		$pconfig['descr']  = $thisca['descr'];
		$pconfig['refid']  = $thisca['refid'];
		$pconfig['cert']   = base64_decode($thisca['crt']);
		$pconfig['serial'] = $thisca['serial'];
		$pconfig['trust']  = ($thisca['trust'] == 'enabled');
		$pconfig['randomserial']  = ($thisca['randomserial'] == 'enabled');
		if (!empty($thisca['prv'])) {
			$pconfig['key'] = base64_decode($thisca['prv']);
		}
		break;
	case 'new':
		/* New CA, so set default values */
		$pconfig['method'] = $_POST['method'];
		$pconfig['keytype'] = "RSA";
		$pconfig['keylen'] = "2048";
		$pconfig['ecname'] = "prime256v1";
		$pconfig['digest_alg'] = "sha256";
		$pconfig['lifetime'] = $default_lifetime;
		$pconfig['dn_commonname'] = "internal-ca";
		break;
	case 'exp':
		/* Exporting a ca */
		send_user_download('data', base64_decode($thisca['crt']), "{$thisca['descr']}.crt");
		break;
	case 'expkey':
		/* Exporting a private key */
		send_user_download('data', base64_decode($thisca['prv']), "{$thisca['descr']}.key");
		break;
	default:
		break;
}

if ($_POST['save']) {
	unset($input_errors);
	$input_errors = array();
	$pconfig = $_POST;

	/* input validation */
	switch ($pconfig['method']) {
		case 'existing':
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
			if (!$input_errors && !empty($_POST['key']) && cert_get_publickey($_POST['cert'], false) != cert_get_publickey($_POST['key'], false, 'prv')) {
				$input_errors[] = gettext("The submitted private key does not match the submitted certificate data.");
			}
			/* we must ensure the certificate is capable of acting as a CA
			 * https://redmine.pfsense.org/issues/7885
			 */
			if (!$input_errors) {
				$purpose = cert_get_purpose($_POST['cert'], false);
				if ($purpose['ca'] != 'Yes') {
					$input_errors[] = gettext("The submitted certificate does not appear to be a Certificate Authority, import it on the Certificates tab instead.");
				}
			}
			break;
		case 'internal':
			$reqdfields = explode(" ",
				"descr keylen ecname keytype lifetime dn_commonname");
			$reqdfieldsn = array(
				gettext("Descriptive name"),
				gettext("Key length"),
				gettext("Elliptic Curve Name"),
				gettext("Key type"),
				gettext("Lifetime"),
				gettext("Common Name"));
			break;
		case 'intermediate':
			$reqdfields = explode(" ",
				"descr caref keylen ecname keytype lifetime dn_commonname");
			$reqdfieldsn = array(
				gettext("Descriptive name"),
				gettext("Signing Certificate Authority"),
				gettext("Key length"),
				gettext("Elliptic Curve Name"),
				gettext("Key type"),
				gettext("Lifetime"),
				gettext("Common Name"));
			break;
		default:
			break;
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
	if ($pconfig['method'] != "existing") {
		/* Make sure we do not have invalid characters in the fields for the certificate */
		if (preg_match("/[\?\>\<\&\/\\\"\']/", $_POST['descr'])) {
			array_push($input_errors, gettext("The field 'Descriptive Name' contains invalid characters."));
		}
		if (!in_array($_POST["keytype"], $ca_keytypes)) {
			array_push($input_errors, gettext("Please select a valid Key Type."));
		}
		if (!in_array($_POST["keylen"], $ca_keylens)) {
			array_push($input_errors, gettext("Please select a valid Key Length."));
		}
		if (!in_array($_POST["ecname"], array_keys($openssl_ecnames))) {
			array_push($input_errors, gettext("Please select a valid Elliptic Curve Name."));
		}
		if (!in_array($_POST["digest_alg"], $openssl_digest_algs)) {
			array_push($input_errors, gettext("Please select a valid Digest Algorithm."));
		}
		if ($_POST['lifetime'] > $max_lifetime) {
			$input_errors[] = gettext("Lifetime is longer than the maximum allowed value. Use a shorter lifetime.");
		}
	}

	if (!empty($_POST['serial']) && !cert_validate_serial($_POST['serial'])) {
		$input_errors[] = gettext("Please enter a valid integer serial number.");
	}

	/* save modifications */
	if (!$input_errors) {
		$ca = array();
		if (!isset($pconfig['refid']) || empty($pconfig['refid'])) {
			$ca['refid'] = uniqid();
		} else {
			$ca['refid'] = $pconfig['refid'];
		}

		if (isset($id) && $thisca) {
			$ca = $thisca;
		}

		$ca['descr'] = $pconfig['descr'];
		$ca['trust'] = ($pconfig['trust'] == 'yes') ? "enabled" : "disabled";
		$ca['randomserial'] = ($pconfig['randomserial'] == 'yes') ? "enabled" : "disabled";

		if ($act == "edit") {
			$ca['descr']  = $pconfig['descr'];
			$ca['refid']  = $pconfig['refid'];
			$ca['serial'] = $pconfig['serial'];
			$ca['crt'] = base64_encode($pconfig['cert']);
			$ca['prv'] = base64_encode($pconfig['key']);
			$savemsg = sprintf(gettext("Updated Certificate Authority %s"), $ca['descr']);
		} else {
			$old_err_level = error_reporting(0); /* otherwise openssl_ functions throw warnings directly to a page screwing menu tab */
			if ($pconfig['method'] == "existing") {
				ca_import($ca, $pconfig['cert'], $pconfig['key'], $pconfig['serial']);
				$savemsg = sprintf(gettext("Imported Certificate Authority %s"), $ca['descr']);
			} else if ($pconfig['method'] == "internal") {
				$dn = array('commonName' => $pconfig['dn_commonname']);
				if (!empty($pconfig['dn_country'])) {
					$dn['countryName'] = $pconfig['dn_country'];
				}
				if (!empty($pconfig['dn_state'])) {
					$dn['stateOrProvinceName'] = $pconfig['dn_state'];
				}
				if (!empty($pconfig['dn_city'])) {
					$dn['localityName'] = $pconfig['dn_city'];
				}
				if (!empty($pconfig['dn_organization'])) {
					$dn['organizationName'] = $pconfig['dn_organization'];
				}
				if (!empty($pconfig['dn_organizationalunit'])) {
					$dn['organizationalUnitName'] = $pconfig['dn_organizationalunit'];
				}
				if (!ca_create($ca, $pconfig['keylen'], $pconfig['lifetime'], $dn, $pconfig['digest_alg'], $pconfig['keytype'], $pconfig['ecname'])) {
					$input_errors = array();
					while ($ssl_err = openssl_error_string()) {
						if (strpos($ssl_err, 'NCONF_get_string:no value') === false) {
							array_push($input_errors, "openssl library returns: " . $ssl_err);
						}
					}
				}
				$savemsg = sprintf(gettext("Created internal Certificate Authority %s"), $ca['descr']);
			} else if ($pconfig['method'] == "intermediate") {
				$dn = array('commonName' => $pconfig['dn_commonname']);
				if (!empty($pconfig['dn_country'])) {
					$dn['countryName'] = $pconfig['dn_country'];
				}
				if (!empty($pconfig['dn_state'])) {
					$dn['stateOrProvinceName'] = $pconfig['dn_state'];
				}
				if (!empty($pconfig['dn_city'])) {
					$dn['localityName'] = $pconfig['dn_city'];
				}
				if (!empty($pconfig['dn_organization'])) {
					$dn['organizationName'] = $pconfig['dn_organization'];
				}
				if (!empty($pconfig['dn_organizationalunit'])) {
					$dn['organizationalUnitName'] = $pconfig['dn_organizationalunit'];
				}
				if (!ca_inter_create($ca, $pconfig['keylen'], $pconfig['lifetime'], $dn, $pconfig['caref'], $pconfig['digest_alg'], $pconfig['keytype'], $pconfig['ecname'])) {
					$input_errors = array();
					while ($ssl_err = openssl_error_string()) {
						if (strpos($ssl_err, 'NCONF_get_string:no value') === false) {
							array_push($input_errors, "openssl library returns: " . $ssl_err);
						}
					}
				}
				$savemsg = sprintf(gettext("Created internal intermediate Certificate Authority %s"), $ca['descr']);
			}
			error_reporting($old_err_level);
		}

		if (isset($id) && $thisca) {
			$thisca = $ca;
		} else {
			$a_ca[] = $ca;
		}

		if (!$input_errors) {
			write_config($savemsg);
			ca_setup_trust_store();
			pfSenseHeader("system_camanager.php");
		}
	}
}

$pgtitle = array(gettext("System"), gettext("Certificate Manager"), gettext("CAs"));
$pglinks = array("", "system_camanager.php", "system_camanager.php");

if ($act == "new" || $act == "edit" || $act == gettext("Save") || $input_errors) {
	$pgtitle[] = gettext('Edit');
	$pglinks[] = "@self";
}
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, $class);
}

$tab_array = array();
$tab_array[] = array(gettext("CAs"), true, "system_camanager.php");
$tab_array[] = array(gettext("Certificates"), false, "system_certmanager.php");
$tab_array[] = array(gettext("Certificate Revocation"), false, "system_crlmanager.php");
display_top_tabs($tab_array);

if (!($act == "new" || $act == "edit" || $act == gettext("Save") || $input_errors)) {
?>
<div class="panel panel-default" id="search-panel">
	<div class="panel-heading">
		<h2 class="panel-title">
			<?=gettext('Search')?>
			<span class="widget-heading-icon pull-right">
				<a data-toggle="collapse" href="#search-panel_panel-body">
					<i class="fa fa-plus-circle"></i>
				</a>
			</span>
		</h2>
	</div>
	<div id="search-panel_panel-body" class="panel-body collapse in">
		<div class="form-group">
			<label class="col-sm-2 control-label">
				<?=gettext("Search term")?>
			</label>
			<div class="col-sm-5"><input class="form-control" name="searchstr" id="searchstr" type="text"/></div>
			<div class="col-sm-2">
				<select id="where" class="form-control">
					<option value="0"><?=gettext("Name")?></option>
					<option value="1"><?=gettext("Distinguished Name")?></option>
					<option value="2" selected><?=gettext("Both")?></option>
				</select>
			</div>
			<div class="col-sm-3">
				<a id="btnsearch" title="<?=gettext("Search")?>" class="btn btn-primary btn-sm"><i class="fa fa-search icon-embed-btn"></i><?=gettext("Search")?></a>
				<a id="btnclear" title="<?=gettext("Clear")?>" class="btn btn-info btn-sm"><i class="fa fa-undo icon-embed-btn"></i><?=gettext("Clear")?></a>
			</div>
			<div class="col-sm-10 col-sm-offset-2">
				<span class="help-block"><?=gettext('Enter a search string or *nix regular expression to search certificate names and distinguished names.')?></span>
			</div>
		</div>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Certificate Authorities')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
		<table id="catable" class="table table-striped table-hover table-rowdblclickedit sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Name")?></th>
					<th><?=gettext("Internal")?></th>
					<th><?=gettext("Issuer")?></th>
					<th><?=gettext("Certificates")?></th>
					<th><?=gettext("Distinguished Name")?></th>
					<th><?=gettext("In Use")?></th>
					<th><?=gettext("Actions")?></th>
				</tr>
			</thead>
			<tbody>
<?php
$pluginparams = array();
$pluginparams['type'] = 'certificates';
$pluginparams['event'] = 'used_ca';
$certificates_used_by_packages = pkg_call_plugins('plugin_certificates', $pluginparams);

foreach ($a_ca as $ca):
	$name = htmlspecialchars($ca['descr']);
	$subj = cert_get_subject($ca['crt']);
	$issuer = cert_get_issuer($ca['crt']);
	if ($subj == $issuer) {
		$issuer_name = gettext("self-signed");
	} else {
		$issuer_name = gettext("external");
	}
	$subj = htmlspecialchars(cert_escape_x509_chars($subj, true));
	$issuer = htmlspecialchars($issuer);
	$certcount = 0;

	$issuer_ca = lookup_ca($ca['caref']);
	if ($issuer_ca) {
		$issuer_name = $issuer_ca['descr'];
	}

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
					<td><i class="fa fa-<?= (!empty($ca['prv'])) ? "check" : "times" ; ?>"></i></td>
					<td><i><?=$issuer_name?></i></td>
					<td><?=$certcount?></td>
					<td>
						<?=$subj?>
						<?php cert_print_infoblock($ca); ?>
						<?php cert_print_dates($ca);?>
					</td>
					<td class="text-nowrap">
						<?php if (is_openvpn_server_ca($ca['refid'])): ?>
							<?=gettext("OpenVPN Server")?><br/>
						<?php endif?>
						<?php if (is_openvpn_client_ca($ca['refid'])): ?>
							<?=gettext("OpenVPN Client")?><br/>
						<?php endif?>
						<?php if (is_ipsec_peer_ca($ca['refid'])): ?>
							<?=gettext("IPsec Tunnel")?><br/>
						<?php endif?>
						<?php if (is_ldap_peer_ca($ca['refid'])): ?>
							<?=gettext("LDAP Server")?>
						<?php endif?>
						<?php echo cert_usedby_description($ca['refid'], $certificates_used_by_packages); ?>
					</td>
					<td class="text-nowrap">
						<a class="fa fa-pencil"	title="<?=gettext("Edit CA")?>"	href="system_camanager.php?act=edit&amp;id=<?=$ca['refid']?>"></a>
						<a class="fa fa-certificate"	title="<?=gettext("Export CA")?>"	href="system_camanager.php?act=exp&amp;id=<?=$ca['refid']?>"></a>
					<?php if ($ca['prv']): ?>
						<a class="fa fa-key"	title="<?=gettext("Export key")?>"	href="system_camanager.php?act=expkey&amp;id=<?=$ca['refid']?>"></a>
					<?php endif?>
					<?php if (is_cert_locally_renewable($ca)): ?>
						<a href="system_certmanager_renew.php?type=ca&amp;refid=<?=$ca['refid']?>" class="fa fa-repeat" title="<?=gettext("Reissue/Renew")?>"></a>
					<?php endif ?>
					<?php if (!ca_in_use($ca['refid'])): ?>
						<a class="fa fa-trash" 	title="<?=gettext("Delete CA and its CRLs")?>"	href="system_camanager.php?act=del&amp;id=<?=$ca['refid']?>" usepost ></a>
					<?php endif?>
					</td>
				</tr>
<?php endforeach; ?>
			</tbody>
		</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<a href="?act=new" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>
<script type="text/javascript">
//<![CDATA[

events.push(function() {

	// Make these controls plain buttons
	$("#btnsearch").prop('type', 'button');
	$("#btnclear").prop('type', 'button');

	// Search for a term in the entry name and/or dn
	$("#btnsearch").click(function() {
		var searchstr = $('#searchstr').val().toLowerCase();
		var table = $("table tbody");
		var where = $('#where').val();

		table.find('tr').each(function (i) {
			var $tds = $(this).find('td'),
				shortname = $tds.eq(0).text().trim().toLowerCase(),
				dn = $tds.eq(4).text().trim().toLowerCase();

			regexp = new RegExp(searchstr);
			if (searchstr.length > 0) {
				if (!(regexp.test(shortname) && (where != 1)) && !(regexp.test(dn) && (where != 0))) {
					$(this).hide();
				} else {
					$(this).show();
				}
			} else {
				$(this).show();	// A blank search string shows all
			}
		});
	});

	// Clear the search term and unhide all rows (that were hidden during a previous search)
	$("#btnclear").click(function() {
		var table = $("table tbody");

		$('#searchstr').val("");

		table.find('tr').each(function (i) {
			$(this).show();
		});
	});

	// Hitting the enter key will do the same as clicking the search button
	$("#searchstr").on("keyup", function (event) {
		if (event.keyCode == 13) {
			$("#btnsearch").get(0).click();
		}
	});
});
//]]>
</script>

<?php
	include("foot.inc");
	exit;
}

$form = new Form;
//$form->setAction('system_camanager.php?act=edit');
if (isset($id) && $thisca) {
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

$section = new Form_Section('Create / Edit CA');

$section->addInput(new Form_Input(
	'descr',
	'*Descriptive name',
	'text',
	$pconfig['descr']
));

if (!isset($id) || $act == "edit") {
	$section->addInput(new Form_Select(
		'method',
		'*Method',
		$pconfig['method'],
		$ca_methods
	))->toggles();
}

$section->addInput(new Form_Checkbox(
	'trust',
	'Trust Store',
	'Add this Certificate Authority to the Operating System Trust Store',
	$pconfig['trust']
))->setHelp('When enabled, the contents of the CA will be added to the trust ' .
	'store so that they will be trusted by the operating system.');

$section->addInput(new Form_Checkbox(
	'randomserial',
	'Randomize Serial',
	'Use random serial numbers when signing certificates',
	$pconfig['randomserial']
))->setHelp('When enabled, if this CA is capable of signing certificates then ' .
		'serial numbers for certificates signed by this CA will be ' .
		'automatically randomized and checked for uniqueness instead of ' .
		'using the sequential value from Next Certificate Serial.');

$form->add($section);

$section = new Form_Section('Existing Certificate Authority');
$section->addClass('toggle-existing collapse');

$section->addInput(new Form_Textarea(
	'cert',
	'*Certificate data',
	$pconfig['cert']
))->setHelp('Paste a certificate in X.509 PEM format here.');

$section->addInput(new Form_Textarea(
	'key',
	'Certificate Private Key (optional)',
	$pconfig['key']
))->setHelp('Paste the private key for the above certificate here. This is '.
	'optional in most cases, but is required when generating a '.
	'Certificate Revocation List (CRL).');

$section->addInput(new Form_Input(
	'serial',
	'Next Certificate Serial',
	'number',
	$pconfig['serial']
))->setHelp('Enter a decimal number to be used as a sequential serial number for ' .
	'the next certificate to be signed by this CA.');

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

$group = new Form_Group('*Signing Certificate Authority');
$group->addClass('toggle-intermediate', 'collapse');
$group->add(new Form_Select(
	'caref',
	null,
	$pconfig['caref'],
	$allCas
));
$section->add($group);

$section->addInput(new Form_Select(
	'keytype',
	'*Key type',
	$pconfig['keytype'],
	array_combine($ca_keytypes, $ca_keytypes)
));

$group = new Form_Group($i == 0 ? '*Key length':'');
$group->addClass('rsakeys');
$group->add(new Form_Select(
	'keylen',
	null,
	$pconfig['keylen'],
	array_combine($ca_keylens, $ca_keylens)
))->setHelp('The length to use when generating a new RSA key, in bits. %1$s' .
	'The Key Length should not be lower than 2048 or some platforms ' .
	'may consider the certificate invalid.', '<br/>');
$section->add($group);

$group = new Form_Group($i == 0 ? '*Elliptic Curve Name':'');
$group->addClass('ecnames');
$group->add(new Form_Select(
	'ecname',
	null,
	$pconfig['ecname'],
	$openssl_ecnames
))->setHelp('Curves may not be compatible with all uses. Known compatible curve uses are denoted in brackets.');
$section->add($group);

$section->addInput(new Form_Select(
	'digest_alg',
	'*Digest Algorithm',
	$pconfig['digest_alg'],
	array_combine($openssl_digest_algs, $openssl_digest_algs)
))->setHelp('The digest method used when the CA is signed. %1$s' .
	'The best practice is to use an algorithm stronger than SHA1. '.
	'Some platforms may consider weaker digest algorithms invalid', '<br/>');

$section->addInput(new Form_Input(
	'lifetime',
	'*Lifetime (days)',
	'number',
	$pconfig['lifetime'],
	['max' => $max_lifetime]
));

$section->addInput(new Form_Input(
	'dn_commonname',
	'*Common Name',
	'text',
	$pconfig['dn_commonname'],
	['placeholder' => 'e.g. internal-ca']
));

$section->addInput(new Form_StaticText(
	null,
	gettext('The following certificate authority subject components are optional and may be left blank.')
));

$section->addInput(new Form_Select(
	'dn_country',
	'Country Code',
	$pconfig['dn_country'],
	get_cert_country_codes()
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
	['placeholder' => 'e.g. My Company Inc']
));

$section->addInput(new Form_Input(
	'dn_organizationalunit',
	'Organizational Unit',
	'text',
	$pconfig['dn_organizationalunit'],
	['placeholder' => 'e.g. My Department Name (optional)']
));

$form->add($section);

print $form;

$internal_ca_count = 0;
foreach ($a_ca as $ca) {
	if ($ca['prv']) {
		$internal_ca_count++;
	}
}

?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {
	function change_keytype() {
	hideClass('rsakeys', ($('#keytype').val() != 'RSA'));
		hideClass('ecnames', ($('#keytype').val() != 'ECDSA'));
	}

	$('#keytype').change(function () {
		change_keytype();
	});

	function check_keylen() {
		var min_keylen = <?= $cert_strict_values['min_private_key_bits'] ?>;
		var klid = '#keylen';
		/* Color the Parent/Label */
		if (parseInt($(klid).val()) < min_keylen) {
			$(klid).parent().parent().removeClass("text-normal").addClass("text-warning");
		} else {
			$(klid).parent().parent().removeClass("text-warning").addClass("text-normal");
		}
		/* Color individual options */
		$(klid + " option").filter(function() {
			return parseInt($(this).val()) < min_keylen;
		}).removeClass("text-normal").addClass("text-warning").siblings().removeClass("text-warning").addClass("text-normal");
	}

	function check_digest() {
		var weak_algs = <?= json_encode($cert_strict_values['digest_blacklist']) ?>;
		var daid = '#digest_alg';
		/* Color the Parent/Label */
		if (jQuery.inArray($(daid).val(), weak_algs) > -1) {
			$(daid).parent().parent().removeClass("text-normal").addClass("text-warning");
		} else {
			$(daid).parent().parent().removeClass("text-warning").addClass("text-normal");
		}
		/* Color individual options */
		$(daid + " option").filter(function() {
			return (jQuery.inArray($(this).val(), weak_algs) > -1);
		}).removeClass("text-normal").addClass("text-warning").siblings().removeClass("text-warning").addClass("text-normal");
	}

	// ---------- Control change handlers ---------------------------------------------------------

	$('#method').on('change', function() {
		check_keylen();
		check_digest();
	});

	$('#keylen').on('change', function() {
		check_keylen();
	});

	$('#digest_alg').on('change', function() {
		check_digest();
	});

	// ---------- On initial page load ------------------------------------------------------------
	change_keytype();
	check_keylen();
	check_digest();
});
//]]>
</script>
<?php
include('foot.inc');
