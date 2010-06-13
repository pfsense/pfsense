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
	"internal" => gettext("Create an internal Certificate Authority"));

$ca_keylens = array( "512", "1024", "2048", "4096");

$pgtitle = array(gettext("System"), gettext("Certificate Authority Manager"));

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (!is_array($config['system']['ca']))
	$config['system']['ca'] = array();

$a_ca =& $config['system']['ca'];

if (!is_array($config['system']['cert']))
	$config['system']['cert'] = array();

$a_cert =& $config['system']['cert'];

$act = $_GET['act'];
if ($_POST['act'])
	$act = $_POST['act'];

if ($act == "del") {

	if (!$a_ca[$id]) {
		pfSenseHeader("system_camanager.php");
		exit;
	}

	$index = count($a_cert) - 1;
	for (;$index >=0; $index--)
		if ($a_cert[$index]['caref'] == $a_ca[$id]['refid'])
			unset($a_cert[$index]);

	$name = $a_ca[$id]['name'];
	unset($a_ca[$id]);
	write_config();
	$savemsg = gettext("Certificate Authority")." {$name} ".
				gettext("successfully deleted")."<br/>";
}

if ($act == "new") {
	$pconfig['method'] = $_GET['method'];
	$pconfig['keylen'] = "2048";
	$pconfig['lifetime'] = "3650";
	$pconfig['dn_commonname'] = "internal-ca";
}

