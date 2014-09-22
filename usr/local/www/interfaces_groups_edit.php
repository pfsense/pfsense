<?php
/*
	Copyright (C) 2009 Ermal Luçi
	Copyright (C) 2004 Scott Ullrich
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
	pfSense_BUILDER_BINARIES:	/sbin/ifconfig
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-interfaces-groups-edit
##|*NAME=Interfaces: Groups: Edit page
##|*DESCR=Allow access to the 'Interfaces: Groups: Edit' page.
##|*MATCH=interfaces_groups_edit.php*
##|-PRIV


require("guiconfig.inc");
require_once("functions.inc");

$pgtitle = array(gettext("Interfaces"),gettext("Groups"),gettext("Edit"));
$shortcut_section = "interfaces";

if (!is_array($config['ifgroups']['ifgroupentry']))
	$config['ifgroups']['ifgroupentry'] = array();

$a_ifgroups = &$config['ifgroups']['ifgroupentry'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_ifgroups[$id]) {
	$pconfig['ifname'] = $a_ifgroups[$id]['ifname'];
	$pconfig['members'] = $a_ifgroups[$id]['members'];
	$pconfig['descr'] = html_entity_decode($a_ifgroups[$id]['descr']);
}

$iflist = get_configured_interface_with_descr();
$iflist_disabled = get_configured_interface_with_descr(false, true);

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if (!isset($id)) {
		foreach ($a_ifgroups as $groupentry)
			if ($groupentry['ifname'] == $_POST['ifname'])
				$input_errors[] = gettext("Group name already exists!");
	}
	if (preg_match("/([^a-zA-Z])+/", $_POST['ifname'], $match))
		$input_errors[] = gettext("Only letters A-Z are allowed as the group name.");

	foreach ($iflist as $gif => $gdescr) {
		if ($gdescr == $_POST['ifname'] || $gif == $_POST['ifname'])
			$input_errors[] = "The specified group name is already used by an interface. Please choose another name.";
	}
	$members = "";
	$isfirst = 0;
	/* item is a normal ifgroupentry type */
	for($x=0; $x<9999; $x++) {
		if($_POST["members{$x}"] <> "") {
			if ($isfirst > 0)
				$members .= " ";
			$members .= $_POST["members{$x}"];
			$isfirst++;
		}
	}

	if (!$input_errors) {
		$ifgroupentry = array();
		$ifgroupentry['members'] = $members;
		$ifgroupentry['descr'] = $_POST['descr'];

		if (isset($id) && $a_ifgroups[$id] && $_POST['ifname'] != $a_ifgroups[$id]['ifname']) {
			if (!empty($config['filter']) && is_array($config['filter']['rule'])) {
				foreach ($config['filter']['rule'] as $ridx => $rule) {
					if (isset($rule['floating'])) {
						$rule_ifs = explode(",", $rule['interface']);
						$rule_changed = false;
						foreach ($rule_ifs as $rule_if_id => $rule_if) {
							if ($rule_if == $a_ifgroups[$id]['ifname']) {
								$rule_ifs[$rule_if_id] = $_POST['ifname'];
								$rule_changed = true;
							}
						}
						if ($rule_changed)
							$config['filter']['rule'][$ridx]['interface'] = implode(",", $rule_ifs);
					} else {
						if ($rule['interface'] == $a_ifgroups[$id]['ifname'])
							$config['filter']['rule'][$ridx]['interface'] = $_POST['ifname'];
					}
				}
			}
			if (!empty($config['nat']) && is_array($config['nat']['rule'])) {
				foreach ($config['nat']['rule'] as $ridx => $rule) {
					if ($rule['interface'] == $a_ifgroups[$id]['ifname'])
						$config['nat']['rule'][$ridx]['interface'] = $_POST['ifname'];
				}
			}
			$omembers = explode(" ", $a_ifgroups[$id]['members']);
			if (count($omembers) > 0) {
				foreach ($omembers as $ifs) {
					$realif = get_real_interface($ifs);
					if ($realif)
						mwexec("/sbin/ifconfig {$realif} -group " . $a_ifgroups[$id]['ifname']);
				}
			}
			$ifgroupentry['ifname'] = $_POST['ifname'];
			$a_ifgroups[$id] = $ifgroupentry;
		} else if (isset($id) && $a_ifgroups[$id]) {
			$omembers = explode(" ", $a_ifgroups[$id]['members']);
			$nmembers = explode(" ", $members);
			$delmembers = array_diff($omembers, $nmembers);
			if (count($delmembers) > 0) {
				foreach ($delmembers as $ifs) {
					$realif = get_real_interface($ifs);
					if ($realif)
						mwexec("/sbin/ifconfig {$realif} -group " . $a_ifgroups[$id]['ifname']);
				}
			}
			$ifgroupentry['ifname'] = $_POST['ifname'];
			$a_ifgroups[$id] = $ifgroupentry;
		} else {
			$ifgroupentry['ifname'] = $_POST['ifname'];
			$a_ifgroups[] = $ifgroupentry;
		}

		write_config();

		interface_group_setup($ifgroupentry);

		header("Location: interfaces_groups.php");
		exit;
	} else {
		$pconfig['descr'] = $_POST['descr'];
		$pconfig['members'] = $members;
	}
}

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>

