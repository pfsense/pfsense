<?php
/* $Id$ */
/*
	Copyright (C) 2008 Ermal Luçi
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
	pfSense_BUILDER_BINARIES:	/bin/rm
	pfSense_MODULE:	dyndns
*/

##|+PRIV
##|*IDENT=page-services-dynamicdnsclient
##|*NAME=Services: Dynamic DNS client page
##|*DESCR=Allow access to the 'Services: Dynamic DNS client' page.
##|*MATCH=services_dyndns_edit.php*
##|-PRIV

/* returns true if $uname is a valid DynDNS username */
function is_dyndns_username($uname) {
        if (!is_string($uname))
                return false;
        
        if (preg_match("/[^a-z0-9\-.@_:]/i", $uname))
                return false;
        else
                return true;
}

require("guiconfig.inc");

if (!is_array($config['dyndnses']['dyndns'])) {
	$config['dyndnses']['dyndns'] = array();
}

$a_dyndns = &$config['dyndnses']['dyndns'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && isset($a_dyndns[$id])) {
	$pconfig['username'] = $a_dyndns[$id]['username'];
	$pconfig['password'] = $a_dyndns[$id]['password'];
	$pconfig['host'] = $a_dyndns[$id]['host'];
	$pconfig['mx'] = $a_dyndns[$id]['mx'];
	$pconfig['type'] = $a_dyndns[$id]['type'];
	$pconfig['enable'] = !isset($a_dyndns[$id]['enable']);
	$pconfig['interface'] = $a_dyndns[$id]['interface'];
	$pconfig['wildcard'] = isset($a_dyndns[$id]['wildcard']);
	$pconfig['verboselog'] = isset($a_dyndns[$id]['verboselog']);
	$pconfig['zoneid'] = $a_dyndns[$id]['zoneid'];
	$pconfig['ttl'] = $a_dyndns[$id]['ttl'];
	$pconfig['updateurl'] = $a_dyndns[$id]['updateurl'];
	$pconfig['verifypeer'] = isset($a_dyndns[$id]['verifypeer']);
	$pconfig['verifyhost'] = isset($a_dyndns[$id]['verifyhost']);
	$pconfig['casource'] = $a_dyndns[$id]['casource'];
	$pconfig['carefid'] = $a_dyndns[$id]['carefid'];
	$pconfig['cafile'] = $a_dyndns[$id]['cafile'];
	$pconfig['resultmatch'] = $a_dyndns[$id]['resultmatch'];
	$pconfig['requestif'] = $a_dyndns[$id]['requestif'];
	$pconfig['descr'] = $a_dyndns[$id]['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;
	
	if(($pconfig['type'] == "freedns" || $pconfig['type'] == "namecheap") && $_POST['username'] == "")
		$_POST['username'] = "none"; 

	/* input validation */
	$reqdfields = array();
	$reqdfieldsn = array();
	$reqdfields = array("type");
	$reqdfieldsn = array(gettext("Service type"));
	if ($pconfig['type'] != "custom" && $pconfig['type'] != "custom-v6") {
		$reqdfields[] = "host";
		$reqdfieldsn[] = gettext("Hostname");
		$reqdfields[] = "password";
		$reqdfieldsn[] = gettext("Password");
 		$reqdfields[] = "username";
 		$reqdfieldsn[] = gettext("Username");
	}else{
		$reqdfields[] = "updateurl";
		$reqdfieldsn[] = gettext("Update URL");
 	}
	if ($pconfig['casource'] == 'ca_manager') {
		$reqdfields[] = "carefid";
		$reqdfieldsn[] = gettext("Certificate Authority - CA Selection");
	}
	elseif ($pconfig['casource'] == 'ca_file') {
		$reqdfields[] = "cafile";
		$reqdfieldsn[] = gettext("Certificate Authority - CA File");
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (($_POST['mx'] && !is_domain($_POST['mx']))) 
		$input_errors[] = gettext("The MX contains invalid characters.");
	if ((in_array("username", $reqdfields) && $_POST['username'] && !is_dyndns_username($_POST['username'])) || ((in_array("username", $reqdfields)) && ($_POST['username'] == ""))) 
 		$input_errors[] = gettext("The username contains invalid characters.");

	if (!$input_errors) {
		$dyndns = array();
		$dyndns['type'] = $_POST['type'];
		$dyndns['username'] = $_POST['username'];
		$dyndns['password'] = $_POST['password'];
		$dyndns['host'] = $_POST['host'];
		$dyndns['mx'] = $_POST['mx'];
		$dyndns['wildcard'] = $_POST['wildcard'] ? true : false;
		$dyndns['verboselog'] = $_POST['verboselog'] ? true : false;
		/* In this place enable means disabled */
		if ($_POST['enable'])
			unset($dyndns['enable']);
		else
			$dyndns['enable'] = true;
		$dyndns['interface'] = $_POST['interface'];
		$dyndns['zoneid'] = $_POST['zoneid'];
		$dyndns['ttl'] = $_POST['ttl'];
		$dyndns['updateurl'] = $_POST['updateurl'];
		$dyndns['verifypeer'] = $_POST['verifypeer'] ? true : false;
		$dyndns['verifyhost'] = $_POST['verifyhost'] ? true : false;
		$dyndns['casource'] = $_POST['casource'];
		$dyndns['carefid'] = $_POST['carefid'];
		$dyndns['cafile'] = $_POST['cafile'];
		// Trim hard-to-type but sometimes returned characters
		$dyndns['resultmatch'] = trim($_POST['resultmatch'], "\t\n\r");
		($dyndns['type'] == "custom" || $dyndns['type'] == "custom-v6") ? $dyndns['requestif'] = $_POST['requestif'] : $dyndns['requestif'] = $_POST['interface'];
		$dyndns['descr'] = $_POST['descr'];
		$dyndns['force'] = isset($_POST['force']);
		
		if($dyndns['username'] == "none")
			$dyndns['username'] = "";

		if (isset($id) && $a_dyndns[$id])
			$a_dyndns[$id] = $dyndns;
		else {
 			$a_dyndns[] = $dyndns;
			$id = count($a_dyndns) - 1;
		}

		$dyndns['id'] = $id;
		//Probably overkill, but its better to be safe
		for($i = 0; $i < count($a_dyndns); $i++) {
			$a_dyndns[$i]['id'] = $i;
		}

		write_config();

		services_dyndns_configure_client($dyndns);

		header("Location: services_dyndns.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"),gettext("Dynamic DNS client"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<script type="text/javascript">
function _onTypeChange(type){ 
	if(!type) type = document.getElementById("type").value;
	switch(type) {
		case "custom":
		case "custom-v6":
			document.getElementById("_resulttr").style.display = '';
			document.getElementById("_urltr").style.display = '';
			document.getElementById("_verifypeerhosttr").style.display = '';
			document.getElementById("_requestiftr").style.display = '';
			document.getElementById("_hostnametr").style.display = 'none';
			document.getElementById("_mxtr").style.display = 'none';
			document.getElementById("_wildcardtr").style.display = 'none';
			document.getElementById("r53_zoneid").style.display='none';
			document.getElementById("r53_ttl").style.display='none';
			break;
		case "route53":
			document.getElementById("_resulttr").style.display = 'none';
			document.getElementById("_urltr").style.display = 'none';
			document.getElementById("_verifypeerhosttr").style.display = '';
			document.getElementById("_requestiftr").style.display = 'none';
			document.getElementById("_hostnametr").style.display = '';
			document.getElementById("_mxtr").style.display = '';
			document.getElementById("_wildcardtr").style.display = '';
			document.getElementById("r53_zoneid").style.display='';
			document.getElementById("r53_ttl").style.display='';
			break;
		default:
			document.getElementById("_resulttr").style.display = 'none';
			document.getElementById("_urltr").style.display = 'none';
			document.getElementById("_verifypeerhosttr").style.display = '';
			document.getElementById("_requestiftr").style.display = 'none';
			document.getElementById("_hostnametr").style.display = '';
			document.getElementById("_mxtr").style.display = '';
			document.getElementById("_wildcardtr").style.display = '';
			document.getElementById("r53_zoneid").style.display='none';
			document.getElementById("r53_ttl").style.display='none';
	}

	SSL = ssl_service[type];

	<? if (!isset($id)) {?>	// When creating a new entry use defaults.
		document.getElementById("verifypeer").checked = ssl_verify_peer[type];
		document.getElementById("verifyhost").checked = ssl_verify_host[type];
	<? } ?>

	_onURLChange();
}
</script>
<form action="services_dyndns_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
                  <td colspan="2" valign="top" class="optsect_t">
				  <table border="0" cellspacing="0" cellpadding="0" width="100%">
				  <tr><td class="optsect_s"><strong><?=gettext("Dynamic DNS client");?></strong></td></tr>
				  </table>
				  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Disable");?></td>
				  <td width="78%" class="vtable">
				    <input name="enable" type="checkbox" id="enable" value="<?=gettext("yes");?>" <?php if ($pconfig['enable']) echo "checked"; ?>>
				  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Service type");?></td>
                  <td width="78%" class="vtable">
			<select name="type" class="formselect" id="type" onchange="_onTypeChange(this.options[this.selectedIndex].value);">
                      <?php
						$types = explode(",", DYNDNS_PROVIDER_DESCRIPTIONS);
						$vals = explode(" ", DYNDNS_PROVIDER_VALUES);
						$SSL = explode(" ", DYNDNS_PROVIDER_SSL_SERVICE);
						$Verify_Peer = explode(" ", DYNDNS_PROVIDER_VERIFY_PEER);
						$Verify_Host = explode(" ", DYNDNS_PROVIDER_VERIFY_HOST);
						$SSL_Service = array();
						$SSL_Verify_Peer = array();
						$SSL_Verify_Host = array();
						$j = 0; for ($j = 0; $j < count($vals); $j++): ?>
                      <option value="<?=$vals[$j];?>" <?php if ($vals[$j] == $pconfig['type']) echo "selected";?>>
                      <?=htmlspecialchars($types[$j]);?>
                      </option>
						<?php
							if($SSL[$j] == "YES") $SSL_Service[$vals[$j]] = true;
							else $SSL_Service[$vals[$j]] = false;

							if($Verify_Peer[$j] == "YES") $SSL_Verify_Peer[$vals[$j]] = true;
							else $SSL_Verify_Peer[$vals[$j]] = false;

							if($Verify_Host[$j] == "YES") $SSL_Verify_Host[$vals[$j]] = true;
							else $SSL_Verify_Host[$vals[$j]] = false;
						?>
                      <?php endfor; ?>
                    </select></td>
				</tr>

<script type="text/javascript">
	/* Make server PHP arrays available to client JavaScript. */
	ssl_service = new Array();
	<?	foreach ($SSL_Service as $key => $value) { ?>
		ssl_service["<? echo $key; ?>"] = "<? echo $value; ?>";
	<?	} ?>

	ssl_verify_peer = new Array();
	<?	foreach ($SSL_Verify_Peer as $key => $value) { ?>
		ssl_verify_peer["<? echo $key; ?>"] = "<? echo $value; ?>";
	<?	} ?>

	ssl_verify_host = new Array();
	<?	foreach ($SSL_Verify_Host as $key => $value) { ?>
		ssl_verify_host["<? echo $key; ?>"] = "<? echo $value; ?>";
	<?	} ?>
</script>

				<tr>
				   <td width="22%" valign="top" class="vncellreq"><?=gettext("Interface to monitor");?></td>  
				   <td width="78%" class="vtable">
				   <select name="interface" class="formselect" id="interface">
				<?php
					$iflist = get_configured_interface_with_descr();					
				   	foreach ($iflist as $if => $ifdesc) {
						echo "<option value=\"{$if}\"";
						if ($pconfig['interface'] == $if)
							echo "selected";
						echo ">{$ifdesc}</option>\n";
					}
					$grouplist = return_gateway_groups_array();
				   	foreach ($grouplist as $name => $group) {
						echo "<option value=\"{$name}\"";
						if ($pconfig['interface'] == $name)
							echo "selected";
						echo ">GW Group {$name}</option>\n";
					}
				?>
					</select>
					</td>
					</td>
				</tr>	
				<tr id="_requestiftr">
				   <td width="22%" valign="top" class="vncellreq"><?=gettext("Interface to send update from");?></td>  
				   <td width="78%" class="vtable">
				   <select name="requestif" class="formselect" id="requestif">
				   <?php $iflist = get_configured_interface_with_descr();
				   		foreach ($iflist as $if => $ifdesc):?>
							<option value="<?=$if;?>" <?php if ($pconfig['requestif'] == $if) echo "selected";?>><?=$ifdesc;?></option>
					<?php endforeach; ?>
					</select>
					<br/><?= gettext("Note: This is almost always the same as the Interface to Monitor.");?>
					</td>
					</td>
				</tr>
                <tr id="_hostnametr">
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Hostname");?></td>
                  <td width="78%" class="vtable">
                    <input name="host" type="text" class="formfld unknown" id="host" size="30" value="<?=htmlspecialchars($pconfig['host']);?>">
                    <br>
				    <span class="vexpl">
				    <span class="red"><strong><?=gettext("Note:");?><br></strong>
				    </span>
					<?=gettext("Enter the complete host/domain name.  example:  myhost.dyndns.org");?><br/>
					<?=gettext("For he.net tunnelbroker, enter your tunnel ID");?>
				    </span>
		          </td>
				</tr>
                <tr id="_mxtr">
                  <td width="22%" valign="top" class="vncell"><?=gettext("MX"); ?></td>
                  <td width="78%" class="vtable">
                    <input name="mx" type="text" class="formfld unknown" id="mx" size="30" value="<?=htmlspecialchars($pconfig['mx']);?>">
                    <br>
					<?=gettext("Note: With DynDNS service you can only use a hostname, not an IP address.");?>
					<br>
                    <?=gettext("Set this option only if you need a special MX record. Not".
                   " all services support this.");?></td>
				</tr>
                <tr id="_wildcardtr">
                  <td width="22%" valign="top" class="vncell"><?=gettext("Wildcards"); ?></td>
                  <td width="78%" class="vtable">
                    <input name="wildcard" type="checkbox" id="wildcard" value="yes" <?php if ($pconfig['wildcard']) echo "checked"; ?>>
                    <?=gettext("Enable ");?><?=gettext("Wildcard"); ?></td>
				</tr>
                <tr id="_verboselogtr">
                  <td width="22%" valign="top" class="vncell"><?=gettext("Verbose logging"); ?></td>
                  <td width="78%" class="vtable">
                    <input name="verboselog" type="checkbox" id="verboselog" value="yes" <?php if ($pconfig['verboselog']) echo "checked"; ?>>
                    <?=gettext("Enable ");?><?=gettext("verbose logging"); ?></td>
				</tr>
                <tr id="_usernametr">
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Username");?></td>
                  <td width="78%" class="vtable">
                    <input name="username" type="text" class="formfld user" id="username" size="20" value="<?=htmlspecialchars($pconfig['username']);?>">
                    <br/><?= gettext("Username is required for all types except Namecheap, FreeDNS and Custom Entries.");?>
		    <br/><?= gettext("Route 53: Enter your Access Key ID.");?>
		    <br/><?= gettext("For Custom Entries, Username and Password represent HTTP Authentication username and passwords.");?>
                  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Password");?></td>
                  <td width="78%" class="vtable">
                    <input name="password" type="password" class="formfld pwd" id="password" size="20" value="<?=htmlspecialchars($pconfig['password']);?>">
                    <br/>
                    <?=gettext("FreeDNS (freedns.afraid.org): Enter your \"Authentication Token\" provided by FreeDNS.");?>
		    <br/><?= gettext("Route 53: Enter your Secret Access Key.");?>
                  </td>
                </tr>

<script type="text/javascript">
function _onURLChange(URL){
	if (!URL) var URL = document.getElementById("updateurl").value;

	var pattern=/^https:/i;
	var https=pattern.test(URL);

	var pattern=/^custom.*/i;
	var custom=pattern.test(document.getElementById("type").value);

	/* For none custom types, use the system default CA source selection. */
	if (custom) document.getElementById("<? if($pconfig['casource']) echo $pconfig['casource']; else echo "ca_default"; ?>").checked = true;
	else document.getElementById("ca_default").checked = true;

	if ((https && custom) || SSL) {
		document.getElementById("_verifypeerhosttr").style.display = '';
		_onVerifyPeerChange();
	}
	else {
		document.getElementById("_verifypeerhosttr").style.display = 'none';
		document.getElementById("_tls_catr").style.display = 'none';
	}
}

function _onVerifyPeerChange(){ 

	var pattern=/^custom.*/i;
	var custom=pattern.test(document.getElementById("type").value);

	if (document.getElementById("verifypeer").checked && custom) {
		document.getElementById("_tls_catr").style.display = '';
	}
	else {
		document.getElementById("_tls_catr").style.display = 'none';
	}
	_ca_source();
}

function _ca_source(){
	if (document.getElementById("ca_bundle").checked) {
		document.getElementById("_ca_manager").style.display = 'none';
		document.getElementById("_ca_file").style.display = 'none';
	}
	else if (document.getElementById("ca_manager").checked) {
		document.getElementById("_ca_manager").style.display = '';
		document.getElementById("_ca_file").style.display = 'none';
	}
	else if (document.getElementById("ca_file").checked) {
		document.getElementById("_ca_manager").style.display = 'none';
		document.getElementById("_ca_file").style.display = '';
	}
	else {
		document.getElementById("_ca_manager").style.display = 'none';
		document.getElementById("_ca_file").style.display = 'none';
	}
}
</script>
				<tr id="_verifypeerhosttr">
					<td width="22%" valign="top" class="vncell"><?=gettext("Verify Peer and/or Host"); ?></td>
					<td width="78%" class="vtable">
						<input name="verifypeer" type="checkbox" id="verifypeer" value="yes" <?php if ($pconfig['verifypeer']) echo "checked"; ?> onchange="_onVerifyPeerChange();">
						<?=gettext("Verify");?> <?=gettext("Peer");?>&nbsp&nbsp&nbsp&nbsp<?=gettext("Verifies the server certificate against a trusted CA.");?><br>
						<input name="verifyhost" type="checkbox" id="verifyhost" value="yes" <?php if ($pconfig['verifyhost']) echo "checked"; ?> >
						<?=gettext("Verify");?> <?=gettext("Host");?>&nbsp&nbsp&nbsp&nbsp<?=gettext("Check existence of a common name and verify that it matches the hostname provided.");?><br>
					</td>
				</tr>

				<tr id="_tls_catr">
					<td width="22%" valign="top" class="vncell"><?=gettext("Certificate Authority"); ?></td>
					<td width="78%" class="vtable">
						<?= gettext("CA Source: ");?>&nbsp

						<input name="casource" id="ca_default" type="radio" value="" checked style="display:none" onClick="_ca_source(this.id)">	<!-- Default Value to Post -->
						<input name="casource" id="ca_bundle" type="radio" value="ca_bundle" <?php if ($pconfig['casource'] == "ca_bundle") echo "checked"; ?> onClick="_ca_source(this.id)">	
						<?= gettext("System CA Bundle");?>&nbsp &nbsp
						<input name="casource" id="ca_manager" type="radio" value="ca_manager" <?php if ($pconfig['casource'] == "ca_manager") echo "checked"; ?> onClick="_ca_source(this.id)">
						<?= gettext("pfSense CA Manager");?>&nbsp &nbsp
						<input name="casource" id="ca_file" type="radio" value="ca_file" <?php if ($pconfig['casource'] == "ca_file") echo "checked"; ?> onClick="_ca_source(this.id)">
						<?= gettext("CA File");?>&nbsp &nbsp

						<div name="_ca_manager" id="_ca_manager">
						<?php 
						if (!is_array($config['ca']))
							$config['ca'] = array();
						$a_ca =& $config['ca'];
						if (count($a_ca)): 
						?>
						<br><select name='carefid' id='carefid' class="formselect">
						<option value="" selected></option>
						<?php
							foreach ($a_ca as $ca):
								$selected = "";
								if ($pconfig['carefid'] == $ca['refid'])
									$selected = "selected";
						?>
							<option value="<?=$ca['refid'];?>" <?=$selected;?>><?=$ca['descr'];?></option>
						<?php endforeach; ?>
						</select>
						<br><?= gettext("Select CA from pfSense CA Manager.");?>
						<?php else: ?>
							<br><b>No Certificate Authorities defined.</b> <br/>Create one under <a href="system_camanager.php">System &gt; Cert Manager</a>.
						<?php endif; ?>
						</div>

						<div name="_ca_file" id="_ca_file">
						<br><input name="cafile" type="text" class="formfld unknown" id="cafile" size="60" value="<?=$pconfig['cafile'];?>">
						<br><?= gettext("CA File (absolute path).");?>
						</div>
					</td>
				</tr>

                <tr id="_urltr">
                  <td width="22%" valign="top" class="vncell"><?=gettext("Update URL");?></td>
                  <td width="78%" class="vtable">
                    <input name="updateurl" type="text" class="formfld unknown" id="updateurl" size="60" value="<?=htmlspecialchars($pconfig['updateurl']);?>" onchange="_onURLChange(this.value);">
                    <br/><?= gettext("This is the only field required by for Custom Dynamic DNS, and is only used by Custom Entries.");?>
			<br/>
			<?= gettext("If you need the new IP to be included in the request, put %IP% in its place.");?>
                  </td>
                </tr>
		<tr id="_resulttr">
                  <td width="22%" valign="top" class="vncell"><?=gettext("Result Match");?></td>
                  <td width="78%" class="vtable">
                    <textarea name="resultmatch" class="formpre" id="resultmatch" cols="65" rows="7"><?=htmlspecialchars($pconfig['resultmatch']);?></textarea>
                    <br/><?= gettext("This field is only used by Custom Dynamic DNS Entries.");?>
			<br/>
			<?= gettext("This field should be identical to what your DDNS Provider will return if the update succeeds, leave it blank to disable checking of returned results.");?>
			<br/>
			<?= gettext("If you need the new IP to be included in the request, put %IP% in its place.");?>
			<br/>
			<?= gettext("If you need to include multiple possible values, sperate them with a |.  If your provider includes a |, escape it with \\|");?>
			<br/>
			<?= gettext("Tabs (\\t), newlines (\\n) and carriage returns (\\r) at the beginning or end of the returned results are removed before comparison.");?>
                  </td>
                </tr>
                <tr>

                <tr id="r53_zoneid" style="display:none">
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Zone ID");?></td>
                  <td width="78%" class="vtable">
                    <input name="zoneid" type="text" class="formfld user" id="zoneid" size="20" value="<?=htmlspecialchars($pconfig['zoneid']);?>">
                    <br/><?= gettext("Enter Zone ID that you received when you created your domain in Route 53.");?>
                  </td>
                </tr>
                <tr id="r53_ttl" style="display:none">
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("TTL");?></td>
                  <td width="78%" class="vtable">
                    <input name="ttl" type="text" class="formfld user" id="ttl" size="20" value="<?=htmlspecialchars($pconfig['ttl']);?>">
                    <br/><?= gettext("Choose TTL for your dns record.");?>
                  </td>
                </tr>
                <tr>


                <tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
                  <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="60" value="<?=htmlspecialchars($pconfig['descr']);?>">
                  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" onClick="enable_change(true)">
					<a href="services_dyndns.php"><input name="cancel" type="button" class="formbtn" value="<?=gettext("Cancel");?>"></a>
					<?php if (isset($id) && $a_dyndns[$id]): ?>
						<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>">
						<input name="force" type="submit" class="formbtn" value="<?=gettext("Save & Force Update");?>" onClick="enable_change(true)">
					<?php endif; ?>
                  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"><span class="vexpl"><span class="red"><strong><?=gettext("Note:");?><br>
                    </strong></span><?php printf(gettext("You must configure a DNS server in %sSystem:
                    General setup%s or allow the DNS server list to be overridden
                    by DHCP/PPP on WAN for dynamic DNS updates to work."),'<a href="system.php">','</a>');?></span></td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>

<script type="text/javascript">
_onTypeChange("<?php echo $pconfig['type']; ?>");
</script>

</body>
</html>
