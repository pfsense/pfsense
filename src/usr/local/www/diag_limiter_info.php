<?php
/*
	diag_limiter_info.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
 */

/*
	pfSense_BUILDER_BINARIES:	/usr/bin/top
	pfSense_MODULE: system
*/

##|+PRIV
##|*IDENT=page-diagnostics-limiter-info
##|*NAME=Diagnostics: Limiter Info
##|*DESCR=Allows access to the 'Diagnostics: Limiter Info' page
##|*MATCH=diag_limiter_info.php*
##|-PRIV

require("guiconfig.inc");

$pgtitle = array(gettext("Diagnostics"), gettext("Limiter Info"));
$shortcut_section = "trafficshaper-limiters";

if ($_REQUEST['getactivity']) {
	$text = `/sbin/ipfw pipe show`;
	if ($text == "") {
		$text = "We could not find any limiters on this system.";
	}
	echo "Limiters:\n";
	echo $text;
	$text = `/sbin/ipfw queue show`;
	if ($text != "") {
		echo "\n\nQueues:\n";
		echo $text;
	}
	exit;
}

include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

?>
<script>
	function getlimiteractivity() {
		$.ajax(
			'/diag_limiter_info.php',
			{
				type: 'post',
				data: {
					getactivity: 'yes'
				},
				success: function (data) {
					$('#xhrOutput').html(data);
				},
		});
	}

	events.push(function(){
		setInterval('getlimiteractivity()', 2500);
		getlimiteractivity();
	});
</script>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title">Limiter Information</h2></div>
	<div class="panel-body">
		<pre id="xhrOutput"><?=gettext("Gathering Limiter information, please wait...")?></pre>
	</div>
</div>

<?php include("foot.inc");
