<?php
/*
    services_status.php
    Copyright (C) 2004, 2005 Scott Ullrich
    All rights reserved.

    services_status.widget.php
    Copyright (C) 2007 Sam Wenham

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
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
require_once("captiveportal.inc");
require_once("service-utils.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("/usr/local/www/widgets/include/services_status.inc");

$services = get_services();

if(isset($_POST['servicestatusfilter'])) {
	$config['widgets']['servicestatusfilter'] = $_POST['servicestatusfilter'];
	write_config("Saved Service Status Filter via Dashboard");
	header("Location: ../../index.php");
}
?>
<input type="hidden" id="services_status-config" name="services_status-config" value="">
<div id="services_status-settings" name="services_status-settings" class="widgetconfigdiv" style="display:none;">
	<form action="/widgets/widgets/services_status.widget.php" method="post" name="iforma">
		Comma separated list of services to NOT display in the widget<br />
		<input type="text" length="30" name="servicestatusfilter" class="formfld unknown" id="servicestatusfilter" value="<?= $config['widgets']['servicestatusfilter'] ?>">
		<input id="submita" name="submita" type="submit" class="formbtn" value="Save" />
    </form>
</div>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
	  <td class="widgetsubheader"><b><center>Service</center></b></td>
	  <td class="widgetsubheader"><b><center>Description</center></b></td>
		<td class="widgetsubheader"><b><center>Status</center></b></td>
		<td class="widgetsubheader">&nbsp;</td>
	</tr>
<?php
$skipservices = explode(",", $config['widgets']['servicestatusfilter']);

if (count($services) > 0) {
	uasort($services, "service_name_compare");
	foreach($services as $service) {
		if((!$service['name']) || (in_array($service['name'], $skipservices)))
			continue;
		if (empty($service['description']))
			$service['description'] = get_pkg_descr($service['name']);
		echo '<tr><td class="listlr">' . $service['name'] . '</td>' . "\n";
		echo '<td class="listr">' . $service['description'] . '</td>' . "\n";
		echo get_service_status_icon($service, false, true);
		echo '<td valign="middle" class="list" nowrap>';
		echo get_service_control_links($service);
		echo "</td></tr>\n";
	}
} else {
	echo "<tr><td colspan=\"3\"><center>" . gettext("No services found") . ".</td></tr>\n";
}
?>
</table>

<!-- needed to display the widget settings menu -->
<script language="javascript" type="text/javascript">
	selectIntLink = "services_status-configure";
	textlink = document.getElementById(selectIntLink);
	textlink.style.display = "inline";
</script>
