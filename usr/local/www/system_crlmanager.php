<?php
/*
	system_crlmanager.php
	
	Copyright (C) 2010 Jim Pingle
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
##|*IDENT=page-system-crlmanager
##|*NAME=System: CRL Manager
##|*DESCR=Allow access to the 'System: CRL Manager' page.
##|*MATCH=system_crlmanager.php*
##|-PRIV

require("guiconfig.inc");
require_once("certs.inc");

$pgtitle = array(gettext("System"), gettext("Certificate Revocation List Manager"));

$crl_methods = array(
	"internal" => gettext("Create an internal Certificate Revocation List"),
	"existing" => gettext("Import an existing Certificate Revocation List"));

$id = $_GET['id'];
if (isset($_POST['id']))
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

	if (!$a_crl[$id]) {
		pfSenseHeader("system_crlmanager.php");
		exit;
	}
	if (crl_in_use($a_crl[$id]['refid'])) {
		$savemsg = sprintf(gettext("Certificate Revocation List %s is in use and cannot be deleted"), $name) . "<br/>";
	} else {
		$name = $a_crl[$id]['descr'];
		unset($a_crl[$id]);
		write_config();
		$savemsg = sprintf(gettext("Certificate Revocation List %s successfully deleted"), $name) . "<br/>";
	}
}

if ($act == "new") {
	$pconfig['method'] = $_GET['method'];
	$pconfig['caref'] = $_GET['caref'];
	$pconfig['lifetime'] = "9999";
	$pconfig['serial'] = "0";
}

if ($act == "exp") {

	if (!$a_crl[$id]) {
		pfSenseHeader("system_crlmanager.php");
		exit;
	}

	$exp_name = urlencode("{$a_crl[$id]['descr']}.crl");
	$exp_data = base64_decode($a_crl[$id]['text']);
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
		$reqdfields = explode(" ", "descr crltext");
		$reqdfieldsn = array(
				gettext("Descriptive name"),
				gettext("Certificate Revocation List data"));
	}
	if ($pconfig['method'] == "internal") {
		$reqdfields = explode(" ",
				"descr caref");
		$reqdfieldsn = array(
				gettext("Descriptive name"),
				gettext("Certificate Authority"));
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	/* if this is an AJAX caller then handle via JSON */
	if (isAjax() && is_array($input_errors)) {
		input_errors2Ajax($input_errors);
		exit;
	}

	/* save modifications */
	if (!$input_errors) {
		$result = false;

		$crl = array();
		$crl['refid'] = uniqid();
		if (isset($id) && $a_crl[$id])
			$crl = $a_crl[$id];

		$crl['descr'] = $pconfig['descr'];
		$crl['caref'] = $pconfig['caref'];

		if ($pconfig['method'] == "existing") {
			$crl['text'] == base64_encode($pconfig['crltext']);
		}

		if ($pconfig['method'] == "internal") {
			$crl['serial'] = empty($pconfig['serial']) ? 9999 : $pconfig['serial'];
			$crl['lifetime'] = empty($pconfig['lifetime']) ? 9999 : $pconfig['lifetime'];
			$crl['cert'] = array();
		}

		if (isset($id) && $a_crl[$id])
			$a_crl[$id] = $crl;
		else
			$a_crl[] = $crl;

		write_config();

		pfSenseHeader("system_crlmanager.php");
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
			document.getElementById("existing").style.display="none";
			document.getElementById("internal").style.display="";
			break;
		case 1:
			document.getElementById("existing").style.display="";
			document.getElementById("internal").style.display="none";
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
NOTE: This page is still a work in progress and is not yet fully functional.
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
		<?php
			$tab_array = array();
			$tab_array[] = array(gettext("CAs"), false, "system_camanager.php");
			$tab_array[] = array(gettext("Certificates"), false, "system_certmanager.php");
			$tab_array[] = array(gettext("Certificate Revocation"), true, "system_crlmanager.php");
			display_top_tabs($tab_array);
		?>
		</td>
	</tr>
	<tr>
		<td id="mainarea">
			<div class="tabcont">

				<?php if ($act == "new" || $act == gettext("Save") || $input_errors): ?>

				<form action="system_crlmanager.php" method="post" name="iform" id="iform">
					<table width="100%" border="0" cellpadding="6" cellspacing="0">
						<?php if (!isset($id)): ?>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Method");?></td>
							<td width="78%" class="vtable">
								<select name='method' id='method' class="formselect" onchange='method_change()'>
								<?php
									foreach($crl_methods as $method => $desc):
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
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Descriptive name");?></td>
							<td width="78%" class="vtable">
								<input name="descr" type="text" class="formfld unknown" id="descr" size="20" value="<?=htmlspecialchars($pconfig['descr']);?>"/>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Certificate Authority");?></td>
							<td width="78%" class="vtable">
								<select name='caref' id='caref' class="formselect">
								<?php
									foreach($a_ca as $ca):
									$selected = "";
									if ($pconfig['caref'] == $ca['refid'])
										$selected = "selected";
								?>
									<option value="<?=$ca['refid'];?>"<?=$selected;?>><?=$ca['descr'];?></option>
								<?php endforeach; ?>
								</select>
							</td>
						</tr>
					</table>

					<table width="100%" border="0" cellpadding="6" cellspacing="0" id="existing">
						<tr>
							<td colspan="2" class="list" height="12"></td>
						</tr>
						<tr>
							<td colspan="2" valign="top" class="listtopic"><?=gettext("Existing Certificate Revocation List");?></td>
						</tr>

						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("CRL data");?></td>
							<td width="78%" class="vtable">
								<textarea name="cert" id="cert" cols="65" rows="7" class="formfld_crl"><?=$pconfig['crltext'];?></textarea>
								<br>
								<?=gettext("Paste a Certificate Revocation List in X.509 CRL format here.");?></td>
							</td>
						</tr>
					</table>

					<table width="100%" border="0" cellpadding="6" cellspacing="0" id="internal">
						<tr>
							<td colspan="2" class="list" height="12"></td>
						</tr>
						<tr>
							<td colspan="2" valign="top" class="listtopic"><?=gettext("Internal Certificate Revocation List");?></td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Lifetime");?></td>
							<td width="78%" class="vtable">
								<input name="lifetime" type="text" class="formfld unknown" id="lifetime" size="5" value="<?=htmlspecialchars($pconfig['lifetime']);?>"/>
								<?=gettext("days");?><br/>
								<?=gettext("Default: 9999");?>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Serial");?></td>
							<td width="78%" class="vtable">
								<input name="lifetime" type="text" class="formfld unknown" id="lifetime" size="5" value="<?=htmlspecialchars($pconfig['serial']);?>"/>
								<br/>
								<?=gettext("Default: 0");?>
							</td>
						</tr>
					</table>

					<table width="100%" border="0" cellpadding="6" cellspacing="0">
						<tr>
							<td width="22%" valign="top">&nbsp;</td>
							<td width="78%">
								<input id="submit" name="save" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" />
								<?php if (isset($id) && $a_crl[$id]): ?>
								<input name="id" type="hidden" value="<?=$id;?>" />
								<?php endif;?>
							</td>
						</tr>
					</table>
				</form>

				<?php else: ?>

				<table width="100%" border="0" cellpadding="0" cellspacing="0">
					<thead>
					<tr>
						<td width="35%" class="listhdrr"><?=gettext("Name");?></td>
						<td width="10%" class="listhdrr"><?=gettext("Internal");?></td>
						<td width="35%" class="listhdrr"><?=gettext("Certificates");?></td>
						<td width="10%" class="listhdrr"><?=gettext("In Use");?></td>
						<td width="10%" class="list"></td>
					</tr>
					</thead>
					<tbody>
					<?php
						// Map CRLs to GWs in one pass
						$ca_crl_map = array();
						foreach($a_crl as $crl)
							$ca_crl_map[$crl['caref']][] = $crl['refid'];

						$i = 0;
						foreach($a_ca as $ca):
							$name = htmlspecialchars($ca['descr']);

							if($ca['prv']) {
								$caimg = "/themes/{$g['theme']}/images/icons/icon_frmfld_cert.png";
								$internal = "YES";
							} else 
								continue;
					?>
					<tr>
						<td class="listlr" colspan="4">
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
						<td class="list">
							<a href="system_crlmanager.php?act=new&caref=<?php echo $ca['refid']; ?>">
								<img src="/themes/<?= $g['theme'];?>/images/icons/icon_plus.gif" title="<?=gettext("Add or Import CRL for ") . $ca['descr'];?>" alt="<?=gettext("add crl");?>" width="17" height="17" border="0" />
							</a>
						</td>
					</tr>
					
						<?php
						if (is_array($ca_crl_map[$ca['refid']])):
							foreach($ca_crl_map[$ca['refid']] as $crl):
								$tmpcrl = lookup_crl($crl);
								$internal = is_crl_internal($tmpcrl);
								$inuse = crl_in_use($tmpcrl['refid']);
						?>
					<tr>
						<td class="listlr"><?php echo $tmpcrl['descr']; ?></td>
						<td class="listr"><?php echo ($internal) ? "YES" : "NO"; ?></td>
						<td class="listr"><?php echo ($internal) ? count($tmpcrl['cert']) : "Unknown (imported)"; ?></td>
						<td class="listr"><?php echo ($inuse) ? "YES" : "NO"; ?></td>
						<td valign="middle" nowrap class="list">
							<a href="system_crlmanager.php?act=exp&id=<?=$i;?>")">
								<img src="/themes/<?= $g['theme'];?>/images/icons/icon_down.gif" title="<?=gettext("Export CRL") . " " . htmlspecialchars($tmpcrl['descr']);?>" alt="<?=gettext("Export CRL") . " " . htmlspecialchars($tmpcrl['descr']);?>" width="17" height="17" border="0" />
							</a>
							<?php if (!$inuse): ?>
							<a href="system_crlmanager.php?act=del&id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this Certificate Revocation List?") . ' (' . htmlspecialchars($tmpcrl['descr']) . ')';?>')">
								<img src="/themes/<?= $g['theme'];?>/images/icons/icon_x.gif" title="<?=gettext("Delete CRL") . " " . htmlspecialchars($tmpcrl['descr']);?>" alt="<?=gettext("Delete CRL") . " " . htmlspecialchars($tmpcrl['descr']); ?>" width="17" height="17" border="0" />
							</a>
							<?php endif; ?>
						</td>
					</tr>
						<?php
								$i++;
							endforeach;
						endif;
						?>
					<tr><td colspan="5">&nbsp;</td></tr>
					<?php
							$i++;
						endforeach;
					?>
					</tbody>
					<tfoot>
					<tr>
						<td colspan="5">
							<p>
								<?=gettext("Additional Certificate Revocation Lists can be added here.");?>
							</p>
						</td>
					</tr>
					</tfoot>
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
