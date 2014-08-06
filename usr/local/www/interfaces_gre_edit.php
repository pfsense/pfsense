<?php
/* $Id$ */
/*
	interfaces_gre_edit.php

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
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-interfaces-gre-edit
##|*NAME=Interfaces: GRE: Edit page
##|*DESCR=Allow access to the 'Interfaces: GRE: Edit' page.
##|*MATCH=interfaces_gre_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");

if (!is_array($config['gres']['gre']))
	$config['gres']['gre'] = array();

$a_gres = &$config['gres']['gre'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_gres[$id]) {
	$pconfig['if'] = $a_gres[$id]['if'];
	$pconfig['greif'] = $a_gres[$id]['greif'];
	$pconfig['remote-addr'] = $a_gres[$id]['remote-addr'];
	$pconfig['tunnel-remote-net'] = $a_gres[$id]['tunnel-remote-net'];
	$pconfig['tunnel-local-addr'] = $a_gres[$id]['tunnel-local-addr'];
	$pconfig['tunnel-remote-addr'] = $a_gres[$id]['tunnel-remote-addr'];
	$pconfig['link1'] = isset($a_gres[$id]['link1']);
	$pconfig['link2'] = isset($a_gres[$id]['link2']);
	$pconfig['link0'] = isset($a_gres[$id]['link0']);
	$pconfig['descr'] = $a_gres[$id]['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "if tunnel-remote-addr tunnel-remote-net tunnel-local-addr");
	$reqdfieldsn = array(gettext("Parent interface"),gettext("Local address"),gettext("Remote tunnel address"),gettext("Remote tunnel network"), gettext("Local tunnel address"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if ((!is_ipaddr($_POST['tunnel-local-addr'])) || (!is_ipaddr($_POST['tunnel-remote-addr'])) ||
			(!is_ipaddr($_POST['remote-addr']))) {
		$input_errors[] = gettext("The tunnel local and tunnel remote fields must have valid IP addresses.");
	}

	foreach ($a_gres as $gre) {
		if (isset($id) && ($a_gres[$id]) && ($a_gres[$id] === $gre))
			continue;

		if (($gre['if'] == $_POST['if']) && ($gre['tunnel-remote-addr'] == $_POST['tunnel-remote-addr'])) {
			$input_errors[] = sprintf(gettext("A GRE tunnel with the network %s is already defined."),$gre['remote-network']);
			break;
		}
	}

	if (!$input_errors) {
		$gre = array();
		$gre['if'] = $_POST['if'];
		$gre['tunnel-local-addr'] = $_POST['tunnel-local-addr'];
		$gre['tunnel-remote-addr'] = $_POST['tunnel-remote-addr'];
		$gre['tunnel-remote-net'] = $_POST['tunnel-remote-net'];
		$gre['remote-addr'] = $_POST['remote-addr'];
		$gre['descr'] = $_POST['descr'];
		$gre['link1'] = isset($_POST['link1']);
		$gre['link2'] = isset($_POST['link2']);
		$gre['link0'] = isset($_POST['link0']);
		$gre['greif'] = $_POST['greif'];

                $gre['greif'] = interface_gre_configure($gre);
                if ($gre['greif'] == "" || !stristr($gre['greif'], "gre"))
                        $input_errors[] = gettext("Error occurred creating interface, please retry.");
                else {
                        if (isset($id) && $a_gres[$id])
                                $a_gres[$id] = $gre;
                        else
                                $a_gres[] = $gre;

                        write_config();

			$confif = convert_real_interface_to_friendly_interface_name($gre['greif']);
                        if ($confif <> "")
                                interface_configure($confif);

			header("Location: interfaces_gre.php");
			exit;
		}
	}
}

$pgtitle = array(gettext("Interfaces"),gettext("GRE"),gettext("Edit"));
$shortcut_section = "interfaces";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script type="text/javascript" src="/javascript/jquery.ipv4v6ify.js"></script>
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="interfaces_gre_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0" summary="interfaces gre edit">
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("GRE configuration");?></td>
				</tr>
				<tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Parent interface");?></td>
                  <td width="78%" class="vtable">
                    <select name="if" class="formselect">
                      <?php
						$portlist = get_configured_interface_with_descr();
						$carplist = get_configured_carp_interface_list();
						foreach ($carplist as $cif => $carpip)
							$portlist[$cif] = $carpip." (".get_vip_descr($carpip).")";
						$aliaslist = get_configured_ip_aliases_list();
						foreach ($aliaslist as $aliasip => $aliasif)
							$portlist[$aliasif.'|'.$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
					  	foreach ($portlist as $ifn => $ifinfo) {
							echo "<option value=\"{$ifn}\"";
							if ($ifn == $pconfig['if'])
								echo " selected=\"selected\"";
							echo ">" . htmlspecialchars($ifinfo) . "</option>\n";
						}
		      		?>
                    </select>
			<br/>
			<span class="vexpl"><?=gettext("The interface here serves as the local address to be used for the GRE tunnel.");?></span></td>
                </tr>
				<tr>
                  <td valign="top" class="vncellreq"><?=gettext("GRE remote address");?></td>
                  <td class="vtable">
                    <input name="remote-addr" type="text" class="formfld unknown" id="remote-addr" size="16" value="<?=htmlspecialchars($pconfig['remote-addr']);?>" />
                    <br/>
                    <span class="vexpl"><?=gettext("Peer address where encapsulated GRE packets will be sent ");?></span></td>
			    </tr>
				<tr>
                  <td valign="top" class="vncellreq"><?=gettext("GRE tunnel local address ");?></td>
                  <td class="vtable">
                    <input name="tunnel-local-addr" type="text" class="formfld unknown" id="tunnel-local-addr" size="16" value="<?=htmlspecialchars($pconfig['tunnel-local-addr']);?>" />
                    <br/>
                    <span class="vexpl"><?=gettext("Local GRE tunnel endpoint");?></span></td>
			    </tr>
				<tr>
                  <td valign="top" class="vncellreq"><?=gettext("GRE tunnel remote address ");?></td>
                  <td class="vtable">
                    <input name="tunnel-remote-addr" type="text" class="formfld unknown ipv4v6" id="tunnel-remote-addr" size="16" value="<?=htmlspecialchars($pconfig['tunnel-remote-addr']);?>" />
                    <select name="tunnel-remote-net" class="formselect ipv4v6" id="tunnel-remote-net">
                                        <?php
                                        for ($i = 128; $i > 0; $i--) {
						echo "<option value=\"{$i}\"";
						if ($i == $pconfig['tunnel-remote-net'])
							echo " selected=\"selected\"";
						echo ">" . $i . "</option>";
                                        }
                                        ?>
                    </select>
                    <br/>
                    <span class="vexpl"><?=gettext("Remote GRE address endpoint. The subnet part is used for the determining the network that is tunneled.");?></span></td>
			    </tr>
				<tr>
                  <td valign="top" class="vncell"><?=gettext("Mobile tunnel");?></td>
                  <td class="vtable">
                    <input name="link0" type="checkbox" id="link0" <?if ($pconfig['link0']) echo "checked=\"checked\"";?> />
                    <br/>
                    <span class="vexpl"><?=gettext("Specify which encapsulation method the tunnel should use. ");?></span></td>
			    </tr>
				<tr>
                  <td valign="top" class="vncell"><?=gettext("Route search type");?></td>
                  <td class="vtable">
                    <input name="link1" type="checkbox" id="link1" <?if ($pconfig['link1']) echo "checked=\"checked\"";?> />
                    <br/>
                    <span class="vexpl">
     <?=gettext("For correct operation, the GRE device needs a route to the destination".
    " that is less specific than the one over the tunnel.  (Basically, there".
    " needs to be a route to the decapsulating host that does not run over the".
    " tunnel, as this would be a loop.");?>
					 </span></td>
			    </tr>
				<tr>
                  <td valign="top" class="vncell"><?=gettext("WCCP version");?></td>
                  <td class="vtable">
                    <input name="link2" type="checkbox" id="link2" <?if ($pconfig['link2']) echo "checked=\"checked\"";?> />
                    <br/>
                    <span class="vexpl"><?=gettext("Check this box for WCCP encapsulation version 2, or leave unchecked for version 1.");?></span></td>
			    </tr>
				<tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
                  <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" />
                    <br/> <span class="vexpl"><?=gettext("You may enter a description here".
                    " for your reference (not parsed).");?></span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
		    <input type="hidden" name="greif" value="<?=htmlspecialchars($pconfig['greif']); ?>" />
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" /> <input type="button" value="<?=gettext("Cancel");?>" onclick="history.back()" />
                    <?php if (isset($id) && $a_gres[$id]): ?>
                    <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
