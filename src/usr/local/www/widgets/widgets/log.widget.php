<?php
/*
 * log.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * Copyright (c) 2007 Scott Dale
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

/* In an effort to reduce duplicate code, many shared functions have been moved here. */
require_once("filter_log.inc");

if ($_POST) {
	if (is_numeric($_POST['filterlogentries'])) {
		$config['widgets']['filterlogentries'] = $_POST['filterlogentries'];
	} else {
		unset($config['widgets']['filterlogentries']);
	}

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

	if (is_numeric($_POST['filterlogentriesinterval'])) {
		$config['widgets']['filterlogentriesinterval'] = $_POST['filterlogentriesinterval'];
	} else {
		unset($config['widgets']['filterlogentriesinterval']);
	}

	write_config(gettext("Saved Filter Log Entries via Dashboard"));
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

$nentriesinterval = isset($config['widgets']['filterlogentriesinterval']) ? $config['widgets']['filterlogentriesinterval'] : 60;

$filter_logfile = "{$g['varlog_path']}/filter.log";

$filterlog = conv_log_filter($filter_logfile, $nentries, 50, $filterfieldsarray);
?>
<script type="text/javascript">
//<![CDATA[
	var logWidgetLastRefresh = <?=time()?>;
//]]>
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

		if ($filterent['act'] == "block") {
			$iconfn = "times text-danger";
		} else if ($filterent['act'] == "reject") {
			$iconfn = "hand-stop-o text-warning";
		} else if ($filterent['act'] == "match") {
			$iconfn = "filter";
		} else {
			$iconfn = "check text-success";
		}

		$rule = find_rule_by_number($filterent['rulenum'], $filterent['tracker'], $filterent['act']);

		// Putting <wbr> tags after each ':'  allows the string to word-wrap at that point
		$srcIP = str_replace(':', ':<wbr>', $srcIP);
		$dstIP = str_replace(':', ':<wbr>', $dstIP);
?>
		<tr>
			<td><a href="#" onclick="javascript:getURL('status_logs_filter.php?getrulenum=<?php echo "{$filterent['rulenum']},{$filterent['tracker']},{$filterent['act']}"; ?>', outputrule);"
			role="button" data-toggle="popover" data-trigger="hover"
				data-title="<?=gettext("Rule that triggered this action")?>"
				data-content="<?=htmlspecialchars($rule)?>"> <i
					class="fa fa-<?=$iconfn?>"></i>
			</a></td>
			<td title="<?=htmlspecialchars($filterent['time'])?>"><?=substr(htmlspecialchars($filterent['time']),0,-3)?></td>
			<td><?=htmlspecialchars($filterent['interface']);?></td>
			<td><a href="diag_dns.php?host=<?=$filterent['srcip']?>"
				title="<?=gettext("Reverse Resolve with DNS");?>"><?=$srcIP?></a>
			</td>
			<td><a href="diag_dns.php?host=<?=$filterent['dstip']?>"
				title="<?=gettext("Reverse Resolve with DNS");?>"><?=$dstIP?></a><?php
				if ($filterent['dstport']) {
					print ':' . htmlspecialchars($filterent['dstport']);
				}
				?>
			</td>
		</tr>
	<?php
	endforeach;

	if (count($filterlog) == 0) {
		print '<tr class="text-nowrap"><td colspan=5 class="text-center">';
		print gettext('No logs to display');
		print '</td></tr>';
	}
?>

	</tbody>
</table>

<?php

/* for AJAX response, we only need the panel-body */
if (isset($_GET['lastsawtime'])) {
	exit;
}
?>

<script type="text/javascript">
//<![CDATA[
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
	setInterval('logWidgetUpdateFromServer()', <?=$nentriesinterval?>*1000);
});
//]]>
</script>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div>
<div id="widget-<?=$widgetname?>_panel-footer" class="panel-footer collapse">

<?php
$pconfig['nentries'] = isset($config['widgets']['filterlogentries']) ? $config['widgets']['filterlogentries'] : '';
$pconfig['nentriesinterval'] = isset($config['widgets']['filterlogentriesinterval']) ? $config['widgets']['filterlogentriesinterval'] : '';
?>
	<form action="/widgets/widgets/log.widget.php" method="post"
		class="form-horizontal">
		<div class="form-group">
			<label for="filterlogentries" class="col-sm-4 control-label"><?=gettext('Number of entries')?></label>
			<div class="col-sm-6">
				<input type="number" name="filterlogentries" id="filterlogentries" value="<?=$pconfig['nentries']?>" placeholder="5"
					min="1" max="20" class="form-control" />
			</div>
		</div>

		<div class="form-group">
			<label class="col-sm-4 control-label"><?=gettext('Filter actions')?></label>
			<div class="col-sm-6 checkbox">
			<?php $include_acts = explode(" ", strtolower($nentriesacts)); ?>
			<label><input name="actpass" type="checkbox" value="Pass"
				<?=(in_array('pass', $include_acts) ? 'checked':'')?> />
				<?=gettext('Pass')?>
			</label>
			<label><input name="actblock" type="checkbox" value="Block"
				<?=(in_array('block', $include_acts) ? 'checked':'')?> />
				<?=gettext('Block')?>
			</label>
			<label><input name="actreject" type="checkbox" value="Reject"
				<?=(in_array('reject', $include_acts) ? 'checked':'')?> />
				<?=gettext('Reject')?>
			</label>
			</div>
		</div>

		<div class="form-group">
			<label for="filterlogentriesinterfaces" class="col-sm-4 control-label">
				<?=gettext('Filter interface')?>
			</label>
			<div class="col-sm-6 checkbox">
				<select name="filterlogentriesinterfaces" id="filterlogentriesinterfaces" class="form-control">
			<?php foreach (array("All" => "ALL") + get_configured_interface_with_descr() as $iface => $ifacename):?>
				<option value="<?=$iface?>"
						<?=($nentriesinterfaces==$iface?'selected':'')?>><?=htmlspecialchars($ifacename)?></option>
			<?php endforeach;?>
				</select>
			</div>
		</div>

		<div class="form-group">
			<label for="filterlogentriesinterval" class="col-sm-4 control-label"><?=gettext('Update interval')?></label>
			<div class="col-sm-4">
				<input type="number" name="filterlogentriesinterval" id="filterlogentriesinterval" value="<?=$pconfig['nentriesinterval']?>" placeholder="60"
					min="1" class="form-control" />
			</div>
			<?=gettext('Seconds');?>
		</div>

		<div class="form-group">
			<div class="col-sm-offset-4 col-sm-6">
				<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
			</div>
		</div>
	</form>

<script type="text/javascript">
//<![CDATA[
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
//]]>
</script>
