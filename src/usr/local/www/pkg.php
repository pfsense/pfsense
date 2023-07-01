<?php
/*
 * pkg.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-package-settings
##|*NAME=Package: Settings
##|*DESCR=Allow access to the 'Package: Settings' page.
##|*MATCH=pkg.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("pkg-utils.inc");

function domTT_title($title_msg) {
	print "onmouseout=\"this.style.color = ''; domTT_mouseout(this, event);\" onmouseover=\"domTT_activate(this, event, 'content', '".gettext($title_msg)."', 'trail', true, 'delay', 0, 'fade', 'both', 'fadeMax', 93, 'styleClass', 'niceTitle');\"";
}

$xml = $_REQUEST['xml'];

if ($xml == "") {
	$pgtitle = array(gettext("Package"), gettext("Editor"));
	$pglinks = array("", "@self");
	include("head.inc");
	print_info_box(gettext("No valid package defined."), 'danger', false);
	include("foot.inc");
	exit;
} else {
	$pkg_xml_prefix = "/usr/local/pkg/";
	$pkg_full_path = "{$pkg_xml_prefix}/{$xml}";
	$pkg_realpath = realpath($pkg_full_path);
	if (empty($pkg_realpath)) {
		$path_error = sprintf(gettext("Package path %s not found."), htmlspecialchars($pkg_full_path));
	} else if (substr_compare($pkg_realpath, $pkg_xml_prefix, 0, strlen($pkg_xml_prefix))) {
		$path_error = sprintf(gettext("Invalid path %s specified."), htmlspecialchars($pkg_full_path));
	}

	if (!empty($path_error)) {
		include("head.inc");
		print_info_box($path_error . "<br />" . gettext("Try reinstalling the package."), 'danger', false);
		include("foot.inc");
		die;
	}

	if (file_exists($pkg_full_path)) {
		$pkg = parse_xml_config_pkg($pkg_full_path, "packagegui");
	} else {
		include("head.inc");
		print_info_box(sprintf(gettext("File not found %s."), htmlspecialchars($xml)), 'danger', false);
		include("foot.inc");
		exit;
	}
}

if ($pkg['donotsave'] != "") {
	header("Location: pkg_edit.php?xml=" . $xml);
	exit;
}

if ($pkg['include_file'] != "") {
	require_once($pkg['include_file']);
}

if ($_REQUEST['startdisplayingat']) {
	$startdisplayingat = $_REQUEST['startdisplayingat'];
}

if ($_REQUEST['display_maximum_rows']) {
	if ($_REQUEST['display_maximum_rows']) {
		$display_maximum_rows = $_REQUEST['display_maximum_rows'];
	}
}

$config_path = sprintf('installedpackages/%s/config', xml_safe_fieldname($pkg['name']));

$evaledvar = config_get_path($config_path, []);

if ($_POST['act'] == "update") {

	if (is_array($config['installedpackages'][$pkg['name']]) && $pkg['name'] != "" && $_POST['ids'] !="") {
		// get current values
		$current_values=config_get_path("installedpackages/{$pkg['name']}/config");
		// get updated ids
		parse_str($_POST['ids'], $update_list);
		// sort ids to know what to change
		// useful to do not lose data when using sorting and paging
		$sort_list=$update_list['ids'];
		sort($sort_list);
		// apply updates
		foreach ($update_list['ids'] as $key=> $value) {
			$config['installedpackages'][$pkg['name']]['config'][$sort_list[$key]]=$current_values[$update_list['ids'][$key]];
		}
		// save current config
		write_config(gettext("Package configuration changes saved from package settings page."));
		// sync package
		eval ("{$pkg['custom_php_resync_config_command']}");
	}
	// function called via jquery, no need to continue after save changes.
	exit;
}
if ($_REQUEST['act'] == "del") {
	// loop through our fieldnames and automatically setup the fieldnames
	// in the environment.	ie: a fieldname of username with a value of
	// testuser would automatically eval $username = "testuser";
	foreach ($evaledvar as $ip) {
		if ($pkg['adddeleteeditpagefields']['columnitem']) {
			foreach ($pkg['adddeleteeditpagefields']['columnitem'] as $column) {
				${xml_safe_fieldname($column['fielddescr'])} = $ip[xml_safe_fieldname($column['fieldname'])];
			}
		}
	}

	init_config_arr(array('installedpackages', xml_safe_fieldname($pkg['name']), 'config'));
	$a_pkg = &$config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config'];

	if ($a_pkg[$_REQUEST['id']]) {
		unset($a_pkg[$_REQUEST['id']]);
		write_config(gettext("Package configuration item deleted from package settings page."));
		if ($pkg['custom_delete_php_command'] != "") {
			if ($pkg['custom_php_command_before_form'] != "") {
				eval($pkg['custom_php_command_before_form']);
			}
			eval($pkg['custom_delete_php_command']);
		}
		header("Location:  pkg.php?xml=" . $xml);
		exit;
	}
}

ob_start();

$iflist = get_configured_interface_with_descr(true);
$evaledvar = config_get_path($config_path, []);

if ($pkg['custom_php_global_functions'] != "") {
	eval($pkg['custom_php_global_functions']);
}

if ($pkg['custom_php_command_before_form'] != "") {
	eval($pkg['custom_php_command_before_form']);
}

// Breadcrumb
if ($pkg['title'] != "") {
	/*if (!$only_edit) {						// Is any package still making use of this?? Is this something that is still wanted, considering the breadcrumb policy https://redmine.pfsense.org/issues/5527
 		$pkg['title'] = $pkg['title'] . '/Edit';		// If this needs to live on, then it has to be moved to run AFTER "foreach ($pkg['tabs']['tab'] as $tab)"-loop. This due to $pgtitle[] = $tab['text'];
	}*/
	if (strpos($pkg['title'], '/')) {
		$title = explode('/', $pkg['title']);

		foreach ($title as $subtitle) {
			$pgtitle[] = gettext($subtitle);
			$pglinks[] = "@self";
		}
	} else {
		$pgtitle = array(gettext("Package"), gettext($pkg['title']));
		$pglinks = array("", "@self");
	}
} else {
	$pgtitle = array(gettext("Package"), gettext("Editor"));
	$pglinks = array("", "@self");
}

