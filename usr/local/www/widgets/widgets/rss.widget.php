<?php
/*
        $Id$
        Copyright 2009 Scott Ullrich
        Part of pfSense widgets (https://www.pfsense.org)

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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

if($_POST['rssfeed']) {
	$config['widgets']['rssfeed'] = str_replace("\n", ",", $_POST['rssfeed']);
	$config['widgets']['rssmaxitems'] = str_replace("\n", ",", $_POST['rssmaxitems']);
	$config['widgets']['rsswidgetheight'] = $_POST['rsswidgetheight'];
	$config['widgets']['rsswidgettextlength'] = $_POST['rsswidgettextlength'];
	write_config("Saved RSS Widget feed via Dashboard");
	Header("Location: /");
}

// Use saved feed and max items
if($config['widgets']['rssfeed'])
	$rss_feed_s = explode(",", $config['widgets']['rssfeed']);

if($config['widgets']['rssmaxitems'])
	$max_items =  $config['widgets']['rssmaxitems'];

if($config['widgets']['rsswidgetheight'])
	$rsswidgetheight =  $config['widgets']['rsswidgetheight'];

if($config['widgets']['rsswidgettextlength'])
	$rsswidgettextlength =  $config['widgets']['rsswidgettextlength'];

// Set a default feed if none exists
if(!$rss_feed_s) {
	$rss_feed_s = "https://blog.pfsense.org";
	$config['widgets']['rssfeed'] = "https://blog.pfsense.org";
}

if(!$max_items)
	$max_items = 10;

if(!$rsswidgetheight)
	$rsswidgetheight = 300;

if(!$rsswidgettextlength)
	$rsswidgettextlength = 140;	// oh twitter, how do we love thee?

if($config['widgets']['rssfeed'])
	$textarea_txt =  str_replace(",", "\n", $config['widgets']['rssfeed']);
else
	$textarea_txt = "";

?>

<input type="hidden" id="rss-config" name="rss-config" value="" />

<div id="rss-settings" class="widgetconfigdiv" style="display:none;">
	<form action="/widgets/widgets/rss.widget.php" method="post" name="iformc">
		<textarea name="rssfeed" class="formfld unknown textarea_widget" id="rssfeed" cols="40" rows="3"><?=$textarea_txt;?></textarea>
		<br />
		<table summary="rss widget">
			<tr>
				<td align="right">
					Display number of items:
				</td>
				<td>
					<select name='rssmaxitems' id='rssmaxitems'>
						<option value='<?= $max_items ?>'><?= $max_items ?></option>
						<?php
							for($x=100; $x<5100; $x=$x+100)
								echo "<option value='{$x}'>{$x}</option>\n";
						?>
					</select>
				</td>
			</tr>
			<tr>
				<td align="right">
					Widget height:
				</td>
				<td>
					<select name='rsswidgetheight' id='rsswidgetheight'>
						<option value='<?= $rsswidgetheight ?>'><?= $rsswidgetheight ?>px</option>
						<?php
							for($x=100; $x<5100; $x=$x+100)
								echo "<option value='{$x}'>{$x}px</option>\n";
						?>
					</select>
				</td>
			</tr>
			<tr>
				<td align="right">
					Show how many characters from story:
				</td>
				<td>
					<select name='rsswidgettextlength' id='rsswidgettextlength'>
						<option value='<?= $rsswidgettextlength ?>'><?= $rsswidgettextlength ?></option>
						<?php
							for($x=10; $x<5100; $x=$x+10)
								echo "<option value='{$x}'>{$x}</option>\n";
						?>
					</select>
				</td>
			</tr>
			<tr>
				<td>
					&nbsp;
				</td>
				<td>
					<input id="submitc" name="submitc" type="submit" class="formbtn" value="Save" />
				</td>
			</tr>
		</table>
	</form>
</div>

<div id="rss-widgets" style="padding: 5px; height: <?=$rsswidgetheight?>px; overflow:scroll;">
<?php
	if(!is_dir("/tmp/simplepie")) {
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
	$feed->set_output_encoding('latin-1');
	$feed->handle_content_type();
	$counter = 1;
	foreach($feed->get_items() as $item) {
		$feed = $item->get_feed();
		$feed->strip_htmltags();
		echo "<a target='blank' href='" . $item->get_permalink() . "'>" . $item->get_title() . "</a><br />";
		$content = $item->get_content();
		$content = strip_tags($content);
		echo textLimit($content, $rsswidgettextlength) . "<br />";
		echo "Source: <a target='_blank' href='" . $item->get_permalink() . "'><img src='" . $feed->get_favicon() . "' alt='" . $feed->get_title() . "' title='" . $feed->get_title() . "' border='0' width='16' height='16' /></a><br />";
		$counter++;
		if($counter > $max_items)
			break;
		echo "<hr/>";
	}
?>
</div>

<!-- needed to display the widget settings menu -->
<script type="text/javascript">
//<![CDATA[
	selectIntLink = "rss-configure";
	textlink = document.getElementById(selectIntLink);
	textlink.style.display = "inline";
//]]>
</script>
