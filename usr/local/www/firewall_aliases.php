<?php
/* $Id$ */
/*
	firewall_aliases.php
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
/*
	pfSense_MODULE:	aliases
*/

##|+PRIV
##|*IDENT=page-firewall-aliases
##|*NAME=Firewall: Aliases page
##|*DESCR=Allow access to the 'Firewall: Aliases' page.
##|*MATCH=firewall_aliases.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

if (!is_array($config['aliases']['alias']))
	$config['aliases']['alias'] = array();
$a_aliases = &$config['aliases']['alias'];

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;

		/* reload all components that use aliases */
		$retval = filter_configure();

		if(stristr($retval, "error") <> true)
		    $savemsg = get_std_save_message($retval);
		else
		    $savemsg = $retval;
		if ($retval == 0)
			clear_subsystem_dirty('aliases');
	}
}

if ($_GET['act'] == "del") {
	if ($a_aliases[$_GET['id']]) {
		/* make sure rule is not being referenced by any nat or filter rules */
		$is_alias_referenced = false;
		$referenced_by = false;
		$alias_name = $a_aliases[$_GET['id']]['name'];
		if(is_array($config['nat']['rule'])) {
			foreach($config['nat']['rule'] as $rule) {
				if($rule['localip'] == $alias_name) {
					$is_alias_referenced = true;
					$referenced_by = $rule['descr'];
					break;
				}
			}
		}
		if($is_alias_referenced == false) {
			if(is_array($config['filter']['rule'])) {
				foreach($config['filter']['rule'] as $rule) {
					if($rule['source']['address'] == $alias_name) {
						$is_alias_referenced = true;
						$referenced_by = $rule['descr'];
						break;
					}
					if($rule['source']['address'] == $alias_name) {
						$is_alias_referenced = true;
						$referenced_by = $rule['descr'];
						break;
					}
					if($rule['source']['port'] == $alias_name) {
						$is_alias_referenced = true;
						$referenced_by = $rule['descr'];
						break;
					}
					if($rule['destination']['port'] == $alias_name) {
						$is_alias_referenced = true;
						$referenced_by = $rule['descr'];
						break;
					}
				}
			}
		}
		if($is_alias_referenced == false) {
			if(is_array($config['nat']['rule'])) {
				foreach($config['nat']['rule'] as $rule) {
					if($rule['source']['address'] == $alias_name) {
						$is_alias_referenced = true;
						$referenced_by = $rule['descr'];
						break;
					}
					if($rule['source']['port'] == $alias_name) {
						$is_alias_referenced = true;
						$referenced_by = $rule['descr'];
						break;
					}
					if($rule['destination']['address'] == $alias_name) {
						$is_alias_referenced = true;
						$referenced_by = $rule['descr'];
						break;
					}
					if($rule['destination']['port'] == $alias_name) {
						$is_alias_referenced = true;
						$referenced_by = $rule['descr'];
						break;
					}
					if($rule['target'] == $alias_name) {
						$is_alias_referenced = true;
						$referenced_by = $rule['descr'];
						break;
					}
					if($rule['local-port'] == $alias_name) {
						$is_alias_referenced = true;
						$referenced_by = $rule['descr'];
						break;
					}
				}
			}
		}
		if($is_alias_referenced == true) {
			$savemsg = "Cannot delete rule.  Currently in use by {$referenced_by}";
		} else {
			unset($a_aliases[$_GET['id']]);
			write_config();
			filter_configure();
			mark_subsystem_dirty('aliases');
			header("Location: firewall_aliases.php");
			exit;
		}
	}
}

$pgtitle = array("Firewall","Aliases");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="firewall_aliases.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('aliases')): ?><p>
<?php print_info_box_np("The alias list has been changed.<br>You must apply the changes in order for them to take effect.");?>
<?php endif; ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
  <td width="25%" class="listhdrr">Name</td>
  <td width="25%" class="listhdrr">Values</td>
  <td width="25%" class="listhdr">Description</td>
  <td width="10%" class="list">
    <table border="0" cellspacing="0" cellpadding="1">
      <tr>
	<td valign="middle" width="17">&nbsp;</td>
        <td valign="middle"><a href="firewall_aliases_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="add a new alias"></a></td>
      </tr>
    </table>
  </td>
</tr>
	  <?php $i = 0; foreach ($a_aliases as $alias): ?>
<tr>
  <td class="listlr" ondblclick="document.location='firewall_aliases_edit.php?id=<?=$i;?>';">
    <?=htmlspecialchars($alias['name']);?>
  </td>
  <td class="listr" ondblclick="document.location='firewall_aliases_edit.php?id=<?=$i;?>';">
      <?php
	$tmpaddr = explode(" ", $alias['address']);
	$addresses = implode(", ", array_slice($tmpaddr, 0, 10));
	echo $addresses;
	if(count($tmpaddr) > 10) {
		echo "...";
	}
    ?>
  </td>
  <td class="listbg" ondblclick="document.location='firewall_aliases_edit.php?id=<?=$i;?>';">
    <?=htmlspecialchars($alias['descr']);?>&nbsp;
  </td>
  <td valign="middle" nowrap class="list">
    <table border="0" cellspacing="0" cellpadding="1">
      <tr>
        <td valign="middle"><a href="firewall_aliases_edit.php?id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" title="edit alias"></a></td>
        <td><a href="firewall_aliases.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this alias? All elements that still use it will become invalid (e.g. filter rules)!')"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="delete alias"></a></td>
      </tr>
    </table>
  </td>
</tr>
	  <?php $i++; endforeach; ?>
<tr>
  <td class="list" colspan="3"></td>
  <td class="list">
    <table border="0" cellspacing="0" cellpadding="1">
      <tr>
	<td valign="middle" width="17">&nbsp;</td>
        <td valign="middle">
          <a href="firewall_aliases_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="add a new alias"></a></td>
        </td>
	      <td valign="middle">
           <a href="firewall_aliases_import.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_import_alias.gif" width="17" height="17" border="0" title="<?=gettext("Bulk import aliases from list");?>" alt="" /></a>
        </td>
      </tr>
    </table>
  </td>
</tr>
<tr>
  <td class="tabcont" colspan="3">
   <p><span class="vexpl"><span class="red"><strong>Note:<br></strong></span>Aliases act as placeholders for real hosts, networks or ports. They can be used to minimize the number of changes that have to be made if a host, network or port changes. You can enter the name of an alias instead of the host, network or port in all fields that have a red background. The alias will be resolved according to the list above. If an alias cannot be resolved (e.g. because you deleted it), the corresponding element (e.g. filter/NAT/shaper rule) will be considered invalid and skipped.</span></p>
  </td>
</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
