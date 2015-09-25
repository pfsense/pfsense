<?php
/* $Id$ */
/*
	pkg_edit.php
*//* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2004, 2005 Scott Ullrich
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */
/*
	pfSense_BUILDER_BINARIES:	/sbin/ifconfig
	pfSense_MODULE: pkgs
*/

##|+PRIV
##|*IDENT=page-package-edit
##|*NAME=Package: Edit page
##|*DESCR=Allow access to the 'Package: Edit' page.
##|*MATCH=pkg_edit.php*
##|-PRIV

ini_set('max_execution_time', '0');

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("pkg-utils.inc");

/* dummy stubs needed by some code that was MFC'd */
function pfSenseHeader($location) {
	header("Location: " . $location);
}

function gentitle_pkg($pgname) {
	global $pfSense_config;
	return $pfSense_config['system']['hostname'] . "." . $pfSense_config['system']['domain'] . " - " . $pgname;
}

function domTT_title($title_msg) {
	if (!empty($title_msg)) {
		$title_msg = preg_replace("/\s+/", " ", $title_msg);
		$title_msg = preg_replace("/'/", "\'", $title_msg);
		return "onmouseout=\"this.style.color = ''; domTT_mouseout(this, event);\" onmouseover=\"domTT_activate(this, event, 'content', '{$title_msg}', 'trail', true, 'delay', 0, 'fade', 'both', 'fadeMax', 93, 'delay', 300, 'styleClass', 'niceTitle');\"";
	}
}

// Turn an embedded table into a bootstrap class table
function bootstrapTable($text) {
	$t = strpos($text, '<table') + strlen('<table');	// Find the <table tag
	$c = strpos($text, '>', $t);						// And its closing bracket

	return(substr_replace($text, ' class="table table-dtriped table-hover table-condensed"', $t, ($c - $t)));
}

$xml = htmlspecialchars($_GET['xml']);
if ($_POST['xml']) {
	$xml = htmlspecialchars($_POST['xml']);
}

$xml_fullpath = realpath('/usr/local/pkg/' . $xml);

if ($xml == "" || $xml_fullpath === false ||
	substr($xml_fullpath, 0, strlen('/usr/local/pkg/')) != '/usr/local/pkg/') {
	print_info_box_np(gettext("ERROR: No valid package defined."));
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

$id = $_GET['id'];
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
if ($config['installedpackages'] && !is_array($config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config'])) {
	$config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config'] = array();
}

// If the first entry in the array is an empty <config/> tag, kill it.
if ($config['installedpackages'] && (count($config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config']) > 0)
	&& ($config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config'][0] == "")) {
	array_shift($config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config']);
}

$a_pkg = &$config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config'];

if ($_GET['savemsg'] != "") {
	$savemsg = htmlspecialchars($_GET['savemsg']);
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
		if (($field['type'] == 'input') && isset($field['required'])) {
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

	// donotsave is enabled.  lets simply exit.
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
									$pkgarr[$rowhelpername][$matches[1]][$rowhelperfield['fieldname']] = $value;
								}
							}
						}
						break;
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

			if (isset($id) && $a_pkg[$id]) {
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
				pfSenseHeader("pkg_edit.php?xml={$xml}&amp;id=0");
			} elseif (!$pkg['preoutput']) {
				pfSenseHeader("pkg.php?xml=" . $xml);
			}
			exit;
		} else {
			$get_from_post = true;
		}
	} elseif (!$input_errors) {
		exit;
	}
}

if ($pkg['title'] != "") {
	$edit = ($only_edit ? '' : ": " . gettext("Edit"));
	$pgtitle = $pkg['title'] . $edit;
} else {
	$pgtitle = gettext("Package Editor");
}

if ($pkg['custom_php_after_head_command']) {
	$closehead = false;
	include("head.inc");
	eval($pkg['custom_php_after_head_command']);
} else {
	include("head.inc");
}

require_once('classes/Form.class.php');

if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg)
	print_info_box($savemsg, 'success');

$form = new Form();

$form->addGlobal(new Form_Input(
	'xml',
	null,
	'hidden',
	$xml
));

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
		} else {
			$active = false;
		}
		if (isset($tab['no_drop_down'])) {
			$no_drop_down = true;
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

	foreach ($tab_array as $tabid => $tab) {
		display_top_tabs($tab); //, $no_drop_down, $tabid);
	}
}

