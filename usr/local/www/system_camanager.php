<?php
/*
    system_camanager.php

    Copyright (C) 2008 Shrew Soft Inc.
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
	pfSense_MODULE:	certificate_managaer
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

$ca_keylens = array( "512", "1024", "2048", "4096");
$openssl_digest_algs = array("sha1", "sha224", "sha256", "sha384", "sha512");

$pgtitle = array(gettext("System"), gettext("Certificate Authority Manager"));

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (!is_array($config['ca']))
	$config['ca'] = array();

$a_ca =& $config['ca'];

if (!is_array($config['cert']))
	$config['cert'] = array();

$a_cert =& $config['cert'];

if (!is_array($config['crl']))
	$config['crl'] = array();

$a_crl =& $config['crl'];

$act = $_GET['act'];
if ($_POST['act'])
	$act = $_POST['act'];

if ($act == "del") {

	if (!isset($a_ca[$id])) {
		pfSenseHeader("system_camanager.php");
		exit;
	}

	$index = count($a_cert) - 1;
	for (;$index >=0; $index--)
		if ($a_cert[$index]['caref'] == $a_ca[$id]['refid'])
			unset($a_cert[$index]);

	$index = count($a_crl) - 1;
	for (;$index >=0; $index--)
		if ($a_crl[$index]['caref'] == $a_ca[$id]['refid'])
			unset($a_crl[$index]);

	$name = $a_ca[$id]['descr'];
	unset($a_ca[$id]);
	write_config();
	$savemsg = sprintf(gettext("Certificate Authority %s and its CRLs (if any) successfully deleted"), $name) . "<br />";
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
	if (!empty($a_ca[$id]['prv']))
		$pconfig['key'] = base64_decode($a_ca[$id]['prv']);
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
		if ($_POST['cert'] && (!strstr($_POST['cert'], "BEGIN CERTIFICATE") || !strstr($_POST['cert'], "END CERTIFICATE")))
			$input_errors[] = gettext("This certificate does not appear to be valid.");
		if ($_POST['key'] && strstr($_POST['key'], "ENCRYPTED"))
			$input_errors[] = gettext("Encrypted private keys are not yet supported.");
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
		for ($i = 0; $i < count($reqdfields); $i++) {
			if ($reqdfields[$i] == 'dn_email'){
				if (preg_match("/[\!\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $_POST["dn_email"]))
					array_push($input_errors, "The field 'Distinguished name Email Address' contains invalid characters.");
			}else if ($reqdfields[$i] == 'dn_commonname'){
				if (preg_match("/[\!\@\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $_POST["dn_commonname"]))
					array_push($input_errors, "The field 'Distinguished name Common Name' contains invalid characters.");
			}else if (($reqdfields[$i] != "descr") && preg_match("/[\!\@\#\$\%\^\(\)\~\?\>\<\&\/\\\,\.\"\']/", $_POST["$reqdfields[$i]"]))
				array_push($input_errors, "The field '" . $reqdfieldsn[$i] . "' contains invalid characters.");
		}
		if (!in_array($_POST["keylen"], $ca_keylens))
			array_push($input_errors, gettext("Please select a valid Key Length."));
		if (!in_array($_POST["digest_alg"], $openssl_digest_algs))
			array_push($input_errors, gettext("Please select a valid Digest Algorithm."));
	}

	/* if this is an AJAX caller then handle via JSON */
	if (isAjax() && is_array($input_errors)) {
		input_errors2Ajax($input_errors);
		exit;
	}

	/* save modifications */
	if (!$input_errors) {

		$ca = array();
		if (!isset($pconfig['refid']) || empty($pconfig['refid']))
			$ca['refid'] = uniqid();
		else
			$ca['refid'] = $pconfig['refid'];

		if (isset($id) && $a_ca[$id])
			$ca = $a_ca[$id];

		$ca['descr'] = $pconfig['descr'];

		if ($_POST['edit'] == "edit") {
			$ca['descr']  = $pconfig['descr'];
			$ca['refid']  = $pconfig['refid'];
			$ca['serial'] = $pconfig['serial'];
			$ca['crt']    = base64_encode($pconfig['cert']);
			if (!empty($pconfig['key']))
				$ca['prv']    = base64_encode($pconfig['key']);
		} else {
			$old_err_level = error_reporting(0); /* otherwise openssl_ functions throw warings directly to a page screwing menu tab */
			if ($pconfig['method'] == "existing")
				ca_import($ca, $pconfig['cert'], $pconfig['key'], $pconfig['serial']);

			else if ($pconfig['method'] == "internal") {
				$dn = array(
					'countryName' => $pconfig['dn_country'],
					'stateOrProvinceName' => $pconfig['dn_state'],
					'localityName' => $pconfig['dn_city'],
					'organizationName' => $pconfig['dn_organization'],
					'emailAddress' => $pconfig['dn_email'],
					'commonName' => $pconfig['dn_commonname']);
				if (!ca_create($ca, $pconfig['keylen'], $pconfig['lifetime'], $dn, $pconfig['digest_alg'])){
					while($ssl_err = openssl_error_string()){
						$input_errors = array();
						array_push($input_errors, "openssl library returns: " . $ssl_err);
					}
				}
			}
			else if ($pconfig['method'] == "intermediate") {
				$dn = array(
					'countryName' => $pconfig['dn_country'],
					'stateOrProvinceName' => $pconfig['dn_state'],
					'localityName' => $pconfig['dn_city'],
					'organizationName' => $pconfig['dn_organization'],
					'emailAddress' => $pconfig['dn_email'],
					'commonName' => $pconfig['dn_commonname']);
				if (!ca_inter_create($ca, $pconfig['keylen'], $pconfig['lifetime'], $dn, $pconfig['caref'], $pconfig['digest_alg'])){
					while($ssl_err = openssl_error_string()){
						$input_errors = array();
						array_push($input_errors, "openssl library returns: " . $ssl_err);
					}
				}
			}
			error_reporting($old_err_level);
		}

		if (isset($id) && $a_ca[$id])
			$a_ca[$id] = $ca;
		else
			$a_ca[] = $ca;

		if (!$input_errors)
			write_config();

//		pfSenseHeader("system_camanager.php");
	}
}

