<?php
/*
	Copyright 2012 mkirbst @ pfSense Forum
	Part of pfSense widgets (www.pfsense.com)
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

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/smart_status.inc");
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="smart status">
	<tr>
		<td class="widgetsubheader" align="center"><b><?php echo gettext("Drive") ?></b></td>
		<td class="widgetsubheader" align="center"><b><?php echo gettext("Ident") ?></b></td>
		<td class="widgetsubheader" align="center"><b><?php echo gettext("SMART Status") ?></b></td>
	</tr>

<?php
$devs = array();
## Get all AD* and DA* (IDE and SCSI) devices currently installed and st$
exec("ls /dev | grep '^[ad][da][0-9]\{1,2\}$'", $devs); ## leant from orginal SMART status screen

if(count($devs) > 0)  {
	foreach($devs as $dev)  {	## for each found drive do
		$dev_ident = exec("diskinfo -v /dev/$dev | grep ident   | awk '{print $1}'"); ## get identifier from drive
		$dev_state = exec("smartctl -H /dev/$dev | grep result: | awk '{print $6}'"); ## get SMART state from drive
		# Use light green color for passed, light coral otherwise.
		$color = ($dev_state == "PASSED") ? "#90EE90" : "#F08080";
?>
		<tr>
			<td class="listlr"><?php echo $dev; ?></td>
			<td class="listr" align="center"><?php echo $dev_ident; ?></td>
			<td class="listr" align="center"><span style="background-color:<?php echo $color; ?>">&nbsp;<?php echo $dev_state; ?>&nbsp;</span></td>
		</tr>
<?php	}
}
?>
</table>
