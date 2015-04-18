<?php

/* $Id$ */
/*
	diag_routes.php
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	Copyright (C) 2006 Fernando Lamos
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
	pfSense_BUILDER_BINARIES:	/usr/bin/netstat	
	pfSense_MODULE:	routing
*/
##|+PRIV
##|*IDENT=page-diagnostics-routingtables
##|*NAME=Diagnostics: Routing tables page
##|*DESCR=Allow access to the 'Diagnostics: Routing tables' page.
##|*MATCH=diag_routes.php*
##|-PRIV

include('guiconfig.inc');

$limit='100';
$filter='';

if (isset($_REQUEST['isAjax'])) {
	$netstat = "/usr/bin/netstat -rW";
	if (isset($_REQUEST['IPv6'])) {
		$netstat .= " -f inet6";
		echo "IPv6\n";
	} else {
		$netstat .= " -f inet";
		echo "IPv4\n";

	}
	if (!isset($_REQUEST['resolve']))
		$netstat .= " -n";

	if (!empty($_REQUEST['filter']))
		$netstat .= " | /usr/bin/sed -e '1,3d; 5,\$ { /" . escapeshellarg(htmlspecialchars($_REQUEST['filter'])) . "/!d; };'";
	else
		$netstat .= " | /usr/bin/sed -e '1,3d'";

	if (is_numeric($_REQUEST['limit']) && $_REQUEST['limit'] > 0)
		$netstat .= " | /usr/bin/head -n {$_REQUEST['limit']}";

	echo htmlspecialchars_decode(shell_exec($netstat));

	exit;
}

$pgtitle = array(gettext("Diagnostics"),gettext("Routing tables"));
$shortcut_section = "routing";

include('head.inc');

?>

<script type="text/javascript">
//<![CDATA[

	function update_routes(section) {
		var url = "diag_routes.php";
		var limit = jQuery('#limit option:selected').text();
		var filter = jQuery('#filter').val();
		var params = "isAjax=true&limit=" + limit + "&filter=" + filter;
		
		if (jQuery('#resolve').is(':checked'))
			params += "&resolve=true";
		if (section == "IPv6")
			params += "&IPv6=true";

    	jQuery.ajax(
    			url,
    			{
    				type: 'post',
    				data: params,
    				complete: update_routes_callback
    			});		
	}

	function update_routes_callback(transport) {
		// First line contains section
		var responseTextArr = transport.responseText.split("\n");
		var section = responseTextArr.shift();
		var tbody = '';
		var field = '';
		var elements = 8;
		var tr_class = '';
        
		var thead = '<tr class="info"><th class="listtopic" colspan="' + elements + '">' + section + '<\/th><\/tr>' + "\n";
		
		for (var i = 0; i < responseTextArr.length; i++) {
			if (responseTextArr[i] == "")
				continue;
			var tmp = '';
			if (i == 0) {
				tr_class = 'listhdrr';
				tmp += '<tr class="sortableHeaderRowIdentifier">' + "\n";
			} else {
				tr_class = 'listlr';
				tmp += '<tr>' + "\n";
			}
			var j = 0;
			var entry = responseTextArr[i].split(" ");
			for (var k = 0; k < entry.length; k++) {
				if (entry[k] == "")
					continue;
				if (i == 0 && j == (elements - 1))
					tr_class = 'listhdr';
				tmp += '<td class="' + tr_class + '">' + entry[k] + '<\/td>' + "\n";
				if (i > 0)
					tr_class = 'listr';
				j++;
			}

			tmp += '<td class="listr">&nbsp;<\/td>' + "\n";

			if (i == 0)
				thead += tmp;
			else
				tbody += tmp;
		}

		jQuery('#' + section + ' > thead').html(thead);
		jQuery('#' + section + ' > tbody').html(tbody);
	}

//]]>
</script>

<script type="text/javascript">
//<![CDATA[
        
	function update_all_routes() {
		update_routes("IPv4");
		update_routes("IPv6");
	}

	setTimeout('update_all_routes()', 5000);

//]]>
</script>

<?php

require('classes/Form.class.php');

$form = new Form(new Form_Button(
	'',
	''
));

$section = new Form_Section('Traceroute');

$section->addInput(new Form_Checkbox(
	'resolve',
	'Enable',
	'',
	$resolve
))->setHelp('Enabling name resolution may cause the query should take longer. You can stop it at any time by clicking the Stop button in your browser.');


$section->addInput(new Form_Select(
	'limit',
	'Rows to display',
	$limit,
	array_combine(array("10", "50", "100", "200", "500", "1000", gettext("all")), array("10", "50", "100", "200", "500", "1000", gettext("all")))
));

$section->addInput(new Form_Input(
	'filter',
	'Filter',
	'text',
	$host,
	['placeholder' => '']
))->setHelp('Use a regular expression to filter IP address or hostnames');

$form->add($section);
	
print $form;

?>

<input type="button" class="btn btn-default" name="update" onclick="update_all_routes();" value="<?=gettext("Update"); ?>" /><br /><br />

    <table class="table table-striped table-compact" id="IPv4">
	    <thead>
		    <tr>
		        <th>IPv4</th>
		    </tr>
	    </thead>
	    <tbody>
		    <tr>
		        <td class="listhdrr"><?=gettext("Gathering data, please wait...")?></td>
		    </tr>
	    </tbody>
    </table>

    <table table class="table table-striped table-compact" id="IPv6">
	    <thead>
		    <tr>
		        <th>IPv6</th>
		    </tr>
	    </thead>
	    <tbody>
		    <tr>
		        <td class="listhdrr"><?=gettext("Gathering data, please wait...")?></td>
		    </tr>
	    </tbody>
    </table>

</div>

<?php include("foot.inc"); ?>