?>

<?php
	$cols = 0;
	$savevalue = gettext("Save");
	if ($pkg['savetext'] != "") {
		$savevalue = $pkg['savetext'];
	}
	/* If a package's XML has <advanced_options/> configured, then setup
	 * the table rows for the fields that have <advancedfield/> set.
	 * These fields will be placed below other fields in a separate area titled 'Advanced Features'.
	 * These advanced fields are not normally configured and generally left to default to 'default settings'.
	 */

	if ($pkg['advanced_options'] == "enabled") {
		$advfield_count = 0;
		$advanced = new Form_Section(gettext("Advanced features"));
		$advanced->addClass('advancedoptions');
	}

	$js_array = array();

	foreach ($pkg['fields']['field'] as $pkga) {
		if ($pkga['type'] == "sorting") {
			continue;
		}

		// Generate a new section

		if ($pkga['type'] == "listtopic") {
			if (isset($pkga['advancedfield']) && isset($advfield_count)) {
				$advanced->addInput(new Form_StaticText(
					$pkga['name'],
					null
				));

				$advfield_count++;
			}  else {
				if(isset($section))
					$form->add($section);

				$section = new Form_Section($pkga['name']);
			}

			continue;
		}

		if ($pkga['combinefields'] == "begin") {
			print('begin not yet converted<br />');
/*
			$input="<tr valign='top' id='tr_{$pkga['fieldname']}'>";
			if (isset($pkga['advancedfield']) && isset($advfield_count)) {
				$advanced .= $input;
			} else {
				echo $input;
			}
*/
		}

		$size = "";
		$colspan="";
/*
		if (isset($pkga['dontdisplayname'])) {
			$input="";
			// If this is in a set of combined fields and;
			// - it is a "begin" (case already handled above) or
			// - usecolspan2 is in effect (so we want to spread all the combined fields horizontally)
			// then we do not want this "tr" to be inserted.
			// Thus only insert the "tr" if the not (!) of the above condition.
			if (!((isset($pkga['combinefields'])) && (($pkga['combinefields'] == "begin") || (isset($pkga['usecolspan2']))))) {
				$input .= "<tr valign='top' id='tr_{$pkga['fieldname']}'>";
			}
			if (isset($pkga['usecolspan2'])) {
				$colspan="colspan='2'";
			} else {
				$input .= "<td width='22%' class='vncell{$req}'>&nbsp;</td>";
			}
			if (isset($pkga['advancedfield']) && isset($advfield_count)) {
				$advanced .= $input;
				$advfield_count++;
			} else {
				echo $input;
			}
		} else if (!isset($pkga['placeonbottom'])) {
			unset($req);
			if (isset($pkga['required'])) {
				$req = 'req';
			}
			$input="";
			// If this is in a set of combined fields and;
			// - it is a "begin" (case already handled above) or
			// - usecolspan2 is in effect (so we want to spread all the combined fields horizontally)
			// then we do not want this "tr" to be inserted.
			// Thus only insert the "tr" if the not (!) of the above condition.
			if (!((isset($pkga['combinefields'])) && (($pkga['combinefields'] == "begin") || (isset($pkga['usecolspan2']))))) {
				$input .= "<tr>";
			}
			$input .= "<td valign='top' width=\"22%\" class=\"vncell{$req}\">";
			$input .= fixup_string($pkga['fielddescr']);
			$input .= "</td>";
			if (isset($pkga['advancedfield']) && isset($advfield_count)) {
				$advanced .= $input;
				$advfield_count++;
			} else {
				echo $input;
			}
		}

		if ($pkga['combinefields'] == "begin") {
			$input="<td class=\"vncell\"><table summary=\"advanced\"><tr>";
			if (isset($pkga['advancedfield']) && isset($advfield_count)) {
				$advanced .= $input;
			} else {
				echo $input;
			}
		}

		$class=(isset($pkga['combinefields']) ? '' : 'class="vtable"');
		if (!isset($pkga['placeonbottom'])) {
			$input="<td valign='top' {$colspan} {$class}>";
			if (isset($pkga['advancedfield']) && isset($advfield_count)) {
				$advanced .= $input;
				$advfield_count++;
			} else {
				echo $input;
			}
		}
*/
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

		switch ($pkga['type']) {
			// Creat an input element
			case "input":
				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput(new Form_Input(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						'text',
						$value
					))->setHelp($pkga['description']);
				} else {
					$section->addInput(new Form_Input(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						'text',
						$value
					))->setHelp($pkga['description']);
				}

				break;

			case "password":
			// Creat a password element
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

				break;

			case "info":
				// If the info containe a table we should detect and Bootstrap it

				if (strpos($pkga['description'], '<table') !== FALSE)
					$info = bootstrapTable($pkga['description']);
				else
					$info = $pkga['description'];


				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput(new Form_StaticText(
						null,
						$info
					));
				} else {
					$section->addInput(new Form_StaticText(
						null,
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
					$multiple = 'multiple="multiple"';
					$items = explode(',', $value);
					$fieldname .= "[]";
				} else {
					$multiple = '';
					$items = array($value);
				}

				$onchange = (isset($pkga['onchange']) ? "onchange=\"{$pkga['onchange']}\"" : '');

				foreach ($pkga['options']['option'] as $opt) {
					$optionlist[$opt['value']] = $opt['name'];

					if (in_array($opt['value'], $items)) {
						array_push($selectedlist, $opt['value']);
					}
				}

				$section->addInput(new Form_Select(
					$pkga['fieldname'],
					$pkga['fielddescr'],
					isset($pkga['multiple']) ? $selectedlist:$selectedlist[0],
					$optionlist,
					isset($pkga['multiple'])
				))->setHelp($pkga['description'])->setOnchange($onchange);


				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput(new Form_Select(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						isset($pkga['multiple']) ? $selectedlist:$selectedlist[0],
						$optionlist,
						isset($pkga['multiple'])
					))->setHelp($pkga['description'])->setOnchange($onchange);
				} else {
					$section->addInput(new Form_Select(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						isset($pkga['multiple']) ? $selectedlist:$selectedlist[0],
						$optionlist,
						isset($pkga['multiple'])
					))->setHelp($pkga['description'])->setOnchange($onchange);
				}

				break;

			case "select_source":

				if (isset($pkga['multiple'])) {
					$items = explode(',', $value);
					$fieldname .= "[]";
				} else {
					$items = array($value);
				}

				$onchange = (isset($pkga['onchange']) ? "onchange=\"{$pkga['onchange']}\"" : '');

				$source_url = $pkga['source'];
				eval("\$pkg_source_txt = &$source_url;");

				#check if show disable option is present on xml
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

					if(in_array($source_value, $items))
						array_push($srcselected, $source_value);
				}

				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput(new Form_Select(
						$pkga['fieldname'],
						$pkga['name'],
						isset($pkga['multiple']) ? $srcselected:$srcselected[0],
						$sourceoptions,
						isset($pkga['multiple'])
					))->setOnchange($onchange);
				} else {
					$section->addInput(new Form_Select(
						$pkga['fieldname'],
						$pkga['name'],
						isset($pkga['multiple']) ? $srcselected:$srcselected[0],
						$sourceoptions,
						isset($pkga['multiple'])
					))->setOnchange($onchange);
				}

				break;

			case "vpn_selection" :
				$vpnlist = array();

				foreach ($config['ipsec']['phase1'] as $vpn) {
					$vpnlist[$vpn['descr']] = $vpn['descr'];

				}

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


				break;

			// Create a checkbox element
			case "checkbox":
				$onchange = (isset($pkga['onchange']) ? "{$pkga['onchange']}" : '');
				if (isset($pkga['enablefields']) || isset($pkga['checkenablefields']))
					$onclick = 'javascript:enablechange();';
				else
					$onclick = '';

				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput(new Form_Checkbox(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						'Show log entries in reverse order (newest entries on top)',
						($value == "on"),
						'on'
					))->setHelp(fixup_string($pkga['description']))
					  ->setOnclick($onclick)
					  ->setOnchange($onchange);
				} else {
					$section->addInput(new Form_Checkbox(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						'Show log entries in reverse order (newest entries on top)',
						($value == "on"),
						'on'
					))->setHelp(fixup_string($pkga['description']))
					  ->setOnclick($onclick)
					  ->setOnchange($onchange);
				}

				break;

			// Creat textarea element
			case "textarea":
				if ($pkga['rows']) {
					$rows = " rows='{$pkga['rows']}' ";
				}
				if ($pkga['cols']) {
					$cols = " cols='{$pkga['cols']}' ";
				}
				if (($pkga['encoding'] == 'base64') && !$get_from_post && !empty($value)) {
					$value = base64_decode($value);
				}

				$wrap =($pkga['wrap'] == "off" ? 'wrap="off" style="white-space:nowrap;"' : '');

				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput(new Form_TextArea(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						$value
					))->setHelp(fixup_string($pkga['description']));
				} else {
					$section->addInput(new Form_TextArea(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						$value
					))->setHelp(fixup_string($pkga['description']));
				}

				break;

			case "aliases":
				// Todo
				print('aliases not yet converted<br />');
