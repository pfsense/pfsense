<?php
/*
	traffic_graphs.widget.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2007 Scott Dale
 *	Copyright (c)  2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
 *	and Jonathan Watt <jwatt@jwatt.org>.
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
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

	write_config("Updated traffic graph settings via dashboard.");
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
		<label for="scale_type_up" class="col-sm-3 control-label">Show graphs</label>
		<div class="col-sm-6 checkbox">
<?php foreach ($ifdescrs as $ifname => $ifdescr): ?>
			<label>
				<input type="checkbox" name="shown[<?= $ifname?>]" value="<?=$ifname?>" <?= ($shown[$ifname]) ? "checked":""?> />
				<?=$ifname?>
			</label>
<?php endforeach; ?>
		</div>
	</div>
	<div class="form-group">
		<label for="scale_type_up" class="col-sm-3 control-label">Default Autoscale</label>
		<div class="col-sm-6 checkbox">
			<label>
				<input name="scale_type" type="radio" id="scale_type_up" value="up" <?=($config["widgets"]["trafficgraphs"]["scale_type"]=="follow" ? '' : 'checked')?> />
				up
			</label>
			<label>
				<input name="scale_type" type="radio" id="scale_type_follow" value="up" <?=($config["widgets"]["trafficgraphs"]["scale_type"]=="follow" ? 'checked' : '')?> />
				follow
			</label>
		</div>
	</div>

	<div class="form-group">
		<label for="refreshinterval" class="col-sm-3 control-label">Refresh Interval</label>
		<div class="col-sm-6">
			<input type="number" id="refreshinterval" name="refreshinterval" value="<?=$refreshinterval?>" min="1" max="30" class="form-control" />
		</div>
	</div>

	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-6">
			<button type="submit" class="btn btn-default">Save</button>
		</div>
	</div>
</form>
