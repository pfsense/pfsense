<?php
/*
 * copynotice.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
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
##|*NAME=System: Copyright notice
##|*DESCR=Copyright and usage notice.
##|*MATCH=copynotice.inc*
##|-PRIV

/*
 * This file displays the copyright modal when required. (New version installed or completion of the setup wizard)
 * The copyright text may have been downloaded from the Netgate server, but if not the default text defined here
 * is used
 */

$copyrightfile = "{$g['cf_conf_path']}/copyright";

?>
<div id="usage" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
<?php
			if (file_exists($copyrightfile)) {
				require_once($copyrightfile);
			} else {
				print("<div class=\"modal-body\" style=\"background-color:#1e3f75; color:white;\">");
				print(gettext("<p align=\"center\"><font size=\"3\">Copyright &copy; 2004-2019. Electric Sheep Fencing LLC (\"ESF\"). All Rights Reserved.</p>
					<p align=\"center\"><strong>NO COMMERCIAL DISTRIBUTION OF THE PFSENSE&reg; CE SOFTWARE IS ALLOWED.</strong>
					</p>
					<p>pfSense&reg; is a federally and internationally registered trademark and service mark of ESF, and is exclusively licensed to Rubicon Communications, LLC (d/b/a Netgate) (\"Netgate\").</p>
					<p>Any unauthorized use of the pfSense&reg; mark is prohibited by United States and international law. Please refer to the Netgate <a href=\"https://www.pfsense.org/trademarks.html\" target=\"_blank\"> Trademark Usage Guidelines</a> for how to properly use the mark.</p>
					</div>
					<div class=\"modal-footer\" style=\"background-color:#1e3f75; color:white;\">
					<button type=\"button\" class=\"btn btn-xs btn-success\" data-dismiss=\"modal\" aria-label=\"Close\">
							<span aria-hidden=\"true\">Accept</span>
					</button>
					</div>"));
			}
?>

		</div>
	</div>
</div>

<?php

if (!file_exists("/tmp/nofile")) :
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$('#usage').modal({backdrop: 'static', keyboard: false});
	$('#usage').modal('show');
});
//]]>
</script>

<?php
endif;
