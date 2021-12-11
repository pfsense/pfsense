<?php
/*
 * states.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
require_once("/usr/local/pfSense/include/www/diag_dump_states.inc");

/* In an effort to reduce duplicate code, many shared functions have been moved here. */
require_once("syslog.inc");

if ($_REQUEST['widgetkey'] && !$_REQUEST['ajax']) {
    set_customwidgettitle($user_settings);

	if (is_numeric($_POST['filterstates'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['filterstates'] = $_POST['filterstates'];
	} else {
		unset($user_settings['widgets'][$_POST['widgetkey']]['filterstates']);
	}

	if (($_POST['filterstatesinterfaces']) and ($_POST['filterstatesinterfaces'] != "All")) {
		$user_settings['widgets'][$_POST['widgetkey']]['filterstatesinterfaces'] = trim($_POST['filterstatesinterfaces']);
	} else {
		unset($user_settings['widgets'][$_POST['widgetkey']]['filterstatesinterfaces']);
	}

	if (($_POST['filterstatesfilter']) and ($_POST['filterstatesfilter'] != "")) {
		$user_settings['widgets'][$_POST['widgetkey']]['filterstatesfilter'] = trim($_POST['filterstatesfilter']);
	} else {
		unset($user_settings['widgets'][$_POST['widgetkey']]['filterstatesfilter']);
	}

	if (is_numeric($_POST['filterstatesinterval'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['filterstatesinterval'] = $_POST['filterstatesinterval'];
	} else {
		unset($user_settings['widgets'][$_POST['widgetkey']]['filterstatesinterval']);
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved Filter States via Dashboard."));

    Header("Location: /");
    exit(0);
}

// When this widget is included in the dashboard, $widgetkey is already defined before the widget is included.
// When the ajax call is made to refresh the table, 'widgetkey' comes in $_REQUEST.
if ($_REQUEST['widgetkey']) {
    $widgetkey = $_REQUEST['widgetkey'];
}

$iface_descr_arr = get_configured_interface_with_descr();

$nstates = isset($user_settings['widgets'][$widgetkey]['filterstates']) ? $user_settings['widgets'][$widgetkey]['filterstates'] : 10;
$nstatesinterval = isset($user_settings['widgets'][$widgetkey]['filterstatesinterval']) ? $user_settings['widgets'][$widgetkey]['filterstatesinterval'] : 60;
$nstatesinterfaces = isset($user_settings['widgets'][$widgetkey]['filterstatesinterfaces']) ? $user_settings['widgets'][$widgetkey]['filterstatesinterfaces'] : 'All';
$nstatesfilter = isset($user_settings['widgets'][$widgetkey]['filterstatesfilter']) ? $user_settings['widgets'][$widgetkey]['filterstatesfilter'] : '';

$_POST['interface'] = $nstatesinterfaces;
$_POST['filter'] = $nstatesfilter;
$statedisp = process_state_req($_POST, $_REQUEST, false);

// sort desc by IO
function sortMax($a, $b) {
    return max($b['bytes_raw_in'], $b['bytes_raw_out']) < max($a['bytes_raw_in'], $a['bytes_raw_out']) ? -1 : 1;
}
usort($statedisp, "sortMax");

$statedisp = array_slice($statedisp, 0, $nstates);
$widgetkey_nodash = str_replace("-", "", $widgetkey);

if (!$_REQUEST['ajax']) {
?>
    <script type="text/javascript">
        //<![CDATA[
        var statesWidgetLastRefresh<?= htmlspecialchars($widgetkey_nodash) ?> = <?= time() ?>;
        //]]>
    </script>

<?php } ?>

<table class="table table-striped table-condensed table-hover sortable-theme-bootstrap" data-sortable>
    <thead>
        <tr>
            <th data-sortable="false"><?= gettext("Interface") ?></th>
            <th data-sortable="false"><?= gettext("Protocol") ?></th>
            <th data-sortable="false"><?= gettext("Src -> Dest") ?></th>
            <th data-sortable="false"><?= gettext("In") ?></th>
            <th data-sortable="false"><?= gettext("Out") ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($statedisp as $dstate) :
        ?>
            <tr>
                <td><?= $dstate['interface'] ?></td>
                <td><?= $dstate['proto'] ?></td>
                <td><?= $dstate['display'] ?></td>
                <td><?= $dstate['bytes_in'] ?></td>
                <td><?= $dstate['bytes_out'] ?></td>
            </tr>
        <?php
        endforeach;
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

    events.push(function() {
        // --------------------- Centralized widget refresh system ------------------------------

        // Callback function called by refresh system when data is retrieved
        function states_callback(s) {
            $(<?= json_encode('#widget-' . $widgetkey . '_panel-body') ?>).html(s);
        }

        // POST data to send via AJAX
        var postdata = {
            ajax: "ajax",
            widgetkey: <?= json_encode($widgetkey) ?>,
            lastsawtime: statesWidgetLastRefresh<?= htmlspecialchars($widgetkey_nodash) ?>
        };

        // Create an object defining the widget refresh AJAX call
        var statesObject = new Object();
        statesObject.name = "States";
        statesObject.url = "/widgets/widgets/states.widget.php";
        statesObject.callback = states_callback;
        statesObject.parms = postdata;
        statesObject.freq = <?=$nstatesinterval?>/5;

        // Register the AJAX object
        register_ajax(statesObject);

        // ---------------------------------------------------------------------------------------------------
    });
    //]]>
</script>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div>
<div id="<?= $widget_panel_footer_id ?>" class="panel-footer collapse">
<?php
$pconfig['nstates'] = isset($user_settings['widgets'][$widgetkey]['filterstates']) ? $user_settings['widgets'][$widgetkey]['filterstates'] : '';
$pconfig['nstatesinterval'] = isset($user_settings['widgets'][$widgetkey]['filterstatesinterval']) ? $user_settings['widgets'][$widgetkey]['filterstatesinterval'] : '';
?>
	<form action="/widgets/widgets/states.widget.php" method="post"
		class="form-horizontal">
		<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
		<?=gen_customwidgettitle_div($widgetconfig['title']); ?>

		<div class="form-group">
			<label for="filterstates" class="col-sm-4 control-label"><?=gettext('Number of entries')?></label>
			<div class="col-sm-6">
				<input type="number" name="filterstates" id="filterstates" value="<?=$pconfig['nstates']?>" placeholder="10"
					min="1" max="100" class="form-control" />
			</div>
		</div>

		<div class="form-group">
			<label for="filterstatesinterfaces" class="col-sm-4 control-label">
				<?=gettext('Filter interface')?>
			</label>
			<div class="col-sm-6 checkbox">
				<select name="filterstatesinterfaces" id="filterstatesinterfaces" class="form-control">
			<?php foreach (array("All" => "ALL") + $iface_descr_arr as $iface => $ifacename):?>
				<option value="<?=$iface?>"
						<?=($nstatesinterfaces==$iface?'selected':'')?>><?=htmlspecialchars($ifacename)?></option>
			<?php endforeach;?>
				</select>
			</div>
		</div>

		<div class="form-group">
			<label for="filterstatesfilter" class="col-sm-4 control-label">
				<?=gettext('Filter states')?>
			</label>
			<div class="col-sm-4">
                <input type="text" name="filterstatesfilter" id="filterstatesfilter" value="<?=$nstatesfilter?>" class="form-control" />
			</div>
		</div>
        

		<div class="form-group">
			<label for="filterstatesinterval" class="col-sm-4 control-label"><?=gettext('Update interval')?></label>
			<div class="col-sm-4">
				<input type="number" name="filterstatesinterval" id="filterstatesinterval" value="<?=$pconfig['nentriesinterval']?>" placeholder="60"
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
                } else if (typeof ActiveXObject != 'undefined') {
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
                        callback({
                            success: true,
                            content: http_request.responseText,
                            contentType: http_request.getResponseHeader("Content-Type")
                        });
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