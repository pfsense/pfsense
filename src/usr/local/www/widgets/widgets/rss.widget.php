<?php
/*
 * rss.widget.php
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

if ($_POST['rssfeed']) {
	$user_settings['widgets']['rssfeed'] = str_replace("\n", ",", htmlspecialchars($_POST['rssfeed'], ENT_QUOTES | ENT_HTML401));
	$user_settings['widgets']['rssmaxitems'] = str_replace("\n", ",", htmlspecialchars($_POST['rssmaxitems'], ENT_QUOTES | ENT_HTML401));
	$user_settings['widgets']['rsswidgetheight'] = htmlspecialchars($_POST['rsswidgetheight'], ENT_QUOTES | ENT_HTML401);
	$user_settings['widgets']['rsswidgettextlength'] = htmlspecialchars($_POST['rsswidgettextlength'], ENT_QUOTES | ENT_HTML401);
	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved RSS Widget feed via Dashboard."));
	header("Location: /");
}

// Use saved feed and max items
if ($user_settings['widgets']['rssfeed']) {
	$rss_feed_s = explode(",", $user_settings['widgets']['rssfeed']);
}

if ($user_settings['widgets']['rssmaxitems']) {
	$max_items =  $user_settings['widgets']['rssmaxitems'];
}

if (is_numeric($user_settings['widgets']['rsswidgetheight'])) {
	$rsswidgetheight =	$user_settings['widgets']['rsswidgetheight'];
}

if (is_numeric($user_settings['widgets']['rsswidgettextlength'])) {
	$rsswidgettextlength =	$user_settings['widgets']['rsswidgettextlength'];
}

// Set a default feed if none exists
if (!$rss_feed_s) {
	$rss_feed_s = "https://blog.pfsense.org";
	$user_settings['widgets']['rssfeed'] = "https://blog.pfsense.org";
}

if (!$max_items || !is_numeric($max_items)) {
	$max_items = 10;
}

if (!$rsswidgetheight || !is_numeric($rsswidgetheight)) {
	$rsswidgetheight = 300;
}

if (!$rsswidgettextlength || !is_numeric($rsswidgettextlength)) {
	$rsswidgettextlength = 140; // oh twitter, how do we love thee?
}

if ($user_settings['widgets']['rssfeed']) {
	$textarea_txt =	 str_replace(",", "\n", $user_settings['widgets']['rssfeed']);
} else {
	$textarea_txt = "";
}

?>
<div class="list-group" style="height: <?=$rsswidgetheight?>px; overflow:scroll;">
<?php
	if (!is_dir("/tmp/simplepie")) {
		mkdir("/tmp/simplepie");
		mkdir("/tmp/simplepie/cache");
	}
	exec("chmod a+rw /tmp/simplepie/.");
	exec("chmod a+rw /tmp/simplepie/cache/.");
	require_once("simplepie/simplepie.inc");
	function textLimit($string, $length, $replacer = '...') {
		if (strlen($string) > $length) {
			return (preg_match('/^(.*)\W.*$/', substr($string, 0, $length+1), $matches) ? $matches[1] : substr($string, 0, $length)) . $replacer;
		}
		return $string;
	}
	$feed = new SimplePie();
	$feed->set_cache_location("/tmp/simplepie/");
	$feed->set_feed_url($rss_feed_s);
	$feed->init();
	$feed->handle_content_type();
	$counter = 1;
	foreach ($feed->get_items(0, $max_items) as $item) {
		$feed = $item->get_feed();
		$feed->strip_htmltags();
		$content = $item->get_content();
		$content = strip_tags($content);
?>
	<a href="<?=$item->get_permalink()?>" target="_blank" class="list-group-item">
		<h4 class="list-group-item-heading">
			<img src="pfs-mini.png" title="Source: <?=$feed->get_title()?>" alt="" width="16" height="16" />
			<?=$item->get_title()?>
		</h4>
		<p class="list-group-item-text">
			<?=textLimit($content, $rsswidgettextlength)?>
			<br />
		</p>
	</a>
<?php
	}
?>

</div>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="widget-<?=$widgetname?>_panel-footer" class="panel-footer collapse">

<form action="/widgets/widgets/rss.widget.php" method="post" class="form-horizontal">
	<div class="form-group">
		<label for="rssfeed" class="col-sm-3 control-label"><?=gettext('Feeds')?></label>
		<div class="col-sm-6">
			<textarea id="rssfeed" name="rssfeed" class="form-control"><?=$textarea_txt;?></textarea>
		</div>
	</div>

	<div class="form-group">
		<label for="rssmaxitems" class="col-sm-3 control-label"><?=gettext('# Stories')?></label>
		<div class="col-sm-6">
			<input type="number" id="rssmaxitems" name="rssmaxitems" value="<?=$max_items?>" min="1" max="100" class="form-control" />
		</div>
	</div>

	<div class="form-group">
		<label for="rsswidgetheight" class="col-sm-3 control-label"><?=gettext('Widget height')?></label>
		<div class="col-sm-6">
			<input type="number" id="rsswidgetheight" name="rsswidgetheight" value="<?=$rsswidgetheight?>" min="100" max="2500" step="100" class="form-control" />
		</div>
	</div>

	<div class="form-group">
		<label for="rsswidgettextlength" class="col-sm-3 control-label"><?=gettext('Content limit')?></label>
		<div class="col-sm-6">
			<input type="number" id="rsswidgettextlength" name="rsswidgettextlength" value="<?=$rsswidgettextlength?>" min="100" max="5000" step="10" class="form-control" />
		</div>
	</div>

	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-6">
			<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
		</div>
	</div>
</form>
