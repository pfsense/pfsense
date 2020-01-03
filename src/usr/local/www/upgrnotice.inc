<?php
/*
 * copynotice.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
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

##|+PRIV
##|*IDENT=page-system-copyright
##|*NAME=System: Upgrade notice
##|*DESCR=Optional upgrade notice notice.
##|*MATCH=upgrnotice.inc*
##|-PRIV

$logincssfile = "#1e3f75";

if (isset($user_settings['webgui']['logincss']) && strlen($user_settings['webgui']['logincss']) == 6) {
	$logincssfile = "#" . $user_settings['webgui']['logincss'];
}

?>
<div id="upgrnotice" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">

			<div class="modal-body" style="background-color:<?=$logincssfile?>; color:white;">
<?php
			print("<div align=\"center\" style=\"font-size:24px;\"><strong>NOTICE</strong></div><br>");
			print(file_get_contents("/tmp/package_ui_notice"));
?>
			</div>
			<div class="modal-footer" style="background-color:<?=$logincssfile?>; color:white;">
				<button type="button" class="btn btn-xs btn-success" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">Accept</span>
				</button>
			</div>
		</div>
	</div>
</div>


<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$('#upgrnotice').modal({backdrop: 'static', keyboard: false});
	$('#upgrnotice').modal('show');
});
//]]>
</script>