/*
				// Use xml tag <typealiases> to filter type aliases
				$size = ($pkga['size'] ? "size=\"{$pkga['size']}\"" : '');
				$fieldname = $pkga['fieldname'];
				$a_aliases = &$config['aliases']['alias'];
				$addrisfirst = 0;
				$aliasesaddr = "";
				$value = "value='{$value}'";

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

				$input = "<input name='{$fieldname}' type='text' class='formfldalias' id='{$fieldname}' {$size} {$value} />\n<br />";
				$input .= fixup_string($pkga['description']) . "\n";

				$script = "<script type='text/javascript'>\n";
				$script .= "//<![CDATA[\n";
				$script .= "var aliasarray = new Array({$aliasesaddr})\n";
				$script .= "var oTextbox1 = new AutoSuggestControl(document.getElementById('{$fieldname}'), new StateSuggestions(aliasarray))\n";
				$script .= "//]]>\n";
				$script .= "</script>";

				echo $input;
				echo $script;
*/
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
					array_unshift($ips, array('ip' => 'All', 'description' => 'Listen on All interfaces/ip addresses '));
				}

				if (!preg_match("/$interface_regex/", "loopback")) {
					$iface_description=(isset($pkga['showips']) ? "127.0.0.1 (loopback)" : "loopback");
					array_push($ips, array('ip' => 'lo0', 'description' => $iface_description));
				}

				#show interfaces array on gui
				$size = ($pkga['size'] ? "size=\"{$pkga['size']}\"" : '');
				$multiple = '';
				$fieldname = $pkga['fieldname'];
				if (isset($pkga['multiple'])) {
					$fieldname .= '[]';
					$multiple = 'multiple="multiple"';
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

				break;

			// Create radio button
			case "radio":
				if (isset($pkga['advancedfield']) && isset($advfield_count)) {
					$advanced->addInput(new Form_Checkbox(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						'Show log entries in reverse order (newest entries on top)',
						($value == "on"),
						'on'
					))->setHelp(fixup_string($pkga['description']))->displayAsRadio();
				} else {
					$section->addInput(new Form_Checkbox(
						$pkga['fieldname'],
						$pkga['fielddescr'],
						'Show log entries in reverse order (newest entries on top)',
						($value == "on"),
						'on'
					))->setHelp(fixup_string($pkga['description']))->displayAsRadio();
				}

				break;

			// Create form button
			case "button":
				$newbtn = new Form_Button(
					$pkga['fieldname'],
					$pkga['fieldname']
				);

				$section->addInput(new Form_StaticText(
					null,
					$newbtn . '<br />' . '<div class="help-block">' . fixup_string($pkga['description']) . '</div>'
				));

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

				break;
/*
			case "rowhelper":
				#$rowhelpername=($fields['fieldname'] ? $fields['fieldname'] : "row");
				$rowhelpername="row";
				?>
				<script type="text/javascript">
				//<![CDATA[
				<?php
					$rowcounter = 0;
					$fieldcounter = 0;
					foreach ($pkga['rowhelper']['rowhelperfield'] as $rowhelper) {
						echo "rowname[{$fieldcounter}] = \"{$rowhelper['fieldname']}\";\n";
						echo "rowtype[{$fieldcounter}] = \"{$rowhelper['type']}\";\n";
						echo "rowsize[{$fieldcounter}] = \"{$rowhelper['size']}\";\n";
						$fieldcounter++;
					}
				?>
				//]]>
				</script>
				<table id="maintable" summary="main table">
				<tr id='<?="tr_{$pkga['fieldname']}"?>'>
				<?php
					foreach ($pkga['rowhelper']['rowhelperfield'] as $rowhelper) {
						echo "<td ".domTT_title($rowhelper['description'])."><b>" . fixup_string($rowhelper['fielddescr']) . "</b></td>\n";
					}

					$rowcounter = 0;
					$trc = 0;

					//Use assigned $a_pkg or create an empty array to enter loop
					if (isset($a_pkg[$id][$rowhelpername])) {
						$saved_rows=$a_pkg[$id][$rowhelpername];
					} else {
						$saved_rows[] = array();
					}

					foreach ($saved_rows as $row) {
						echo "</tr>\n<tr class=\"sortable\" id=\"id_{$rowcounter}\">\n";
						foreach ($pkga['rowhelper']['rowhelperfield'] as $rowhelper) {
							unset($value);
							if ($rowhelper['value'] != "") {
								$value = $rowhelper['value'];
							}
							$fieldname = $rowhelper['fieldname'];
							// if user is editing a record, load in the data.
							if (isset($id) && $a_pkg[$id]) {
								$value = $row[$fieldname];
							}
							$type = $rowhelper['type'];
							$fieldname = $rowhelper['fieldname'];
							if ($rowhelper['size']) {
								$size = $rowhelper['size'];
							} else if ($pkga['size']) {
								$size = $pkga['size'];
							} else {
								$size = "8";
							}
							display_row($rowcounter, $value, $fieldname, $type, $rowhelper, $size);

							$text = "";
							$trc++;
						}
						$rowcounter++;
						echo "<td>";
						#echo "<a onclick=\"removeRow(this); return false;\" href=\"#\"><img border=\"0\" src=\"./themes/".$g['theme']."/images/icons/icon_x.gif\" alt=\"remove\" /></a>";
						echo "<a class='delete' href=\"#\"><img border='0' src='./themes/{$g['theme']}/images/icons/icon_x.gif' alt='delete' /></a>";
						echo "</td>\n";
					}
				?>
				</tr>
				<tbody></tbody>
				</table>

				<!-- <br /><a onclick="javascript:addRowTo('maintable'); return false;" href="#"><img border="0" src="./themes/<?#= $g['theme']; ?>/images/icons/icon_plus.gif" alt="add" /></a>-->
				<br /><a class="add" href="#"><img border="0" src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" alt="add" /></a>
				<br /><?php if ($pkga['description'] != "") echo $pkga['description']; ?>
				<script type="text/javascript">
				//<![CDATA[
				field_counter_js = <?= $fieldcounter ?>;
				rows = <?= $rowcounter ?>;
				totalrows = <?=$rowcounter; ?>;
				loaded = <?=$rowcounter; ?>;
				//typesel_change();
				//]]>
				</script>

				<?php
				break;
*/
		}