include("head.inc");
?>

<body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[

function method_change() {

	method = document.iform.method.selectedIndex;

	switch (method) {
		case 0:
			document.getElementById("existing").style.display="";
			document.getElementById("internal").style.display="none";
			document.getElementById("intermediate").style.display="none";
			break;
		case 1:
			document.getElementById("existing").style.display="none";
			document.getElementById("internal").style.display="";
			document.getElementById("intermediate").style.display="none";
			break;
		case 2:
			document.getElementById("existing").style.display="none";
			document.getElementById("internal").style.display="";
			document.getElementById("intermediate").style.display="";
			break;
	}
}

//]]>
</script>
<?php
	if ($input_errors)
		print_input_errors($input_errors);
	if ($savemsg)
		print_info_box($savemsg);

	// Load valid country codes
	$dn_cc = array();
	if (file_exists("/etc/ca_countries")){
		$dn_cc_file=file("/etc/ca_countries");
		foreach($dn_cc_file as $line)
			if (preg_match('/^(\S*)\s(.*)$/', $line, $matches))
				array_push($dn_cc, $matches[1]);
	}
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="CA manager">
	<tr>
		<td>
		<?php
			$tab_array = array();
			$tab_array[] = array(gettext("CAs"), true, "system_camanager.php");
			$tab_array[] = array(gettext("Certificates"), false, "system_certmanager.php");
			$tab_array[] = array(gettext("Certificate Revocation"), false, "system_crlmanager.php");
			display_top_tabs($tab_array);
		?>
		</td>
	</tr>
	<tr>
		<td id="mainarea">
			<div class="tabcont">

				<?php if ($act == "new" || $act == "edit" || $act == gettext("Save") || $input_errors): ?>

				<form action="system_camanager.php" method="post" name="iform" id="iform">
					<?php if ($act == "edit"): ?>
					<input type="hidden" name="edit" value="edit" id="edit" />
					<input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>" id="id" />
					<input type="hidden" name="refid" value="<?php echo $pconfig['refid']; ?>" id="refid" />
					<?php endif; ?>
					<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Descriptive name");?></td>
							<td width="78%" class="vtable">
								<input name="descr" type="text" class="formfld unknown" id="descr" size="20" value="<?=htmlspecialchars($pconfig['descr']);?>"/>
							</td>
						</tr>
						<?php if (!isset($id) || $act == "edit"): ?>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Method");?></td>
							<td width="78%" class="vtable">
								<select name='method' id='method' class="formselect" onchange='method_change()'>
								<?php
									foreach($ca_methods as $method => $desc):
									$selected = "";
									if ($pconfig['method'] == $method)
										$selected = " selected=\"selected\"";
								?>
									<option value="<?=$method;?>"<?=$selected;?>><?=$desc;?></option>
								<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<?php endif; ?>
					</table>

					<table width="100%" border="0" cellpadding="6" cellspacing="0" id="existing" summary="existing">
						<tr>
							<td colspan="2" class="list" height="12"></td>
						</tr>
						<tr>
							<td colspan="2" valign="top" class="listtopic"><?=gettext("Existing Certificate Authority");?></td>
						</tr>

						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Certificate data");?></td>
							<td width="78%" class="vtable">
								<textarea name="cert" id="cert" cols="65" rows="7" class="formfld_cert"><?=htmlspecialchars($pconfig['cert']);?></textarea>
								<br />
								<?=gettext("Paste a certificate in X.509 PEM format here.");?>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Certificate Private Key");?><br /><?=gettext("(optional)");?></td>
							<td width="78%" class="vtable">
								<textarea name="key" id="key" cols="65" rows="7" class="formfld_cert"><?=htmlspecialchars($pconfig['key']);?></textarea>
								<br />
								<?=gettext("Paste the private key for the above certificate here. This is optional in most cases, but required if you need to generate a Certificate Revocation List (CRL).");?>
							</td>
						</tr>

					<?php if (!isset($id) || $act == "edit"): ?>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Serial for next certificate");?></td>
							<td width="78%" class="vtable">
								<input name="serial" type="text" class="formfld unknown" id="serial" size="20" value="<?=htmlspecialchars($pconfig['serial']);?>"/>
								<br /><?=gettext("Enter a decimal number to be used as the serial number for the next certificate to be created using this CA.");?>
							</td>
						</tr>
					<?php endif; ?>
					</table>

					<table width="100%" border="0" cellpadding="6" cellspacing="0" id="internal" summary="internal">
						<tr>
							<td colspan="2" class="list" height="12"></td>
						</tr>
						<tr>
							<td colspan="2" valign="top" class="listtopic"><?=gettext("Internal Certificate Authority");?></td>
						</tr>
						<tr id='intermediate'>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Signing Certificate Authority");?></td>
							<td width="78%" class="vtable">
                                                                <select name='caref' id='caref' class="formselect" onchange='internalca_change()'>
                                                                <?php
                                                                        foreach( $a_ca as $ca):
                                                                        if (!$ca['prv'])
                                                                                continue;
                                                                        $selected = "";
                                                                        if ($pconfig['caref'] == $ca['refid'])
                                                                                $selected = " selected=\"selected\"";
                                                                ?>
                                                                        <option value="<?=$ca['refid'];?>"<?=$selected;?>><?=$ca['descr'];?></option>
                                                                <?php endforeach; ?>
                                                                </select>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Key length");?></td>
							<td width="78%" class="vtable">
								<select name='keylen' id='keylen' class="formselect">
								<?php
									foreach( $ca_keylens as $len):
									$selected = "";
									if ($pconfig['keylen'] == $len)
										$selected = " selected=\"selected\"";
								?>
									<option value="<?=$len;?>"<?=$selected;?>><?=$len;?></option>
								<?php endforeach; ?>
								</select>
								<?=gettext("bits");?>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Digest Algorithm");?></td>
							<td width="78%" class="vtable">
								<select name='digest_alg' id='digest_alg' class="formselect">
								<?php
									foreach( $openssl_digest_algs as $digest_alg):
									$selected = "";
									if ($pconfig['digest_alg'] == $digest_alg)
										$selected = " selected=\"selected\"";
								?>
									<option value="<?=$digest_alg;?>"<?=$selected;?>><?=strtoupper($digest_alg);?></option>
								<?php endforeach; ?>
								</select>
								<br /><?= gettext("NOTE: It is recommended to use an algorithm stronger than SHA1 when possible.") ?>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Lifetime");?></td>
							<td width="78%" class="vtable">
								<input name="lifetime" type="text" class="formfld unknown" id="lifetime" size="5" value="<?=htmlspecialchars($pconfig['lifetime']);?>"/>
								<?=gettext("days");?>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Distinguished name");?></td>
							<td width="78%" class="vtable">
								<table border="0" cellspacing="0" cellpadding="2" summary="name">
									<tr>
										<td align="right"><?=gettext("Country Code");?> : &nbsp;</td>
										<td align="left">
											<select name='dn_country' class="formselect">
											<?php
											foreach( $dn_cc as $cc){
												$selected = "";
												if ($pconfig['dn_country'] == $cc)
													$selected = " selected=\"selected\"";
												print "<option value=\"$cc\"$selected>$cc</option>";
												}
											?>
											</select>
										</td>
									</tr>
									<tr>
										<td align="right"><?=gettext("State or Province");?> : &nbsp;</td>
										<td align="left">
											<input name="dn_state" type="text" class="formfld unknown" size="40" value="<?=htmlspecialchars($pconfig['dn_state']);?>"/>
											&nbsp;
											<em><?=gettext("ex:");?></em>
											&nbsp;
											<?=gettext("Texas");?>
										</td>
									</tr>
									<tr>
										<td align="right"><?=gettext("City");?> : &nbsp;</td>
										<td align="left">
											<input name="dn_city" type="text" class="formfld unknown" size="40" value="<?=htmlspecialchars($pconfig['dn_city']);?>"/>
											&nbsp;
											<em><?=gettext("ex:");?></em>
											&nbsp;
											<?=gettext("Austin");?>
										</td>
									</tr>
									<tr>
										<td align="right"><?=gettext("Organization");?> : &nbsp;</td>
										<td align="left">
											<input name="dn_organization" type="text" class="formfld unknown" size="40" value="<?=htmlspecialchars($pconfig['dn_organization']);?>"/>
											&nbsp;
											<em><?=gettext("ex:");?></em>
											&nbsp;
											<?=gettext("My Company Inc.");?>
										</td>
									</tr>
									<tr>
										<td align="right"><?=gettext("Email Address");?> : &nbsp;</td>
										<td align="left">
											<input name="dn_email" type="text" class="formfld unknown" size="25" value="<?=htmlspecialchars($pconfig['dn_email']);?>"/>
											&nbsp;
											<em><?=gettext("ex:");?></em>
											&nbsp;
											<?=gettext("admin@mycompany.com");?>
										</td>
									</tr>
									<tr>
										<td align="right"><?=gettext("Common Name");?> : &nbsp;</td>
										<td align="left">
											<input name="dn_commonname" type="text" class="formfld unknown" size="25" value="<?=htmlspecialchars($pconfig['dn_commonname']);?>"/>
											&nbsp;
											<em><?=gettext("ex:");?></em>
											&nbsp;
											<?=gettext("internal-ca");?>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>

					<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="save">
						<tr>
							<td width="22%" valign="top">&nbsp;</td>
							<td width="78%">
								<input id="submit" name="save" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" />
								<?php if (isset($id) && $a_ca[$id]): ?>
								<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
								<?php endif;?>
							</td>
						</tr>
					</table>
				</form>

				<?php else: ?>

				<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="">
					<tr>
						<td width="20%" class="listhdrr"><?=gettext("Name");?></td>
						<td width="10%" class="listhdrr"><?=gettext("Internal");?></td>
						<td width="10%" class="listhdrr"><?=gettext("Issuer");?></td>
						<td width="10%" class="listhdrr"><?=gettext("Certificates");?></td>
						<td width="40%" class="listhdrr"><?=gettext("Distinguished Name");?></td>
						<td width="10%" class="list"></td>
					</tr>
					<?php
						$i = 0;
						foreach($a_ca as $ca):
							$name = htmlspecialchars($ca['descr']);
							$subj = cert_get_subject($ca['crt']);
							$issuer = cert_get_issuer($ca['crt']);
							list($startdate, $enddate) = cert_get_dates($ca['crt']);
							if($subj == $issuer)
							  $issuer_name = "<em>" . gettext("self-signed") . "</em>";
							else
							  $issuer_name = "<em>" . gettext("external") . "</em>";
							$subj = htmlspecialchars($subj);
							$issuer = htmlspecialchars($issuer);
							$certcount = 0;

							$issuer_ca = lookup_ca($ca['caref']);
							if ($issuer_ca)
								$issuer_name = $issuer_ca['descr'];

							// TODO : Need gray certificate icon

							if($ca['prv']) {
								$caimg = "/themes/{$g['theme']}/images/icons/icon_frmfld_cert.png";
								$internal = "YES";

							} else {
								$caimg = "/themes/{$g['theme']}/images/icons/icon_frmfld_cert.png";
								$internal = "NO";
							}
							foreach ($a_cert as $cert)
								if ($cert['caref'] == $ca['refid'])
									$certcount++;
  						foreach ($a_ca as $cert)
  							if ($cert['caref'] == $ca['refid'])
  								$certcount++;
					?>
					<tr>
						<td class="listlr">
							<table border="0" cellpadding="0" cellspacing="0" summary="icon">
								<tr>
									<td align="left" valign="middle">
										<img src="<?=$caimg;?>" alt="CA" title="CA" border="0" height="16" width="16" />
									</td>
									<td align="left" valign="middle">
										<?=$name;?>
									</td>
								</tr>
							</table>
						</td>
						<td class="listr"><?=$internal;?>&nbsp;</td>
						<td class="listr"><?=$issuer_name;?>&nbsp;</td>
						<td class="listr"><?=$certcount;?>&nbsp;</td>
						<td class="listr"><?=$subj;?><br />
							<table width="100%" style="font-size: 9px" summary="valid">
								<tr>
									<td width="10%">&nbsp;</td>
									<td width="20%"><?=gettext("Valid From")?>:</td>
									<td width="70%"><?= $startdate ?></td>
								</tr>
								<tr>
									<td>&nbsp;</td>
									<td><?=gettext("Valid Until")?>:</td>
									<td><?= $enddate ?></td>
								</tr>
							</table>
						</td>
						<td valign="middle" class="list nowrap">
							<a href="system_camanager.php?act=edit&amp;id=<?=$i;?>">
								<img src="/themes/<?= $g['theme'];?>/images/icons/icon_e.gif" title="<?=gettext("edit CA");?>" alt="<?=gettext("edit CA");?>" width="17" height="17" border="0" />
							</a>
							<a href="system_camanager.php?act=exp&amp;id=<?=$i;?>">
								<img src="/themes/<?= $g['theme'];?>/images/icons/icon_down.gif" title="<?=gettext("export CA cert");?>" alt="<?=gettext("export CA cert");?>" width="17" height="17" border="0" />
							</a>
							<?php if ($ca['prv']): ?>
							<a href="system_camanager.php?act=expkey&amp;id=<?=$i;?>">
								<img src="/themes/<?= $g['theme'];?>/images/icons/icon_down.gif" title="<?=gettext("export CA private key");?>" alt="<?=gettext("export CA private key");?>" width="17" height="17" border="0" />
							</a>
							<?php endif; ?>
							<a href="system_camanager.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this Certificate Authority and its CRLs, and unreference any associated certificates?");?>')">
								<img src="/themes/<?= $g['theme'];?>/images/icons/icon_x.gif" title="<?=gettext("delete ca");?>" alt="<?=gettext("delete ca"); ?>" width="17" height="17" border="0" />
							</a>
						</td>
					</tr>
					<?php
							$i++;
						endforeach;
					?>
					<tr>
						<td class="list" colspan="5"></td>
						<td class="list">
							<a href="system_camanager.php?act=new">
								<img src="/themes/<?= $g['theme'];?>/images/icons/icon_plus.gif" title="<?=gettext("add or import ca");?>" alt="<?=gettext("add ca");?>" width="17" height="17" border="0" />
							</a>
						</td>
					</tr>
					<tr>
						<td colspan="5">
							<p>
								<?=gettext("Additional trusted Certificate Authorities can be added here.");?>
							</p>
						</td>
					</tr>
				</table>

				<?php endif; ?>

			</div>
		</td>
	</tr>
</table>
<?php include("fend.inc");?>
<script type="text/javascript">
//<![CDATA[

method_change();

//]]>
</script>

</body>
</html>
