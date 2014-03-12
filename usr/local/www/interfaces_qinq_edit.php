<?php
/*
	Copyright (C) 2009 Ermal Luçi
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
	pfSense_BUILDER_BINARIES:	/usr/sbin/ngctl	
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-interfacess-qinq
##|*NAME=Interfaces: QinQ: Edit page
##|*DESCR=Allow access to 'Interfaces: QinQ: Edit' page
##|*MATCH=interfaces_qinq_edit.php*
##|-PRIV

$pgtitle = array(gettext("Interfaces"),gettext("QinQ"), gettext("Edit"));
$shortcut_section = "interfaces";

require("guiconfig.inc");

if (!is_array($config['qinqs']['qinqentry']))
	$config['qinqs']['qinqentry'] = array();

$a_qinqs = &$config['qinqs']['qinqentry'];

$portlist = get_interface_list();

/* add LAGG interfaces */
if (is_array($config['laggs']['lagg']) && count($config['laggs']['lagg'])) {
        foreach ($config['laggs']['lagg'] as $lagg)
                $portlist[$lagg['laggif']] = $lagg;
}

if (count($portlist) < 1) {
	header("Location: interfaces_qinq.php");
	exit;
}

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_qinqs[$id]) {
	$pconfig['if'] = $a_qinqs[$id]['if'];
	$pconfig['tag'] = $a_qinqs[$id]['tag'];
	$pconfig['members'] = $a_qinqs[$id]['members'];
	$pconfig['descr'] = html_entity_decode($a_qinqs[$id]['descr']);
/*
	$pconfig['autoassign'] = isset($a_qinqs[$id]['autoassign']);
	$pconfig['autoenable'] = isset($a_qinqs[$id]['autoenable']);
*/
	$pconfig['autogroup'] = isset($a_qinqs[$id]['autogroup']);
	$pconfig['autoadjustmtu'] = isset($a_qinqs[$id]['autoadjustmtu']);
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if (empty($_POST['tag']))
		$input_errors[] = gettext("First level tag cannot be empty.");
	if (isset($id) && $a_qinqs[$id]['tag'] != $_POST['tag'])
		$input_errors[] = gettext("You are editing an existing entry and modifying the first level tag is not allowed.");
	if (isset($id) && $a_qinqs[$id]['if'] != $_POST['if'])
		$input_errors[] = gettext("You are editing an existing entry and modifying the interface is not allowed.");
	if (!isset($id)) {
		foreach ($a_qinqs as $qinqentry)
			if ($qinqentry['tag'] == $_POST['tag'] && $qinqentry['if'] == $_POST['if'])
				$input_errors[] = gettext("QinQ level already exists for this interface, edit it!");
		if (is_array($config['vlans']['vlan'])) {
			foreach ($config['vlans']['vlan'] as $vlan)
				if ($vlan['tag'] == $_POST['tag'] && $vlan['if'] == $_POST['if'])
					$input_errors[] = gettext("A normal VLAN exists with this tag please remove it to use this tag for QinQ first level.");
		}
	}

	$qinqentry = array();
	$qinqentry['if'] = $_POST['if'];
	$qinqentry['tag'] = $_POST['tag'];
/*
	if ($_POST['autoassign'] == "yes") {
		$qinqentry['autoassign'] = true;
	if ($_POST['autoenable'] == "yes")
		$qinqentry['autoenable'] = true;
	if ($_POST['autoadjust'] == "yes")
		$qinqentry['autoadjustmtu'] = true;
*/
	if ($_POST['autogroup'] == "yes")
		$qinqentry['autogroup'] = true;

	$members = "";
	$isfirst = 0;
	/* item is a normal qinqentry type */
	for($x=0; $x<9999; $x++) {
		if($_POST["members{$x}"] <> "") {
			$member = explode("-", $_POST["members{$x}"]);
			if (count($member) > 1) {
				if (preg_match("/([^0-9])+/", $member[0], $match)  ||
					preg_match("/([^0-9])+/", $member[1], $match))
					$input_errors[] = gettext("Tags can contain only numbers or a range in format #-#.");

				for ($i = $member[0]; $i <= $member[1]; $i++) {
					if ($isfirst > 0)
						$members .= " ";
					$members .= $i;
					$isfirst++;
				}
			} else {
				if (preg_match("/([^0-9])+/", $_POST["members{$x}"], $match))
					$input_errors[] = gettext("Tags can contain only numbers or a range in format #-#.");

				if ($isfirst > 0)
					$members .= " ";
				$members .= $_POST["members{$x}"];
				$isfirst++;
			}
		}
	}

	if (!$input_errors) {
		$qinqentry['members'] = $members;
		$qinqentry['descr'] = $_POST['descr'];
		$qinqentry['vlanif'] = "{$_POST['if']}_{$_POST['tag']}";
		$nmembers = explode(" ", $members);

		if (isset($id) && $a_qinqs[$id]) {
			$omembers = explode(" ", $a_qinqs[$id]['members']);
			$delmembers = array_diff($omembers, $nmembers);
			$addmembers = array_diff($nmembers, $omembers);

			if ((count($delmembers) > 0) || (count($addmembers) > 0)) {
				$fd = fopen("{$g['tmp_path']}/netgraphcmd", "w");
				foreach ($delmembers as $tag) {
					fwrite($fd, "shutdown {$qinqentry['vlanif']}h{$tag}:\n");
					fwrite($fd, "msg {$qinqentry['vlanif']}qinq: delfilter \\\"{$qinqentry['vlanif']}{$tag}\\\"\n");
				}

				foreach ($addmembers as $member) {
					$qinq = array();
					$qinq['if'] = $qinqentry['vlanif'];
					$qinq['tag'] = $member;
					$macaddr = get_interface_mac($qinqentry['vlanif']);
					interface_qinq2_configure($qinq, $fd, $macaddr);
				}

				fclose($fd);
				mwexec("/usr/sbin/ngctl -f {$g['tmp_path']}/netgraphcmd");
			}
			$a_qinqs[$id] = $qinqentry;
		} else {
			interface_qinq_configure($qinqentry);
			$a_qinqs[] = $qinqentry;
		}
		if ($_POST['autogroup'] == "yes") {
			if (!is_array($config['ifgroups']['ifgroupentry']))
				$config['ifgroups']['ifgroupentry'] = array();
			foreach ($config['ifgroups']['ifgroupentry'] as $gid => $group) {
				if ($group['ifname'] == "QinQ") {
					$found = true;
					break;
				}
			}
			$additions = "";
			foreach($nmembers as $qtag)
				$additions .= "{$qinqentry['vlanif']}_{$qtag} ";
			$additions .= "{$qinqentry['vlanif']}";
			if ($found == true)
				$config['ifgroups']['ifgroupentry'][$gid]['members'] .= " {$additions}";
			else {
				$gentry = array();
				$gentry['ifname'] = "QinQ";
				$gentry['members'] = "{$additions}";
				$gentry['descr'] = gettext("QinQ VLANs group");
				$config['ifgroups']['ifgroupentry'][] = $gentry;
			}
		}

		write_config();

		header("Location: interfaces_qinq.php");
		exit;
	} else {
		$pconfig['descr'] = $_POST['descr'];
		$pconfig['tag'] = $_POST['tag'];
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
        rowname[i] = 'members';
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
		td.innerHTML="<input type='hidden' value='" + totalrows +"' name='" + rowname[i] + "_row-" + totalrows + "' /><input size='" + rowsize[i] + "' class='formfld unknown' name='" + rowname[i] + totalrows + "' /> ";
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

	rowname[0] = <?=gettext("members");?>;
	rowtype[0] = "textbox";
	rowsize[0] = "30";

	rowname[2] = <?=gettext("detail");?>;
	rowtype[2] = "textbox";
	rowsize[2] = "50";
//]]>
</script>
<input type='hidden' name='members_type' value='textbox' class="formfld unknown" />

<?php if ($input_errors) print_input_errors($input_errors); ?>
<div id="inputerrors"></div>

<form action="interfaces_qinq_edit.php" method="post" name="iform" id="iform">
<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="interfaces qinq edit">
  <tr>
	<td colspan="2" valign="top" class="listtopic"><?=gettext("Interface QinQ Edit");?></td>
  </tr>
  <tr>
    <td width="22%" valign="top" class="vncellreq"><?=gettext("Parent interface");?></td>
    <td width="78%" class="vtable">
    <select name="if" id="if" class="formselect">
    <?php
        foreach ($portlist as $ifn => $ifinfo) {
		if (is_jumbo_capable($ifn)) {
			echo "<option value=\"{$ifn}\"";
                        if ($ifn == $pconfig['if'])
				echo " selected=\"selected\"";
                        echo ">";
                        echo htmlspecialchars($ifn . " (" . $ifinfo['mac'] . ")");
                        echo "</option>";
                }
	}
    ?>
    </select>
    <br/>
    <span class="vexpl"><?=gettext("Only QinQ capable interfaces will be shown.");?></span></td>
  </tr>
  <tr>
    <td width="22%" valign="top" class="vncellreq"><?=gettext("First level tag");?></td>
    <td width="78%" class="vtable">
      <input name="tag" type="text" class="formfld unknown" id="tag" size="10" value="<?=htmlspecialchars($pconfig['tag']);?>" />
      <br />
      <span class="vexpl">
	<?=gettext("This is the first level VLAN tag. On top of this are stacked the member VLANs defined below.");?>
      </span>
    </td>
  </tr>
  <tr>
	<td width="22%" valign="top" class="vncell"><?=gettext("Options");?></td>
	<td width="78%" class="vtable">
<?php /* ?>
		<br/>
		<input type="checkbox" value="yes" name="autoassign" id="autoassign" <?php if ($pconfig['autoassign']) echo "checked=\"checked\""; ?> />
		<span class="vexpl"> Auto assign interface so it can be configured with ip etc...</span>
		<br/>
		<input type="checkbox" value="yes" name="autoenable" id="autoenable" <?php if ($pconfig['autoenable']) echo "checked=\"checked\""; ?> />
		<span class="vexpl"> Auto enable interface so it can be used on filter rules.</span>
		<br/>
		<input type="checkbox" value="yes" name="autoadjustmtu" id="autoadjustmtu" <?php if ($pconfig['autoadjustmtu']) echo "checked=\"checked\""; ?> />
		<span class="vexpl"> Allows to keep clients mtu unchanged(1500). <br/>NOTE: if you are using jumbo frames this option is not needed and may produce incorrect results!</span>
<?php */ ?>
		<br/>
		<input name="autogroup" type="checkbox" value="yes" id="autogroup" <?php if ($pconfig['autogroup']) echo "checked=\"checked\""; ?> />
		<span class="vexpl"><?=gettext("Adds interface to QinQ interface groups so you can write filter rules easily.");?></span>
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
	<span class="vexpl">
		<?=gettext("You can specify ranges in the input below. The format is pretty simple i.e 9-100 or 10.20...");?>
	</span>
	<br/>
      <table id="maintable" summary="main table">
        <tbody>
          <tr>
            <td><div id="onecolumn"><?=gettext("Tag");?></div></td>
          </tr>

	<?php
	$counter = 0;
	$members = $pconfig['members'];
	if ($members <> "") {
		$item = explode(" ", $members);
		foreach($item as $ww) {
			$member = $item[$counter];
	?>
        <tr>
	<td class="vtable">
	        <input name="members<?php echo $counter; ?>" class="formselect" id="members<?php echo $counter; ?>" value="<?php echo $member;?>" />
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
		</td>
  </tr>
  <tr>
    <td width="22%" valign="top">&nbsp;</td>
    <td width="78%">
      <input id="submit" name="submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
      <a href="interfaces_qinq.php"><input id="cancelbutton" name="cancelbutton" type="button" class="formbtn" value="<?=gettext("Cancel");?>" /></a>
      <?php if (isset($id) && $a_qinqs[$id]): ?>
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

<?php include("fend.inc"); ?>
</body>
</html>
