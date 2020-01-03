<?php
/*
 * pkg_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-package-edit
##|*NAME=Package: Edit
##|*DESCR=Allow access to the 'Package: Edit' page.
##|*MATCH=pkg_edit.php*
##|-PRIV

ini_set('max_execution_time', '0');

require_once("filter.inc");
require_once("functions.inc");
require_once("guiconfig.inc");
require_once("shaper.inc");
require_once("pkg-utils.inc");
require_once("pfsense-utils.inc");
require_once("util.inc");

$xml = htmlspecialchars($_REQUEST['xml']);

$xml_fullpath = realpath('/usr/local/pkg/' . $xml);

if ($xml == "" || $xml_fullpath === false || substr($xml_fullpath, 0, strlen('/usr/local/pkg/')) != '/usr/local/pkg/') {
	$pgtitle = array(gettext("Package"), gettext("Editor"));
	$pglinks = array("", "@self");
	include("head.inc");
	print_info_box(gettext("No valid package defined."), 'danger', false);
	include("foot.inc");
	die;
} else {
	$pkg = parse_xml_config_pkg($xml_fullpath, "packagegui");
}

if ($pkg['include_file'] != "") {
	require_once($pkg['include_file']);
}

if (!isset($pkg['adddeleteeditpagefields'])) {
	$only_edit = true;
} else {
	$only_edit = false;
}

$id = $_REQUEST['id'];
if (isset($_POST['id'])) {
	$id = htmlspecialchars($_POST['id']);
}

// Not posting?	 Then user is editing a record. There must be a valid id
// when editing a record.
if (!$id && !$_POST) {
	$id = "0";
}

if (!is_numeric($id)) {
	header("Location: /");
	exit;
}

if ($pkg['custom_php_global_functions'] != "") {
	eval($pkg['custom_php_global_functions']);
}

// grab the installedpackages->package_name section.
if ($config['installedpackages'] && !is_array($config['installedpackages'][xml_safe_fieldname($pkg['name'])])) {
	$config['installedpackages'][xml_safe_fieldname($pkg['name'])] = array();
}

if ($config['installedpackages'] && !is_array($config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config'])) {
	$config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config'] = array();
}

/* If the first entry in the array is an empty <config/> tag, kill it.
 * See the following tickets for more:
 *  https://redmine.pfsense.org/issues/7624
 *  https://redmine.pfsense.org/issues/476
 */