if ($act == "exp") {

	if (!$a_ca[$id]) {
		pfSenseHeader("system_camanager.php");
		exit;
	}

	$exp_name = urlencode("{$a_ca[$id]['name']}.crt");
	$exp_data = base64_decode($a_ca[$id]['crt']);
	$exp_size = strlen($exp_data);

	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename={$exp_name}");
	header("Content-Length: $exp_size");
	echo $exp_data;
	exit;
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($pconfig['method'] == "existing") {
		$reqdfields = explode(" ", "name cert");
		$reqdfieldsn = array(
				gettext("Descriptive name"),
				gettext("Certificate data"));
	}
	if ($pconfig['method'] == "internal") {
		$reqdfields = explode(" ",
				"name keylen lifetime dn_country dn_state dn_city ".
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

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	/* if this is an AJAX caller then handle via JSON */
	if (isAjax() && is_array($input_errors)) {
		input_errors2Ajax($input_errors);
		exit;
	}

	/* save modifications */
	if (!$input_errors) {

		$ca = array();
		$ca['refid'] = uniqid();
		if (isset($id) && $a_ca[$id])
			$ca = $a_ca[$id];

	    $ca['name'] = $pconfig['name'];

		if ($pconfig['method'] == "existing")
			ca_import($ca, $pconfig['cert']);

		if ($pconfig['method'] == "internal")
		{
			$dn = array(
				'countryName' => $pconfig['dn_country'],
				'stateOrProvinceName' => $pconfig['dn_state'],
				'localityName' => $pconfig['dn_city'],
				'organizationName' => $pconfig['dn_organization'],
				'emailAddress' => $pconfig['dn_email'],
				'commonName' => $pconfig['dn_commonname']);

			ca_create($ca, $pconfig['keylen'], $pconfig['lifetime'], $dn);
		}

		if (isset($id) && $a_ca[$id])
			$a_ca[$id] = $ca;
		else
			$a_ca[] = $ca;

		write_config();

//		pfSenseHeader("system_camanager.php");
	}
}

include("head.inc");
?>

<body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
<!--

function method_change() {

	method = document.iform.method.selectedIndex;

	switch (method) {
		case 0:
			document.getElementById("existing").style.display="";
			document.getElementById("internal").style.display="none";
			break;
		case 1:
			document.getElementById("existing").style.display="none";
			document.getElementById("internal").style.display="";
			break;
	}
}

//-->
</script>
<?php
	if ($input_errors)
		print_input_errors($input_errors);
	if ($savemsg)
		print_info_box($savemsg);
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
		<?php
			$tab_array = array();
			$tab_array[] = array(gettext("CAs"), true, "system_camanager.php");
			$tab_array[] = array(gettext("Certificates"), false, "system_certmanager.php");
			display_top_tabs($tab_array);
		?>
		</td>
	</tr>
	<tr>
		<td id="mainarea">
			<div class="tabcont">

				<?php if ($act == "new" || $act == gettext("save") || $input_errors): ?>

				<form action="system_camanager.php" method="post" name="iform" id="iform">
					<table width="100%" border="0" cellpadding="6" cellspacing="0">
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Descriptive name");?></td>
							<td width="78%" class="vtable">
								<input name="name" type="text" class="formfld unknown" id="name" size="20" value="<?=htmlspecialchars($pconfig['name']);?>"/>
							</td>
						</tr>
						<?php if (!isset($id)): ?>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Method");?></td>
							<td width="78%" class="vtable">
								<select name='method' id='method' class="formselect" onchange='method_change()'>
								<?php
									foreach($ca_methods as $method => $desc):
									$selected = "";
									if ($pconfig['method'] == $method)
										$selected = "selected";
								?>
									<option value="<?=$method;?>"<?=$selected;?>><?=$desc;?></option>
								<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<?php endif; ?>
					</table>

					<table width="100%" border="0" cellpadding="6" cellspacing="0" id="existing">
						<tr>
							<td colspan="2" class="list" height="12"></td>
						</tr>
						<tr>
							<td colspan="2" valign="top" class="listtopic"><?=gettext("Existing Certificate Authority");?></td>
						</tr>

						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Certificate data");?></td>
							<td width="78%" class="vtable">
								<textarea name="cert" id="cert" cols="65" rows="7" class="formfld_cert"><?=$pconfig['cert'];?></textarea>
								<br>
								<?=gettext("Paste a certificate in X.509 PEM format here.");?></td>
							</td>
						</tr>
					</table>

					<table width="100%" border="0" cellpadding="6" cellspacing="0" id="internal">
						<tr>
							<td colspan="2" class="list" height="12"></td>
						</tr>
						<tr>
							<td colspan="2" valign="top" class="listtopic"><?=gettext("Internal Certificate Authority");?></td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Key length");?></td>
							<td width="78%" class="vtable">
								<select name='keylen' id='keylen' class="formselect">
								<?php
									foreach( $ca_keylens as $len):
									$selected = "";
									if ($pconfig['keylen'] == $len)
										$selected = "selected";
								?>
									<option value="<?=$len;?>"<?=$selected;?>><?=$len;?></option>
								<?php endforeach; ?>
								</select>
								<?=gettext(bits);?>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Lifetime");?></td>
							<td width="78%" class="vtable">
								<input name="lifetime" type="text" class="formfld unknown" id="lifetime" size="5" value="<?=htmlspecialchars($pconfig['lifetime']);?>"/>
								<?=gettext(days);?>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Distinguished name");?></td>
							<td width="78%" class="vtable">
								<table border="0" cellspacing="0" cellpadding="2">
									<tr>
										<td align="right"><?=gettext("Country Code");?> : &nbsp;</td>
										<td align="left">
											<input name="dn_country" type="text" class="formfld unknown" maxlength="2" size="2" value="<?=htmlspecialchars($pconfig['dn_country']);?>"/>
											&nbsp;
											<em>ex:</em>
											&nbsp;
											<?=gettext("US");?>
											<em><?=gettext("( two letters )");?></em>
										</td>
									</tr>
									<tr>
										<td align="right"><?=gettext("State or Province");?> : &nbsp;</td>
										<td align="left">
											<input name="dn_state" type="text" class="formfld unknown" size="40" value="<?=htmlspecialchars($pconfig['dn_state']);?>"/>
											&nbsp;
											<em>ex:</em>
											&nbsp;
											<?=gettext("Texas");?>
										</td>
									</tr>
									<tr>
										<td align="right"><?=gettext("City");?> : &nbsp;</td>
										<td align="left">
											<input name="dn_city" type="text" class="formfld unknown" size="40" value="<?=htmlspecialchars($pconfig['dn_city']);?>"/>
											&nbsp;
											<em>ex:</em>
											&nbsp;
											<?=gettext("Austin");?>
										</td>
									</tr>
									<tr>
										<td align="right"><?=gettext("Organization");?> : &nbsp;</td>
										<td align="left">
											<input name="dn_organization" type="text" class="formfld unknown" size="40" value="<?=htmlspecialchars($pconfig['dn_organization']);?>"/>
											&nbsp;
											<em>ex:</em>
											&nbsp;
											<?=gettext("My Company Inc.");?>
										</td>
									</tr>
									<tr>
										<td align="right"><?=gettext("Email Address");?> : &nbsp;</td>
										<td align="left">
											<input name="dn_email" type="text" class="formfld unknown" size="25" value="<?=htmlspecialchars($pconfig['dn_email']);?>"/>
											&nbsp;
											<em>ex:</em>
											&nbsp;
											<?=gettext("admin@mycompany.com");?>
										</td>
									</tr>
									<tr>
										<td align="right"><?=gettext("Common Name");?> : &nbsp;</td>
										<td align="left">
											<input name="dn_commonname" type="text" class="formfld unknown" size="25" value="<?=htmlspecialchars($pconfig['dn_commonname']);?>"/>
											&nbsp;
											<em>ex:</em>
											&nbsp;
											<?=gettext("internal-ca");?>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>

					<table width="100%" border="0" cellpadding="6" cellspacing="0">
						<tr>
							<td width="22%" valign="top">&nbsp;</td>
							<td width="78%">
								<input id="submit" name="save" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" />
								<?php if (isset($id) && $a_ca[$id]): ?>
								<input name="id" type="hidden" value="<?=$id;?>" />
								<?php endif;?>
							</td>
						</tr>
					</table>
				</form>

				<?php else: ?>

				<table width="100%" border="0" cellpadding="0" cellspacing="0">
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
							$name = htmlspecialchars($ca['name']);
							$subj = cert_get_subject($ca['crt']);
							$issuer = cert_get_issuer($ca['crt']);
							if($subj == $issuer)
							  $issuer_name = "<em>" . gettext("self-signed") . "</em>";
							else
							  $issuer_name = "<em>" . gettext("external") . "</em>";
							$subj = htmlspecialchars($subj);
							$issuer = htmlspecialchars($issuer);
							$certcount = 0;

							$issuer_ca = lookup_ca($ca['caref']);
							if ($issuer_ca)
								$issuer_name = $issuer_ca['name'];

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
							<table border="0" cellpadding="0" cellspacing="0">
								<tr>
									<td align="left" valign="center">
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
						<td class="listr"><?=$subj;?>&nbsp;</td>
						<td valign="middle" nowrap class="list">
							<a href="system_camanager.php?act=exp&id=<?=$i;?>")">
								<img src="/themes/<?= $g['theme'];?>/images/icons/icon_down.gif" title="<?=gettext("export ca");?>" alt="<?=gettext("export ca");?>" width="17" height="17" border="0" />
							</a>
							<a href="system_camanager.php?act=del&id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this Certificate Authority and all associated certificates?");?>')">
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
<!--

method_change();

//-->
</script>

</body>
