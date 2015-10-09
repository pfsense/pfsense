<?php
/*
	log.widget.php
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP

	Copyright 2007 Scott Dale
	Part of pfSense widgets (https://www.pfsense.org)
	originally based on m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
	and Jonathan Watt <jwatt@jwatt.org>.
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

/* In an effort to reduce duplicate code, many shared functions have been moved here. */
require_once("filter_log.inc");

if (is_numeric($_POST['filterlogentries'])) {
	$config['widgets']['filterlogentries'] = $_POST['filterlogentries'];

	$acts = array();
	if ($_POST['actpass']) {
		$acts[] = "Pass";
	}
	if ($_POST['actblock']) {
		$acts[] = "Block";
	}
	if ($_POST['actreject']) {
		$acts[] = "Reject";
	}

	if (!empty($acts)) {
		$config['widgets']['filterlogentriesacts'] = implode(" ", $acts);
	} else {
		unset($config['widgets']['filterlogentriesacts']);
	}
	unset($acts);

	if (($_POST['filterlogentriesinterfaces']) and ($_POST['filterlogentriesinterfaces'] != "All")) {
		$config['widgets']['filterlogentriesinterfaces'] = trim($_POST['filterlogentriesinterfaces']);
	} else {
		unset($config['widgets']['filterlogentriesinterfaces']);
	}

	write_config("Saved Filter Log Entries via Dashboard");
	Header("Location: /");
	exit(0);
}

$nentries = isset($config['widgets']['filterlogentries']) ? $config['widgets']['filterlogentries'] : 5;

//set variables for log
$nentriesacts		= isset($config['widgets']['filterlogentriesacts'])		? $config['widgets']['filterlogentriesacts']		: 'All';
$nentriesinterfaces = isset($config['widgets']['filterlogentriesinterfaces']) ? $config['widgets']['filterlogentriesinterfaces'] : 'All';

$filterfieldsarray = array(
	"act" => $nentriesacts,
	"interface" => $nentriesinterfaces
);

$filter_logfile = "{$g['varlog_path']}/filter.log";

/* AJAX related routines */
if (isset($_POST['lastsawtime'])) {
	$filterlog = conv_log_filter($filter_logfile, $nentries, $nentries + 20);

	foreach ($filterlog as $idx => $row) {
		if (strtotime($log_row['time']) <= $_POST['lastsawtime'])
			unset($filterlog[$idx]);
	}
}
else
	$filterlog = conv_log_filter($filter_logfile, $nentries, 50, $filterfieldsarray);
?>
<script>
	var logWidgetLastRefresh = <?=time()?>;
</script>

<table class="table table-striped table-hover">
	<thead>
		<tr>
			<th><?=gettext("Act");?></th>
			<th><?=gettext("Time");?></th>
			<th><?=gettext("IF");?></th>
			<th><?=gettext("Source");?></th>
			<th><?=gettext("Destination");?></th>
		</tr>
	</thead>
	<tbody>
<?php
	foreach ($filterlog as $filterent):
		if ($filterent['version'] == '6') {
			$srcIP = "[" . htmlspecialchars($filterent['srcip']) . "]";
			$dstIP = "[" . htmlspecialchars($filterent['dstip']) . "]";
		} else {
			$srcIP = htmlspecialchars($filterent['srcip']);
			$dstIP = htmlspecialchars($filterent['dstip']);
		}

		if ($filterent['act'] == "block")
			$iconfn = "remove";
		else if ($filterent['act'] == "reject")
			$iconfn = "fire";
		else if ($filterent['act'] == "match")
			$iconfn = "filter";
		else
			$iconfn = "ok";

		$rule = find_rule_by_number($filterent['rulenum'], $filterent['tracker'], $filterent['act']);
?>
		<tr>
			<td><a <a href="#" onclick="javascript:getURL('diag_logs_filter.php?getrulenum=<?php echo "{$filterent['rulenum']},{$filterent['tracker']},{$filterent['act']}"; ?>', outputrule);" 
			role="button" data-toggle="popover" data-trigger="hover"
				data-title="Rule that triggered this action"
				data-content="<?=htmlspecialchars($rule)?>"> <i
					class="icon icon-<?=$iconfn?>"></i>
			</a></td>
			<td title="<?=htmlspecialchars($filterent['time'])?>"><?=substr(htmlspecialchars($filterent['time']),0,-3)?></td>
			<td><?=htmlspecialchars($filterent['interface']);?></td>
			<td><a href="diag_dns.php?host=<?=$filterent['srcip']?>"
				title="<?=gettext("Reverse Resolve with DNS")?>"><?=$srcIP?></a>:<?=htmlspecialchars($filterent['srcport'])?>
			</td>
			<td><a href="diag_dns.php?host=<?=$filterent['dstip']?>"
				title="<?=gettext("Reverse Resolve with DNS");?>"><?=$dstIP?></a>:<?=htmlspecialchars($filterent['dstport'])?>
			</td>
		</tr>
	<?php
	endforeach;
	?>
	</tbody>
