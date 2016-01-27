<?php
/*
	system_crlmanager.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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
##|*IDENT=page-system-crlmanager
##|*NAME=System: CRL Manager
##|*DESCR=Allow access to the 'System: CRL Manager' page.
##|*MATCH=system_crlmanager.php*
##|-PRIV

require("guiconfig.inc");
require_once("certs.inc");
require_once("openvpn.inc");
require_once("vpn.inc");

global $openssl_crl_status;

$pgtitle = array(gettext("System"), gettext("Certificate Manager"), gettext("Certificate Revocation Lists"));

$crl_methods = array(
	"internal" => gettext("Create an internal Certificate Revocation List"),
	"existing" => gettext("Import an existing Certificate Revocation List"));

if (ctype_alnum($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && ctype_alnum($_POST['id'])) {
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

foreach ($a_crl as $cid => $acrl) {
	if (!isset($acrl['refid'])) {
		unset ($a_crl[$cid]);
	}
}

$act = $_GET['act'];
if ($_POST['act']) {
	$act = $_POST['act'];
}

if (!empty($id)) {
	$thiscrl =& lookup_crl($id);
}

// If we were given an invalid crlref in the id, no sense in continuing as it would only cause errors.
if (!$thiscrl && (($act != "") && ($act != "new"))) {
	pfSenseHeader("system_crlmanager.php");
	$act="";
	$savemsg = gettext("Invalid CRL reference.");
}

if ($act == "del") {
	$name = htmlspecialchars($thiscrl['descr']);
	if (crl_in_use($id)) {
		$savemsg = sprintf(gettext("Certificate Revocation List %s is in use and cannot be deleted"), $name) . "<br />";
	} else {
		foreach ($a_crl as $cid => $acrl) {
			if ($acrl['refid'] == $thiscrl['refid']) {
				unset($a_crl[$cid]);
			}
		}
		write_config("Deleted CRL {$name}.");
		$savemsg = sprintf(gettext("Certificate Revocation List %s successfully deleted"), $name) . "<br />";
	}
}

if ($act == "new") {
	$pconfig['method'] = $_GET['method'];
	$pconfig['caref'] = $_GET['caref'];
	$pconfig['lifetime'] = "9999";
	$pconfig['serial'] = "0";
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
	if ($_POST) {
		unset($input_errors);
		$pconfig = $_POST;

		if (!$pconfig['crlref'] || !$pconfig['certref']) {
			pfSenseHeader("system_crlmanager.php");
			exit;
		}

		// certref, crlref
		$crl =& lookup_crl($pconfig['crlref']);
		$cert = lookup_cert($pconfig['certref']);

		if (!$crl['caref'] || !$cert['caref']) {
			$input_errors[] = gettext("Both the Certificate and CRL must be specified.");
		}

		if ($crl['caref'] != $cert['caref']) {
			$input_errors[] = gettext("CA mismatch between the Certificate and CRL. Unable to Revoke.");
		}
		if (!is_crl_internal($crl)) {
			$input_errors[] = gettext("Cannot revoke certificates for an imported/external CRL.");
		}

		if (!$input_errors) {
			$reason = (empty($pconfig['crlreason'])) ? OCSP_REVOKED_STATUS_UNSPECIFIED : $pconfig['crlreason'];
			cert_revoke($cert, $crl, $reason);
			// refresh IPsec and OpenVPN CRLs
			openvpn_refresh_crls();
			vpn_ipsec_configure();
			write_config("Revoked cert {$cert['descr']} in CRL {$crl['descr']}.");
			pfSenseHeader("system_crlmanager.php");
			exit;
		}
	}
}

if ($act == "delcert") {
	if (!is_array($thiscrl['cert'])) {
		pfSenseHeader("system_crlmanager.php");
		exit;
	}
	$found = false;
	foreach ($thiscrl['cert'] as $acert) {
		if ($acert['refid'] == $_GET['certref']) {
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
		$savemsg = sprintf(gettext("Deleted Certificate %s from CRL %s"), $certname, $crlname) . "<br />";
		// refresh IPsec and OpenVPN CRLs
		openvpn_refresh_crls();
		vpn_ipsec_configure();
		write_config(sprintf(gettext("Deleted Certificate %s from CRL %s"), $certname, $crlname));
	} else {
		$savemsg = sprintf(gettext("Failed to delete Certificate %s from CRL %s"), $certname, $crlname) . "<br />";
	}
	$act="edit";
}

if ($_POST) {
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

	/* if this is an AJAX caller then handle via JSON */
	if (isAjax() && is_array($input_errors)) {
		input_errors2Ajax($input_errors);
		exit;
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
			$crl['lifetime'] = empty($pconfig['lifetime']) ? 9999 : $pconfig['lifetime'];
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

function build_method_list() {
	global $_GET, $crl_methods;

	$list = array();

	foreach ($crl_methods as $method => $desc) {
		if (($_GET['importonly'] == "yes") && ($method != "existing")) {
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

	foreach($ca_certs as $cert) {
		$list[$cert['refid']] = $cert['descr'];
	}

	return($list);
}

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("CAs"), false, "system_camanager.php");
$tab_array[] = array(gettext("Certificates"), false, "system_certmanager.php");
$tab_array[] = array(gettext("Certificate Revocation"), true, "system_crlmanager.php");
display_top_tabs($tab_array);

if ($act == "new" || $act == gettext("Save") || $input_errors) {
	if (!isset($id)) {
		$form = new Form();

		$section = new Form_Section('Create new revocation list');

		$section->addInput(new Form_Select(
			'method',
			'Method',
			$pconfig['method'],
			build_method_list()
		));

	}

	$section->addInput(new Form_Input(
		'descr',
		'Descriptive name',
		'text',
		$pconfig['descr']
	));

	$section->addInput(new Form_Select(
		'caref',
		'Certificate Authority',
		$pconfig['caref'],
		build_ca_list()
	));

	$form->add($section);

	$section = new Form_Section('Existing Certificate Revocation List');
	$section->addClass('existing');

	$section->addInput(new Form_Textarea(
		'crltext',
		'CRL data',
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
		[max => '9999']
	));

	$section->addInput(new Form_Input(
		'serial',
		'Serial',
		'number',
		$pconfig['serial'],
		[min => '0', max => '9999']
	));

	$form->add($section);

	if (isset($id) && $thiscrl) {
		$section->addInput(new Form_Input(
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
		'Descriptive name',
		'text',
		$pconfig['descr']
	));

	$section->addInput(new Form_Textarea(
		'crltext',
		'CRL data',
		$pconfig['crltext']
	))->setHelp('Paste a Certificate Revocation List in X.509 CRL format here.');

	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));

	$section->addInput(new Form_Input(
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
		print_info_box(gettext("No Certificates Found for this CRL."), 'danger');
	} else {
?>
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><?=gettext("Certificate Name")?></th>
						<th><?=gettext("Revocation Reason")?></th>
						<th><?=gettext("Revoked At")?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
<?php
		foreach ($crl['cert'] as $i => $cert):
			$name = htmlspecialchars($cert['descr']);
?>
					<tr>
						<td class="listlr">
							<?=$name; ?>
						</td>
						<td class="listlr">
							<?=$openssl_crl_status[$cert["reason"]]; ?>
						</td>
						<td class="listlr">
							<?=date("D M j G:i:s T Y", $cert["revoke_time"]); ?>
						</td>
						<td class="list">
							<a href="system_crlmanager.php?act=delcert&amp;id=<?=$crl['refid']; ?>&amp;certref=<?=$cert['refid']; ?>" onclick="return confirm('<?=gettext("Do you really want to delete this Certificate from the CRL?")?>')">
								<i class="fa fa-times-circle" title="<?=gettext("Delete this certificate from the CRL ")?>" alt="<?=gettext("Delete this certificate from the CRL ")?>"></i>
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
		if ($cert['caref'] == $crl['caref']) {
			$ca_certs[] = $cert;
		}
	}

	if (count($ca_certs) == 0) {
		print_info_box(gettext("No Certificates Found for this CA."), 'danger');
	} else {
		$section = new Form_Section('Choose a certificate to revoke');
		$group = new Form_Group(null);

		$group->add(new Form_Select(
			'certref',
			null,
			$pconfig['certref'],
			build_cacert_list()
			))->setWidth(4)->setHelp('Certificate');

		$group->add(new Form_Select(
			'crlreason',
			null,
			-1,
			$openssl_crl_status
			))->setHelp('Reason');

		$group->add(new Form_Button(
			'submit',
			'Add'
			))->removeClass('btn-primary')->addClass('btn-success btn-sm');

		$section->add($group);

		$section->addInput(new Form_Input(
			'id',
			null,
			'hidden',
			$crl['refid']
		));

		$section->addInput(new Form_Input(
			'act',
			null,
			'hidden',
			'addcert'
		));

		$section->addInput(new Form_Input(
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
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("Additional Certificate Revocation Lists")?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><?=gettext("Name")?></th>
						<th><?=gettext("Internal")?></th>
						<th><?=gettext("Certificates")?></th>
						<th><?=gettext("In Use")?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
<?php
	// Map CRLs to CAs in one pass
	$ca_crl_map = array();
	foreach ($a_crl as $crl) {
		$ca_crl_map[$crl['caref']][] = $crl['refid'];
	}

	$i = 0;
	foreach ($a_ca as $ca):
		$name = htmlspecialchars($ca['descr']);

		if ($ca['prv']) {
			$cainternal = "YES";
		} else {
			$cainternal = "NO";
		}
?>
					<tr>
						<td colspan="4">
							<?=$name?>
						</td>
						<td>
<?php
		if ($cainternal == "YES"):
?>
							<a href="system_crlmanager.php?act=new&amp;caref=<?=$ca['refid']; ?>" class="btn btn-xs btn-success">
								<?=gettext("Add or Import CRL")?>
							</a>
<?php
		else:
?>
							<a href="system_crlmanager.php?act=new&amp;caref=<?=$ca['refid']; ?>&amp;importonly=yes" class="btn btn-xs btn-success">
								<?=gettext("Add or Import CRL")?>
							</a>
<?php
		endif;
?>
						</td>
					</tr>
<?php
		if (is_array($ca_crl_map[$ca['refid']])):
			foreach ($ca_crl_map[$ca['refid']] as $crl):
				$tmpcrl = lookup_crl($crl);
				$internal = is_crl_internal($tmpcrl);
				$inuse = crl_in_use($tmpcrl['refid']);
?>
					<tr>
						<td><?=$tmpcrl['descr']; ?></td>
						<td><?=($internal) ? "YES" : "NO"; ?></td>
						<td><?=($internal) ? count($tmpcrl['cert']) : "Unknown (imported)"; ?></td>
						<td><?=($inuse) ? "YES" : "NO"; ?></td>
						<td>
							<a href="system_crlmanager.php?act=exp&amp;id=<?=$tmpcrl['refid']?>" class="btn btn-xs btn-success">
								<?=gettext("Export CRL")?>
							</a>
<?php
				if ($internal): ?>
							<a href="system_crlmanager.php?act=edit&amp;id=<?=$tmpcrl['refid']?>" class="btn btn-xs btn-info">
								<?=gettext("Edit CRL")?>
							</a>
<?php
				else:
?>
							<a href="system_crlmanager.php?act=editimported&amp;id=<?=$tmpcrl['refid']?>" class="btn btn-xs btn-info">
								<?=gettext("Edit CRL")?>
							</a>
<?php			endif;
				if (!$inuse):
?>
							<a href="system_crlmanager.php?act=del&amp;id=<?=$tmpcrl['refid']?>" class="btn btn-xs btn-danger">
								<?=gettext("Delete CRL")?>
							</a>
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
}
?>

<script>
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
});
//]]>
</script>

<?php include("foot.inc");