<script type="text/javascript">
//<![CDATA[
// Global Variables
var rowname = new Array(9999);
var rowtype = new Array(9999);
var newrow  = new Array(9999);
var rowsize = new Array(9999);

for (i = 0; i < 9999; i++) {
        rowname[i] = '';
        rowtype[i] = 'select';
        newrow[i] = '';
        rowsize[i] = '30';
}

var field_counter_js = 0;
var loaded = 0;
var is_streaming_progress_bar = 0;
var temp_streaming_text = "";

var addRowTo = (function() {
    return (function (tableId) {
        var d, tbody, tr, td, bgc, i, ii, j;
        d = document;
        tbody = d.getElementById(tableId).getElementsByTagName("tbody").item(0);
        tr = d.createElement("tr");
        for (i = 0; i < field_counter_js; i++) {
                td = d.createElement("td");
		<?php
                        $innerHTML="\"<input type='hidden' value='\" + totalrows +\"' name='\" + rowname[i] + \"_row-\" + totalrows + \"' /><select size='1' name='\" + rowname[i] + totalrows + \"'>\" +\"";

                        foreach ($iflist as $ifnam => $ifdescr)
                                $innerHTML .= "<option value='{$ifnam}'>{$ifdescr}<\/option>";
			$innerHTML .= "<\/select>\";";
                ?>
			td.innerHTML=<?=$innerHTML;?>
                tr.appendChild(td);
        }
        td = d.createElement("td");
        td.rowSpan = "1";

        td.innerHTML = '<a onclick="removeRow(this);return false;" href="#"><img border="0" src="/themes/' + theme + '/images/icons/icon_x.gif" alt="remove" /><\/a>';
        tr.appendChild(td);
        tbody.appendChild(tr);
        totalrows++;
    });
})();

function removeRow(el) {
    var cel;
    while (el && el.nodeName.toLowerCase() != "tr")
            el = el.parentNode;

    if (el && el.parentNode) {
        cel = el.getElementsByTagName("td").item(0);
        el.parentNode.removeChild(el);
    }
}

	rowname[0] = "members";
	rowtype[0] = "textbox";
	rowsize[0] = "30";

	rowname[2] = "detail";
	rowtype[2] = "textbox";
	rowsize[2] = "50";
//]]>
</script>
<input type='hidden' name='members_type' value='textbox' class="formfld unknown" />

<?php if ($input_errors) print_input_errors($input_errors); ?>
<div id="inputerrors"></div>

