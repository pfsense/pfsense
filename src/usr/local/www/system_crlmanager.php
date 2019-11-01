<?php
/*
 * system_crlmanager.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-system-crlmanager
##|*NAME=System: CRL Manager
##|*DESCR=Allow access to the 'System: CRL Manager' page.
##|*MATCH=system_crlmanager.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("certs.inc");
require_once("openvpn.inc");
require_once("pfsense-utils.inc");
require_once("vpn.inc");

$max_lifetime = crl_get_max_lifetime();
$default_lifetime = 3650;
if ($max_lifetime < $default_lifetime) {
	$default_lifetime = $max_lifetime;
}

global $openssl_crl_status;

$crl_methods = array(
	"internal" => gettext("Create an internal Certificate Revocation List"),
	"existing" => gettext("Import an existing Certificate Revocation List"));

if (isset($_REQUEST['id']) && ctype_alnum($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

init_config_arr(array('ca'));
$a_ca = &$config['ca'];

init_config_arr(array('cert'));
$a_cert = &$config['cert'];

init_config_arr(array('crl'));
$a_crl = &$config['crl'];

foreach ($a_crl as $cid => $acrl) {
	if (!isset($acrl['refid'])) {
		unset ($a_crl[$cid]);
	}
}

$act = $_REQUEST['act'];


if (!empty($id)) {
	$thiscrl =& lookup_crl($id);
}

// If we were given an invalid crlref in the id, no sense in continuing as it would only cause errors.
if (!$thiscrl && (($act != "") && ($act != "new"))) {
	pfSenseHeader("system_crlmanager.php");
	$act="";
	$savemsg = gettext("Invalid CRL reference.");
	$class = "danger";
}

if ($_POST['act'] == "del") {
	$name = htmlspecialchars($thiscrl['descr']);
	if (crl_in_use($id)) {
		$savemsg = sprintf(gettext("Certificate Revocation List %s is in use and cannot be deleted."), $name);
		$class = "danger";
	} else {
		foreach ($a_crl as $cid => $acrl) {
			if ($acrl['refid'] == $thiscrl['refid']) {
				unset($a_crl[$cid]);
			}
		}
		write_config("Deleted CRL {$name}.");
		$savemsg = sprintf(gettext("Certificate Revocation List %s successfully deleted."), $name);
		$class = "success";
	}
}

if ($act == "new") {
	$pconfig['method'] = $_REQUEST['method'];
	$pconfig['caref'] = $_REQUEST['caref'];
	$pconfig['lifetime'] = $default_lifetime;
	$pconfig['serial'] = "0";
	$crlca =& lookup_ca($pconfig['caref']);
	if (!$crlca) {
		$input_errors[] = gettext('Invalid CA');
		unset($act);
	}
}

if ($act == "exp") {
	crl_update($thiscrl);
	$exp_name = urlencode("{$thiscrl['descr']}.crl");
	$exp_data = base64_decode($thiscrl['text']);
	$exp_size = strlen($exp_data);

	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename={$exp_name}");
	header("Content-Length: $exp_size");
	echo $exp_data;
	exit;
}

if ($act == "addcert") {
	unset($input_errors);
	$pconfig = $_REQUEST;
	$revoke_list = array();

	if (!$pconfig['crlref'] || (!$pconfig['certref'] && !$pconfig['revokeserial'])) {
		pfSenseHeader("system_crlmanager.php");
		exit;
	}

	// certref, crlref
	$crl =& lookup_crl($pconfig['crlref']);
	if (!is_array($pconfig['certref'])) {
		$pconfig['certref'] = array();
	}

	if (empty($pconfig['certref']) && empty($pconfig['revokeserial'])) {
		$input_errors[] = gettext("Select one or more certificates or enter a serial number to revoke.");
	}
	if (!is_crl_internal($crl)) {
		$input_errors[] = gettext("Cannot revoke certificates for an imported/external CRL.");
	}

	foreach ($pconfig['certref'] as $rcert) {
		$cert = lookup_cert($rcert);
		if ($crl['caref'] == $cert['caref']) {
			$revoke_list[] = $cert;
		} else {
			$input_errors[] = gettext("CA mismatch between the Certificate and CRL. Unable to Revoke.");
		}
	}

	foreach (explode(' ', $pconfig['revokeserial']) as $serial) {
		if (empty($serial)) {
			continue;
		}
		$vserial = cert_validate_serial($serial, true);
		if ($vserial != null) {
			$revoke_list[] = $vserial;
		} else {
			$input_errors[] = gettext("Invalid serial in list (Must be ASN.1 integer compatible decimal or hex string).");
		}
	}

	if (!$input_errors) {
		$reason = (empty($pconfig['crlreason'])) ? 0 : $pconfig['crlreason'];

		foreach ($revoke_list as $cert) {
			cert_revoke($cert, $crl, $reason);
		}

		// refresh IPsec and OpenVPN CRLs
		openvpn_refresh_crls();
		vpn_ipsec_configure();
		write_config("Revoked certificate(s) in CRL {$crl['descr']}.");
		pfSenseHeader("system_crlmanager.php");
		exit;
	} else {
		$act = 'edit';
	}
}

if ($act == "delcert") {
	if (!is_array($thiscrl['cert'])) {
		pfSenseHeader("system_crlmanager.php");
		exit;
	}
	$found = false;
	foreach ($thiscrl['cert'] as $acert) {
		if ($acert['refid'] == $_REQUEST['certref']) {
			$found = true;
			$thiscert = $acert;
		}
	}
	if (!$found) {
		pfSenseHeader("system_crlmanager.php");
		exit;
	}
	$certname = htmlspecialchars($thiscert['descr']);
	$crlname = htmlspecialchars($thiscrl['descr']);
	if (cert_unrevoke($thiscert, $thiscrl)) {
		$savemsg = sprintf(gettext('Deleted Certificate %1$s from CRL %2$s.'), $certname, $crlname);
		$class = "success";
		// refresh IPsec and OpenVPN CRLs
		openvpn_refresh_crls();
		vpn_ipsec_configure();
		write_config($savemsg);
	} else {
		$savemsg = sprintf(gettext('Failed to delete Certificate %1$s from CRL %2$s.'), $certname, $crlname);
		$class = "danger";
	}
	$act="edit";
}

if ($_POST['save']) {
	$input_errors = array();
	$pconfig = $_POST;

	/* input validation */
	if (($pconfig['method'] == "existing") || ($act == "editimported")) {
		$reqdfields = explode(" ", "descr crltext");
		$reqdfieldsn = array(
			gettext("Descriptive name"),
			gettext("Certificate Revocation List data"));
	}
	if ($pconfig['method'] == "internal") {
		$reqdfields = explode(" ", "descr caref");
		$reqdfieldsn = array(
			gettext("Descriptive name"),
			gettext("Certificate Authority"));
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (preg_match("/[\?\>\<\&\/\\\"\']/", $pconfig['descr'])) {
		array_push($input_errors, "The field 'Descriptive Name' contains invalid characters.");
	}
	if ($pconfig['lifetime'] > $max_lifetime) {
		$input_errors[] = gettext("Lifetime is longer than the maximum allowed value. Use a shorter lifetime.");
	}

	/* save modifications */
	if (!$input_errors) {
		$result = false;

		if ($thiscrl) {
			$crl =& $thiscrl;
		} else {
			$crl = array();
			$crl['refid'] = uniqid();
		}

		$crl['descr'] = $pconfig['descr'];
		if ($act != "editimported") {
			$crl['caref'] = $pconfig['caref'];
			$crl['method'] = $pconfig['method'];
		}

		if (($pconfig['method'] == "existing") || ($act == "editimported")) {
			$crl['text'] = base64_encode($pconfig['crltext']);
		}

		if ($pconfig['method'] == "internal") {
			$crl['serial'] = empty($pconfig['serial']) ? 9999 : $pconfig['serial'];
			$crl['lifetime'] = empty($pconfig['lifetime']) ? $default_lifetime : $pconfig['lifetime'];
			$crl['cert'] = array();
		}

		if (!$thiscrl) {
			$a_crl[] = $crl;
		}

		write_config("Saved CRL {$crl['descr']}");
		// refresh IPsec and OpenVPN CRLs
		openvpn_refresh_crls();
		vpn_ipsec_configure();
		pfSenseHeader("system_crlmanager.php");
	}
}