init_config_arr(array('installedpackages', xml_safe_fieldname($pkg['name']), 'config'));
if ((count($config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config']) > 0) &&
    (empty($config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config'][0])) &&
    is_array($config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config'])) {
	array_shift($config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config']);
}

$a_pkg = &$config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config'];

if ($_REQUEST['savemsg'] != "") {
	$savemsg = htmlspecialchars($_REQUEST['savemsg']);
}

if ($pkg['custom_php_command_before_form'] != "") {
	eval($pkg['custom_php_command_before_form']);
}

if ($_POST) {
	$rows = 0;

	$input_errors = array();
	$reqfields = array();
	$reqfieldsn = array();
	foreach ($pkg['fields']['field'] as $field) {
		if (isset($field['required'])) {
			if ($field['fieldname']) {
				$reqfields[] = $field['fieldname'];
			}
			if ($field['fielddescr']) {
				$reqfieldsn[] = $field['fielddescr'];
			}
		}
	}
	do_input_validation($_POST, $reqfields, $reqfieldsn, $input_errors);

	if ($pkg['custom_php_validation_command']) {
		eval($pkg['custom_php_validation_command']);
	}

	if ($_POST['act'] == "del") {
		if ($pkg['custom_delete_php_command']) {
			if ($pkg['custom_php_command_before_form'] != "") {
				eval($pkg['custom_php_command_before_form']);
			}
			eval($pkg['custom_delete_php_command']);
		}
		write_config($pkg['delete_string']);
		// resync the configuration file code if defined.
		if ($pkg['custom_php_resync_config_command'] != "") {
			if ($pkg['custom_php_command_before_form'] != "") {
				eval($pkg['custom_php_command_before_form']);
			}
			eval($pkg['custom_php_resync_config_command']);
		}
	} else {
		if (!$input_errors && $pkg['custom_add_php_command']) {
			if ($pkg['donotsave'] != "" or $pkg['preoutput'] != "") {
				include("head.inc");
			}

			if ($pkg['preoutput']) {
				echo "<pre>";
			}
			eval($pkg['custom_add_php_command']);
			if ($pkg['preoutput']) {
				echo "</pre>";
			}
		}
	}

	if (empty($pkg['donotsave'])) {

		// store values in xml configuration file.
		if (!$input_errors) {
			$pkgarr = array();
			foreach ($pkg['fields']['field'] as $fields) {
				switch ($fields['type']) {
					case "rowhelper":
						// save rowhelper items.
						#$rowhelpername=($fields['fieldname'] ? $fields['fieldname'] : "row");
						$rowhelpername="row";
						foreach ($fields['rowhelper']['rowhelperfield'] as $rowhelperfield) {
							foreach ($_POST as $key => $value) {
								$matches = array();
								if (preg_match("/^{$rowhelperfield['fieldname']}(\d+)$/", $key, $matches)) {
									if ($rowhelperfield['type'] == "textarea") {
										$pkgarr[$rowhelpername][$matches[1]][$rowhelperfield['fieldname']] = unixnewlines($value);
									} else {
										$pkgarr[$rowhelpername][$matches[1]][$rowhelperfield['fieldname']] = $value;
									}
								}
							}
						}
						break;
					case "textarea":
						$fieldname = $fields['fieldname'];
						$fieldvalue = unixnewlines(trim($_POST[$fieldname]));
						if ($fields['encoding'] == 'base64') {
							$fieldvalue = base64_encode($fieldvalue);
						}
						if ($fieldname) {
							$pkgarr[$fieldname] = $fieldvalue;
						}
					default:
						$fieldname = $fields['fieldname'];
						if ($fieldname == "interface_array") {
							$fieldvalue = $_POST[$fieldname];
						} elseif (is_array($_POST[$fieldname])) {
							$fieldvalue = implode(',', $_POST[$fieldname]);
						} else {
							$fieldvalue = trim($_POST[$fieldname]);
							if ($fields['encoding'] == 'base64') {
								$fieldvalue = base64_encode($fieldvalue);
							}
						}
						if ($fieldname) {
							$pkgarr[$fieldname] = $fieldvalue;
						}
					}
			}

			/* If the user supplied an ID and it exists, or if id=0
			 * and the settings are invalid, overwrite.
			 * See https://redmine.pfsense.org/issues/7624
			 */
			if (isset($id) && ($a_pkg[$id] ||
			   (($id == 0) && !is_array($a_pkg[$id])) )) {
				$a_pkg[$id] = $pkgarr;
			} else {
				$a_pkg[] = $pkgarr;
			}

			write_config($pkg['addedit_string']);
			// late running code
			if ($pkg['custom_add_php_command_late'] != "") {
				eval($pkg['custom_add_php_command_late']);
			}

			if (isset($pkg['filter_rules_needed'])) {
				filter_configure();
			}

			// resync the configuration file code if defined.
			if ($pkg['custom_php_resync_config_command'] != "") {
				eval($pkg['custom_php_resync_config_command']);
			}

			parse_package_templates();

			/* if start_command is defined, restart w/ this */
			if ($pkg['start_command'] != "") {
				exec($pkg['start_command'] . ">/dev/null 2&>1");
			}

			/* if restart_command is defined, restart w/ this */
			if ($pkg['restart_command'] != "") {
				exec($pkg['restart_command'] . ">/dev/null 2&>1");
			}

			if ($pkg['aftersaveredirect'] != "") {
				pfSenseHeader($pkg['aftersaveredirect']);
			} elseif (!$pkg['adddeleteeditpagefields']) {
				pfSenseHeader("pkg_edit.php?xml={$xml}&id=0");
			} elseif (!$pkg['preoutput']) {
				pfSenseHeader("pkg.php?xml=" . $xml);
			}
			exit;
		} else {
			$get_from_post = true;
		}
	} elseif (!$input_errors) {
		// donotsave is enabled.  lets simply exit.
		exit;
	}
}


// Turn an embedded table into a bootstrap class table. This is for backward compatibility.
// We remove any table attributes in the XML and replace them with Bootstrap table classes
function bootstrapTable($text) {
	$t = strpos($text, '<table') + strlen('<table');	// Find the <table tag
	$c = strpos($text, '>', $t);						// And its closing bracket

	// Substitute everything inbetween with our new classes
	if ($t && $c && (($c - $t) < 200)) {
		return(substr_replace($text, ' class="table table-striped table-hover table-condensed"', $t, ($c - $t)));
	}
}

/*
 * ROW helper function. Creates one element in the row from a PHP table by adding
 * the specified element to $group
 */
function display_row($trc, $value, $fieldname, $type, $rowhelper, $description, $ewidth = null) {
	global $text, $group, $config;

	switch ($type) {
		case "input":
			$inpt = new Form_Input(
				$fieldname . $trc,
				null,
				'text',
				$value
			);

			$inpt->setHelp($description);

			if ($ewidth) {
				$inpt->setWidth($ewidth);
			}

			$group->add($inpt);
			break;
		case "checkbox":
			$group->add(new Form_Checkbox(
				$fieldname . $trc,
				null,
				null,
				$value,
				'ON'
			))->setHelp($description);

			break;
		case "password":
			$group->add(new Form_Input(
				$fieldname . $trc,
				null,
				'password',
				$value
			))->setHelp($description);
			break;
		case "textarea":
			$group->add(new Form_Textarea(
				$fieldname . $trc,
				null,
				$value
			))->setHelp($description);

			break;
		case "select":
			$options = array();
			foreach ($rowhelper['options']['option'] as $rowopt) {
				$options[$rowopt['value']] = $rowopt['name'];
			}

			$grp = new Form_Select(
				$fieldname . $trc,
				null,
				$value,
				$options
			);

			$grp->setHelp($description);

			if ($ewidth) {
				$grp->setWidth($ewidth);
			}

			$group->add($grp);

			break;
		case "interfaces_selection":
			$size = ($size ? "size=\"{$size}\"" : '');
			$multiple = '';
			if (isset($rowhelper['multiple'])) {
				$multiple = "multiple";
			}
			echo "<select style='height:22px;' id='{$fieldname}{$trc}' name='{$fieldname}{$trc}' {$size} {$multiple}>\n";
			$ifaces = get_configured_interface_with_descr();
			$additional_ifaces = $rowhelper['add_to_interfaces_selection'];
			if (!empty($additional_ifaces)) {
				$ifaces = array_merge($ifaces, explode(',', $additional_ifaces));
			}

			if (is_array($value)) {
				$values = $value;
			} else {
				$values = explode(',', $value);
			}

			$ifaces["lo0"] = "loopback";
			$options = array();
			$selected = array();

			foreach ($ifaces as $ifname => $iface) {
				$options[$ifname] = $iface;

				if (in_array($ifname, $values)) {
					array_push($selected, $ifname);
				}
			}

			$group->add(new Form_Select(
				$fieldname . $trc,
				null,
				($multiple) ? $selected:$selected[0],
				$options,
				$multiple
			))->setHelp($description);

			//echo "</select>\n";
			break;
		case "select_source":
			$options = array();
			$selected = array();

			if (isset($rowhelper['show_disable_value'])) {
				$options[$rowhelper['show_disable_value']] = $rowhelper['show_disable_value'];
			}

			$source_url = $rowhelper['source'];
			eval("\$pkg_source_txt = &$source_url;");

			foreach ($pkg_source_txt as $opt) {
				$source_name = ($rowhelper['source_name'] ? $opt[$rowhelper['source_name']] : $opt[$rowhelper['name']]);
				$source_value = ($rowhelper['source_value'] ? $opt[$rowhelper['source_value']] : $opt[$rowhelper['value']]);
				$options[$source_value] = $source_name;

				if ($source_value == $value) {
					array_push($selected, $value);
				}
			}

			$group->add(new Form_Select(
				$fieldname . $trc,
				null,
				($multiple) ? $selected:$selected[0],
				$options,
				$multiple
			))->setHelp($description);

			break;
	}
}

function fixup_string($string) {
	global $config;
	// fixup #1: $myurl -> http[s]://ip_address:port/
	$https = "";
	$port = $config['system']['webguiport'];
	if ($port != "443" and $port != "80") {
		$urlport = ":" . $port;
	} else {
		$urlport = "";
	}

	if ($config['system']['webgui']['protocol'] == "https") {
		$https = "s";
	}
	$myurl = "http" . $https . "://" . getenv("HTTP_HOST") . $urlport;
	$newstring = str_replace("\$myurl", $myurl, $string);
	$string = $newstring;
	// fixup #2: $wanip
	$curwanip = get_interface_ip();
	$newstring = str_replace("\$wanip", $curwanip, $string);
	$string = $newstring;
	// fixup #3: $lanip
	$lancfg = $config['interfaces']['lan'];
	$lanip = $lancfg['ipaddr'];
	$newstring = str_replace("\$lanip", $lanip, $string);
	$string = $newstring;
	// fixup #4: fix'r'up here.
	return $newstring;
}

/*
 *	Parse templates if they are defined
 */
function parse_package_templates() {
	global $pkg;
	if ($pkg['templates']['template'] != "") {
		foreach ($pkg['templates']['template'] as $pkg_template_row) {
			$filename = $pkg_template_row['filename'];
			$template_text = $pkg_template_row['templatecontents'];
			/* calculate total row helpers count and */
			/* change fields defined as fieldname_fieldvalue to their value */
			foreach ($pkg['fields']['field'] as $fields) {
				switch ($fields['type']) {
					case "rowhelper":
						// save rowhelper items.
						$row_helper_total_rows = 0;
						$row_helper_data = "";
						foreach ($fields['rowhelper']['rowhelperfield'] as $rowhelperfield) {
							foreach ($_POST as $key => $value) {
								if (preg_match("/^{$rowhelperfield['fieldname']}(\d+)$/", $key, $matches)) {
									$row_helper_total_rows++;
									$row_helper_data .= $value;
									$sep = "";
									ereg($rowhelperfield['fieldname'] . "_fieldvalue\[(.*)\]", $template_text, $sep);
									foreach ($sep as $se) {
										$separator = $se;
									}
									if ($separator != "") {
										$row_helper_data = ereg_replace("  ", $separator, $row_helper_data);
										$template_text = ereg_replace("\[{$separator}\]", "", $template_text);
									}
									$template_text = str_replace($rowhelperfield['fieldname'] . "_fieldvalue", $row_helper_data, $template_text);
								}
							}
						}
						break;
					default:
						$fieldname = $fields['fieldname'];
						$fieldvalue = $_POST[$fieldname];
						$template_text = str_replace($fieldname . "_fieldvalue", $fieldvalue, $template_text);
				}
			}
			/* replace $domain_total_rows with total rows */
			$template_text = str_replace("$domain_total_rows", $row_helper_total_rows, $template_text);

			/* replace cr's */
			$template_text = str_replace("\\n", "\n", $template_text);

			/* write out new template file */
			$fout = fopen($filename, "w");
			fwrite($fout, $template_text);
			fclose($fout);
		}
	}
}

//breadcrumb
if ($pkg['title'] != "") {
	if (!$only_edit) {
		$pkg['title'] = $pkg['title'] . '/Edit';
	}

	if (strpos($pkg['title'], '/')) {
		$title = explode('/', $pkg['title']);

		foreach ($title as $subtitle) {
			$pgtitle[] = gettext($subtitle);
			$pglinks[] = ((($subtitle == "Edit") || (strlen($pkg['menu'][0]['url']) == 0)) ? "@self" : $pkg['menu'][0]['url']);
		}
	} else {
		$pgtitle = array(gettext("Package"), gettext($pkg['title']));
		$pglinks = array("", ((($subtitle == "Edit") || (strlen($pkg['menu'][0]['url']) == 0)) ? "@self" : $pkg['menu'][0]['url']));
	}
} else {
	$pgtitle = array(gettext("Package"), gettext("Editor"));
	$pglinks = array("", "@self");
}

// Create any required tabs
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
			$pgtitle[] = $tab['text'] ;
			$pglinks[] = ((strlen($tab['url']) > 0) ? $tab['url'] : "@self");
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
if ($pkg['custom_php_after_head_command']) {
	eval($pkg['custom_php_after_head_command']);
}
if (isset($tab_array)) {
	foreach ($tab_array as $tabid => $tab) {
		display_top_tabs($tab);
	}
}

// Start of page display
if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$cols = 0;
$savevalue = gettext("Save");
if ($pkg['savetext'] != "") {
	$savevalue = $pkg['savetext'];
}

$savehelp = "";
if ($pkg['savehelp'] != "") {
	$savehelp = $pkg['savehelp'];
}

$saveicon = "fa-save";
if ($pkg['saveicon'] != "") {
	$saveicon = $pkg['saveicon'];
}

$savebtnclass = "btn-primary";
if ($pkg['savebtnclass'] != "") {
	$savebtnclass = $pkg['savebtnclass'];
}

$grouping = false; // Indicates the elements we are composing are part of a combined group

$savebutton = new Form_Button(
	'submit',
	$savevalue,
	null,
	$saveicon
);
$savebutton->addClass($savebtnclass);

if ($savehelp) {
	$savebutton->setHelp($savehelp);
}

$form = new Form($savebutton);

$form->addGlobal(new Form_Input(
	'xml',
	null,
	'hidden',
	$xml
));

/* If a package's XML has <advanced_options/> configured, then setup
 * the section for the fields that have <advancedfield/> set.
 * These fields will be placed below other fields in a separate area titled 'Advanced Features'.
 * These advanced fields are not normally configured and generally left to default to 'default settings'.
 */

if ($pkg['advanced_options'] == "enabled") {
	$advfield_count = 0;
	$advanced = new Form_Section("Advanced Features");
	$advanced->addClass('advancedoptions');
}

$js_array = array();

// Now loop through all of the fields defined in the XML
if (!is_array($pkg['fields']['field'])) {
	$pkg['fields']['field'] = array();
}
foreach ($pkg['fields']['field'] as $pkga) {

	$action = "";
	$uid = "";

	if ($pkga['type'] == "sorting") {
		continue;
	}

	// Generate a new section
	if ($pkga['type'] == "listtopic") {
		if (isset($pkga['advancedfield']) && isset($advfield_count)) {
			$advanced->addInput(new Form_StaticText(
				strip_tags($pkga['name']),
				null
			));

			$advfield_count++;
		} else {
			if (isset($section)) {
				$form->add($section);
			}

			if (isset($pkga['collapse'])) {
				$uid = uniqid("section");

				$action = COLLAPSIBLE;

				if ($pkga['collapse'] == "open") {
					$action |= SEC_OPEN;
				} else {
					$action |= SEC_CLOSED;
				}
			}

			$section = new Form_Section(strip_tags($pkga['name']), $uid, $action);
		}

		continue;
	}

	// 'begin' starts a form group. ('end' ends it)
	if ($pkga['combinefields'] == "begin") {
		$group = new Form_Group(strip_tags($pkga['fielddescr']));
		$grouping = true;
	}

	$size = "";
	$colspan="";

	// if user is editing a record, load in the data.
	$fieldname = $pkga['fieldname'];
	unset($value);
	if ($get_from_post) {
		$value = $_POST[$fieldname];
		if (is_array($value)) {
			$value = implode(',', $value);
		}
	} else {
		if (isset($id) && isset($a_pkg[$id][$fieldname])) {
			$value = $a_pkg[$id][$fieldname];
		} else {
			if (isset($pkga['default_value'])) {
				$value = $pkga['default_value'];
			}
		}
	}

	// If we get here but have no $section, the package config file probably had no listtopic field
	// We can create a section with a generic name to fix that
	if (!$section) {
		$section = new Form_Section('General Options');
	}

	// If this is a required field, pre-pend a "*" to the field description
	// This tells the system to add "element-required" class text decoration to the field label
	if (isset($pkga['required'])) {
		$pkga['fielddescr'] = "*" . $pkga['fielddescr'];
	}

	switch ($pkga['type']) {
		// Create an input element. The format is slightly different depending on whether we are composing a group,
		// section, or advanced section. This is true for every element type
		case "input":
			if (($pkga['encoding'] == 'base64') && !$get_from_post && !empty($value)) {
				$value = base64_decode($value);
			}

			$grp = new Form_Input(
					$pkga['fieldname'],
					$pkga['fielddescr'],
					'text',
					$value
				);

			$grp->setHelp($pkga['description']);

			if ($pkga['width']) {
				$grp->setWidth($pkga['width']);
			}

			if ($grouping) {
				$group->add($grp);
			} else {
				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput($grp);
				} else {
					$section->addInput($grp);
				}
			}

			break;

		case "password":
			if (($pkga['encoding'] == 'base64') && !$get_from_post && !empty($value)) {
				$value = base64_decode($value);
			}

			// Create a password element
			if ($grouping) {
				$group->add(new Form_Input(
					$pkga['fieldname'],
					$pkga['fielddescr'],
					'password',
					$value
				))->setHelp($pkga['description']);
			} else {
				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput(new Form_Input(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						'password',
						$value
					))->setHelp($pkga['description']);
				} else {
					$section->addInput(new Form_Input(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						'password',
						$value
					))->setHelp($pkga['description']);
				}
			}

			break;

		case "info":
			// If the info contains a table we should detect and Bootstrap it

			if (strpos($pkga['description'], '<table') !== FALSE) {
				$info = bootstrapTable($pkga['description']);
			} else {
				$info = $pkga['description'];
			}

			if (isset($pkga['advancedfield']) && isset($advfield_count)) {
				$advanced->addInput(new Form_StaticText(
					strip_tags($pkga['fielddescr']),
					$info
				));
			} else {
				$section->addInput(new Form_StaticText(
					strip_tags($pkga['fielddescr']),
					$info
				));
			}

			break;

		case "select":
			// Create a select element
			$optionlist = array();
			$selectedlist = array();

			$fieldname = $pkga['fieldname'];

			if (isset($pkga['multiple'])) {
				$multiple = 'multiple';
				$items = explode(',', $value);
				$fieldname .= "[]";
			} else {
				$multiple = '';
				$items = array($value);
			}

			$onchange = (isset($pkga['onchange']) ? "{$pkga['onchange']}" : '');

			foreach ($pkga['options']['option'] as $opt) {
				$optionlist[$opt['value']] = $opt['name'];

				if (in_array($opt['value'], $items)) {
					array_push($selectedlist, $opt['value']);
				}
			}

			if (isset($pkga['advancedfield']) && isset($advfield_count)) {
				$function = $grouping ? $advanced->add:$advanced->addInput;
			} else {
				$function = ($grouping) ? $section->add:$section->addInput;
			}

			$grp = new Form_Select(
						$pkga['fieldname'],
						strip_tags($pkga['fielddescr']),
						isset($pkga['multiple']) ? $selectedlist:$selectedlist[0],
						$optionlist,
						isset($pkga['multiple'])
					);

			$grp ->setHelp($pkga['description'])->setOnchange($onchange)->setAttribute('size', $pkga['size']);

			if ($pkga['width']) {
				$grp->setWidth($pkga['width']);
			}

			if ($grouping) {
				$group->add($grp);
			} else {
				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput($grp);
				} else {
					$section->addInput($grp);
				}
			}

			break;

		case "select_source":

			if (isset($pkga['multiple'])) {
				$items = explode(',', $value);
				$fieldname .= "[]";
			} else {
				$items = array($value);
			}

			$onchange = (isset($pkga['onchange']) ? "{$pkga['onchange']}" : '');

			$source_url = $pkga['source'];
			try{
				@eval("\$pkg_source_txt = &$source_url;");
			} catch (\Throwable | \Error | \Exception $e) {
				//do nothing
			}
			#check if show disable option is present on xml
			if (!is_array($pkg_source_txt)) {
				$pkg_source_txt = array();
			}
			if (isset($pkga['show_disable_value'])) {
				array_push($pkg_source_txt,
					array(($pkga['source_name']? $pkga['source_name'] : $pkga['name'])=> $pkga['show_disable_value'], ($pkga['source_value']? $pkga['source_value'] : $pkga['value'])=> $pkga['show_disable_value']));
			}

			$srcoptions = array();
			$srcselected = array();

			foreach ($pkg_source_txt as $opt) {
				$source_name =($pkga['source_name']? $opt[$pkga['source_name']] : $opt[$pkga['name']]);
				$source_value =($pkga['source_value'] ? $opt[$pkga['source_value']] : $opt[$pkga['value']]);
				$srcoptions[$source_value] = $source_name;

				if (in_array($source_value, $items)) {
					array_push($srcselected, $source_value);
				}
			}

			$descr = (isset($pkga['description'])) ? $pkga['description'] : "";
			if ($grouping) {
				$group->add(new Form_Select(
					$pkga['fieldname'],
					strip_tags($pkga['fielddescr']),
					isset($pkga['multiple']) ? $srcselected:$srcselected[0],
					$srcoptions,
					isset($pkga['multiple'])
				))->setHelp($descr)->setOnchange($onchange);
			} else {
				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput(new Form_Select(
						$pkga['fieldname'],
						strip_tags($pkga['fielddescr']),
						isset($pkga['multiple']) ? $srcselected:$srcselected[0],
						$srcoptions,
						isset($pkga['multiple'])
					))->setHelp($descr)->setOnchange($onchange);
				} else {
					$section->addInput(new Form_Select(
						$pkga['fieldname'],
						strip_tags($pkga['fielddescr']),
						isset($pkga['multiple']) ? $srcselected:$srcselected[0],
						$srcoptions,
						isset($pkga['multiple'])
					))->setHelp($descr)->setOnchange($onchange);
				}
			}

			break;

		case "vpn_selection" :
			$vpnlist = array();

			foreach ($config['ipsec']['phase1'] as $vpn) {
				$vpnlist[$vpn['descr']] = $vpn['descr'];

			}

			if ($grouping) {
				$group->add(new Form_Select(
					$pkga['fieldname'],
					null,
					false,
					$vpnlist
				))->setHelp(fixup_string($pkga['description']));
			} else {
				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput(new Form_Select(
						$pkga['fieldname'],
						null,
						false,
						$vpnlist
					))->setHelp(fixup_string($pkga['description']));
				} else {
					$section->addInput(new Form_Select(
						$pkga['fieldname'],
						null,
						false,
						$vpnlist
					))->setHelp(fixup_string($pkga['description']));
				}
			}

			break;

		// Create a checkbox element
		case "checkbox":
			$onchange = (isset($pkga['onchange']) ? "{$pkga['onchange']}" : '');
			if (isset($pkga['enablefields']) || isset($pkga['checkenablefields'])) {
				$onclick = 'javascript:enablechange();';
			} else {
				$onclick = '';
			}

			if ($grouping) {
				$group->add(new Form_Checkbox(
					$pkga['fieldname'],
					$pkga['fielddescr'],
					fixup_string($pkga['description']),
					($value == "on"),
					'on'
				))->setOnclick($onclick)
				  ->setOnchange($onchange)
				  ->setHelp($pkga['sethelp']);
			} else {
				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput(new Form_Checkbox(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						fixup_string($pkga['description']),
						($value == "on"),
						'on'
					))->setOnclick($onclick)
					  ->setOnchange($onchange)
					  ->setHelp($pkga['sethelp']);
				} else {
					$section->addInput(new Form_Checkbox(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						fixup_string($pkga['description']),
						($value == "on"),
						'on'
					))->setOnclick($onclick)
					  ->setOnchange($onchange)
					  ->setHelp($pkga['sethelp']);
				}
			}

			break;

		// Create a textarea element
		case "textarea":
			$rows = $cols = 0;

			if ($pkga['rows']) {
				$rows = $pkga['rows'];
			}
			if ($pkga['cols']) {
				$cols = $pkga['cols'];
			}

			if (($pkga['encoding'] == 'base64') && !$get_from_post && !empty($value)) {
				$value = base64_decode($value);
			}

			$grp = new Form_Textarea(
					$pkga['fieldname'],
					$pkga['fielddescr'],
					$value
			);

			$grp->setHelp(fixup_string($pkga['description']));

			if ($rows > 0) {
				$grp->setRows($rows);
			}

			if ($cols > 0) {
				$grp->setCols($cols);
			}

			if ($pkga['wrap'] == "off") {
				$grp->setAttribute("wrap", "off");
				$grp->setAttribute("style", "white-space:nowrap; width: auto;");
			} else {
				$grp->setAttribute("style", "width: auto;");
			}

			if ($grouping) {
				$group->add($grp);
			} else {
				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput($grp);
				} else {
					$section->addInput($grp);
				}
			}

			break;

		case "aliases":

			// Use xml tag <typealiases> to filter type aliases
			$size = ($pkga['size'] ? "size=\"{$pkga['size']}\"" : '');
			$fieldname = $pkga['fieldname'];

			init_config_arr(array('aliases', 'alias'));
			$a_aliases = &$config['aliases']['alias'];
			$addrisfirst = 0;
			$aliasesaddr = "";

			if (isset($a_aliases)) {
				if (!empty($pkga['typealiases'])) {
					foreach ($a_aliases as $alias) {
						if ($alias['type'] == $pkga['typealiases']) {
							if ($addrisfirst == 1) {
								$aliasesaddr .= ",";
							}
							$aliasesaddr .= "'" . $alias['name'] . "'";
							$addrisfirst = 1;
						}
					}
				} else {
					foreach ($a_aliases as $alias) {
						if ($addrisfirst == 1) {
							$aliasesaddr .= ",";
						}
						$aliasesaddr .= "'" . $alias['name'] . "'";
						$addrisfirst = 1;
					}
				}
			}

			$grp = new Form_Input(
					$pkga['fieldname'],
					$pkga['fielddescr'],
					'text',
					$value
				);

			$grp->setHelp($pkga['description']);

			if ($pkga['width']) {
				$grp->setWidth($pkga['width']);
			}

			if ($grouping) {
				$group->add($grp);
			} else {
				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput($grp);
				} else {
					$section->addInput($grp);
				}
			}

			$script = "<script type='text/javascript'>\n";
			$script .= "//<![CDATA[\n";
			$script .= "events.push(function(){\n";
			$script .= "	var aliasarray = new Array({$aliasesaddr})\n";
			$script .= "	$('#' + '{$fieldname}').autocomplete({\n";
			$script .= "		source: aliasarray\n";
			$script .= "	})\n";
			$script .= "});\n";
			$script .= "//]]>\n";
			$script .= "</script>";

			echo $script;

			break;

		case "interfaces_selection":
			$ips = array();
			$interface_regex=(isset($pkga['hideinterfaceregex']) ? $pkga['hideinterfaceregex'] : "nointerfacestohide");
			if (is_array($config['interfaces'])) {
				foreach ($config['interfaces'] as $iface_key=>$iface_value) {
					if (isset($iface_value['enable']) && !preg_match("/$interface_regex/", $iface_key)) {
						$iface_description=($iface_value['descr'] !="" ? strtoupper($iface_value['descr']) : strtoupper($iface_key));
						if (isset($pkga['showips'])) {
							$iface_description .= " address";
						}
						$ips[] = array('ip'=> $iface_key, 'description'=> $iface_description);
					}
				}
			}

			if (is_array($config['virtualip']) && isset($pkga['showvirtualips'])) {
				foreach ($config['virtualip']['vip'] as $vip) {
					if (!preg_match("/$interface_regex/", $vip['interface'])) {
						$vip_description=($vip['descr'] !="" ? " ({$vip['descr']}) " : " ");
					}
					switch ($vip['mode']) {
						case "ipalias":
						case "carp":
							$ips[] = array('ip' => $vip['subnet'], 'description' => "{$vip['subnet']} $vip_description");
							break;
						case "proxyarp":
							if ($vip['type'] == "network") {
								$start = ip2long32(gen_subnet($vip['subnet'], $vip['subnet_bits']));
								$end = ip2long32(gen_subnet_max($vip['subnet'], $vip['subnet_bits']));
								$len = $end - $start;
								for ($i = 0; $i <= $len; $i++) {
									$ips[]= array('ip' => long2ip32($start+$i), 'description' => long2ip32($start+$i)." from {$vip['subnet']}/{$vip['subnet_bits']} {$vip_description}");
								}
							} else {
								$ips[]= array('ip' => $vip['subnet'], 'description' => "{$vip['subnet']} $vip_description");
							}
							break;
					}
				}
			}

			sort($ips);
			if (isset($pkga['showlistenall'])) {
				array_unshift($ips, array('ip' => gettext('All'), 'description' => gettext('Listen on All interfaces/ip addresses ')));
			}

			if (!preg_match("/$interface_regex/", "loopback")) {
				$loopback_text = gettext("loopback");
				$iface_description=(isset($pkga['showips']) ? "127.0.0.1 (" . $loopback_text . ")" : $loopback_text);
				array_push($ips, array('ip' => 'lo0', 'description' => $iface_description));
			}

			#show interfaces array on gui
			$size = ($pkga['size'] ? "size=\"{$pkga['size']}\"" : '');
			$multiple = '';
			$fieldname = $pkga['fieldname'];
			if (isset($pkga['multiple'])) {
				$fieldname .= '[]';
				$multiple = 'multiple';
			}

			$selectedlist = array();
			$optionlist = array();

			if (is_array($value)) {
				$values = $value;
			} else {
				$values = explode(',', $value);
			}

			foreach ($ips as $iface) {
				if (in_array($iface['ip'], $values)) {
					array_push($selectedlist, $iface['ip']);
				}

				$optionlist[$iface['ip']] = $iface['description'];
			}

			if ($grouping) {
				$group->add(new Form_Select(
					$pkga['fieldname'],
					$pkga['fielddescr'],
					isset($pkga['multiple']) ? $selectedlist:$selectedlist[0],
					$optionlist,
					isset($pkga['multiple'])
				))->setHelp($pkga['description']);
			} else {
				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput(new Form_Select(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						isset($pkga['multiple']) ? $selectedlist:$selectedlist[0],
						$optionlist,
						isset($pkga['multiple'])
					))->setHelp($pkga['description']);
				} else {
					$section->addInput(new Form_Select(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						isset($pkga['multiple']) ? $selectedlist:$selectedlist[0],
						$optionlist,
						isset($pkga['multiple'])
					))->setHelp($pkga['description']);
				}
			}

			break;

		// Create radio button
		case "radio":
			if ($grouping) {
				$group->add(new Form_Checkbox(
					$pkga['fieldname'],
					$pkga['fielddescr'],
					fixup_string($pkga['description']),
					($value == "on"),
					'on'
				))->displayAsRadio();
			} else {
				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput(new Form_Checkbox(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						fixup_string($pkga['description']),
						($value == "on"),
						'on'
					))->displayAsRadio();
				} else {
					$section->addInput(new Form_Checkbox(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						fixup_string($pkga['description']),
						($value == "on"),
						'on'
					))->displayAsRadio();
				}
			}

			break;

		// Create form button
		case "button":
			$newbtnicon = "fa-save";
			if ($pkga['buttonicon'] != "") {
				$newbtnicon = $pkga['buttonicon'];
			}
			$newbtnclass = "btn-primary";
			if ($pkga['buttonclass'] != "") {
				$newbtnclass = $pkga['buttonclass'];
			}

			$newbtn = new Form_Button(
				$pkga['fieldname'],
				$pkga['fieldname'],
				null,
				$newbtnicon
			);
			$newbtn->addClass($newbtnclass);

			if ($grouping) {
				$group->add(new Form_StaticText(
					null,
					$newbtn . '<br />' . '<div class="help-block">' . fixup_string($pkga['description']) . '</div>'
				));
			} else {
				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput(new Form_StaticText(
						null,
						$newbtn . '<br />' . '<div class="help-block">' . fixup_string($pkga['description']) . '</div>'
					));
				} else {
					$section->addInput(new Form_StaticText(
						null,
						$newbtn . '<br />' . '<div class="help-block">' . fixup_string($pkga['description']) . '</div>'
					));
				}
			}

			break;

		case "schedule_selection":

			$input = "<select id='{$pkga['fieldname']}' name='{$pkga['fieldname']}'>\n";
			$schedules = array();
			$schedules[] = "none";
			if (is_array($config['schedules']['schedule'])) {
				foreach ($config['schedules']['schedule'] as $schedule) {
					if ($schedule['name'] != "") {
						$schedules[] = $schedule['name'];
					}
				}
			}

			foreach ($schedules as $schedule) {
				if ($schedule == "none") {
					$schedlist[""] = $schedule;
				} else {
					$schedlist[$schedule] = $schedule;
				}
			}

			if ($grouping) {
				$group->add(new Form_Select(
					$pkga['fieldname'],
					$pkga['fielddescr'],
					$value,
					$schedlist
				))->setHelp(fixup_string($pkga['description']));
			} else {
				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput(new Form_Select(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						$value,
						$schedlist
					))->setHelp(fixup_string($pkga['description']));
				} else {
					$section->addInput(new Form_Select(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						$value,
						$schedlist
					))->setHelp(fixup_string($pkga['description']));
				}
			}

			break;

		case "rowhelper":

			$rowhelpername="row";

				$rowcounter = 0;
				$trc = 0;

				//Use assigned $a_pkg or create an empty array to enter loop
				if (isset($a_pkg[$id][$rowhelpername])) {
					$saved_rows=$a_pkg[$id][$rowhelpername];
				} else {
					$saved_rows[] = array();
				}

				$numrows = count($saved_rows) - 1;

				foreach ($saved_rows as $row) {
					$group = new Form_Group(($rowcounter == 0) ? $pkga['fielddescr']:null);
					$group->addClass('repeatable');

					foreach ($pkga['rowhelper']['rowhelperfield'] as $rowhelper) {
						unset($value);
						$width = null;

						$fieldname = $rowhelper['fieldname'];
						$fielddescr = $rowhelper['fielddescr'];

						// If input validation failed, read the value from the POST data so that the user's input is not lost
						if ($get_from_post && isset($_POST[$fieldname.$rowcounter])) {
							$value = $_POST[$fieldname.$rowcounter];
						} elseif (isset($id) && $a_pkg[$id]) {
							$value = $row[$fieldname];
						} elseif ($rowhelper['value'] != "") {
							$value = $rowhelper['value'];
						}

						$type = $rowhelper['type'];
						if ($type == "input" || $type == "password" || $type == "textarea") {
							if (($rowhelper['encoding'] == 'base64') && !$get_from_post && !empty($value)) {
								$value = base64_decode($value);
							}
						}


						if ($rowhelper['size']) {
							$size = $rowhelper['size'];
						} else if ($pkga['size']) {
							$size = $pkga['size'];
						} else {
							$size = "8";
						}

						if ($rowhelper['width']) {
							$width = $rowhelper['width'];
						}

						display_row($rowcounter, $value, $fieldname, $type, $rowhelper, ($numrows == $rowcounter) ? $fielddescr:null, $width);

						$text = "";
						$trc++;
					}

					// Delete row button
					$group->add(new Form_Button(
						'deleterow' . $rowcounter,
						'Delete',
						null,
						'fa-trash'
					))->removeClass('btn-primary')->addClass('btn-warning btn-sm');

					$rowcounter++;
					$section->add($group);
				}

			// Add row button
			$section->addInput(new Form_Button(
				'addrow',
				'Add',
				null,
				'fa-plus'
			))->addClass('btn-success');

			break;

	}

		if ($pkga['combinefields'] == "end") {
			$group->add(new Form_StaticText(
				null,
				null
			));

			if ($advanced) {
				$advanced->add($group);
			} else {
				$section->add($group);
			}

			$grouping = false;
		}

	#increment counter
	$i++;
} // e-o-foreach field described in the XML

