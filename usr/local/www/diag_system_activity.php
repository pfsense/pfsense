<?php
/* $Id$ */
/*
	diag_system_activity.php
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP

	Copyright (C) 2008-2009 Scott Ullrich
	All rights reserved.

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
	pfSense_BUILDER_BINARIES:	/usr/bin/top
	pfSense_MODULE: system
*/

##|+PRIV
##|*IDENT=page-diagnostics-system-activity
##|*NAME=Diagnostics: System Activity
##|*DESCR=Allows access to the 'Diagnostics: System Activity' page
##|*MATCH=diag_system_activity.php*
##|-PRIV

require("guiconfig.inc");

$pgtitle = array(gettext("Diagnostics"),gettext("System Activity"));

if ($_REQUEST['getactivity']) {
	$text = `/usr/bin/top -aHS | /usr/bin/cut -c1-105`;
	echo $text;
	exit;
}

include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

?>
<script>
function getcpuactivity() {
	$.ajax(
		'/diag_system_activity.php',
		{
			method: 'post',
			data: {
				getactivity: 'yes'
			},
			dataType: "html",
			success: function (data) {
				$('#xhrOutput').html(data);
			},
		}
	);
}

events.push(function(){
	setInterval('getcpuactivity()', 2500);
	getcpuactivity();
});
</script>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('CPU Activity')?></h2></div>
	<div class="panel panel-body">
		<pre id="xhrOutput"><?=gettext("Gathering CPU activity, please wait...")?></pre>
	</div>
</div>

<?php include("foot.inc");