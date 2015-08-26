<?php
/* $Id$ */
/*
	diag_limiter_info.php
	Copyright (C) 2010 Scott Ullrich
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
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
##|*IDENT=page-diagnostics-limiter-info
##|*NAME=Diagnostics: Limiter Info
##|*DESCR=Allows access to the 'Diagnostics: Limiter Info' page
##|*MATCH=diag_limiter_info.php*
##|-PRIV

require("guiconfig.inc");

$pgtitle = gettext("Diagnostics: Limiter Info");
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