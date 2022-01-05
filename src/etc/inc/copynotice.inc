<?php
/*
 * copynotice.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
<?php
			if (file_exists($copyrightfile) && (filesize($copyrightfile) > 0)) {
				require_once($copyrightfile);
			} else {
?>
			<div id="main1" class="modal-body modal-lg modal-dialog-scrollable" style="background-color:#1e3f75; color:white;">
				<p style="font-size:16px">
					<b>Copyright and Trademark Notices.</b>
				</p>
				<p>
					Copyright<sup>&copy;</sup> 2004-2016. Electric Sheep Fencing, LLC ("ESF").  All Rights Reserved. <br />
					Copyright<sup>&copy;</sup> 2014-2022. Rubicon Communications, LLC d/b/a Netgate ("Netgate").  All Rights Reserved.
				</p>
				<p>
					All logos, text, and content of ESF and/or Netgate, including underlying HTML code, designs, and graphics used and/or depicted herein are protected under United States and international copyright and trademark laws and treaties, and may not be used or reproduced without the prior express written permission of ESF and/or Netgate.
				</p>
				<p>
					"pfSense" is a registered trademark of ESF, exclusively licensed to Netgate, and may not be used without the prior express written permission of ESF and/or Netgate.  All other trademarks shown herein are owned by the respective companies or persons indicated.
				</p>

				<p>
					pfSense<sup>&copy;</sup> software is open source and distributed under the Apache 2.0 license.  However, no commercial distribution of ESF and/or Netgate software is allowed without the prior written consent of ESF and/or Netgate.
				</p>
				<p>
					ESF and/or Netgate make no warranty of any kind, including but not limited to the implied warranties of merchantability and fitness for a particular purpose. ESF and/or Netgate shall not be liable for errors contained herein or for any direct, indirect, special, incidental or consequential damages in connection with the furnishing, performance, or use of any software, information, or material.
				</p>

				<p style="font-size:16px">
					<b>Restricted Rights Legend.</b>
				</p>
				<p>
					No part of ESF and/or Netgate's information or materials may be published, distributed, reproduced, publicly displayed, used to create derivative works, or translated to another language, without the prior written consent of ESF and/or Netgate. The information contained herein is subject to change without notice.
				</p>
				<p>
					Use, duplication or disclosure by the U.S. Government may be subject to restrictions as set forth in subparagraph (c) (1) (ii) of the Rights in Technical Data and Computer Software clause at DFARS 252.227-7013 for DOD agencies, and subparagraphs (c) (1) and (c) (2) of the Commercial Computer Software Restricted Rights clause at FAR 52.227-19 for other agencies.
				</p>
				<p style="font-size:16px">
					<b>Regulatory/Export Compliance.</b>
				</p>
				<p>
					The export and re-export of software is controlled for export purposes by the U.S. Government. By accepting this software and/or documentation, Licensee agrees to comply with all U.S. and foreign export laws and regulations as they relate to software and related documentation.  Licensee will not export or re-export outside the United States software or documentation, whether directly or indirectly, to any Prohibited Party and will not cause, approve or otherwise intentionally facilitate others in so doing. A Prohibited Party includes: a party in a U.S. embargoed country or country the United States has named as a supporter of international terrorism; a party involved in proliferation; a party identified by the U.S. Government as a Denied Party; a party named on the U.S. Government's Enemies List; a party prohibited from participation in export or re-export transactions by a U.S. Government General Order; a party listed by the U.S. Government's Office of Foreign Assets Control as ineligible to participate in transactions subject to U.S. jurisdiction; or any party that Licensee knows or has reason to know has violated or plans to violate U.S. or foreign export laws or regulations. Licensee shall ensure that each of its software users complies with U.S. and foreign export laws and regulations as they relate to software and related documentation.
				<p>
			</div>

			<div id="main2" hidden class="modal-body modal-lg" style="background-color:#1e5175; color:white;">
				<p align="center" style="font-size:24px">
					<b>Thank you!</b>
				</p>
				<p>
					Many people expend a lot of effort improving and expanding pfSense software. It would be very helpful if you would please take a moment to complete this brief and anonymous survey to help guide those efforts.
				</p>
				<p align="center">
					<a href="https://redirects.netgate.com/survey_1" target="_blank" style="color: #bdf4fc; background: #1e5175;text-decoration: underline;">
						User survey
					</a>
				</p>
			</div>

			<div id="f1" class="modal-footer modal-lg" style="background-color:#1e3f75; color:white;">
				<button type="button" class="btn btn-xs btn-success" aria-label="Close" onClick="$('#main1').attr('hidden', true); $('#main2').attr('hidden', false);$('#f1').attr('hidden', true); $('#f2').attr('hidden', false);">
					<span aria-hidden="true">
						Accept
					</span>
				</button>
			</div>

			<div id="f2" hidden class="modal-footer modal-lg" style="background-color:#1e5175; color:white;">
				<button type="button" class="btn btn-xs btn-success" aria-label="Close" onClick="$('.modal').modal('hide');">
					<span aria-hidden="true">
						Close
					</span>
				</button>
			</div>

<?php
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
