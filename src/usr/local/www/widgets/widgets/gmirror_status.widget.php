<?php
/*
 * gmirror_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
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

require_once("guiconfig.inc");
require_once("gmirror.inc");

?>
<div id="gmirror_status">
	<?=gmirror_html_status()?>
</div>

<?php if ($widget_first_instance): ?>
<script type="text/javascript">
//<![CDATA[
function gmirrorStatusUpdateFromServer() {
	$.ajax({
		type: 'get',
		url: '/widgets/widgets/gmirror_status.widget.php',
		dataType: 'html',
		dataFilter: function(raw){
			// We reload the entire widget, strip this block of javascript from it
			return raw.replace(/<script>([\s\S]*)<\/script>/gi, '');
		},
		success: function(data){
			$('[id="gmirror_status"]').html(data);
		},
		error: function(){
			$('[id="gmirror_status"]').html("<div class=\"alert alert-danger\"><?=gettext('Unable to retrieve status'); ?></div>");
		}
	});
}

events.push(function(){
	setInterval('gmirrorStatusUpdateFromServer()', 60*1000);
});
//]]>
</script>
<?php endif; ?>
