<?php
/* $Id$ */
/*
    firewall_virtual_ip_edit.php
    part of pfSense (http://www.pfsense.com/)
    Copyright (C) 2004-2005 Scott Ullrich <geekgod@pfsense.com>.
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

##|+PRIV
##|*IDENT=page-firewall-system-tunables-edit
##|*NAME=Firewall: System: Tunables: Edit page
##|*DESCR=Allow access to the 'Firewall: System: Tunables: Edit' page.
##|*MATCH=firewall_system_tunables_edit.php*
##|-PRIV


$pgtitle = array("Firewall","System Tunables","Edit");

require("guiconfig.inc");
if (!is_array($config['sysctl']['item'])) {
        $config['sysctl']['item'] = array();
}
$a_tunable = &$config['sysctl']['item'];

if (isset($_POST['id']))
	$id = $_POST['id'];
else
	$id = $_GET['id'];

if (isset($id) && $a_tunable[$id]) {
	$pconfig['tunable'] = $a_tunable[$id]['tunable'];
	$pconfig['value'] = $a_tunable[$id]['value'];
	$pconfig['desc'] = $a_tunable[$id]['desc'];
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* if this is an AJAX caller then handle via JSON */
	if(isAjax() && is_array($input_errors)) {
		input_errors2Ajax($input_errors);
		exit;
	}

	if (!$input_errors) {
		$tunableent = array();

		$tunableent['tunable'] = $_POST['tunable'];
		$tunableent['value'] = $_POST['value'];
		$tunableent['desc'] = $_POST['desc'];

		if (isset($id) && $a_tunable[$id]) {
			$a_tunable[$id] = $tunableent;
		} else
			$a_tunable[] = $tunableent;

		touch($d_sysctldirty_path);

		write_config();

		pfSenseHeader("firewall_system_tunables.php");

		exit;
	}
}

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>
<div id="inputerrors"></div>

<?php if ($input_errors) print_input_errors($input_errors); ?>

            <form action="firewall_system_tunables_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
		  		  <td width="22%" valign="top" class="vncellreq">Tunable</td>
                  <td width="78%" class="vtable">
						<input size="65" name="tunable" value="<?php echo $pconfig['tunable']; ?>">
				  </td>
				</tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Description</td>
                  <td width="78%">
						<textarea rows="7" cols="50" name="desc"><?php echo $pconfig['desc']; ?></textarea>
                  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Value</td>
                  <td width="78%">
						<input size="65" name="value" value="<?php echo $pconfig['value']; ?>">
                  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input id="submit" name="Submit" type="submit" class="formbtn" value="Save" />
                    <input id="cancelbutton" name="cancelbutton" type="button" class="formbtn" value="Cancel" onclick="history.back()" />
                    <?php if (isset($id) && $a_tunable[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>" />
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>

<?php include("fend.inc"); ?>
</body>
</html>
