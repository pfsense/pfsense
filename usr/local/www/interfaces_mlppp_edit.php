<?php
/* $Id$ */
/*
	interfaces_mlppp_edit.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
##|*IDENT=page-interfaces-mlppp-edit
##|*NAME=Interfaces: mlppp: Edit page
##|*DESCR=Allow access to the 'Interfaces: mlppp: Edit' page.
##|*MATCH=interfaces_mlppp_edit.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['mlppps']['mlppp']))
	$config['mlppps']['mlppp'] = array();

$a_mlppps = &$config['mlppps']['mlppp'];

$portlist = get_interface_list();

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_mlppps[$id]) {
	$pconfig['if'] = $a_mlppps[$id]['if'];
	$pconfig['mlpppif'] = $a_mlppps[$id]['mlpppif'];
	$pconfig['tag'] = $a_mlppps[$id]['tag'];
	$pconfig['descr'] = $a_mlppps[$id]['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "if tag");
	$reqdfieldsn = explode(",", "Parent interface,mlppp tag");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if ($_POST['tag'] && (!is_numericint($_POST['tag']) || ($_POST['tag'] < '1') || ($_POST['tag'] > '4094'))) {
		$input_errors[] = "The mlppp tag must be an integer between 1 and 4094.";
	}

	foreach ($a_mlppps as $mlppp) {
		if (isset($id) && ($a_mlppps[$id]) && ($a_mlppps[$id] === $mlppp))
			continue;

		if (($mlppp['if'] == $_POST['if']) && ($mlppp['tag'] == $_POST['tag'])) {
			$input_errors[] = "A mlppp with the tag {$mlppp['tag']} is already defined on this interface.";
			break;
		}
	}
	if (is_array($config['qinqs']['qinqentry'])) {
		foreach ($config['qinqs']['qinqentry'] as $qinq)
			if ($qinq['tag'] == $_POST['tag'] && $qinq['if'] == $_POST['if'])
				$input_errors[] = "A QinQ mlppp exists with this tag please remove it to use this tag with.";
	}

	if (!$input_errors) {
		$mlppp = array();
		$mlppp['if'] = $_POST['if'];
		$mlppp['tag'] = $_POST['tag'];
		$mlppp['descr'] = $_POST['descr'];
		$mlppp['mlpppif'] = "{$_POST['if']}_mlppp{$_POST['tag']}";

		$mlppp['mlpppif'] = interface_mlppp_configure($mlppp);
                if ($mlppp['mlpppif'] == "" || !stristr($mlppp['mlpppif'], "mlppp"))
                        $input_errors[] = "Error occured creating interface, please retry.";
                else {
                        if (isset($id) && $a_mlppps[$id])
                                $a_mlppps[$id] = $mlppp;
                        else
                                $a_mlppps[] = $mlppp;

                        write_config();

			$confif = convert_real_interface_to_friendly_interface_name($mlppp['mlpppif']);
			if ($confif <> "")
				interface_configure($confif);
				
			header("Location: interfaces_mlppp.php");
			exit;
		}
	}
}

$pgtitle = array("Firewall","mlppp","Edit");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="interfaces_mlppp_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
				<tr>
					<td colspan="2" valign="top" class="listtopic">mlppp configuration</td>
				</tr>
				<tr>
                  <td width="22%" valign="top" class="vncellreq">Parent interface</td>
                  <td width="78%" class="vtable">
                    <select name="if" class="formselect">
                      <?php
					  foreach ($portlist as $ifn => $ifinfo)
						if (is_jumbo_capable($ifn)) {
							echo "<option value=\"{$ifn}\"";
							if ($ifn == $pconfig['if'])
								echo "selected";
							echo ">";
                      				        echo htmlspecialchars($ifn . " (" . $ifinfo['mac'] . ")");
                      					echo "</option>";
						}
		      ?>
                    </select>
			<br/>
			<span class="vexpl">Only mlppp capable interfaces will be shown.</span></td>
                </tr>
				<tr>
                  <td valign="top" class="vncellreq">mlppp tag </td>
                  <td class="vtable">
                    <input name="tag" type="text" class="formfld unknown" id="tag" size="6" value="<?=htmlspecialchars($pconfig['tag']);?>">
                    <br>
                    <span class="vexpl">802.1Q mlppp tag (between 1 and 4094) </span></td>
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
		    <input type="hidden" name="mlpppif" value="<?=$pconfig['mlpppif']; ?>">
                    <input name="Submit" type="submit" class="formbtn" value="Save"> <input type="button" value="Cancel" onclick="history.back()">
                    <?php if (isset($id) && $a_mlppps[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
