<?php
/*
    system_usermanager_addcert.php

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
	pfSense_MODULE:	certificate_manager
*/

##|+PRIV
##|*IDENT=page-system-usermanager_addcert
##|*NAME=System: User Manager: Add Certificate
##|*DESCR=Allow access to the 'User Manager: Add Certificate' page.
##|*MATCH=system_usermanager_addcert.php*
##|-PRIV

require("guiconfig.inc");
require("certs.inc");

$cert_keylens = array( "512", "1024", "2048", "4096");

$pgtitle = array(gettext("System"), gettext("User Manager: Add Certificate"));

$userid = $_GET['userid'];
if (isset($_POST['userid']))
	$userid = $_POST['userid'];

if (!is_array($config['system']['user']))
	$config['system']['user'] = array();

$a_user =& $config['system']['user'];

if (!is_array($config['ca']))
	$config['ca'] = array();

$a_ca =& $config['ca'];

$internal_ca_count = 0;
foreach ($a_ca as $ca)
	if ($ca['prv'])	
		$internal_ca_count++;

if ($_GET) {
	$pconfig['keylen'] = "2048";
	$pconfig['lifetime'] = "3650";
}

if ($_POST) {
	conf_mount_rw();
	
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($pconfig['method'] == "existing") {
		$reqdfields = explode(" ",
				"name cert key");
		$reqdfieldsn = array(
				gettext("Descriptive name"),
				gettext("Certificate data"),
				gettext("Key data"));
	}

	if ($pconfig['method'] == "internal") {
		$reqdfields = explode(" ",
				"name caref keylen lifetime");
		$reqdfieldsn = array(
				gettext("Descriptive name"),
				gettext("Certificate authority"),
				gettext("Key length"),
				gettext("Lifetime"));
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	$ca = lookup_ca($pconfig['caref']);
	if (!$ca)
		$input_errors[] = sprintf(gettext("Invalid internal Certificate Authority%s"),"\n");

	/* if this is an AJAX caller then handle via JSON */
	if (isAjax() && is_array($input_errors)) {
		input_errors2Ajax($input_errors);
		conf_mount_ro();
		exit;
	}

	/* save modifications */
	if (!$input_errors) {

		$cert = array();
		$cert['refid'] = uniqid();
		if (!is_array($a_user[$userid]['cert']))
			$a_user[$userid]['cert'] = array();

	    $cert['name'] = $pconfig['name'];

		$subject = cert_get_subject_array($ca['crt']);

		$dn = array(
			'countryName' => $subject[0]['v'],
			'stateOrProvinceName' => $subject[1]['v'],
			'localityName' => $subject[2]['v'],
			'organizationName' => $subject[3]['v'],
			'emailAddress' => $subject[4]['v'],
			'commonName' => $a_user[$userid]['name']);

		cert_create($cert, $pconfig['caref'], $pconfig['keylen'],
			$pconfig['lifetime'], $dn);

		$a_user[$userid]['cert'][] = $cert;

		write_config();

		conf_mount_ro();
		
		pfSenseHeader("system_usermanager.php?act=edit&id={$userid}");
	}
}

include("head.inc");
?>

<body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
<!--

<?php if ($internal_ca_count): ?>
function internalca_change() {

	index = document.iform.caref.selectedIndex;
	caref = document.iform.caref[index].value;

	switch (caref) {
<?php
		foreach ($a_ca as $ca):
			if (!$ca['prv'])
				continue;
			$subject = cert_get_subject_array($ca['crt']);
?>
		case "<?=$ca['refid'];?>":
			document.iform.dn_country.value = "<?=$subject[0]['v'];?>";
			document.iform.dn_state.value = "<?=$subject[1]['v'];?>";
			document.iform.dn_city.value = "<?=$subject[2]['v'];?>";
			document.iform.dn_organization.value = "<?=$subject[3]['v'];?>";
			break;
<?php	endforeach; ?>
	}
}
<?php endif; ?>

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
			$tab_array[] = array(gettext("Users"), true, "system_usermanager.php");
			$tab_array[] = array(gettext("Groups"), false, "system_groupmanager.php");
			$tab_array[] = array(gettext("Settings"), false, "system_usermanager_settings.php");
			$tab_array[] = array(gettext("Servers"), false, "system_authservers.php");
			display_top_tabs($tab_array);
		?>
		</td>
	</tr>
	<tr>
		<td id="mainarea">
			<div class="tabcont">
				<form action="system_usermanager_addcert.php" method="post" name="iform" id="iform">
					<table width="100%" border="0" cellpadding="6" cellspacing="0">

						<?php if (!$internal_ca_count): ?>

						<tr>
							<td colspan="2" align="center" class="vtable">
								<?=gettext("No internal Certificate Authorities have been defined. You must");?>
								<a href="system_camanager.php?act=new&method=internal"><?=gettext("create");?></a>
								<?=gettext("an internal CA before creating an internal certificate.");?>
							</td>
						</tr>

						<?php else: ?>

						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Descriptive name");?></td>
							<td width="78%" class="vtable">
								<input name="name" type="text" class="formfld unknown" id="name" size="20" value="<?=htmlspecialchars($pconfig['name']);?>"/>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Certificate authority");?></td>
							<td width="78%" class="vtable">
								<select name='caref' id='caref' class="formselect" onChange='internalca_change()'>
								<?php
									foreach( $a_ca as $ca):
									if (!$ca['prv'])
										continue;
									$selected = "";
									if ($pconfig['caref'] == $ca['refid'])
										$selected = "selected";
								?>
									<option value="<?=$ca['refid'];?>"<?=$selected;?>><?=$ca['name'];?></option>
								<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Key length");?></td>
							<td width="78%" class="vtable">
								<select name='keylen' class="formselect">
								<?php
									foreach( $cert_keylens as $len):
									$selected = "";
									if ($pconfig['keylen'] == $len)
										$selected = "selected";
								?>
									<option value="<?=$len;?>"<?=$selected;?>><?=$len;?></option>
								<?php endforeach; ?>
								</select>
								<?=gettext("bits");?>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Lifetime");?></td>
							<td width="78%" class="vtable">
								<input name="lifetime" type="text" class="formfld unknown" id="lifetime" size="5" value="<?=htmlspecialchars($pconfig['lifetime']);?>"/>
								<?=gettext("days");?>
							</td>
						</tr>

						<?php endif; ?>

						<tr>
							<td width="22%" valign="top">&nbsp;</td>
							<td width="78%">
								<?php if ($internal_ca_count): ?>
								<input id="submit" name="save" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
								<input id="cancelbutton" class="formbtn" type="button" value="<?=gettext("Cancel");?>" onclick="history.back()" />
								<?php endif; ?>
								<?php if (isset($userid) && $a_user[$userid]): ?>
								<input name="userid" type="hidden" value="<?=$userid;?>" />
								<?php endif;?>
							</td>
						</tr>
					</table>
				</form>
			</div>
		</td>
	</tr>
</table>
<?php include("fend.inc");?>
<script type="text/javascript">
<!--

internalca_change();

//-->
</script>

</body>
