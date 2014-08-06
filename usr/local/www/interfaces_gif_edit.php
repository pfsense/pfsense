<?php
/* $Id$ */
/*
	interfaces_gif_edit.php

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
##|*IDENT=page-interfaces-gif-edit
##|*NAME=Interfaces: GIF: Edit page
##|*DESCR=Allow access to the 'Interfaces: GIF: Edit' page.
##|*MATCH=interfaces_gif_edit.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['gifs']['gif']))
	$config['gifs']['gif'] = array();

$a_gifs = &$config['gifs']['gif'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_gifs[$id]) {
	$pconfig['if'] = $a_gifs[$id]['if'];
	if (!empty($a_gifs[$id]['ipaddr'])) {
		$pconfig['if'] = $pconfig['if'] . '|' . $a_gifs[$id]['ipaddr'];
	}
	$pconfig['gifif'] = $a_gifs[$id]['gifif'];
	$pconfig['remote-addr'] = $a_gifs[$id]['remote-addr'];
	$pconfig['tunnel-remote-net'] = $a_gifs[$id]['tunnel-remote-net'];
	$pconfig['tunnel-local-addr'] = $a_gifs[$id]['tunnel-local-addr'];
	$pconfig['tunnel-remote-addr'] = $a_gifs[$id]['tunnel-remote-addr'];
	$pconfig['link1'] = isset($a_gifs[$id]['link1']);
	$pconfig['link0'] = isset($a_gifs[$id]['link0']);
	$pconfig['descr'] = $a_gifs[$id]['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "if tunnel-remote-addr tunnel-remote-net tunnel-local-addr");
	$reqdfieldsn = array(gettext("Parent interface,Local address, Remote tunnel address, Remote tunnel network, Local tunnel address"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if ((!is_ipaddr($_POST['tunnel-local-addr'])) || (!is_ipaddr($_POST['tunnel-remote-addr'])) ||
			(!is_ipaddr($_POST['remote-addr']))) {
		$input_errors[] = gettext("The tunnel local and tunnel remote fields must have valid IP addresses.");
	}

	$alias = strstr($_POST['if'],'|');
	if ((is_ipaddrv4($alias) && !is_ipaddrv4($_POST['remote-addr'])) ||
			(is_ipaddrv6($alias) && !is_ipaddrv6($_POST['remote-addr'])))
		$input_errors[] = gettext("The alias IP address family has to match the family of the remote peer address.");

	foreach ($a_gifs as $gif) {
		if (isset($id) && ($a_gifs[$id]) && ($a_gifs[$id] === $gif))
			continue;

		/* FIXME: needs to perform proper subnet checks in the feature */
		if (($gif['if'] == $interface) && ($gif['tunnel-remote-addr'] == $_POST['tunnel-remote-addr'])) {
			$input_errors[] = sprintf(gettext("A gif with the network %s is already defined."), $gif['tunnel-remote-addr']);
			break;
		}
	}

	if (!$input_errors) {
		$gif = array();
		list($gif['if'], $gif['ipaddr']) = explode("|",$_POST['if']);
		$gif['tunnel-local-addr'] = $_POST['tunnel-local-addr'];
		$gif['tunnel-remote-addr'] = $_POST['tunnel-remote-addr'];
		$gif['tunnel-remote-net'] = $_POST['tunnel-remote-net'];
		$gif['remote-addr'] = $_POST['remote-addr'];
		$gif['descr'] = $_POST['descr'];
		$gif['link1'] = isset($_POST['link1']);
		$gif['link0'] = isset($_POST['link0']);
		$gif['gifif'] = $_POST['gifif'];

                $gif['gifif'] = interface_gif_configure($gif);
                if ($gif['gifif'] == "" || !stristr($gif['gifif'], "gif"))
                        $input_errors[] = gettext("Error occurred creating interface, please retry.");
                else {
                        if (isset($id) && $a_gifs[$id])
                                $a_gifs[$id] = $gif;
                        else
                                $a_gifs[] = $gif;

                        write_config();

			$confif = convert_real_interface_to_friendly_interface_name($gif['gifif']);
                        if ($confif <> "")
                                interface_configure($confif);

			header("Location: interfaces_gif.php");
			exit;
		}
	}
}

$pgtitle = array(gettext("Interfaces"),gettext("GIF"),gettext("Edit"));
$shortcut_section = "interfaces";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script type="text/javascript" src="/javascript/jquery.ipv4v6ify.js"></script>
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="interfaces_gif_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0" summary="interfaces gif edit">
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("GIF configuration"); ?></td>
				</tr>
				<tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Parent interface"); ?></td>
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
			<span class="vexpl"><?=gettext("The interface here serves as the local address to be used for the gif tunnel."); ?></span></td>
                </tr>
				<tr>
                  <td valign="top" class="vncellreq"><?=gettext("gif remote address"); ?></td>
                  <td class="vtable">
                    <input name="remote-addr" type="text" class="formfld unknown" id="remote-addr" size="24" value="<?=htmlspecialchars($pconfig['remote-addr']);?>" />
                    <br/>
                    <span class="vexpl"><?=gettext("Peer address where encapsulated gif packets will be sent. "); ?></span></td>
			    </tr>
				<tr>
                  <td valign="top" class="vncellreq"><?=gettext("gif tunnel local address"); ?></td>
                  <td class="vtable">
                    <input name="tunnel-local-addr" type="text" class="formfld unknown" id="tunnel-local-addr" size="24" value="<?=htmlspecialchars($pconfig['tunnel-local-addr']);?>" />
                    <br/>
                    <span class="vexpl"><?=gettext("Local gif tunnel endpoint"); ?></span></td>
			    </tr>
				<tr>
                  <td valign="top" class="vncellreq"><?=gettext("gif tunnel remote address "); ?></td>
                  <td class="vtable">
                    <input name="tunnel-remote-addr" type="text" class="formfld unknown ipv4v6" id="tunnel-remote-addr" size="24" value="<?=htmlspecialchars($pconfig['tunnel-remote-addr']);?>" />
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
                    <span class="vexpl"><?=gettext("Remote gif address endpoint. The subnet part is used for determining the network that is tunnelled."); ?></span></td>
			    </tr>
				<tr>
                  <td valign="top" class="vncell"><?=gettext("Route caching  "); ?></td>
                  <td class="vtable">
                    <input name="link0" type="checkbox" id="link0" <?if ($pconfig['link0']) echo "checked=\"checked\"";?> />
                    <br/>
                    <span class="vexpl"><?=gettext("Specify if route caching can be enabled. Be careful with these settings on dynamic networks. "); ?></span></td>
			    </tr>
				<tr>
                  <td valign="top" class="vncell"><?=gettext("ECN friendly behavior"); ?></td>
                  <td class="vtable">
                    <input name="link1" type="checkbox" id="link1" <?if ($pconfig['link1']) echo "checked=\"checked\"";?> />
                    <br/>
                    <span class="vexpl">
     <?=gettext("Note that the ECN friendly behavior violates RFC2893.  This should be " .
     "used in mutual agreement with the peer."); ?>					
					 </span></td>
			    </tr>
				<tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
                  <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" />
                    <br/> <span class="vexpl"><?=gettext("You may enter a description here " .
                    "for your reference (not parsed)."); ?></span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
		    <input type="hidden" name="gifif" value="<?=htmlspecialchars($pconfig['gifif']); ?>" />
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" /> <input type="button" value="<?=gettext("Cancel"); ?>" onclick="history.back()" />
                    <?php if (isset($id) && $a_gifs[$id]): ?>
                    <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
