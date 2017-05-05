<?php
/*
 * firewall_aliases.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-firewall-aliases
##|*NAME=Firewall: Aliases
##|*DESCR=Allow access to the 'Firewall: Aliases' page.
##|*MATCH=firewall_aliases.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

if (!is_array($config['aliases']['alias'])) {
	$config['aliases']['alias'] = array();
}
$a_aliases = &$config['aliases']['alias'];

$tab = ($_REQUEST['tab'] == "" ? "ip" : preg_replace("/\W/", "", $_REQUEST['tab']));

if ($_POST['apply']) {
	$retval = 0;

	/* reload all components that use aliases */
	$retval |= filter_configure();

	if ($retval == 0) {
		clear_subsystem_dirty('aliases');
	}
}


if ($_POST['act'] == "del") {
	if ($a_aliases[$_POST['id']]) {
		/* make sure rule is not being referenced by any nat or filter rules */
		$is_alias_referenced = false;
		$referenced_by = false;
		$alias_name = $a_aliases[$_POST['id']]['name'];
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
			$delete_error = sprintf(gettext("Cannot delete alias. Currently in use by %s."), htmlspecialchars($referenced_by));
		} else {
			if (preg_match("/urltable/i", $a_aliases[$_POST['id']]['type'])) {
				// this is a URL table type alias, delete its file as well
				unlink_if_exists("/var/db/aliastables/" . $a_aliases[$_POST['id']]['name'] . ".txt");
			}
			unset($a_aliases[$_POST['id']]);
			if (write_config(gettext("Deleted a firewall alias."))) {
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
$pglinks = array("", "firewall_aliases.php", "@self");
$shortcut_section = "aliases";

include("head.inc");

if ($delete_error) {
	print_info_box($delete_error, 'danger');
}
if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('aliases')) {
	print_apply_box(gettext("The alias list has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}

display_top_tabs($tab_array);

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=sprintf(gettext('Firewall Aliases %s'), $bctab)?></h2></div>
	<div class="panel-body">

<div class="table-responsive">
<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
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
				<a class="fa fa-trash"	title="<?=gettext("Delete alias")?>" href="?act=del&amp;tab=<?=$tab?>&amp;id=<?=$i?>" usepost></a>
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
<?php
if (($tab == "ip") || ($tab == "port") || ($tab == "all")):
?>
	<a href="firewall_aliases_import.php?tab=<?=$tab?>" role="button" class="btn btn-primary btn-sm">
		<i class="fa fa-upload icon-embed-btn"></i>
		<?=gettext("Import");?>
	</a>
<?php
endif
?>
</nav>

<!-- Information section. Icon ID must be "showinfo" and the information <div> ID must be "infoblock".
	 That way jQuery (in pfenseHelpers.js) will automatically take care of the display. -->
<div>
	<div class="infoblock">
		<?php print_info_box(gettext('Aliases act as placeholders for real hosts, networks or ports. They can be used to minimize the number ' .
			'of changes that have to be made if a host, network or port changes.') . '<br />' .
			gettext('The name of an alias can be entered instead of the host, network or port where indicated. The alias will be resolved according to the list above.') . '<br />' .
			gettext('If an alias cannot be resolved (e.g. because it was deleted), the corresponding element (e.g. filter/NAT/shaper rule) will be considered invalid and skipped.'), 'info', false); ?>
	</div>
</div>

<?php
include("foot.inc");
