<?php

/* $Id$ */
/*
	diag_routes.php
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
<body link="#000000" vlink="#000000" alink="#000000">

<?php include("fbegin.inc"); ?>

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
		var myAjax = new Ajax.Request(
			url,
			{
				method: 'post',
				parameters: params,
				onComplete: update_routes_callback
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

		var thead = '<tr><td class="listtopic" colspan="' + elements + '"><strong>' + section + '<\/strong><\/td><\/tr>' + "\n";
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
			// The 'Expire' field might be blank
			if (j == (elements - 1))
				tmp += '<td class="listr">&nbsp;<\/td>' + "\n";
			tmp += '<\/tr>' + "\n";
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

	jQuery(document).ready(function(){setTimeout('update_all_routes()', 5000);});

//]]>
</script>

<div id="mainarea">
<form action="diag_routes.php" method="post">
<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="6" summary="diag routes">

<tr>
<td class="vncellreq" width="22%"><?=gettext("Name resolution");?></td>
<td class="vtable" width="78%">
<input type="checkbox" class="formfld" id="resolve" name="resolve" value="yes" <?php if ($_POST['resolve'] == 'yes') echo "checked=\"checked\""; ?> /><?=gettext("Enable");?>
<br />
<span class="expl"><?=gettext("Enable this to attempt to resolve names when displaying the tables.");?></span>
</td>
</tr>

<tr>
<td class="vncellreq" width="22%"><?=gettext("Number of rows");?></td>
<td class="vtable" width="78%">
<select id="limit" name="limit">
<?php
	foreach (array("10", "50", "100", "200", "500", "1000", gettext("all")) as $item) {
		echo "<option value=\"{$item}\" " . ($item == "100" ? "selected=\"selected\"" : "") . ">{$item}</option>\n";
	}
?>
</select>
<br />
<span class="expl"><?=gettext("Select how many rows to display.");?></span>
</td>
</tr>

<tr>
<td class="vncellreq" width="22%"><?=gettext("Filter expression");?></td>
<td class="vtable" width="78%">
<input type="text" class="formfld search" name="filter" id="filter" />
<br />
<span class="expl"><?=gettext("Use a regular expression to filter IP address or hostnames.");?></span>
</td>
</tr>

<tr>
<td class="vncellreq" width="22%">&nbsp;</td>
<td class="vtable" width="78%">
<input type="button" class="formbtn" name="update" onclick="update_all_routes();" value="<?=gettext("Update"); ?>" />
<br />
<br />
<span class="vexpl"><span class="red"><strong><?=gettext("Note:")?></strong></span> <?=gettext("By enabling name resolution, the query should take a bit longer. You can stop it at any time by clicking the Stop button in your browser.");?></span>
</td>
</tr>

</table>
</form>

<table class="tabcont sortable" width="100%" cellspacing="0" cellpadding="6" border="0" id="IPv4" summary="ipv4 routes">
	<thead>
		<tr><td class="listtopic"><strong>IPv4</strong></td></tr>
	</thead>
	<tbody>
		<tr><td class="listhdrr"><?=gettext("Gathering data, please wait...");?></td></tr>
	</tbody>
</table>
<table class="tabcont sortable" width="100%" cellspacing="0" cellpadding="6" border="0" id="IPv6" summary="ipv6 routes">
	<thead>
		<tr><td class="listtopic"><strong>IPv6</strong></td></tr>
	</thead>
	<tbody>
		<tr><td class="listhdrr"><?=gettext("Gathering data, please wait...");?></td></tr>
	</tbody>
</table>

</div>

<?php
include('fend.inc');
?>

</body>
</html>