/*
		#check typehint value
		if ($pkga['typehint']) {
			echo " " . $pkga['typehint'];
		}

		#check combinefields options
		if (isset($pkga['combinefields'])) {
			// At the end of each combined-fields field we always want to end a td tag.
			$input = "</td>";
			// The tr tag end is used to end the whole set of combined fields,
			// but also if usecolspan2 is not in effect then we also put each combined field in its own tr.
			if (($pkga['combinefields'] == "end") || (!isset($pkga['usecolspan2']))) {
				$input .= "</tr>";
			}
			// At the end of the combined fields we finish up the table that encloses the combined fields...
			if ($pkga['combinefields'] == "end") {
				$input .= "</table></td></tr>";
			}
		} else {
			$input = "</td></tr>";
			if ($pkga['usecolspan2']) {
				$input .= "</tr><br />";
			}
		}

		if (isset($pkga['advancedfield']) && isset($advfield_count)) {
			$advanced .= "{$input}\n";
		} else {
			echo "{$input}\n";
		}
*/
		#increment counter
		$i++;
	}

	$form->add($section);

	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));

	// If we created and advanced section, add it here
	if(!empty($advanced)) {
		$form->addGlobal(new Form_Button(
			'showadv',
			'Show advanced options'
		))->removeClass('btn-primary')->addClass('btn-default');

		$form->add($advanced);
	}

	print($form);

	if ($pkg['note'] != "") {
		print_info_box($pkg['note']);

	if ($pkg['custom_php_after_form_command'])
		eval($pkg['custom_php_after_form_command']); ?>

<?php
}

