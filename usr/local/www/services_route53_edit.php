<?php
/* $Id$ */
/*
	Copyright (C) 2012 Edson Brandi <ebrandi@fugspbr.org>
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
	pfSense_MODULE:	route53update
*/

require("guiconfig.inc");

if (!is_array($config['route53updates']['route53update'])) {
	$config['route53updates']['route53update'] = array();
}

$a_route53 = &$config['route53updates']['route53update'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && isset($a_route53[$id])) {
	$pconfig['enable'] = isset($a_route53[$id]['enable']);
	$pconfig['domain'] = $a_route53[$id]['domain'];
	$pconfig['host'] = $a_route53[$id]['host'];
	$pconfig['ttl'] = $a_route53[$id]['ttl'];
	if (!$pconfig['ttl'])
		$pconfig['ttl'] = 60;
	$pconfig['zoneid'] = $a_route53[$id]['zoneid'];
	$pconfig['accesskeyid'] = $a_route53[$id]['accesskeyid'];
	$pconfig['secretaccesskey'] = $a_route53[$id]['secretaccesskey'];
	$pconfig['interface'] = $a_route53[$id]['interface'];
	$pconfig['descr'] = $a_route53[$id]['descr'];

}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = array();
	$reqdfieldsn = array();
	$reqdfields = array_merge($reqdfields, explode(" ", "domain host ttl zoneid accesskeyid secretaccesskey"));
	$reqdfieldsn = array_merge($reqdfieldsn, array(gettext("Domain"), gettext("Hostname"), gettext("TTL"), gettext("Zone ID"), gettext("Amazon ID"), gettext("Secret key")));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (($_POST['domain'] && !is_domain($_POST['domain'])))  
		$input_errors[] = gettext("The DNS update domain name contains invalid characters.");
        if (($_POST['host'] && !is_domain($_POST['host'])))
                $input_errors[] = gettext("The DNS update host name contains invalid characters.");
	if (($_POST['ttl'] && !is_numericint($_POST['ttl']))) 
		$input_errors[] = gettext("The DNS update TTL must be an integer.");

	if (!$input_errors) {
		$route53 = array();
		$route53['enable'] = $_POST['enable'] ? true : false;
		$route53['domain'] = $_POST['domain'];
		$route53['host'] = $_POST['host'];
		$route53['ttl'] = $_POST['ttl'];
		$route53['zoneid'] = $_POST['zoneid'];
		$route53['accesskeyid'] = $_POST['accesskeyid'];
		$route53['secretaccesskey'] = $_POST['secretaccesskey'];
		$route53['interface'] = $_POST['interface'];
		$route53['descr'] = $_POST['descr'];

		if (isset($id) && $a_route53[$id])
			$a_route53[$id] = $route53;
		else
			$a_route53[] = $route53;

		write_config();

                $retval = services_route53update_process();

		header("Location: services_route53.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"),gettext("Route 53 client"), gettext("Edit"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
            <form action="services_route53_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
			  	<tr>
                  <td colspan="2" valign="top" class="optsect_t">
				  <table border="0" cellspacing="0" cellpadding="0" width="100%">
				  	<tr><td class="optsect_s"><strong><?=gettext("Route 53 client");?></strong></td></tr>
				  </table>
				  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Enable");?></td>
				  <td width="78%" class="vtable">
				    <input name="enable" type="checkbox" id="enable" value="yes" <?php if ($pconfig['enable']) echo "checked"; ?>>
				  </td>
                </tr>
				<tr>
				   <td width="22%" valign="top" class="vncellreq"><?=gettext("Interface to monitor");?></td>  
				   <td width="78%" class="vtable">
				   <select name="interface" class="formselect" id="interface">
				   <?php $iflist = get_configured_interface_with_descr();
				   		foreach ($iflist as $if => $ifdesc):?>
							<option value="<?=$if;?>" <?php if ($pconfig['interface'] == $if) echo "selected";?>><?=$ifdesc;?></option>
					<?php endforeach; ?>
					</select>
					</td>
					</td>
				</tr>	
                 <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Domain");?></td>
                  <td width="78%" class="vtable">
                    <input name="domain" type="text" class="formfld unknown" id="domain" size="30" value="<?=htmlspecialchars($pconfig['domain']);?>">
                        <br/><span>Domain name of the host to be updated</span>
                  </td>
                </tr> 

		 <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Hostname");?></td>
                  <td width="78%" class="vtable">
                    <input name="host" type="text" class="formfld unknown" id="host" size="30" value="<?=htmlspecialchars($pconfig['host']);?>">
			<br/><span>Hostname to be updated</span>
                  </td>
		</tr>
                <tr>
                  <td valign="top" class="vncellreq"><?=gettext("TTL"); ?></td>
                  <td class="vtable">
                    <input name="ttl" type="text" class="formfld unknown" id="ttl" size="6" value="<?=htmlspecialchars($pconfig['ttl']);?>">
                  <?=gettext("seconds");?></td>
                </tr>
                <tr>
                  <td valign="top" class="vncellreq"><?=gettext("Zone ID");?></td>
                  <td class="vtable">
                    <input name="zoneid" type="text" class="formfld unknown" id="zoneid" size="30" value="<?=htmlspecialchars($pconfig['zoneid']);?>">
                    <br>
                    <?=gettext("This must match the Zone ID on your Route 53 account.");?></td>
                </tr>
                <tr>
                  <td valign="top" class="vncellreq"><?=gettext("Access Key ID");?></td>
                  <td class="vtable">
                    <input name="accesskeyid" type="text" class="formfld unknown" id="accesskeyid" size="70" value="<?=htmlspecialchars($pconfig['accesskeyid']);?>">
                    <br>
                    <?=gettext("This must be a Amazon AWS Access Key ID with full access to Route 53 API.");?></td>
				</tr>
                <tr>
		<tr>
                  <td valign="top" class="vncellreq"><?=gettext("Secret Access Key");?></td>
                  <td class="vtable">
                    <input name="secretaccesskey" type="text" class="formfld unknown" id="secretaccesskey" size="70" value="<?=htmlspecialchars($pconfig['secretaccesskey']);?>">
                    <br>
                    <?=gettext("Your API Secret Key.");?></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Description");?></td>
                  <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="60" value="<?=htmlspecialchars($pconfig['descr']);?>">
                  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" onClick="enable_change(true)">
					<a href="services_route53.php"><input name="Cancel" type="button" class="formbtn" value="<?=gettext("Cancel");?>"></a>
					<?php if (isset($id) && $a_route53[$id]): ?>
						<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>">
					<?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