if ($pkg['tabs'] != "") {
	$tab_array = array();
	foreach ($pkg['tabs']['tab'] as $tab) {
		if ($tab['tab_level']) {
			$tab_level = $tab['tab_level'];
		} else {
			$tab_level = 1;
		}
		if (isset($tab['active'])) {
			$active = true;
			$pgtitle[] = $tab['text'];
			$pglinks[] = "@self";
		} else {
			$active = false;
		}
		$urltmp = "";
		if ($tab['url'] != "") {
			$urltmp = $tab['url'];
		}
		if ($tab['xml'] != "") {
			$urltmp = "pkg_edit.php?xml=" . $tab['xml'];
		}

		$addresswithport = getenv("HTTP_HOST");
		$colonpos = strpos($addresswithport, ":");
		if ($colonpos !== False) {
			//my url is actually just the IP address of the pfsense box
			$myurl = substr($addresswithport, 0, $colonpos);
		} else {
			$myurl = $addresswithport;
		}
		// eval url so that above $myurl item can be processed if need be.
		$url = str_replace('$myurl', $myurl, $urltmp);

		$tab_array[$tab_level][] = array(
			$tab['text'],
			$active,
			$url
		);
	}

	ksort($tab_array);
}

if (!empty($pkg['tabs'])) {
	$shortcut_section = $pkg['shortcut_section'];
}

include("head.inc");
if (isset($tab_array)) {
	foreach ($tab_array as $tab) {
		display_top_tabs($tab);
	}
}

?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	function setFilter(filtertext) {
		$('#pkg_filter').val(filtertext);
		document.pkgform.submit();
	}

<?php
	if ($pkg['adddeleteeditpagefields']['movable']) {
?>
		$('#mainarea table tbody').sortable({
		items: 'tr.sortable',
			cursor: 'move',
			distance: 10,
			opacity: 0.8,
			helper: function(e, ui) {
				ui.children().each(function() {
					$(this).width($(this).width());
				});
			return ui;
			},
		});
<?php
	}
?>
});

function save_changes_to_xml(xml) {
	var ids = $('#mainarea table tbody').sortable('serialize', {key:"ids[]"});
	var strloading="<?=gettext('Saving changes...')?>";
	if (confirm("<?=gettext("Confirmation Required to save changes.")?>")) {
		$.ajax({
			type: 'post',
			cache: false,
			url: "<?=$_SERVER['SCRIPT_NAME']?>",
			data: {xml:'<?=$xml?>', act:'update', ids: ids},
			beforeSend: function() {
				$('#savemsg').empty().html(strloading);
			},
			error: function(data) {
				$('#savemsg').empty().html('Error:' + data);
			},
			success: function(data) {
				$('#savemsg').empty().html(data);
			}
		});
	}
}

