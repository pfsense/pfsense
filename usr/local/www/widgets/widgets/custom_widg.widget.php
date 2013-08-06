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

/*
	Note Chromium does not allow display iframe links via a query string, source: https://code.google.com/p/chromium/issues/detail?id=132255
	The result will be a empty src
*/

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

if($_POST['widget_code']) {
	$config['widgets']['widget_code'] = $_POST['widget_code'];
	$config['widgets']['customwidgetheight'] = $_POST['customwidgetheight'];
	write_config("Saved custom widget codebox via dashboard");
	Header("Location: /");
}

// Use saved feed and max items
if($config['widgets']['widget_code'])
	$widget_code_s = $config['widgets']['widget_code'];

if($config['widgets']['customwidgetheight'])
	$customwidgetheight =  $config['widgets']['customwidgetheight'];

if($config['widgets']['widget_code'])
	$textarea_txt = $config['widgets']['widget_code'];

// Set a default feed if none exists
if(!$widget_code_s) {
	$widget_code_s = 'Add whatever you want here including html code. For example images or iframes.<br><br>Enjoy the power of <br><br><img src="/../themes/pfsense_ng/images/logo.gif"/>';
	$config['widgets']['widget_code'] = 'Add whatever you want here including html code. For example images or iframes.<br><br>Enjoy the power of <br><br><img src="/../themes/pfsense_ng/images/logo.gif"/>';
}

if(!$customwidgetheight)
	$customwidgetheight = 300;

if($config['widgets']['widget_code'])
	$textarea_txt =  str_replace(",", "\n", $config['widgets']['widget_code']);
else
	$textarea_txt = "";

?>

<input type="hidden" id="custom_widg-configure" name="custom_widg-configure" value=""/>

<div id="custom_widg-settings" class="widgetconfigdiv" style="display:none;">
	<form action="/widgets/widgets/custom_widg.widget.php" method="post" name="iformc">
		<textarea name="widget_code" class="formfld unknown textarea_widget" id="widget_code" rows="3"><?=$textarea_txt;?></textarea>
		<br/>
			<tr>
				<td align="right">
					Widget height: <input name="customwidgetheight" type="text" class="formfld unknown" id="customwidgetheight" size="5" value="<?=$customwidgetheight?>" autocomplete="off"> px<br/>
				</td>
			</tr>
			<tr>
				<td>
					<input id="submitc" name="submitc" type="submit" class="formbtn" value="Save" />
				</td>
			</tr>
		</table>
	</form>
</div>

<div id="customwidgetheight" style="padding: 5px; height: <?=$customwidgetheight?>px; overflow:scroll;"><?=$widget_code_s?></div>

<!-- needed to display the widget settings menu -->
<script type="text/javascript">
//<![CDATA[
	selectIntLink = "custom_widg-configure";
	textlink = document.getElementById(selectIntLink);
	textlink.style.display = "inline";
//]]>
</script>

