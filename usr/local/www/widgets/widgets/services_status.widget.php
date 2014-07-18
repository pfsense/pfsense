<?php
/*
    services_status.widget.php
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
	$config['widgets']['servicestatusfilter'] = htmlspecialchars($_POST['servicestatusfilter'], ENT_QUOTES | ENT_HTML401);
	write_config("Saved Service Status Filter via Dashboard");
	header("Location: ../../index.php");
}
?>
<input type="hidden" id="services_status-config" name="services_status-config" value="" />
<div id="services_status-settings" class="widgetconfigdiv" style="display:none;">
	<form action="/widgets/widgets/services_status.widget.php" method="post" name="iformd">
		Comma separated list of services to NOT display in the widget<br />
		<input type="text" size="30" name="servicestatusfilter" class="formfld unknown" id="servicestatusfilter" value="<?= $config['widgets']['servicestatusfilter'] ?>" />
		<input id="submitd" name="submitd" type="submit" class="formbtn" value="Save" />
    </form>
</div>

<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="services">
	<tr>
	  <td class="widgetsubheader" align="center"><b>Service</b></td>
	  <td class="widgetsubheader" align="center"><b>Description</b></td>
	  <td class="widgetsubheader" align="center"><b>Status</b></td>
	  <td class="widgetsubheader">&nbsp;</td>
	</tr>
<?php
$skipservices = explode(",", $config['widgets']['servicestatusfilter']);

if (count($services) > 0) {
	uasort($services, "service_name_compare");
	foreach($services as $service) {
		if((!$service['name']) || (in_array($service['name'], $skipservices)) || (!is_service_enabled($service['name'])))
			continue;
		if (empty($service['description']))
			$service['description'] = get_pkg_descr($service['name']);
		$service_desc = explode(".",$service['description']);
		echo "<tr><td class=\"listlr\">" . $service['name'] . "</td>\n";
		echo "<td class=\"listr\">" . $service_desc[0] . "</td>\n";
		// if service is running then listr else listbg
		$bgclass = null;
		if (get_service_status($service))
			$bgclass = "listr";
		else
			$bgclass = "listbg";
		echo "<td class=\"" . $bgclass . "\" align=\"center\">" . get_service_status_icon($service, false, true) . "</td>\n";
		echo "<td valign=\"middle\" class=\"list nowrap\">" . get_service_control_links($service) . "</td></tr>\n";
	}
} else {
	echo "<tr><td colspan=\"3\" align=\"center\">" . gettext("No services found") . " . </td></tr>\n";
}
?>
</table>

<!-- needed to display the widget settings menu -->
<script type="text/javascript">
//<![CDATA[
	selectIntLink = "services_status-configure";
	textlink = document.getElementById(selectIntLink);
	textlink.style.display = "inline";
//]]>
</script>
