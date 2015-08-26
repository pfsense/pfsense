<?php
/* $Id$ */
/*
	firewall_aliases.php
	Copyright (C) 2004 Scott Ullrich
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
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
		find_alias_reference(array('load_balancer', 'lbpool'),		 array('port'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('load_balancer', 'virtual_server'), array('port'), $alias_name, $is_alias_referenced, $referenced_by);
		// Static routes
		find_alias_reference(array('staticroutes', 'route'), array('network'), $alias_name, $is_alias_referenced, $referenced_by);
		if ($is_alias_referenced == true) {
			$savemsg = sprintf(gettext("Cannot delete alias. Currently in use by %s"), $referenced_by);
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

$pgtitle = array(gettext("Firewall"), gettext("Aliases"));
$shortcut_section = "aliases";

include("head.inc");

if ($savemsg)
	print_info_box($savemsg);
if (is_subsystem_dirty('aliases'))
	print_info_box_np(gettext("The alias list has been changed.") . "<br />" . gettext("You must apply the changes in order for them to take effect."));

$tab_array = array();
$tab_array[] = array(gettext("IP"),($tab=="ip" ? true : ($tab=="host" ? true : ($tab == "network" ? true : false))), "/firewall_aliases.php?tab=ip");
$tab_array[] = array(gettext("Ports"), ($tab=="port"? true : false), "/firewall_aliases.php?tab=port");
$tab_array[] = array(gettext("URLs"), ($tab=="url"? true : false), "/firewall_aliases.php?tab=url");
$tab_array[] = array(gettext("All"), ($tab=="all"? true : false), "/firewall_aliases.php?tab=all");
display_top_tabs($tab_array);

?>
<div class="table-responsive">
<table class="table table-striped table-hover">
	<thead>
		<tr>
			<th><?=gettext("Name")?></th>
			<th><?=gettext("Values")?></th>
			<th><?=gettext("Description")?></th>
		</tr>
	</thead>
	<tbody>
<?php
	asort($a_aliases);
	foreach ($a_aliases as $i=> $alias):
		unset ($show_alias);
		switch ($tab){
		case "all":
			$show_alias= true;
			break;
		case "ip":
		case "host":
		case "network":
			if (preg_match("/(host|network)/",$alias["type"]))
				$show_alias= true;
			break;
		case "url":
			if (preg_match("/(url)/i",$alias["type"]))
				$show_alias= true;
			break;
		case "port":
			if($alias["type"] == "port")
				$show_alias= true;
			break;
		}
		if ($show_alias):
?>
		<tr>
			<td>
				<?=htmlspecialchars($alias['name'])?>
			</td>
			<td>
<?php
	if ($alias["url"]) {
		echo $alias["url"] . "<br />";
	} else {
		if(is_array($alias["aliasurl"])) {
			$aliasurls = implode(", ", array_slice($alias["aliasurl"], 0, 10));
			echo $aliasurls;
			if(count($aliasurls) > 10) {
				echo "&hellip;<br />";
			}
			echo "<br />\n";
		}
		$tmpaddr = explode(" ", $alias['address']);
		$addresses = implode(", ", array_slice($tmpaddr, 0, 10));
		echo $addresses;
		if(count($tmpaddr) > 10) {
			echo '&hellip;';
		}
	}
?>
			</td>
			<td>
				<?=htmlspecialchars($alias['descr'])?>&nbsp;
			</td>
			<td>
				<a href="firewall_aliases_edit.php?id=<?=$i?>" class="btn btn-xs btn-primary">edit</a>
				<a href="?act=del&amp;tab=<?=$tab?>&amp;id=<?=$i?>" class="btn btn-xs btn-danger">delete</a>
				</td>
			</tr>
<?php endif?>
<?php endforeach?>
	</tbody>
</table>
</div>

<nav class="action-buttons">
	<a href="firewall_aliases_edit.php?tab=<?=$tab?>" role="button" class="btn btn-success">
		<?=gettext("add new alias");?>
	</a>
	<a href="firewall_aliases_import.php" role="button" class="btn btn-default">
		<?=gettext("bulk import");?>
	</a>
</nav>

<br/><br/>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Note:")?></h2></div>
	<div class="panel-body">
		<?=gettext("Aliases act as placeholders for real hosts, networks or ports. They can be used to minimize the number of changes that have to be made if a host, network or port changes. You can enter the name of an alias instead of the host, network or port in all fields that have a red background. The alias will be resolved according to the list above. If an alias cannot be resolved (e.g. because you deleted it), the corresponding element (e.g. filter/NAT/shaper rule) will be considered invalid and skipped.")?>
	</div>
</div>

<?php include("foot.inc");