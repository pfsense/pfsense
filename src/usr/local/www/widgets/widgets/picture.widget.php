<?php
/*
 * picture.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

if ($_GET['getpic']=="true") {
	$pic_type_s = explode(".", $user_settings['widgets'][$_GET['widgetkey']]['picturewidget_filename']);
	$pic_type = $pic_type_s[1];
	if ($user_settings['widgets'][$_GET['widgetkey']]['picturewidget']) {
		$data = base64_decode($user_settings['widgets'][$_GET['widgetkey']]['picturewidget']);
	}
	header("Content-Disposition: inline; filename=\"{$user_settings['widgets'][$_GET['widgetkey']]['picturewidget_filename']}\"");
	header("Content-Type: image/{$pic_type}");
	header("Content-Length: " . strlen($data));
	echo $data;
	exit;
}

if ($_POST['widgetkey']) {
	set_customwidgettitle($user_settings);

	if (is_uploaded_file($_FILES['pictfile']['tmp_name'])) {
		/* read the file contents */
		$fd_pic = fopen($_FILES['pictfile']['tmp_name'], "rb");
		while (($buf=fread($fd_pic, 8192)) != '') {
		    // Here, $buf is guaranteed to contain data
		    $data .= $buf;
		}
		fclose($fd_pic);
		if (!$data) {
			log_error("Warning, could not read file " . $_FILES['pictfile']['tmp_name']);
			die("Could not read temporary file");
		} else {
			$picname = basename($_FILES['uploadedfile']['name']);
			$user_settings['widgets'][$_POST['widgetkey']]['picturewidget'] = base64_encode($data);
			$user_settings['widgets'][$_POST['widgetkey']]['picturewidget_filename'] = $_FILES['pictfile']['name'];
		}
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Picture widget saved via Dashboard."));
	header("Location: /index.php");
	exit;
}

?>
<a href="/widgets/widgets/picture.widget.php?getpic=true&widgetkey=<?=$widgetkey?>" target="_blank">
	<img style="width:100%; height:100%" src="/widgets/widgets/picture.widget.php?getpic=true&widgetkey=<?=$widgetkey?>" alt="picture" />
</a>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">

<form action="/widgets/widgets/picture.widget.php" method="post" enctype="multipart/form-data" class="form-horizontal">
	<input type="hidden" name="widgetkey" value="<?=$widgetkey; ?>">
	<?=gen_customwidgettitle_div($widgetconfig['title']); ?>
	<div class="form-group">
		<label for="pictfile" class="col-sm-4 control-label"><?=gettext('New picture:')?> </label>
		<div class="col-sm-6">
			<input id="pictfile" name="pictfile" type="file" class="form-control" />
		</div>
	</div>
	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-6">
			<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
		</div>
	</div>
</form>