<form action="interfaces_groups_edit.php" method="post" name="iform" id="iform">
<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="interfaces groups edit">
  <tr>
	<td colspan="2" valign="top" class="listtopic"><?=gettext("Interface Groups Edit");?></td>
  </tr>
  <tr>
    <td valign="top" class="vncellreq"><?=gettext("Group Name");?></td>
    <td class="vtable">
	<input class="formfld unknown" name="ifname" id="ifname" maxlength="15" value="<?=htmlspecialchars($pconfig['ifname']);?>" />
	<br />
	<?=gettext("No numbers or spaces are allowed. Only characters in a-zA-Z");?>
    </td>
  </tr>
  <tr>
    <td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
    <td width="78%" class="vtable">
      <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" />
      <br />
      <span class="vexpl">
        <?=gettext("You may enter a description here for your reference (not parsed).");?>
      </span>
    </td>
  </tr>
  <tr>
    <td width="22%" valign="top" class="vncellreq"><div id="membersnetworkport"><?=gettext("Member (s)");?></div></td>
    <td width="78%" class="vtable">
      <table id="maintable" summary="main table">
        <tbody>
          <tr>
            <td><div id="onecolumn"><?=gettext("Interface");?></div></td>
          </tr>

	<?php
	$counter = 0;
	$members = $pconfig['members'];
	if ($members <> "") {
		$item = explode(" ", $members);
		foreach($item as $ww) {
			$members = $item[$counter];
			$tracker = $counter;
	?>
        <tr>
	<td class="vtable">
	        <select name="members<?php echo $tracker; ?>" class="formselect" id="members<?php echo $tracker; ?>">
			<?php
				$found = false;
				foreach ($iflist as $ifnam => $ifdescr) {
					echo "<option value=\"{$ifnam}\"";
					if ($ifnam == $members) {
						$found = true;
						echo " selected=\"selected\"";
					}
					echo ">{$ifdescr}</option>";
				}

				if ($found === false)
					foreach ($iflist_disabled as $ifnam => $ifdescr)
						if ($ifnam == $members)
							echo "<option value=\"{$ifnam}\" selected=\"selected\">{$ifdescr}</option>";
			?>
                        </select>
	</td>
        <td>
	<a onclick="removeRow(this); return false;" href="#"><img border="0" src="/themes/<?echo $g['theme'];?>/images/icons/icon_x.gif" alt="remove" /></a>
	      </td>
          </tr>
<?php
		$counter++;

		} // end foreach
	} // end if
?>
        </tbody>
		  </table>
			<a onclick="javascript:addRowTo('maintable'); return false;" href="#">
        <img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" alt="" title="<?=gettext("add another entry");?>" />
      </a>
		<br /><br />
		<strong><?PHP echo gettext("NOTE:");?></strong>
		<?PHP echo gettext("Rules for WAN type interfaces in groups do not contain the reply-to mechanism upon which Multi-WAN typically relies.");?>
		<a href="https://doc.pfsense.org/index.php/Interface_Groups"><?PHP echo gettext("More Information");?></a>
		</td>
  </tr>
  <tr>
    <td width="22%" valign="top">&nbsp;</td>
    <td width="78%">
      <input id="submit" name="submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
      <a href="interfaces_groups.php"><input id="cancelbutton" name="cancelbutton" type="button" class="formbtn" value="<?=gettext("Cancel");?>" /></a>
      <?php if (isset($id) && $a_ifgroups[$id]): ?>
      <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
      <?php endif; ?>
    </td>
  </tr>
</table>
</form>

<script type="text/javascript">
//<![CDATA[
	field_counter_js = 1;
	rows = 1;
	totalrows = <?php echo $counter; ?>;
	loaded = <?php echo $counter; ?>;
//]]>
</script>

<?php
	unset($iflist);
	unset($iflist_disabled);
	include("fend.inc");
?>
</body>
</html>