$pgtitle = array(gettext("System"), gettext("Certificate Manager"), gettext("Certificate Revocation"));
$pglinks = array("", "system_camanager.php", "system_crlmanager.php");

if ($act == "new" || $act == gettext("Save") || $input_errors || $act == "edit") {
	$pgtitle[] = gettext('Edit');
	$pglinks[] = "@self";
}
include("head.inc");
?>

<script type="text/javascript">
//<![CDATA[

function method_change() {

	method = document.iform.method.value;

	switch (method) {
		case "internal":
			document.getElementById("existing").style.display="none";
			document.getElementById("internal").style.display="";
			break;
		case "existing":
			document.getElementById("existing").style.display="";
			document.getElementById("internal").style.display="none";
			break;
	}
}

//]]>
</script>

<?php

function build_method_list($importonly = false) {
	global $_POST, $crl_methods;

	$list = array();

	foreach ($crl_methods as $method => $desc) {
		if ($importonly && ($method != "existing")) {
			continue;
		}

		$list[$method] = $desc;
	}

	return($list);
}

function build_ca_list() {
	global $a_ca;

	$list = array();

	foreach ($a_ca as $ca) {
		$list[$ca['refid']] = $ca['descr'];
	}

	return($list);
}

function build_cacert_list() {
	global $ca_certs;

	$list = array();

	foreach ($ca_certs as $cert) {
		$list[$cert['refid']] = $cert['descr'];
	}

	return($list);
}

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, $class);
}

