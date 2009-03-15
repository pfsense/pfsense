<?php
/*
        $Id$
        Copyright 2009 Scott Ullrich
        Part of pfSense widgets (www.pfsense.com)

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

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

if($_POST) {
	if($_POST['rssfeed'] <> "")
		$config['widgets']['rssfeed'] = $_POST['rssfeed'];
	if($_POST['rssmaxitems'] <> "")
		$config['widgets']['rssmaxitems'] = $_POST['rssmaxitems'];
	write_config("Saved RSS Widget feed via Dashboard");
	Header("Location: /");
}

// Use saved feed and max items
if($config['widgets']['rssfeed'])
	$rss_feed = $config['widgets']['rssfeed'];
if($config['widgets']['rssmaxitems'])
	$max_items =  $config['widgets']['rssmaxitems'];

// Set a default feed if none exists
if(!$rss_feed)
	$rss_feed = "http://blog.pfsense.org";
	
if(!$max_items)
	$max_items = 5;

?>

<input type="hidden" id="rss-config" name="rss-config" value="">

<div id="rss-settings" name="rss-settings" class="widgetconfigdiv" style="display:none;">
	</form>
	<form action="/widgets/widgets/rss.widget.php" method="post" name="iforma">
		<input name="rssfeed" class="formfld unknown" id="rssfeed" size="30"></p>
		Display number of items: <select name='rssmaxitems' id='rssmaxitems'>
			<?php
				for($x=1; $x<100; $x++) {
					echo "<option value='{$x}'>$x</option>\n";
				}
			?>
		</select>
		<input id="submita" name="submita" type="submit" class="formbtn" value="Save" /><br/>
	</form>
</div>

<div id="rss-widgets" style="padding: 5px">
<?php
	require_once("simplepie/simplepie.inc");
	function textLimit($string, $length, $replacer = '...')
	{
	  if(strlen($string) > $length)
	  return (preg_match('/^(.*)\W.*$/', substr($string, 0, $length+1), $matches) ? $matches[1] : substr($string, 0, $length)) . $replacer;

	  return $string;
	}	
	$feed = new SimplePie();
	$feed->set_cache_location("/tmp/simplepie/");
	$feed->set_feed_url("{$rss_feed}");
	$feed->init();
	$feed->set_output_encoding('latin-1');
	$feed->handle_content_type();
	if(!is_dir("/tmp/simplepie")) {
		mkdir("/tmp/simplepie");
		mkdir("/tmp/simplepie/cache");
	}
	exec("chmod a+rw/tmp/simplepie/.");
	exec("chmod a+rw/tmp/simplepie/cache/.");
	$counter = 1;
	foreach($feed->get_items() as $item) {
		$feed = $item->get_feed();
		$feed->strip_htmltags();
		echo "<a target='_new' href='" . $item->get_permalink() . "'>" . $item->get_title() . "</a><br/>";
		$content = $item->get_content();
//		if(strlen($content) > 140)
//			$content = substr($content,0,140) . " (cont)...";
		$content = strip_tags($content);
		echo textLimit($content, 140) . "<br/>";
		echo "Source: <a target='_new' href='" . $item->get_permalink() . "'><img src='" . $feed->get_favicon() . "' alt='" . $feed->get_title() . "' title='" . $feed->get_title() . "' border='0' width='16' height='16'><br/>";
		echo "<hr/>";
		$counter++;
		if($counter > $max_items)
			break;
	}
?>
</div>				 

<!-- needed to display the widget settings menu -->
<script language="javascript" type="text/javascript">
	selectIntLink = "rss-configure";
	textlink = document.getElementById(selectIntLink);
	textlink.style.display = "inline";
</script>