if ($section) {
	$form->add($section);
}

$form->addGlobal(new Form_Input(
	'id',
	null,
	'hidden',
	$id
));

// If we created an advanced section, add it (and a button) to the form here
if (!empty($advanced)) {
	$form->addGlobal(new Form_Button(
		'showadv',
		'Show Advanced Options',
		null,
		'fa-cog'
	))->setAttribute('type','button')->addClass('btn-info');

	$form->add($advanced);
}

print($form);

if ($pkg['note'] != "") {
	print_info_box($pkg['note'], 'info');
}

if ($pkg['custom_php_after_form_command']) {
	eval($pkg['custom_php_after_form_command']);
}


$hidemsg = gettext("Show Advanced Options");
$showmsg = gettext("Hide Advanced Options");

if ($pkg['fields']['field'] != "") { ?>
<script type="text/javascript">
//<![CDATA[
	events.push(function() {

	// Hide the advanced section
	var advanced_visible = false;

	// Hide on page load
	$('.advancedoptions').hide();

	// Suppress "Delete row" button if there are fewer than two rows
	checkLastRow();

	// Show advanced section if you click the showadv button
	$("#showadv").click(function() {
		advanced_visible = !advanced_visible;

		if (advanced_visible) {
			$('.advancedoptions').show();
			$("#showadv").html('<i class="fa fa-cog icon-embed-btn"></i>' + "<?=$showmsg?>");
		} else {
			$('.advancedoptions').hide();
			$("#showadv").html('<i class="fa fa-cog icon-embed-btn"></i>' + "<?=$hidemsg?>");
		}
	});

	// Call enablechange function
	enablechange();
});

	function enablechange() {
<?php
	foreach ($pkg['fields']['field'] as $field) {
		if (isset($field['enablefields']) or isset($field['checkenablefields'])) {
			echo "\tif ($('input[name=\"{$field['fieldname']}\"]').prop('checked') == false) {\n";

			if (isset($field['enablefields'])) {
				foreach (explode(',', $field['enablefields']) as $enablefield) {
					echo "\t\tif ($('input[name=\"{$enablefield}\"]').length > 0) {\n";
					echo "\t\t\t$('input[name=\"{$enablefield}\"]').prop('disabled',true);\n";
					echo "\t\t}\n";
				}
			}

			if (isset($field['checkenablefields'])) {
				foreach (explode(',', $field['checkenablefields']) as $checkenablefield) {
					echo "\t\tif ($('input[name=\"{$checkenablefield}\"]').length > 0) {\n";
					echo "\t\t\t$('input[name=\"{$checkenablefield}\"]').prop('checked',true);\n";
					echo "\t\t}\n";
				}
			}

			echo "\t}\n\telse {\n";

			if (isset($field['enablefields'])) {
				foreach (explode(',', $field['enablefields']) as $enablefield) {
					echo "\t\tif ($('input[name=\"{$enablefield}\"]').length > 0) {\n";
					echo "\t\t\t$('input[name=\"{$enablefield}\"]').prop('disabled',false);\n";
					echo "\t\t}\n";
				}
			}

			if (isset($field['checkenablefields'])) {
				foreach (explode(',', $field['checkenablefields']) as $checkenablefield) {
					echo "\t\tif ($('input[name=\"{$checkenablefield}\"]').length > 0) {\n";
					echo "\t\t\t$('input[name=\"{$checkenablefield}\"]').prop('checked',false);\n";
					echo "\t\t}\n";
				}
			}

			echo "\t}\n";
		}
	}
	?>
	}
//]]>
</script>

<?php
}

include("foot.inc");