</table>

<?php

/* for AJAX response, we only need the panel-body */
if (isset($_GET['lastsawtime']))
	exit;
?>

<script>
function logWidgetUpdateFromServer(){
	$.ajax({
		type: 'get',
		url: '/widgets/widgets/log.widget.php',
		data: 'lastsawtime='+logWidgetLastRefresh,
		dataFilter: function(raw){
			// We reload the entire widget, strip this block of javascript from it
			return raw.replace(/<script>([\s\S]*)<\/script>/gi, '');
		},
		dataType: 'html',
		success: function(data){
			$('#widget-log .panel-body').html(data);
		}
	});
}

events.push(function(){
	setInterval('logWidgetUpdateFromServer()', 60*1000);
});
</script>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div>
<div class="panel-footer collapse">

	<form action="/widgets/widgets/log.widget.php" method="post"
		class="form-horizontal">
		<div class="form-group">
			<label for="filterlogentries" class="col-sm-4 control-label">Number
				of entries</label>
			<div class="col-sm-6">
				<input type="number" name="filterlogentries" value="<?=$nentries?>"
					min="1" max="20" class="form-control" />
			</div>
		</div>

		<div class="form-group">
			<label class="col-sm-4 control-label">Filter actions</label>
			<div class="col-sm-6 checkbox">
			<?php $include_acts = explode(" ", strtolower($nentriesacts)); ?>
			<label><input name="actpass" type="checkbox" value="Pass"
					<?=(in_array('pass', $include_acts) ? 'checked="checked"':'')?> />Pass</label>
				<label><input name="actblock" type="checkbox" value="Block"
					<?=(in_array('block', $include_acts) ? 'checked="checked"':'')?> />Block</label>
				<label><input name="actreject" type="checkbox" value="Reject"
					<?=(in_array('reject', $include_acts) ? 'checked="checked"':'')?> />Reject</label>
			</div>
		</div>

		<div class="form-group">
			<label for="filterlogentriesinterfaces"
				class="col-sm-4 control-label">Filter interface</label>
			<div class="col-sm-6 checkbox">
				<select name="filterlogentriesinterfaces" class="form-control">
			<?php foreach (array("All" => "ALL") + get_configured_interface_with_descr() as $iface => $ifacename):?>
				<option value="<?=$iface?>"
						<?=($nentriesinterfaces==$iface?'selected="selected"':'')?>><?=htmlspecialchars($ifacename)?></option>
			<?php endforeach;?>
			</select>
			</div>
		</div>

		<div class="form-group">
			<div class="col-sm-offset-4 col-sm-6">
				<button type="submit" class="btn btn-default">Save</button>
			</div>
		</div>
	</form>

<script>
if (typeof getURL == 'undefined') {
	getURL = function(url, callback) {
		if (!url)
			throw 'No URL for getURL';
		try {
			if (typeof callback.operationComplete == 'function')
				callback = callback.operationComplete;
		} catch (e) {}
			if (typeof callback != 'function')
				throw 'No callback function for getURL';
		var http_request = null;
		if (typeof XMLHttpRequest != 'undefined') {
		    http_request = new XMLHttpRequest();
		}
		else if (typeof ActiveXObject != 'undefined') {
			try {
				http_request = new ActiveXObject('Msxml2.XMLHTTP');
			} catch (e) {
				try {
					http_request = new ActiveXObject('Microsoft.XMLHTTP');
				} catch (e) {}
			}
		}
		if (!http_request)
			throw 'Both getURL and XMLHttpRequest are undefined';
		http_request.onreadystatechange = function() {
			if (http_request.readyState == 4) {
				callback( { success : true,
				  content : http_request.responseText,
				  contentType : http_request.getResponseHeader("Content-Type") } );
			}
		};
		http_request.open('GET', url, true);
		http_request.send(null);
	};
}

function outputrule(req) {
	alert(req.content);
}

</script>