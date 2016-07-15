<?php
/*
 * traffic_graphs.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * Copyright (c) 2007 Scott Dale
 * Copyright (c) 2004-2005 T. Lechat <dev@lechat.org>
 * Copyright (c) 2004-2005 Jonathan Watt <jwatt@jwatt.org>.
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
require_once("ipsec.inc");
require_once("functions.inc");

$first_time = false;
if (!is_array($config["widgets"]["trafficgraphs"])) {
	$first_time = true;
	$config["widgets"]["trafficgraphs"] = array();
}
$a_config = &$config["widgets"]["trafficgraphs"];

if (!is_array($a_config["shown"])) {
	$a_config["shown"] = array();
}
if (!is_array($a_config["shown"]["item"])) {
	$a_config["shown"]["item"] = array();
}

$ifdescrs = get_configured_interface_with_descr();
if (ipsec_enabled()) {
	$ifdescrs['enc0'] = "IPsec";
}

if ($_POST) {
	if (isset($_POST["refreshinterval"]) && is_numericint($_POST["refreshinterval"])) {
		$a_config["refreshinterval"] = $_POST["refreshinterval"];
	}

	if (isset($_POST["scale_type"])) {
		$a_config["scale_type"] = $_POST["scale_type"];
	}

	$a_config["shown"]["item"] = array();

	foreach ($ifdescrs as $ifname => $ifdescr) {
		if (in_array($ifname, $_POST["shown"])) {
			$a_config["shown"]["item"][] = $ifname;
		}
	}

	write_config(gettext("Updated traffic graph settings via dashboard."));
	header("Location: /");
	exit(0);
}

$shown = array();
foreach ($a_config["shown"]["item"] as $if) {
	$shown[$if] = true;
}

if ($first_time) {
	$keys = array_keys($ifdescrs);
	$shown[$keys[0]] = true;
}

if (isset($a_config["refreshinterval"]) && is_numericint($a_config["refreshinterval"])) {
	$refreshinterval = $a_config["refreshinterval"];
} else {
	$refreshinterval = 10;
}

if (isset($a_config["scale_type"])) {
		$scale_type = $a_config["scale_type"];
} else {
		$scale_type = "up";
}

$graphcounter = 0;

foreach ($ifdescrs as $ifname => $ifdescr):
	$ifinfo = get_interface_info($ifname);
	if ($shown[$ifname]) {
		$mingraphbutton = "inline";
		$showgraphbutton = "none";
		$graphdisplay = "inline";
		$interfacevalue = "show";
		$graphcounter++;
	} else {
		$mingraphbutton = "none";
		$showgraphbutton = "inline";
		$graphdisplay = "none";
		$interfacevalue = "hide";
	}

	if ($ifinfo['status'] != "down"):
?>
	<div style="display:<?=$graphdisplay?>">
		<object data="graph.php?ifnum=<?=$ifname?>&amp;ifname=<?=rawurlencode($ifdescr)?>&amp;timeint=<?=$refreshinterval?>&amp;initdelay=<?=$graphcounter * 2?>">
			<param name="id" value="graph" />
			<param name="type" value="image/svg+xml" />
		</object>
	</div>
<?php endif; ?>
<?php endforeach; ?>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="widget-<?=$widgetname?>_panel-footer" class="panel-footer collapse">

<form action="/widgets/widgets/traffic_graphs.widget.php" method="post" class="form-horizontal">
	<div class="form-group">
		<label for="scale_type_up" class="col-sm-3 control-label"><?=gettext('Show graphs')?></label>
		<div class="col-sm-6 checkbox">
<?php foreach ($ifdescrs as $ifname => $ifdescr): ?>
			<label>
				<input type="checkbox" name="shown[<?= $ifname?>]" value="<?=$ifname?>" <?= ($shown[$ifname]) ? "checked":""?> />
				<?=$ifdescr?>
			</label>
<?php endforeach; ?>
		</div>
	</div>
	<div class="form-group">
		<label for="scale_type_up" class="col-sm-3 control-label"><?=gettext('Default Autoscale')?></label>
		<div class="col-sm-6 checkbox">
			<label>
				<input name="scale_type" type="radio" id="scale_type_up" value="up" <?=($config["widgets"]["trafficgraphs"]["scale_type"]=="up" ? '' : 'checked')?> />
				<?=gettext('Up')?>
			</label>
			<label>
				<input name="scale_type" type="radio" id="scale_type_follow" value="follow" <?=($config["widgets"]["trafficgraphs"]["scale_type"]=="follow" ? 'checked' : '')?> />
				<?=gettext('Follow')?>
			</label>
		</div>
	</div>

	<div class="form-group">
		<label for="refreshinterval" class="col-sm-3 control-label"><?=gettext('Refresh Interval')?></label>
		<div class="col-sm-6">
			<input type="number" id="refreshinterval" name="refreshinterval" value="<?=$refreshinterval?>" min="1" max="30" class="form-control" />
		</div>
	</div>

	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-6">
			<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
		</div>
	</div>
</form>
