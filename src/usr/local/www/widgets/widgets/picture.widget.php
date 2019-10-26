<?php
/*
 * picture.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
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

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");


if ($_GET['getpic']=="true") {
	$wk = basename($_GET['widgetkey']);
	$image_filename = "/conf/widget_image.{$wk}";
	if (empty($wk) ||
	    !isset($user_settings['widgets'][$wk]) ||
	    !is_array($user_settings['widgets'][$wk]) ||
	    !file_exists($image_filename)) {
		echo null;
		exit;
	}

	/* Do not rely on filename to determine image type. */
	$pic_type = is_supported_image($image_filename);
	if (empty($pic_type)) {
		exit;
	}

	if ($user_settings['widgets'][$wk]['picturewidget']) {
		if (file_exists($image_filename)) {
			$data = file_get_contents($image_filename);
		} else {
			$data = "";
		}
	}

	header("Content-Disposition: inline; filename=\"" . basename($image_filename) . "\"");
	header("Content-Type: " . image_type_to_mime_type($pic_type));
	header("Content-Length: " . strlen($data));
	echo $data;
	exit;
}

if ($_POST['widgetkey']) {
	$wk = basename($_POST['widgetkey']);

	// Valid widgetkey format is "picture-nnn". Check it looks reasonable
	if (preg_match('/^picture-[0-9]{1,3}$/', $wk) == 1) {
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
				// Make sure they upload an image and not some other file
				if (!is_supported_image($_FILES['pictfile']['tmp_name'])) {
					die("Not a supported image type");
				}
				$picname = basename($_FILES['uploadedfile']['name']);
				$user_settings['widgets'][$wk]['picturewidget'] = "/conf/widget_image";
				file_put_contents("/conf/widget_image.{$wk}", $data);
				$user_settings['widgets'][$wk]['picturewidget_filename'] = $_FILES['pictfile']['name'];
			}
		}

		save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Picture widget saved via Dashboard."));
	}

	header("Location: /index.php");
	exit;
}

?>
<?php
if($user_settings['widgets'][$widgetkey]["picturewidget"] != null){?>
<a href="/widgets/widgets/picture.widget.php?getpic=true&widgetkey=<?=htmlspecialchars($widgetkey)?>" target="_blank">
	<img style="width:100%; height:100%" src="/widgets/widgets/picture.widget.php?getpic=true&widgetkey=<?=htmlspecialchars($widgetkey)?>" alt="picture" />
</a>
<?php } ?>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>"
	<?php echo "class= " . "'" . "panel-footer". ($user_settings['widgets'][$widgetkey]["picturewidget"] != null ? " collapse": ""). "'";  ?>>

<form action="/widgets/widgets/picture.widget.php" method="post" enctype="multipart/form-data" class="form-horizontal">
	<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
	<?=gen_customwidgettitle_div($widgetconfig['title']); ?>
	<div class="form-group">
		<label for="pictfile" class="col-sm-4 control-label"><?=gettext('New pictures:')?> </label>
		<div class="col-sm-6">
			<input id="pictfile" name="pictfile" type="file" class="form-control" accept="image/*"/>
		</div>
	</div>
	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-6">
			<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
		</div>
	</div>
</form>