if ($pkg['fields']['field'] != "") { ?>
<script type="text/javascript">
//<![CDATA[
	//Everything inside it will load as soon as the DOM is loaded and before the page contents are loaded
	events.push(function(){

		// Hide the advanced section

		var advanced_visible = false;

		// Hide on page load
		$('.advancedoptions').hide();

		// But show it if you click the showadv button
		$('#showadv').prop('type', 'button');

		$("#showadv").click(function() {
			advanced_visible = !advanced_visible;

			if(advanced_visible) {
				$('.advancedoptions').show();
				$("#showadv").prop('value', 'Hide advanced Options');
			}
			else {
				$('.advancedoptions').hide();
				$("#showadv").prop('value', 'Show advanced Options');
			}
		});


		//Sortable function
		jQuery('#mainarea table tbody').sortable({
			items: 'tr.sortable',
			cursor: 'move',
			distance: 10,
			opacity: 0.8,
			helper: function(e, ui) {
				ui.children().each(function() {
					jQuery(this).width(jQuery(this).width());
				});
			return ui;
			},
		});

		//delete current line jQuery function
		jQuery('#maintable td .delete').on('click', function() {
			//do not remove first line
			if (jQuery("#maintable tr").length > 2) {
				jQuery(this).parent().parent().remove();
				return false;
			}
		});

		//add new line jQuery function
		jQuery('#mainarea table .add').click(function() {
			//get table size and assign as new id
			var c_id=jQuery("#maintable tr").length;
			var new_row=jQuery("table#maintable tr:last").html().replace(/(name|id)="(\w+)(\d+)"/g,"$1='$2"+c_id+"'");
			//apply new id to created line rowhelperid
			jQuery("table#maintable tr:last").after("<tr>"+new_row+"<\/tr>");
			return false;
		});
		// Call enablechange function
		enablechange();
	});

	function enablechange() {
<?php
	foreach ($pkg['fields']['field'] as $field) {
		if (isset($field['enablefields']) or isset($field['checkenablefields'])) {
			echo "\tif (jQuery('input[name=\"{$field['fieldname']}\"]').prop('checked') == false) {\n";

			if (isset($field['enablefields'])) {
				foreach (explode(',', $field['enablefields']) as $enablefield) {
					echo "\t\tif (jQuery('input[name=\"{$enablefield}\"]').length > 0) {\n";
					echo "\t\t\tjQuery('input[name=\"{$enablefield}\"]').prop('disabled',true);\n";
					echo "\t\t}\n";
				}
			}

			if (isset($field['checkenablefields'])) {
				foreach (explode(',', $field['checkenablefields']) as $checkenablefield) {
					echo "\t\tif (jQuery('input[name=\"{$checkenablefield}\"]').length > 0) {\n";
					echo "\t\t\tjQuery('input[name=\"{$checkenablefield}\"]').prop('checked',true);\n";
					echo "\t\t}\n";
				}
			}

			echo "\t}\n\telse {\n";

			if (isset($field['enablefields'])) {
				foreach (explode(',', $field['enablefields']) as $enablefield) {
					echo "\t\tif (jQuery('input[name=\"{$enablefield}\"]').length > 0) {\n";
					echo "\t\t\tjQuery('input[name=\"{$enablefield}\"]').prop('disabled',false);\n";
					echo "\t\t}\n";
				}
			}

			if (isset($field['checkenablefields'])) {
				foreach (explode(',', $field['checkenablefields']) as $checkenablefield) {
					echo "\t\tif (jQuery('input[name=\"{$checkenablefield}\"]').length > 0) {\n";
					echo "\t\t\tjQuery('input[name=\"{$checkenablefield}\"]').prop('checked',false);\n";
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


/*
 * ROW Helpers function
 */
function display_row($trc, $value, $fieldname, $type, $rowhelper, $size) {
	global $text;
	echo "<td>\n";
	switch ($type) {
		case "input":
			echo "<input size='{$size}' name='{$fieldname}{$trc}' id='{$fieldname}{$trc}' class='formfld unknown' value=\"" . htmlspecialchars($value) . "\" />\n";
			break;
		case "checkbox":
			echo "<input size='{$size}' type='checkbox' id='{$fieldname}{$trc}' name='{$fieldname}{$trc}' value='ON' ".($value?"CHECKED":"")." />\n";
			break;
		case "password":
			echo "<input size='{$size}' type='password' id='{$fieldname}{$trc}' name='{$fieldname}{$trc}' class='formfld pwd' value=\"" . htmlspecialchars($value) . "\" />\n";
			break;
		case "textarea":
			echo "<textarea rows='2' cols='12' id='{$fieldname}{$trc}' class='formfld unknown' name='{$fieldname}{$trc}'>{$value}</textarea>\n";
		case "select":
			echo "<select style='height:22px;' id='{$fieldname}{$trc}' name='{$fieldname}{$trc}' {$title}>\n";
			foreach ($rowhelper['options']['option'] as $rowopt) {
				$text .= "<option value='{$rowopt['value']}'>{$rowopt['name']}</option>";
				echo "<option value='{$rowopt['value']}'".($rowopt['value'] == $value?" selected=\"selected\"":"").">{$rowopt['name']}</option>\n";
			}
			echo "</select>\n";
			break;
		case "interfaces_selection":
			$size = ($size ? "size=\"{$size}\"" : '');
			$multiple = '';
			if (isset($rowhelper['multiple'])) {
				$fieldname .= '[]';
				$multiple = "multiple=\"multiple\"";
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
			echo "<option><name></name><value></value></option>/n";
			foreach ($ifaces as $ifname => $iface) {
				$text .="<option value=\"{$ifname}\">$iface</option>";
				echo "<option value=\"{$ifname}\" ".(in_array($ifname, $values) ? 'selected="selected"' : '').">{$iface}</option>\n";
			}
			echo "</select>\n";
			break;
		case "select_source":
			echo "<select style='height:22px;' id='{$fieldname}{$trc}' name='{$fieldname}{$trc}'>\n";
			if (isset($rowhelper['show_disable_value'])) {
				echo "<option value='{$rowhelper['show_disable_value']}'>{$rowhelper['show_disable_value']}</option>\n";
			}
			$source_url = $rowhelper['source'];
			eval("\$pkg_source_txt = &$source_url;");
			foreach ($pkg_source_txt as $opt) {
				$source_name = ($rowhelper['source_name'] ? $opt[$rowhelper['source_name']] : $opt[$rowhelper['name']]);
				$source_value = ($rowhelper['source_value'] ? $opt[$rowhelper['source_value']] : $opt[$rowhelper['value']]);
				$text .= "<option value='{$source_value}'>{$source_name}</option>";
				echo "<option value='{$source_value}'".($source_value == $value?" selected=\"selected\"":"").">{$source_name}</option>\n";
			}
			echo "</select>\n";
			break;
	}
	echo "</td>\n";
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

/* Return html div fields */
function display_advanced_field($fieldname) {
	$div = "<div id='showadv_{$fieldname}'>\n";
	$div .= "<input type='button' onclick='show_{$fieldname}()' value='" . gettext("Advanced") . "' /> - " . gettext("Show advanced option") ."</div>\n";
	$div .= "<div id='show_{$fieldname}' style='display:none'>\n";
	return $div;
}

?>
