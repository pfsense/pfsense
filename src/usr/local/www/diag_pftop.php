<?php
/*
 * diag_pftop.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2018 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "This product includes software developed by the pfSense Project
 *    for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 * 4. The names "pfSense" and "pfSense Project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. For written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. Products derived from this software may not be called "pfSense"
 *    nor may "pfSense" appear in their names without prior written
 *    permission of the Electric Sheep Fencing, LLC.
 *
 * 6. Redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "This product includes software developed by the pfSense Project
 * for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 * THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 * ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
 */

##|+PRIV
##|*IDENT=page-diagnostics-system-pftop
##|*NAME=Diagnostics: pfTop
##|*DESCR=Allows access to the 'Diagnostics: pfTop' page
##|*MATCH=diag_pftop.php*
##|-PRIV

require_once("guiconfig.inc");

$pgtitle = array(gettext("Diagnostics"), gettext("pfTop"));
$pftop = "/usr/local/sbin/pftop";

$sorttypes = array('age', 'bytes', 'dest', 'dport', 'exp', 'none', 'peak', 'pkt', 'rate', 'size', 'sport', 'src');
$viewtypes = array('default', 'label', 'long', 'queue', 'rules', 'size', 'speed', 'state', 'time');
$viewall = array('queue', 'label', 'rules');
$numstates = array('50', '100', '200', '500', '1000', 'all');

if ($_REQUEST['getactivity']) {
	if ($_REQUEST['sorttype'] && in_array($_REQUEST['sorttype'], $sorttypes) &&
	    $_REQUEST['viewtype'] && in_array($_REQUEST['viewtype'], $viewtypes) &&
	    $_REQUEST['states'] && in_array($_REQUEST['states'], $numstates)) {
		$viewtype = escapeshellarg($_REQUEST['viewtype']);
		if (in_array($_REQUEST['viewtype'], $viewall)) {
			$sorttype = "";
			$numstate = "-a";
		} else {
			$sorttype = "-o " . escapeshellarg($_REQUEST['sorttype']);
			$numstate = ($_REQUEST['states'] == "all" ? "-a" : escapeshellarg($_REQUEST['states']));
		}
	} else {
		$sorttype = "bytes";
		$viewtype = "default";
		$numstate = "100";
	}
	if ($_REQUEST['filter'] != "") {
		$filter = "-f " . escapeshellarg($_REQUEST['filter']);
	} else {
		$filter = "";
	}
	$text = shell_exec("$pftop {$filter} -b {$sorttype} -w 135 -v {$viewtype} {$numstate}");
	if (empty($text)) {
		echo "Invalid filter, check syntax";
	} else {
		echo trim($text);
	}
	exit;
}

include("head.inc");

if ($_REQUEST['sorttype'] && in_array($_REQUEST['sorttype'], $sorttypes) &&
    $_REQUEST['viewtype'] && in_array($_REQUEST['viewtype'], $viewtypes) &&
    $_REQUEST['states'] && in_array($_REQUEST['states'], $numstates)) {
	$viewtype = escapeshellarg($_REQUEST['viewtype']);
	if (in_array($_REQUEST['viewtype'], $viewall)) {
		$sorttype = "";
		$numstate = "-a";
	} else {
		$sorttype = "-o " . escapeshellarg($_REQUEST['sorttype']);
		$numstate = ($_REQUEST['states'] == "all" ? "-a" : escapeshellarg($_REQUEST['states']));
	}
} else {
	$sorttype = "bytes";
	$viewtype = "default";
	$numstate = "100";
}
if ($_REQUEST['filter'] != "") {
	$filter = "-f " . escapeshellarg($_REQUEST['filter']);
} else {
	$filter = "";
}

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form(false);
$form->addGlobal(new Form_Input(
	'getactivity',
	null,
	'hidden',
	'yes'
));
$section = new Form_Section('pfTop Configuration');

$validViews = array(
	'default' => gettext('default'), 
	'label' => gettext('label'), 
	'long' => gettext('long'),
	'queue' => gettext('queue'), 
	'rules' => gettext('rules'), 
	'size' => gettext('size'),
	'speed' => gettext('speed'), 
	'state' => gettext('state'), 
	'time' => gettext('time'),
);
$section->addInput(new Form_Select(
	'viewtype',
	'View',
	$viewtype,
	$validViews
));

$section->addInput(new Form_Input(
	'filter',
	'Filter expression',
	'text',
	$_REQUEST['filter'],
	['placeholder' => 'e.g. tcp, ip6 or dst net 208.123.73.0/24']
))->setHelp('<em>click for filter help</em>%1$s' .
	'<code>[proto &lt;ip|ip6|ah|carp|esp|icmp|ipv6-icmp|pfsync|tcp|udp&gt;]</code><br />' .
	'<code>[src|dst|gw] [host|net|port] &lt;host/network/port&gt;</code><br />' .
	'<code>[in|out]</code><br /><br />' .
	'These are the most common selectors. Some expressions can be combined using "and" / "or". ' .
	'See %2$s for more detailed expression syntax.%3$s',
	['<span class="infoblock"><br />',
	'<a target="_blank" href="https://www.freebsd.org/cgi/man.cgi?query=pftop#STATE_FILTERING">pftop(8)</a>',
	'</span></p>']
);

$section->addInput(new Form_Select(
	'sorttype',
	'Sort by',
	$sorttype,
	array(
		'none' => gettext('None'),
		'age' => gettext('Age'),
		'bytes' => gettext('Bytes'),
		'dest' => gettext('Destination Address'),
		'dport' => gettext('Destination Port'),
		'exp' => gettext('Expiry'),
		'peak' => gettext('Peak'),
		'pkt' => gettext('Packet'),
		'rate' => gettext('Rate'),
		'size' => gettext('Size'),
		'sport' => gettext('Source Port'),
		'src' => gettext('Source Address'),
	)
));

$validStates = array(50, 100, 200, 500, 1000, 'all');
$section->addInput(new Form_Select(
	'states',
	'Maximum # of States',
	$numstate,
	array_combine($validStates, $validStates)
));

$form->add($section);
print $form;
?>

<script type="text/javascript">
//<![CDATA[
	function getpftopactivity() {
		$.ajax(
			'/diag_pftop.php',
			{
				method: 'post',
				data: $(document.forms[0]).serialize(),
				dataType: "html",
				success: function (data) {
					$('#xhrOutput').html(data);
				},
			}
		);
	}

	events.push(function() {
		setInterval('getpftopactivity()', 2500);
		getpftopactivity();
	});
//]]>
</script>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Output')?></h2></div>
	<div class="panel panel-body">
		<pre id="xhrOutput"><?=gettext("Gathering pfTOP activity, please wait...")?></pre>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$('#viewtype').on('change', function() {
		if (['queue', 'label', 'rules'].indexOf($(this).val()) > -1) {
			$("#sorttype, #sorttypediv, #statesdiv, #states").parents('.form-group').hide();
		} else {
			$("#sorttype, #sorttypediv, #statesdiv, #states").parents('.form-group').show();
		}
	});
	$('#filter').on('keypress keyup', function(event) {
		var keyPressed = event.keyCode || event.which;
		if (keyPressed === 13) {
			event.preventDefault();
			return false;
		}
	});
});
//]]>
</script>
<?php include("foot.inc");
