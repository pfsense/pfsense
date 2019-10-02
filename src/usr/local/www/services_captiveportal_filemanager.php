<?php
/*
 * services_captiveportal_filemanager.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005-2006 Jonathan De Graeve (jonathan.de.graeve@imelda.be)
 * Copyright (c) 2005-2006 Paul Taylor (paultaylor@winn-dixie.com)
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
##|*IDENT=page-services-captiveportal-filemanager
##|*NAME=Services: Captive Portal: File Manager
##|*DESCR=Allow access to the 'Services: Captive Portal: File Manager' page.
##|*MATCH=services_captiveportal_filemanager.php*
##|-PRIV

function cpelementscmp($a, $b) {
	return strcasecmp($a['name'], $b['name']);
}

function cpelements_sort() {
	global $config, $cpzone;

	usort($config['captiveportal'][$cpzone]['element'], "cpelementscmp");
}

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("captiveportal.inc");

$cpzone = $_REQUEST['zone'];

$cpzone = strtolower(htmlspecialchars($cpzone));

if (empty($cpzone)) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

init_config_arr(array('captiveportal', $cpzone, 'element'));
$a_cp = &$config['captiveportal'];
$a_element = &$a_cp[$cpzone]['element'];

$pgtitle = array(gettext("Services"), gettext("Captive Portal"), $a_cp[$cpzone]['zone'], gettext("File Manager"));
$pglinks = array("", "services_captiveportal_zones.php", "services_captiveportal.php?zone=" . $cpzone, "@self");
$shortcut_section = "captiveportal";

// Calculate total size of all files
$total_size = 0;
for ($i = 0; $i < count($a_element); $i++) {

	// if the image in the directory does not exist remove it from config
	if(!file_exists("{$g['captiveportal_path']}/" . $a_element[$i]['name'])){
		@unlink("{$g['captiveportal_element_path']}/" . $a_element[$i]['name']);
		// remove from list and reorder array.
		unset($a_element[$i]);
		$a_element = array_values($a_element);
		continue;
	}
	if(!isset($a_element[$i]['nocontent'])) {
		$total_size += $a_element[$i]['size'];
	}

}

if ($_POST['Submit']) {
	unset($input_errors);

	if (is_uploaded_file($_FILES['new']['tmp_name'])) {

		if ((!stristr($_FILES['new']['name'], "captiveportal-")) && ($_FILES['new']['name'] != 'favicon.ico')) {
			$name = "captiveportal-" . $_FILES['new']['name'];
		} else {
			$name = $_FILES['new']['name'];
		}
		$size = filesize($_FILES['new']['tmp_name']);

		// is there already a file with that name?
		foreach ($a_element as $element) {
			if ($element['name'] == $name) {
				$input_errors[] = sprintf(gettext("A file with the name '%s' already exists."), $name);
				break;
			}
		}

		// check total file size
		if (($total_size + $size) > $g['captiveportal_element_sizelimit']) {
			$input_errors[] = sprintf(gettext("The total size of all files uploaded may not exceed %s."),
				format_bytes($g['captiveportal_element_sizelimit']));
		}

		if (!$input_errors) {
			$element = array();
			$element['name'] = $name;
			$element['size'] = $size;
			$element['content'] = base64_encode(file_get_contents($_FILES['new']['tmp_name']));

			$a_element[] = $element;
			cpelements_sort();

			write_config();
			captiveportal_write_elements();
			header("Location: services_captiveportal_filemanager.php?zone={$cpzone}");
			exit;
		}
	}
} else if (($_POST['act'] == "del") && !empty($cpzone) && $a_element[$_POST['id']]) {
	@unlink("{$g['captiveportal_element_path']}/" . $a_element[$_POST['id']]['name']);
	@unlink("{$g['captiveportal_path']}/" . $a_element[$_POST['id']]['name']);
	unset($a_element[$_POST['id']]);
	write_config();
	header("Location: services_captiveportal_filemanager.php?zone={$cpzone}");
	exit;
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Configuration"), false, "services_captiveportal.php?zone={$cpzone}");
$tab_array[] = array(gettext("MACs"), false, "services_captiveportal_mac.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed IP Addresses"), false, "services_captiveportal_ip.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed Hostnames"), false, "services_captiveportal_hostname.php?zone={$cpzone}");
$tab_array[] = array(gettext("Vouchers"), false, "services_captiveportal_vouchers.php?zone={$cpzone}");
$tab_array[] = array(gettext("File Manager"), true, "services_captiveportal_filemanager.php?zone={$cpzone}");
display_top_tabs($tab_array, true);

if ($_REQUEST['act'] == 'add') {

	$form = new Form(false);

	$form->setMultipartEncoding();

	$section = new Form_Section('Upload a New File');

	$form->addGlobal(new Form_Input(
		'zone',
		null,
		'hidden',
		$cpzone
	));

	$section->addInput(new Form_Input(
		'new',
		'File',
		'file'
	));

	$form->add($section);

	$form->addGlobal(new Form_Button(
		'Submit',
		'Upload',
		null,
		'fa-upload'
	))->addClass('btn-primary');

	print($form);
}

if (is_array($a_cp[$cpzone]['element'])):
?>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("Installed Files")?></h2></div>
		<div class="panel-body">
			<div class="table-responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?=gettext("Name"); ?></th>
							<th><?=gettext("Size"); ?></th>
							<th><?=gettext("Actions"); ?></th>
						</tr>
					</thead>
					<tbody>
<?php
	$i = 0;
	foreach ($a_cp[$cpzone]['element'] as $element):
?>
						<tr>
							<td><?=htmlspecialchars($element['name'])?></td>
							<td><?=format_bytes($element['size'])?></td>
							<td>
								<a class="fa fa-trash"	title="<?=gettext("Delete file")?>" href="services_captiveportal_filemanager.php?zone=<?=$cpzone?>&amp;act=del&amp;id=<?=$i?>" usepost></a>
							</td>
						</tr>
<?php
		$i++;
	endforeach;

	if ($total_size > 0) :
?>
						<tr>
							<th>
								<?=gettext("Total");?>
							</th>
							<th>
								<?=format_bytes($total_size);?>
							</th>
							<th></th>
						</tr>
<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
<?php
endif;

?>
	   <nav class="action-buttons">
<?php if (!$_REQUEST['act'] == 'add'): ?>
			<a href="services_captiveportal_filemanager.php?zone=<?=$cpzone?>&amp;act=add" class="btn btn-success">
		   		<i class="fa fa-plus icon-embed-btn"></i>
		   		<?=gettext("Add")?>
		   	</a>
<?php endif; ?>
	   </nav>
<?php
// The notes displayed on the page are large, the page content comparatively small. A "Note" button
// is provided so that you only see the notes if you ask for them
?>
<div class="infoblock panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Notes");?></h2></div>
	<div class="panel-body">
	<?=gettext("Any files that are uploaded here with the filename prefix of captiveportal- will " .
	"be made available in the root directory of the captive portal HTTP(S) server. " .
	"An icon file named favicon.ico may also be uploaded and will remain without prefix. " .
	"They may be referenced directly from the portal page HTML code using relative paths. " .
	"Example: An image uploaded with the name 'captiveportal-test.jpg' using the " .
	"file manager can then be included in the portal page like this:")?><br /><br />
	<pre>&lt;img src=&quot;captiveportal-test.jpg&quot; width=... height=...&gt;</pre><br />
	<?=gettext("In addition, .php files can also be uploaded for execution.	The filename can be passed " .
	"to the custom page from the initial page by using text similar to:")?><br /><br />
	<pre>&lt;a href="/captiveportal-aup.php?zone=$PORTAL_ZONE$&amp;redirurl=$PORTAL_REDIRURL$"&gt;<?=gettext("Acceptable usage policy"); ?>&lt;/a&gt;</pre><br />
	<?=sprintf(gettext("The total size limit for all files is %s."), format_bytes($g['captiveportal_element_sizelimit']))?>
	</div>
</div>
<?php
include("foot.inc");
