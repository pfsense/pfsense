<?php
/*
	firewall_aliases.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-firewall-aliases
##|*NAME=Firewall: Aliases
##|*DESCR=Allow access to the 'Firewall: Aliases' page.
##|*MATCH=firewall_aliases.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

if (!is_array($config['aliases']['alias'])) {
	$config['aliases']['alias'] = array();
}
$a_aliases = &$config['aliases']['alias'];

$tab = ($_REQUEST['tab'] == "" ? "ip" : preg_replace("/\W/", "", $_REQUEST['tab']));

if ($_POST) {

	if ($_POST['apply']) {
		$retval = 0;

		/* reload all components that use aliases */
		$retval = filter_configure();

		if (stristr($retval, "error") <> true) {
			$savemsg = get_std_save_message($retval);
		} else {
			$savemsg = $retval;
		}
		if ($retval == 0) {
			clear_subsystem_dirty('aliases');
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_aliases[$_GET['id']]) {
		/* make sure rule is not being referenced by any nat or filter rules */
		$is_alias_referenced = false;
		$referenced_by = false;
		$alias_name = $a_aliases[$_GET['id']]['name'];
		// Firewall rules
		find_alias_reference(array('filter', 'rule'), array('source', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('filter', 'rule'), array('destination', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('filter', 'rule'), array('source', 'port'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('filter', 'rule'), array('destination', 'port'), $alias_name, $is_alias_referenced, $referenced_by);
		// NAT Rules
		find_alias_reference(array('nat', 'rule'), array('source', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'rule'), array('source', 'port'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'rule'), array('destination', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'rule'), array('destination', 'port'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'rule'), array('target'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'rule'), array('local-port'), $alias_name, $is_alias_referenced, $referenced_by);
		// NAT 1:1 Rules
		//find_alias_reference(array('nat', 'onetoone'), array('external'), $alias_name, $is_alias_referenced, $referenced_by);
		//find_alias_reference(array('nat', 'onetoone'), array('source', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'onetoone'), array('destination', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
		// NAT Outbound Rules
		find_alias_reference(array('nat', 'outbound', 'rule'), array('source', 'network'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'outbound', 'rule'), array('sourceport'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'outbound', 'rule'), array('destination', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'outbound', 'rule'), array('dstport'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'outbound', 'rule'), array('target'), $alias_name, $is_alias_referenced, $referenced_by);
		// Alias in an alias
		find_alias_reference(array('aliases', 'alias'), array('address'), $alias_name, $is_alias_referenced, $referenced_by);
		// Load Balancer
		find_alias_reference(array('load_balancer', 'lbpool'), array('port'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('load_balancer', 'virtual_server'), array('port'), $alias_name, $is_alias_referenced, $referenced_by);
		// Static routes
		find_alias_reference(array('staticroutes', 'route'), array('network'), $alias_name, $is_alias_referenced, $referenced_by);
		if ($is_alias_referenced == true) {
			$savemsg = sprintf(gettext("Cannot delete alias. Currently in use by %s"), htmlspecialchars($referenced_by));
		} else {
			unset($a_aliases[$_GET['id']]);
			if (write_config()) {
				filter_configure();
				mark_subsystem_dirty('aliases');
			}
			header("Location: firewall_aliases.php?tab=" . $tab);
			exit;
		}
	}
}

function find_alias_reference($section, $field, $origname, &$is_alias_referenced, &$referenced_by) {
	global $config;
	if (!$origname || $is_alias_referenced) {
		return;
	}

	$sectionref = &$config;
	foreach ($section as $sectionname) {
		if (is_array($sectionref) && isset($sectionref[$sectionname])) {
			$sectionref = &$sectionref[$sectionname];
		} else {
			return;
		}
	}

	if (is_array($sectionref)) {
		foreach ($sectionref as $itemkey => $item) {
			$fieldfound = true;
			$fieldref = &$sectionref[$itemkey];
			foreach ($field as $fieldname) {
				if (is_array($fieldref) && isset($fieldref[$fieldname])) {
					$fieldref = &$fieldref[$fieldname];
				} else {
					$fieldfound = false;
					break;
				}
			}
			if ($fieldfound && $fieldref == $origname) {
				$is_alias_referenced = true;
				if (is_array($item)) {
					$referenced_by = $item['descr'];
				}
				break;
			}
		}
	}
}

$tab_array = array();
$tab_array[] = array(gettext("IP"),    ($tab == "ip" ? true : ($tab == "host" ? true : ($tab == "network" ? true : false))), "/firewall_aliases.php?tab=ip");
$tab_array[] = array(gettext("Ports"), ($tab == "port"? true : false), "/firewall_aliases.php?tab=port");
$tab_array[] = array(gettext("URLs"),  ($tab == "url"? true : false), "/firewall_aliases.php?tab=url");
$tab_array[] = array(gettext("All"),   ($tab == "all"? true : false), "/firewall_aliases.php?tab=all");

foreach ($tab_array as $dtab) {
	if ($dtab[1] == true) {
		$bctab = $dtab[0];
		break;
	}
}

$pgtitle = array(gettext("Firewall"), gettext("Aliases"), $bctab);
$shortcut_section = "aliases";

include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('aliases')) {
	print_info_box_np(gettext("The alias list has been changed.") . "<br />" . gettext("You must apply the changes in order for them to take effect."));
}

display_top_tabs($tab_array);

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Firewall Aliases') . " " . $bctab?></h2></div>
	<div class="panel-body">

<div class="table-responsive">
<table class="table table-striped table-hover">
	<thead>
		<tr>
			<th><?=gettext("Name")?></th>
			<th><?=gettext("Values")?></th>
			<th><?=gettext("Description")?></th>
			<th><?=gettext("Actions")?></th>
		</tr>
	</thead>
	<tbody>
<?php
	asort($a_aliases);
	foreach ($a_aliases as $i => $alias):
		unset ($show_alias);
		switch ($tab) {
		case "all":
			$show_alias= true;
			break;
		case "ip":
		case "host":
		case "network":
			if (preg_match("/(host|network)/", $alias["type"])) {
				$show_alias= true;
			}
			break;
		case "url":
			if (preg_match("/(url)/i", $alias["type"])) {
				$show_alias= true;
			}
			break;
		case "port":
			if ($alias["type"] == "port") {
				$show_alias= true;
			}
			break;
		}
		if ($show_alias):
?>
		<tr>
			<td ondblclick="document.location='firewall_aliases_edit.php?id=<?=$i;?>';">
				<?=htmlspecialchars($alias['name'])?>
			</td>
			<td ondblclick="document.location='firewall_aliases_edit.php?id=<?=$i;?>';">
<?php
	if ($alias["url"]) {
		echo $alias["url"] . "<br />";
	} else {
		if (is_array($alias["aliasurl"])) {
			$aliasurls = implode(", ", array_slice($alias["aliasurl"], 0, 10));
			echo $aliasurls;
			if (count($aliasurls) > 10) {
				echo "&hellip;<br />";
			}
			echo "<br />\n";
		}
		$tmpaddr = explode(" ", $alias['address']);
		$addresses = implode(", ", array_slice($tmpaddr, 0, 10));
		echo $addresses;
		if (count($tmpaddr) > 10) {
			echo '&hellip;';
		}
	}
?>
			</td>
			<td ondblclick="document.location='firewall_aliases_edit.php?id=<?=$i;?>';">
				<?=htmlspecialchars($alias['descr'])?>&nbsp;
			</td>
			<td>
				<a class="fa fa-pencil" title="<?=gettext("Edit alias"); ?>" href="firewall_aliases_edit.php?id=<?=$i?>"></a>
				<a class="fa fa-trash"	title="<?=gettext("Delete alias")?>" href="?act=del&amp;tab=<?=$tab?>&amp;id=<?=$i?>"></a>
			</td>
		</tr>
<?php endif?>
<?php endforeach?>
	</tbody>
</table>
</div>

	</div>
</div>

<nav class="action-buttons">
	<a href="firewall_aliases_edit.php?tab=<?=$tab?>" role="button" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add");?>
	</a>
	<a href="firewall_aliases_import.php" role="button" class="btn btn-default btn-sm">
		<i class="fa fa-download icon-embed-btn"></i>
		<?=gettext("Import");?>
	</a>
</nav>

<!-- Information section. Icon ID must be "showinfo" and the information <div> ID must be "infoblock".
	 That way jQuery (in pfenseHelpers.js) will automatically take care of the display. -->
<div>
	<div class="infoblock">
		<?=print_info_box(gettext('Aliases act as placeholders for real hosts, networks or ports. They can be used to minimize the number ' .
			'of changes that have to be made if a host, network or port changes. <br />' .
			'You can enter the name of an alias instead of the host, network or port where indicated. The alias will be resolved according to the list above.' . '<br />' .
			'If an alias cannot be resolved (e.g. because you deleted it), the corresponding element (e.g. filter/NAT/shaper rule) will be considered invalid and skipped.'), 'info')?>
	</div>
</div>

<?php
include("foot.inc");
