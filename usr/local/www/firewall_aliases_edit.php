#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	firewall_aliases_edit.php
	Copyright (C) 2004 Scott Ullrich
	All rights reserved.

	originially part of m0n0wall (http://m0n0.ch/wall)
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

require("guiconfig.inc");

if (!is_array($config['aliases']['alias']))
	$config['aliases']['alias'] = array();

aliases_sort();
$a_aliases = &$config['aliases']['alias'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_aliases[$id]) {
	$pconfig['name'] = $a_aliases[$id]['name'];
	$addresses = explode(' ', $a_aliases[$id]['address']);
	if (is_array($addresses))
		$address = $addresses[0];
	else
		$address = $addresses;
	list($pconfig['address'],$pconfig['address_subnet']) =
		explode('/', $address);
	if ($pconfig['address_subnet'])
		$pconfig['type'] = "network";
	else
		if (is_ipaddr($pconfig['address']))
			$pconfig['type'] = "host";
		else
			$pconfig['type'] = "port";
			
	$pconfig['descr'] = $a_aliases[$id]['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "name address");
	$reqdfieldsn = explode(",", "Name,Address");

	if ($_POST['type'] == "network") {
		$reqdfields[] = "address_subnet";
		$reqdfieldsn[] = "Subnet bit count";
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (($_POST['name'] && !is_validaliasname($_POST['name']))) {
		$input_errors[] = "The alias name may only consist of the characters a-z, A-Z, 0-9.";
	}
	if ($_POST['type'] == "host")
		if (!is_ipaddr($_POST['address'])) {
			$input_errors[] = "A valid address must be specified.";
		}
	if ($_POST['type'] == "network") {
		if (!is_ipaddr($_POST['address'])) {
			$input_errors[] = "A valid address must be specified.";
		}
		if (!is_numeric($_POST['address_subnet'])) {
			$input_errors[] = "A valid subnet bit count must be specified.";
		}
	}
	if ($_POST['type'] == "port")
		if (!is_port($_POST['address']))
			$input_errors[] = "The port must be an integer between 1 and 65535.";

	/* check for name conflicts */
	foreach ($a_aliases as $alias) {
		if (isset($id) && ($a_aliases[$id]) && ($a_aliases[$id] === $alias))
			continue;

		if ($alias['name'] == $_POST['name']) {
			$input_errors[] = "An alias with this name already exists.";
			break;
		}
	}

	if (!$input_errors) {
		$alias = array();
		$alias['name'] = $_POST['name'];
		if ($_POST['type'] == "network")
			$alias['address'] = $_POST['address'] . "/" . $_POST['address_subnet'];

		else
			$alias['address'] = $_POST['address'];

		$address = $alias['address'];
		$isfirst = 0;
		for($x=0; $x<99; $x++) {
			$comd = "\$subnet = \$_POST['address" . $x . "'];";
			eval($comd);
			$comd = "\$subnet_address = \$_POST['address_subnet" . $x . "'];";
			eval($comd);
			if($subnet <> "") {
				$address .= " ";
				$address .= $subnet;
				if($subnet_address <> "") $address .= "/" . $subnet_address;
			}
		}

		$alias['address'] = $address;
		$alias['descr'] = $_POST['descr'];

		if (isset($id) && $a_aliases[$id])
			$a_aliases[$id] = $alias;
		else
			$a_aliases[] = $alias;

		filter_configure();

		write_config();

		header("Location: firewall_aliases.php");
		exit;
	}
}

$pgtitle = "System: Firewall: Aliases: Edit";
include("head.inc");

?>

<script language="JavaScript">
<!--
function typesel_change() {
	switch (document.iform.type.selectedIndex) {
		case 0:	/* host */
			var cmd;
			document.iform.address_subnet.disabled = 1;
			document.iform.address_subnet.value = "";
			document.iform.address_subnet.selected = 0;
			newrows = totalrows+1;
			for(i=2; i<newrows; i++) {
				comd = 'document.iform.address_subnet' + i + '.disabled = 1;';
				eval(comd);
				comd = 'document.iform.address_subnet' + i + '.value = "";';
				eval(comd);
			}
			break;
		case 1:	/* network */
			var cmd;
			document.iform.address_subnet.disabled = 0;
//			document.iform.address_subnet.value = "";
			newrows = totalrows+1;
			for(i=2; i<newrows; i++) {
				comd = 'document.iform.address_subnet' + i + '.disabled = 0;';
				eval(comd);
//				comd = 'document.iform.address_subnet' + i + '.value = "32";';
//				eval(comd);
			}
			break;
		case 2:	/* port */
			var cmd;
			document.iform.address_subnet.disabled = 1;
			document.iform.address_subnet.value = "";
			newrows = totalrows+1;
			for(i=2; i<newrows; i++) {
				comd = 'document.iform.address_subnet' + i + '.disabled = 1;';
				eval(comd);
				comd = 'document.iform.address_subnet' + i + '.value = "32";';
				eval(comd);
			}
			break;
	}
}

function update_box_type() {
	var indexNum = document.forms[0].type.selectedIndex;
	var selected = document.forms[0].type.options[indexNum].text;
	if(selected == 'Network(s)') {
		document.getElementById ("addressnetworkport").firstChild.data = "Network(s)";
		document.getElementById ("address_subnet").visible = true;
		document.getElementById ("address_subnet").disabled = false;
	} else if(selected == 'Host(s)') {
		document.getElementById ("addressnetworkport").firstChild.data = "Host(s)";
		document.getElementById ("address_subnet").visible = false;
		document.getElementById ("address_subnet").disabled = true;
	} else if(selected == 'Port(s)') {
		document.getElementById ("addressnetworkport").firstChild.data = "Port(s)";
		document.getElementById ("address_subnet").visible = false;
		document.getElementById ("address_subnet").disabled = true;
	}
}

-->
</script>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>

<script type="text/javascript" language="javascript" src="row_helper.js">
</script>

<input type='hidden' name='address_type' value='textbox'></input>
<input type='hidden' name='address_subnet_type' value='select'></input>

<script type="text/javascript" language='javascript'>
<!--

rowname[0] = "address";
rowtype[0] = "textbox";

rowname[1] = "address_subnet";
rowtype[1] = "select";

rowname[2] = "address_subnet";
rowtype[2] = "select";
-->
</script>

<p class="pgtitle"><?=$pgtitle?></p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="firewall_aliases_edit.php" method="post" name="iform" id="iform">
              <?display_topbar()?>
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
                  <td valign="top" class="vncellreq">Name</td>
                  <td class="vtable"> <input name="name" type="text" class="formfld" id="name" size="40" value="<?=htmlspecialchars($pconfig['name']);?>">
                    <br> <span class="vexpl">The name of the alias may only consist
                    of the characters a-z, A-Z and 0-9.</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">Description</td>
                  <td width="78%" class="vtable"> <input name="descr" type="text" class="formfld" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
                    <br> <span class="vexpl">You may enter a description here
                    for your reference (not parsed).</span></td>
                </tr>
                <tr>
                  <td valign="top" class="vncellreq">Type</td>
                  <td class="vtable">
                    <select name="type" class="formfld" id="type" onChange="update_box_type(); typesel_change();">
                      <option value="host" <?php if ($pconfig['type'] == "host") echo "selected"; ?>>Host(s)</option>
                      <option value="network" <?php if ($pconfig['type'] == "network") echo "selected"; ?>>Network(s)</option>
		      <option value="port" <?php if ($pconfig['type'] == "port") echo "selected"; ?>>Port(s)</option>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><div id="addressnetworkport" name="addressnetworkport">Host(s)</div></td>
                  <td width="78%" class="vtable">


		    <table name="maintable" id="maintable">
		      <tbody>

			<?php
			$counter = 0;
			$address = $a_aliases[$id]['address'];
			$item = explode(" ", $address);
			foreach($item as $ww) {
				$address = $item[$counter];
				$address_subnet = "";
				$item2 = explode("/", $address);
				foreach($item2 as $current) {
					if($item2[1] <> "") {
						$address = $item2[0];
						$address_subnet = $item2[1];
					}
				}
				if($counter > 0) $tracker = $counter + 1;
			?>
			<tr><td> <input name="address<?php echo $tracker; ?>" type="text" class="formfld" id="address<?php echo $tracker; ?>" size="20" value="<?=htmlspecialchars($address);?>"></td><td>
			<select name="address_subnet<?php echo $tracker; ?>" class="formfld" id="address_subnet<?php echo $tracker; ?>">
			  <option></option>
			  <?php for ($i = 32; $i >= 1; $i--): ?>
			  <option value="<?=$i;?>" <?php if ($i == $address_subnet) echo "selected"; ?>><?=$i;?></option>
			  <?php endfor; ?>
			</select>
			  <?php
				if($counter > 0)
					echo "<input type=\"image\" src=\"/themes/".$g['theme']."/images/icons/icon_x.gif\" onclick=\"removeRow(this); return false;\" value=\"Delete\">";
			  ?>

			</td></tr>
			<?php $counter++; } ?>

		     </tbody>
		    </table>
			<a onClick="javascript:addRowTo('maintable'); typesel_change(); return false;" href="#"><img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="add another entry"></a>
		    </td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> <input name="Submit" type="submit" class="formbtn" value="Save"> <input class="formbtn" type="button" value="Cancel" onclick="history.back()">
                    <?php if (isset($id) && $a_aliases[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<script language="JavaScript">
<!--
field_counter_js = 2;
rows = 1;
totalrows = <?php echo $counter; ?>;
loaded = <?php echo $counter; ?>;
typesel_change();
update_box_type();

//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>