//]]>
</script>

<?php
if ($_REQUEST['savemsg'] != "") {
	$savemsg = htmlspecialchars($_REQUEST['savemsg']);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}
?>

<form action="pkg.php" name="pkgform" method="get">
	<input type='hidden' name='xml' value='<?=$_REQUEST['xml']?>' />
		<div id="mainarea" class="panel panel-default">
			<table id="mainarea" class="table table-striped table-hover table-condensed">
				<thead>
<?php
	/* Handle filtering bar A-Z */
	$include_filtering_inputbox = false;
	$colspan = 0;
	if ($pkg['adddeleteeditpagefields']['columnitem'] != "") {
		foreach ($pkg['adddeleteeditpagefields']['columnitem'] as $column) {
			$colspan++;
		}
	}
	if ($pkg['fields']['field']) {
		// First find the sorting type field if it exists
		foreach ($pkg['fields']['field'] as $field) {
			if ($field['type'] == "sorting") {
				if (isset($field['include_filtering_inputbox'])) {
					$include_filtering_inputbox = true;
				}
				if ($display_maximum_rows < 1) {
					if ($field['display_maximum_rows']) {
						$display_maximum_rows = $field['display_maximum_rows'];
					}
				}
				echo "<tr><td colspan='$colspan' class='text-center'>";
				echo gettext("Filter by: ");
				$isfirst = true;
				for ($char = 65; $char < 91; $char++) {
					if (!$isfirst) {
						echo " | ";
					}
					echo "<a href=\"#\" onclick=\"setFilter('" . chr($char) . "');\">" . chr($char) . "</a>";
					$isfirst = false;
				}
				echo "</td></tr>";
				echo "<tr><td colspan='$colspan' class='text-center'>";
				if ($field['sortablefields']) {
					echo gettext("Filter field: ") . "<select name='pkg_filter_type'>";
					foreach ($field['sortablefields']['item'] as $si) {
						if ($si['name'] == $_REQUEST['pkg_filter_type']) {
							$SELECTED = "selected";
						} else {
							$SELECTED = "";
						}
						echo "<option value='{$si['name']}' {$SELECTED}>{$si['name']}</option>";
					}
					echo "</select>";
				}
				if ($include_filtering_inputbox) {
					echo '&nbsp;&nbsp;' . gettext("Filter text: ") . '<input id="pkg_filter" name="pkg_filter" value="' . htmlspecialchars($_REQUEST['pkg_filter']) . '" />';
					echo '&nbsp;<button type="submit" value="Filter" class="btn btn-primary btn-xs">';
					echo '<i class="fa fa-filter icon-embed-btn"></i>';
					echo gettext("Filter");
					echo "</button>";
				}
				echo "</td></tr><tr><td><font size='-3'>&nbsp;</font></td></tr>";
			}
		}
	}
?>
				<tr>

<?php
	if ($display_maximum_rows) {
		$totalpages = ceil(round((count($evaledvar) / $display_maximum_rows), 9));
		$page = 1;
		$tmpcount = 0;
		$tmppp = 0;
		if (is_array($evaledvar)) {
			foreach ($evaledvar as $ipa) {
				if ($tmpcount == $display_maximum_rows) {
					$page++;
					$tmpcount = 0;
				}
				if ($tmppp == $startdisplayingat) {
					break;
				}
				$tmpcount++;
				$tmppp++;
			}
		}
		echo "<tr><th colspan='" . count($pkg['adddeleteeditpagefields']['columnitem']) . "'>";
		echo "<table width='100%' summary=''>";
		echo "<tr>";
		echo "<td class='text-left'>" . sprintf(gettext('Displaying page %1$s of %2$s'), $page, $totalpages) . "</b></td>";
		echo "<td class='text-right'>" . gettext("Rows per page: ") . "<select onchange='document.pkgform.submit();' name='display_maximum_rows'>";
		for ($x = 0; $x < 250; $x++) {
			if ($x == $display_maximum_rows) {
				$SELECTED = "selected";
			} else {
				$SELECTED = "";
			}
			echo "<option value='$x' $SELECTED>$x</option>\n";
			$x = $x + 4;
		}
		echo "</select></td></tr>";
		echo "</table>";
		echo "</th></tr>";
	}

	$cols = 0;
	if ($pkg['adddeleteeditpagefields']['columnitem'] != "") {
		foreach ($pkg['adddeleteeditpagefields']['columnitem'] as $column) {
			echo "<th class=\"listhdrr\">" . $column['fielddescr'] . "</th>";
			$cols++;
		}
	}
