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


$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_gifs[$id]) {
	$pconfig['if'] = $a_gifs[$id]['if'];
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
	$reqdfieldsn = explode(",", "Parent interface,Local address, Remote tunnel address, Remote tunnel network, Local tunnel address");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if ((!is_ipaddr($_POST['tunnel-local-addr'])) || (!is_ipaddr($_POST['tunnel-remote-addr'])) ||
			(!is_ipaddr($_POST['remote-addr']))) {
		$input_errors[] = "The tunnel local and tunnel remote fields must have valid IP addresses.";
	}

	foreach ($a_gifs as $gif) {
		if (isset($id) && ($a_gifs[$id]) && ($a_gifs[$id] === $gif))
			continue;

		if (($gif['if'] == $_POST['if']) && ($gif['tunnel-remote-net'] == $_POST['tunnel-remote-net'])) {
			$input_errors[] = "A gif with the network {$gif['remote-network']} is already defined.";
			break;
		}
	}

	if (!$input_errors) {
		$gif = array();
		$gif['if'] = $_POST['if'];
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
                        $input_errors[] = "Error occured creating interface, please retry.";
                else {
                        if (isset($id) && $a_gifs[$id])
                                $a_gifs[$id] = $gif;
                        else
                                $a_gifs[] = $gif;

                        write_config();

			header("Location: interfaces_gif.php");
			exit;
		}
	}
}

$pgtitle = array("Firewall","GIF","Edit");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="interfaces_gif_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
				<tr>
					<td colspan="2" valign="top" class="listtopic">GIF configuration</td>
				</tr>
				<tr>
                  <td width="22%" valign="top" class="vncellreq">Parent interface</td>
                  <td width="78%" class="vtable">
                    <select name="if" class="formselect">
                      <?php
						$portlist = get_configured_interface_with_descr();
					  	foreach ($portlist as $ifn => $ifinfo) {
							echo "<option value=\"{$ifn}\"";
							if ($ifn == $pconfig['if'])
								echo "selected";
							echo ">{$ifinfo}</option>";
						}
		      		?>
                    </select>
			<br/>
			<span class="vexpl">The interface here servers as the local address to be used for the gif tunnel.</span></td>
                </tr>
				<tr>
                  <td valign="top" class="vncellreq">gif remote address</td>
                  <td class="vtable">
                    <input name="remote-addr" type="text" class="formfld unknown" id="remote-addr" size="16" value="<?=$pconfig['remote-addr'];?>">
                    <br>
                    <span class="vexpl">Peer address where encapsulated gif packets will be sent. </span></td>
			    </tr>
				<tr>
                  <td valign="top" class="vncellreq">gif tunnel local address</td>
                  <td class="vtable">
                    <input name="tunnel-local-addr" type="text" class="formfld unknown" id="tunnel-local-addr" size="16" value="<?=$pconfig['tunnel-local-addr'];?>">
                    <br>
                    <span class="vexpl">Local gif tunnel endpoint</span></td>
			    </tr>
				<tr>
                  <td valign="top" class="vncellreq">gif tunnel remote address </td>
                  <td class="vtable">
                    <input name="tunnel-remote-addr" type="text" class="formfld unknown" id="tunnel-remote-addr" size="16" value="<?=$pconfig['tunnel-remote-addr'];?>">
                    <select name="tunnel-remote-net" class="formselect" id="tunnel-remote-net">
                                        <?php
                                        for ($i = 32; $i > 0; $i--) {
                                                if($i <> 31) {
                                                        echo "<option value=\"{$i}\" ";
                                                        if ($i == $pconfig['tunnel-remote-net']) echo "selected";
                                                        echo ">" . $i . "</option>";
                                                }
                                        }
                                        ?>
                    </select>					
                    <br/>
                    <span class="vexpl">Remote gif address endpoint. The subnet part is used for the determinig the network that is tunneled.</span></td>
			    </tr>
				<tr>
                  <td valign="top" class="vncell">Route caching  </td>
                  <td class="vtable">
                    <input name="link0" type="checkbox" id="link0" <?if ($pconfig['link0']) echo "checked";?>>
                    <br>
                    <span class="vexpl">Specify if route caching can be enabled. Be careful with these settings on dynamic networks. </span></td>
			    </tr>
				<tr>
                  <td valign="top" class="vncell">ECN friendly behaviour</td>
                  <td class="vtable">
                    <input name="link1" type="checkbox" id="link1" <?if ($pconfig['link1']) echo "checked";?>>
                    <br>
                    <span class="vexpl">
     Note that the ECN friendly behavior violates RFC2893.  This should be
     used in mutual agreement with the peer.					
					 </span></td>
			    </tr>
				<tr>
                  <td width="22%" valign="top" class="vncell">Description</td>
                  <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
                    <br> <span class="vexpl">You may enter a description here
                    for your reference (not parsed).</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
		    <input type="hidden" name="gifif" value="<?=$pconfig['gifif']; ?>">
                    <input name="Submit" type="submit" class="formbtn" value="Save"> <input type="button" value="Cancel" onclick="history.back()">
                    <?php if (isset($id) && $a_gifs[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
