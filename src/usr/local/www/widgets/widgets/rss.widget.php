<?php
/*
	rss.widget.php
*/
/* ====================================================================
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

if ($_POST['rssfeed']) {
	$config['widgets']['rssfeed'] = str_replace("\n", ",", htmlspecialchars($_POST['rssfeed'], ENT_QUOTES | ENT_HTML401));
	$config['widgets']['rssmaxitems'] = str_replace("\n", ",", htmlspecialchars($_POST['rssmaxitems'], ENT_QUOTES | ENT_HTML401));
	$config['widgets']['rsswidgetheight'] = htmlspecialchars($_POST['rsswidgetheight'], ENT_QUOTES | ENT_HTML401);
	$config['widgets']['rsswidgettextlength'] = htmlspecialchars($_POST['rsswidgettextlength'], ENT_QUOTES | ENT_HTML401);
	write_config("Saved RSS Widget feed via Dashboard");
	header("Location: /");
}

// Use saved feed and max items
if ($config['widgets']['rssfeed']) {
	$rss_feed_s = explode(",", $config['widgets']['rssfeed']);
}

if ($config['widgets']['rssmaxitems']) {
	$max_items =  $config['widgets']['rssmaxitems'];
}

if (is_numeric($config['widgets']['rsswidgetheight'])) {
	$rsswidgetheight =	$config['widgets']['rsswidgetheight'];
}

if (is_numeric($config['widgets']['rsswidgettextlength'])) {
	$rsswidgettextlength =	$config['widgets']['rsswidgettextlength'];
}

// Set a default feed if none exists
if (!$rss_feed_s) {
	$rss_feed_s = "https://blog.pfsense.org";
	$config['widgets']['rssfeed'] = "https://blog.pfsense.org";
}

if (!$max_items) {
	$max_items = 10;
}

if (!$rsswidgetheight) {
	$rsswidgetheight = 300;
}

if (!$rsswidgettextlength) {
	$rsswidgettextlength = 140; // oh twitter, how do we love thee?
}

if ($config['widgets']['rssfeed']) {
	$textarea_txt =	 str_replace(",", "\n", $config['widgets']['rssfeed']);
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
		if(strlen($string) > $length)
			return (preg_match('/^(.*)\W.*$/', substr($string, 0, $length+1), $matches) ? $matches[1] : substr($string, 0, $length)) . $replacer;
		return $string;
	}
	$feed = new SimplePie();
	$feed->set_cache_location("/tmp/simplepie/");
	$feed->set_feed_url($rss_feed_s);
	$feed->init();
	$feed->handle_content_type();
	$counter = 1;
	foreach($feed->get_items(0, $max_items) as $item) {
		$feed = $item->get_feed();
		$feed->strip_htmltags();
		$content = $item->get_content();
		$content = strip_tags($content);
?>
	<a href="<?=$item->get_permalink()?>" target="_blank" class="list-group-item">
		<h4 class="list-group-item-heading">
			<img src="<?=$feed->get_favicon()?>" title="Source: <?=$feed->get_title()?>" width="16" height="16" />
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
</div><div class="panel-footer collapse">

<form action="/widgets/widgets/rss.widget.php" method="post" class="form-horizontal">
	<div class="form-group">
		<label for="rssfeed" class="col-sm-3 control-label">Feeds</label>
		<div class="col-sm-6">
			<textarea name="rssfeed" class="form-control"><?=$textarea_txt;?></textarea>
		</div>
	</div>

	<div class="form-group">
		<label for="rssmaxitems" class="col-sm-3 control-label"># Stories</label>
		<div class="col-sm-6">
			<input type="number" name="rssmaxitems" value="<?=$max_items?>" min="1" max="100" class="form-control" />
		</div>
	</div>

	<div class="form-group">
		<label for="rsswidgetheight" class="col-sm-3 control-label">Widget height</label>
		<div class="col-sm-6">
			<input type="number" name="rsswidgetheight" value="<?=$rsswidgetheight?>" min="100" max="2500" step="100" class="form-control" />
		</div>
	</div>

	<div class="form-group">
		<label for="rsswidgettextlength" class="col-sm-3 control-label">Content limit</label>
		<div class="col-sm-6">
			<input type="number" name="rsswidgettextlength" value="<?=$rsswidgettextlength?>" min="100" max="5000" step="10" class="form-control" />
		</div>
	</div>

	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-6">
			<button type="submit" class="btn btn-default">Save</button>
		</div>
	</div>
</form>