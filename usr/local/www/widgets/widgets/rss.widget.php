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
	$config['widgets']['rssfeed'] = $_POST['rssfeed'];
	config_write("Saved RSS Widget feed via Dashboard");
	Header("Location: /");
}

// Set a default feed if none exists
if(!$rss_feed)
	$rss_feed = "http://blog.pfsense.org";

?>

<input type="hidden" id="rss-config" name="rss-config" value="">

<div id="rss-settings" name="rss-settings" class="widgetconfigdiv" style="display:none;">
	</form>
	<form action="/widgets/widgets/rss.widget.php" method="post" name="iforma" enctype="multipart/form-data">
		<input name="rssfeed" class="formfld unknown" id="rssfeed" size="30"></p>
		<input id="submita" name="submita" type="submit" class="formbtn" value="Save" /><br/>
	</form>
</div>

<div id="rss-widgets" style="padding: 5px">
<?php
	require_once("simplexml/simplepie.inc");
	$feed = new SimplePie("{$rss_feed}");
	$feed->handle_content_type();
	
	foreach($feed->get_items() as $item) {}
		$feed = $item->get_feed();
		
		echo "<br>";
		echo "<a href='" . $item->get_permalink() . "'>" . $item->get_title() . "</a>";
		echo "<p>" . $item->get_content() . "</p>";
		echo "<p>Source: <a href='" . $item->get_permalink() . "'><img src='" . $feed->get_facicon() . "' alt='" . $feed->get_title() . "' title='" . $feed->get_title() . "' border='0' width='16' height='16'>";
		echo "<hr/>";
?>
</div>											 

<!-- needed to display the widget settings menu -->
<script language="javascript" type="text/javascript">
	selectIntLink = "rss-configure";
	textlink = document.getElementById(selectIntLink);
	textlink.style.display = "inline";
</script>
