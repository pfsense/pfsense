<?php
/*
 * log.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

/* In an effort to reduce duplicate code, many shared functions have been moved here. */
require_once("syslog.inc");

if ($_REQUEST['widgetkey'] && !$_REQUEST['ajax']) {
	set_customwidgettitle($user_settings);

	if (is_numeric($_POST['filterlogentries'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['filterlogentries'] = $_POST['filterlogentries'];
	} else {
		unset($user_settings['widgets'][$_POST['widgetkey']]['filterlogentries']);
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
		$user_settings['widgets'][$_POST['widgetkey']]['filterlogentriesacts'] = implode(" ", $acts);
	} else {
		unset($user_settings['widgets'][$_POST['widgetkey']]['filterlogentriesacts']);
	}
	unset($acts);

	if (($_POST['filterlogentriesinterfaces']) and ($_POST['filterlogentriesinterfaces'] != "All")) {
		$user_settings['widgets'][$_POST['widgetkey']]['filterlogentriesinterfaces'] = trim($_POST['filterlogentriesinterfaces']);
	} else {
		unset($user_settings['widgets'][$_POST['widgetkey']]['filterlogentriesinterfaces']);
	}

	if (is_numeric($_POST['filterlogentriesinterval'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['filterlogentriesinterval'] = $_POST['filterlogentriesinterval'];
	} else {
		unset($user_settings['widgets'][$_POST['widgetkey']]['filterlogentriesinterval']);
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved Filter Log Entries via Dashboard."));
	Header("Location: /");
	exit(0);
}

// When this widget is included in the dashboard, $widgetkey is already defined before the widget is included.
// When the ajax call is made to refresh the firewall log table, 'widgetkey' comes in $_REQUEST.
if ($_REQUEST['widgetkey']) {
	$widgetkey = $_REQUEST['widgetkey'];
}

$iface_descr_arr = get_configured_interface_with_descr();

$nentries = isset($user_settings['widgets'][$widgetkey]['filterlogentries']) ? $user_settings['widgets'][$widgetkey]['filterlogentries'] : 5;

//set variables for log
$nentriesacts		= isset($user_settings['widgets'][$widgetkey]['filterlogentriesacts']) ? $user_settings['widgets'][$widgetkey]['filterlogentriesacts'] : 'All';
$nentriesinterfaces = isset($user_settings['widgets'][$widgetkey]['filterlogentriesinterfaces']) ? $user_settings['widgets'][$widgetkey]['filterlogentriesinterfaces'] : 'All';

$filterfieldsarray = array(
	"act" => $nentriesacts,
	"interface" => isset($iface_descr_arr[$nentriesinterfaces]) ? $iface_descr_arr[$nentriesinterfaces] : $nentriesinterfaces
);

$nentriesinterval = isset($user_settings['widgets'][$widgetkey]['filterlogentriesinterval']) ? $user_settings['widgets'][$widgetkey]['filterlogentriesinterval'] : 60;

$filter_logfile = "{$g['varlog_path']}/filter.log";

$filterlog = conv_log_filter($filter_logfile, $nentries, 50, $filterfieldsarray);

$widgetkey_nodash = str_replace("-", "", $widgetkey);

if (!$_REQUEST['ajax']) {
?>
<script type="text/javascript">
//<![CDATA[
	var logWidgetLastRefresh<?=htmlspecialchars($widgetkey_nodash)?> = <?=time()?>;
//]]>
</script>

<?php } ?>

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
if ($_REQUEST['ajax']) {
	exit;
}
?>

<script type="text/javascript">
//<![CDATA[

events.push(function(){
	// --------------------- Centralized widget refresh system ------------------------------

	// Callback function called by refresh system when data is retrieved
	function logs_callback(s) {
		$(<?=json_encode('#widget-' . $widgetkey . '_panel-body')?>).html(s);
	}

	// POST data to send via AJAX
	var postdata = {
		ajax: "ajax",
		widgetkey : <?=json_encode($widgetkey)?>,
		lastsawtime: logWidgetLastRefresh<?=htmlspecialchars($widgetkey_nodash)?>
	 };

	// Create an object defining the widget refresh AJAX call
	var logsObject = new Object();
	logsObject.name = "Gateways";
	logsObject.url = "/widgets/widgets/log.widget.php";
	logsObject.callback = logs_callback;
	logsObject.parms = postdata;
	logsObject.freq = <?=$nentriesinterval?>/5;

	// Register the AJAX object
	register_ajax(logsObject);

	// ---------------------------------------------------------------------------------------------------
});
//]]>
</script>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div>
<div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">

<?php
$pconfig['nentries'] = isset($user_settings['widgets'][$widgetkey]['filterlogentries']) ? $user_settings['widgets'][$widgetkey]['filterlogentries'] : '';
$pconfig['nentriesinterval'] = isset($user_settings['widgets'][$widgetkey]['filterlogentriesinterval']) ? $user_settings['widgets'][$widgetkey]['filterlogentriesinterval'] : '';
?>
	<form action="/widgets/widgets/log.widget.php" method="post"
		class="form-horizontal">
		<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
		<?=gen_customwidgettitle_div($widgetconfig['title']); ?>

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
			<?php foreach (array("All" => "ALL") + $iface_descr_arr as $iface => $ifacename):?>
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
