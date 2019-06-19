<?php
/*
 * picture.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2019 Rubicon Communications, LLC (Netgate)
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
	$pic_type_s = explode(".", $user_settings['widgets'][$_GET['widgetkey']]['picturewidget_filename']);
	$pic_type = $pic_type_s[1];

	if ($user_settings['widgets'][$_GET['widgetkey']]['picturewidget']) {
		if (file_exists("/conf/widget_image." . $_GET['widgetkey'])) {
			$data = file_get_contents("/conf/widget_image." . $_GET['widgetkey']);
		} else {
			$data = "";
		}
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
			// Make sure they upload an image and not some other file
			$img_info =getimagesize($_FILES['pictfile']['tmp_name']);
			if($img_info === FALSE){
				die("Unable to determine image type of uploaded file");
			}
			if(($img_info[2] !== IMAGETYPE_GIF) && ($img_info[2] !== IMAGETYPE_JPEG) && ($img_info[2] !== IMAGETYPE_PNG)){
				die("Not a gif/jpg/png");
			}
			$picname = basename($_FILES['uploadedfile']['name']);
			$user_settings['widgets'][$_POST['widgetkey']]['picturewidget'] = "/conf/widget_image";
			file_put_contents("/conf/widget_image." . $_POST['widgetkey'], $data);
			$user_settings['widgets'][$_POST['widgetkey']]['picturewidget_filename'] = $_FILES['pictfile']['name'];
		}
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Picture widget saved via Dashboard."));
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