?>
				</tr>
				</thead>
				<tbody>
<?php
	$i = 0;
	$pagination_counter = 0;
	if ($evaledvar && is_array($evaledvar)) {
		foreach ($evaledvar as $ip) {
			if ($startdisplayingat) {
				if ($i < $startdisplayingat) {
					$i++;
					continue;
				}
			}
			if ($_REQUEST['pkg_filter']) {
				// Handle filtered items
				if ($pkg['fields']['field'] && !$filter_regex) {
					// First find the sorting type field if it exists
					foreach ($pkg['fields']['field'] as $field) {
						if ($field['type'] == "sorting") {
							if ($field['sortablefields']['item']) {
								foreach ($field['sortablefields']['item'] as $sf) {
									if ($sf['name'] == $_REQUEST['pkg_filter_type']) {
										$filter_fieldname = $sf['fieldname'];
										#Use a default regex on sortable fields when none is declared
										$pkg_filter = cleanup_regex_pattern(htmlspecialchars(strip_tags($_REQUEST['pkg_filter'])));
										if ($sf['regex']) {
											$filter_regex = str_replace("%FILTERTEXT%", $pkg_filter, trim($sf['regex']));
										} else {
											$filter_regex = "/{$pkg_filter}/i";
										}
									}
								}
							}
						}
					}
				}
				// Do we have something to filter on?
				unset($filter_matches);
				if ($pkg['adddeleteeditpagefields']['columnitem'] != "") {
					foreach ($pkg['adddeleteeditpagefields']['columnitem'] as $column) {
						$fieldname = $ip[xml_safe_fieldname($column['fieldname'])];
						if ($column['fieldname'] == $filter_fieldname) {
							if ($filter_regex) {
								preg_match($filter_regex, $fieldname, $filter_matches);
								break;
							}
						}
					}
				}
				if (!$filter_matches) {
					$i++;
					continue;
				}
			}
			if ($pkg['adddeleteeditpagefields']['movable']) {
				echo "<tr style=\"vertical-align: top\" class=\"sortable\" id=\"id_{$i}\">\n";
			} else {
				echo "<tr style=\"vertical-align: top\">\n";
			}
			if ($pkg['adddeleteeditpagefields']['columnitem'] != "") {
				foreach ($pkg['adddeleteeditpagefields']['columnitem'] as $column) {
					if ($column['fieldname'] == "description") {
						$class = "listbg";
					} else {
						$class = "listlr";
					}
?>
					<td class="<?=$class?>" ondblclick="document.location='pkg_edit.php?xml=<?=$xml?>&amp;act=edit&amp;id=<?=$i?>';">
<?php
					$fieldname = $ip[xml_safe_fieldname($column['fieldname'])];
					#Check if columnitem has a type field declared
					if ($column['type'] == "checkbox") {
						if ($fieldname == "") {
							echo gettext("No");
						} else {
							echo gettext("Yes");
						}
					} else if ($column['type'] == "interface") {
						echo $column['prefix'] . $iflist[$fieldname] . $column['suffix'];
					} else {
						$display_text = "";
						#Check if columnitem has an encoding field declared
						if ($column['encoding'] == "base64") {
							$display_text = $column['prefix'] . base64_decode($fieldname) . $column['suffix'];
						#Check if there is a custom info to show when $fieldname is not empty
						} else if ($column['listmodeon'] && $fieldname != "") {
							$display_text = $column['prefix'] . gettext($column['listmodeon']). $column['suffix'];
						#Check if there is a custom info to show when $fieldname is empty
						} else if ($column['listmodeoff'] && $fieldname == "") {
							$display_text = $column['prefix'] .gettext($column['listmodeoff']). $column['suffix'];
						} else {
							$display_text = $column['prefix'] . $fieldname ." ". $column['suffix'];
						}
						if (!isset($column['allow_html'])) {
							$display_text = htmlspecialchars($display_text);
						}
						echo $display_text;
					}
?>
					</td>
<?php
				} // foreach columnitem
			} // if columnitem
?>
					<td style="vertical-align: middle" class="list text-nowrap">
						<table border="0" cellspacing="0" cellpadding="1" summary="icons">
							<tr>
<?php
			#Show custom description to edit button if defined
			$edit_msg=($pkg['adddeleteeditpagefields']['edittext']?$pkg['adddeleteeditpagefields']['edittext']:gettext("Edit this item"));
?>
								<td><a class="fa fa-pencil" href="pkg_edit.php?xml=<?=$xml?>&amp;act=edit&amp;id=<?=$i?>" title="<?=$edit_msg?>"></a></td>
<?php
			#Show custom description to delete button if defined
			$delete_msg=($pkg['adddeleteeditpagefields']['deletetext']?$pkg['adddeleteeditpagefields']['deletetext']:gettext("Delete this item"));
?>
								<td>&nbsp;<a class="fa fa-trash" href="pkg.php?xml=<?=$xml?>&amp;act=del&amp;id=<?=$i?>" title="<?=$delete_msg?>"></a></td>
							</tr>
						</tbody>
					</table>
				</td>
<?php
			echo "</tr>\n"; // Pairs with an echo tr some way above
			// Handle pagination and display_maximum_rows
			if ($display_maximum_rows) {
				if ($pagination_counter == ($display_maximum_rows-1) or
					$i == (count($evaledvar)-1)) {
					$colcount = count($pkg['adddeleteeditpagefields']['columnitem']);
					$final_footer = "";
					$final_footer .= "<tr><td colspan='$colcount'>";
					$final_footer .= "<table width='100%' summary=''><tr>";
					$final_footer .= "<td class='text-left'>";
					$startingat = $startdisplayingat - $display_maximum_rows;
					if ($startingat > -1) {
						$final_footer .= "<a href='pkg.php?xml=" . $_REQUEST['xml'] . "&amp;startdisplayingat={$startingat}&amp;display_maximum_rows={$display_maximum_rows}'>";
					} else if ($startdisplayingat > 1) {
						$final_footer .= "<a href='pkg.php?xml=" . $_REQUEST['xml'] . "&amp;startdisplayingat=0&amp;display_maximum_rows={$display_maximum_rows}'>";
					}
					$final_footer .= "<font size='2'><< " . gettext("Previous page") . "</font></a>";
					if ($tmppp + $display_maximum_rows > count($evaledvar)) {
						$endingrecord = count($evaledvar);
					} else {
						$endingrecord = $tmppp + $display_maximum_rows;
					}
					$final_footer .= "</td><td class='text-center'>";
					$tmppp++;
					$final_footer .= "<font size='2'>Displaying {$tmppp} - {$endingrecord} / " . count($evaledvar) . " records";
					$final_footer .= "</font></td><td class='text-right'>&nbsp;";
					if (($i+1) < count($evaledvar)) {
						$final_footer .= "<a href='pkg.php?xml=" . $_REQUEST['xml'] . "&amp;startdisplayingat=" . ($startdisplayingat + $display_maximum_rows) . "&amp;display_maximum_rows={$display_maximum_rows}'>";
					}
					$final_footer .= "<font size='2'>" . gettext("Next page") . " >></font></a>";
					$final_footer .= "</td></tr></table></td></tr>";
					$i = count($evaledvar);
					break;
				}
			}
			$i++;
			$pagination_counter++;
		} // foreach evaledvar
	} // if evaledvar