$tab_array = array();
$tab_array[] = array(gettext("CAs"), false, "system_camanager.php");
$tab_array[] = array(gettext("Certificates"), false, "system_certmanager.php");
$tab_array[] = array(gettext("Certificate Revocation"), true, "system_crlmanager.php");
display_top_tabs($tab_array);

if ($act == "new" || $act == gettext("Save")) {
	$form = new Form();

	$section = new Form_Section('Create new Revocation List');

	$section->addInput(new Form_StaticText(
		'Certificate Authority',
		$crlca['descr'],
	));

	if (!isset($id)) {
		$section->addInput(new Form_Select(
			'method',
			'*Method',
			$pconfig['method'],
			build_method_list((!isset($crlca['prv']) || empty($crlca['prv'])))
		));
	}

	$section->addInput(new Form_Input(
		'descr',
		'*Descriptive name',
		'text',
		$pconfig['descr']
	));

	$form->addGlobal(new Form_Input(
		'caref',
		null,
		'hidden',
		$pconfig['caref']
	));

	$form->add($section);

	$section = new Form_Section('Existing Certificate Revocation List');
	$section->addClass('existing');

	$section->addInput(new Form_Textarea(
		'crltext',
		'*CRL data',
		$pconfig['crltext']
		))->setHelp('Paste a Certificate Revocation List in X.509 CRL format here.');

	$form->add($section);

	$section = new Form_Section('Internal Certificate Revocation List');
	$section->addClass('internal');

	$section->addInput(new Form_Input(
		'lifetime',
		'Lifetime (Days)',
		'number',
		$pconfig['lifetime'],
		['max' => $max_lifetime]
	));

	$section->addInput(new Form_Input(
		'serial',
		'Serial',
		'number',
		$pconfig['serial'],
		['min' => '0', 'max' => '9999']
	));

	$form->add($section);

	if (isset($id) && $thiscrl) {
		$form->addGlobal(new Form_Input(
			'id',
			null,
			'hidden',
			$id
		));
	}

	print($form);

} elseif ($act == "editimported") {

	$form = new Form();

	$section = new Form_Section('Edit Imported Certificate Revocation List');

	$section->addInput(new Form_Input(
		'descr',
		'*Descriptive name',
		'text',
		$pconfig['descr']
	));

	$section->addInput(new Form_Textarea(
		'crltext',
		'*CRL data',
		$pconfig['crltext']
	))->setHelp('Paste a Certificate Revocation List in X.509 CRL format here.');

	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));

	$form->addGlobal(new Form_Input(
		'act',
		null,
		'hidden',
		'editimported'
	));

	$form->add($section);

	print($form);

} elseif ($act == "edit") {
	$crl = $thiscrl;

	$form = new Form(false);
?>

	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("Currently Revoked Certificates for CRL") . ': ' . $crl['descr']?></h2></div>
		<div class="panel-body table-responsive">
<?php
	if (!is_array($crl['cert']) || (count($crl['cert']) == 0)) {
		print_info_box(gettext("No certificates found for this CRL."), 'danger');
	} else {
?>
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Serial")?></th>
						<th><?=gettext("Certificate Name")?></th>
						<th><?=gettext("Revocation Reason")?></th>
						<th><?=gettext("Revoked At")?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
<?php
		foreach ($crl['cert'] as $i => $cert):
			$name = empty($cert['descr']) ? gettext('Revoked by Serial') : htmlspecialchars($cert['descr']);
			$serial = crl_get_entry_serial($cert);
			if (empty($serial)) {
				$serial = gettext("Invalid");
			} ?>
					<tr>
						<td><?=htmlspecialchars($serial);?></td>
						<td><?=$name; ?></td>
						<td><?=$openssl_crl_status[$cert['reason']]; ?></td>
						<td><?=date("D M j G:i:s T Y", $cert['revoke_time']); ?></td>
						<td class="list">
							<a href="system_crlmanager.php?act=delcert&amp;id=<?=$crl['refid']; ?>&amp;certref=<?=$cert['refid']; ?>" usepost>
								<i class="fa fa-trash" title="<?=gettext("Delete this certificate from the CRL")?>" alt="<?=gettext("Delete this certificate from the CRL")?>"></i>
							</a>
						</td>
					</tr>
<?php
		endforeach;
?>
				</tbody>
			</table>
<?php
	}
?>
		</div>
	</div>
<?php

	$ca_certs = array();
	foreach ($a_cert as $cert) {
		if ($cert['caref'] == $crl['caref'] && !is_cert_revoked($cert, $id)) {
			$ca_certs[] = $cert;
		}
	}

	if (count($ca_certs) == 0) {
		print_info_box(gettext("No certificates found for this CA."), 'danger');
	} else {
		$section = new Form_Section('Choose a Certificate to Revoke');

		$section->addInput(new Form_Select(
			'crlreason',
			'Reason',
			-1,
			$openssl_crl_status
			))->setHelp('Select the reason for which the certificates are being revoked.');

		$cacert_list = build_cacert_list();

		$section->addInput(new Form_Select(
			'certref',
			'Revoke Certificates',
			$pconfig['certref'],
			$cacert_list,
			true
			))->addClass('multiselect')
			->setHelp('Hold down CTRL (PC)/COMMAND (Mac) key to select multiple items.');

		$section->addInput(new Form_Input(
			'revokeserial',
			'Revoke by Serial',
			'text',
			$pconfig['revokeserial']
		))->setHelp('List of certificate serial numbers to revoke (separated by spaces)');

		$form->addGlobal(new Form_Button(
			'submit',
			'Add',
			null,
			'fa-plus'
			))->addClass('btn-success btn-sm');

		$form->addGlobal(new Form_Input(
			'id',
			null,
			'hidden',
			$crl['refid']
		));

		$form->addGlobal(new Form_Input(
			'act',
			null,
			'hidden',
			'addcert'
		));

		$form->addGlobal(new Form_Input(
			'crlref',
			null,
			'hidden',
			$crl['refid']
		));

		$form->add($section);
	}

	print($form);
} else {
?>

	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("Certificate Revocation Lists")?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th><?=gettext("CA")?></th>
						<th><?=gettext("Name")?></th>
						<th><?=gettext("Internal")?></th>
						<th><?=gettext("Certificates")?></th>
						<th><?=gettext("In Use")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody>
<?php
	$pluginparams = array();
	$pluginparams['type'] = 'certificates';
	$pluginparams['event'] = 'used_crl';
	$certificates_used_by_packages = pkg_call_plugins('plugin_certificates', $pluginparams);
	// Map CRLs to CAs in one pass
	$ca_crl_map = array();
	foreach ($a_crl as $crl) {
		$ca_crl_map[$crl['caref']][] = $crl['refid'];
	}

	$i = 0;
	foreach ($a_ca as $ca):
		$caname = htmlspecialchars($ca['descr']);
		if (is_array($ca_crl_map[$ca['refid']])):
			foreach ($ca_crl_map[$ca['refid']] as $crl):
				$tmpcrl = lookup_crl($crl);
				$internal = is_crl_internal($tmpcrl);
				if ($internal && (!isset($tmpcrl['cert']) || empty($tmpcrl['cert'])) ) {
					$tmpcrl['cert'] = array();
				}
				$inuse = crl_in_use($tmpcrl['refid']);
?>
					<tr>
						<td><?=$caname?></td>
						<td><?=$tmpcrl['descr']; ?></td>
						<td><i class="fa fa-<?=($internal) ? "check" : "times"; ?>"></i></td>
						<td><?=($internal) ? count($tmpcrl['cert']) : "Unknown (imported)"; ?></td>
						<td><i class="fa fa-<?=($inuse) ? "check" : "times"; ?>"></i>
						<?php echo cert_usedby_description($tmpcrl['refid'], $certificates_used_by_packages); ?>
						</td>
						<td>
							<a href="system_crlmanager.php?act=exp&amp;id=<?=$tmpcrl['refid']?>" class="fa fa-download" title="<?=gettext("Export CRL")?>" ></a>
<?php
				if ($internal): ?>
							<a href="system_crlmanager.php?act=edit&amp;id=<?=$tmpcrl['refid']?>" class="fa fa-pencil" title="<?=gettext("Edit CRL")?>"></a>
<?php
				else:
?>
							<a href="system_crlmanager.php?act=editimported&amp;id=<?=$tmpcrl['refid']?>" class="fa fa-pencil" title="<?=gettext("Edit CRL")?>"></a>
<?php			endif;
				if (!$inuse):
?>
							<a href="system_crlmanager.php?act=del&amp;id=<?=$tmpcrl['refid']?>" class="fa fa-trash" title="<?=gettext("Delete CRL")?>" usepost></a>
<?php
				endif;
?>
						</td>
					</tr>
<?php
				$i++;
				endforeach;
			endif;
			$i++;
		endforeach;
?>
				</tbody>
			</table>
		</div>
	</div>

<?php
	$form = new Form(false);
	$section = new Form_Section('Create or Import a New Certificate Revocation List');
	$group = new Form_Group(null);
	$group->add(new Form_Select(
		'caref',
		'Certificate Authority',
		null,
		build_ca_list()
		))->setHelp('Select a Certificate Authority for the new CRL');
	$group->add(new Form_Button(
		'submit',
		'Add',
		null,
		'fa-plus'
		))->addClass('btn-success btn-sm');
	$section->add($group);
	$form->addGlobal(new Form_Input(
		'act',
		null,
		'hidden',
		'new'
	));
	$form->add($section);
	print($form);
}

?>

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

	// When the 'method" selector is changed, we show/hide certain sections
	$('#method').on('change', function() {
		hideClass('internal', ($('#method').val() == 'existing'));
		hideClass('existing', ($('#method').val() == 'internal'));
	});

	hideClass('internal', ($('#method').val() == 'existing'));
	hideClass('existing', ($('#method').val() == 'internal'));
	$('.multiselect').attr("size","<?= max(3, min(15, count($cacert_list))) ?>");
});
//]]>
</script>

<?php include("foot.inc");