?>
				<tr>
					<td colspan="<?=$cols?>"></td>
					<td>
						<table border="0" cellspacing="0" cellpadding="1" summary="icons">
							<tr>
<?php
	#Show custom description to add button if defined
	$add_msg=($pkg['adddeleteeditpagefields']['addtext']?$pkg['adddeleteeditpagefields']['addtext']:gettext("Add a new item"));
?>
								<td><a href="pkg_edit.php?xml=<?=$xml?>&amp;id=<?=$i?>" class="btn btn-sm btn-success" title="<?=$add_msg?>"><i class="fa fa-plus icon-embed-btn"></i><?=gettext('Add')?></a></td>
<?php
	#Show description button and info if defined
	if ($pkg['adddeleteeditpagefields']['description']) {
?>
								<td>
									<i class="fa fa-info-circle"><?=$pkg['adddeleteeditpagefields']['description']?></i>
								</td>
<?php
	}
?>
							</tr>
						</table>
					</td>
				</tr>
				<?=$final_footer?>
			</table>
			</div>
		<button class="btn btn-primary" type="button" value="Save" name="Submit" onclick="save_changes_to_xml('<?=$xml?>')"><i class="fa fa-save icon-embed-btn"></i><?=gettext("Save")?></button>

</form>
<?php
include("foot.inc"); ?>
