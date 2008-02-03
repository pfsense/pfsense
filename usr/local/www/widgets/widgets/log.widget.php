<?php
/*
        $Id$
        Copyright 2007 Scott Dale
        Part of pfSense widgets (www.pfsense.com)
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

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/log.inc");
?>

<div class="log-header">
    <span class="log-action-mini-header">Act</span>
    <span class="log-interface-mini-header">IF</span>
    <span class="log-source-mini-header">Source</span>
    <span class="log-destination-mini-header">Destination</span>
    <span class="log-protocol-mini-header">Prot</span>
</div>
<?php $counter=0; foreach ($filterlog as $filterent): ?>
<?php
	if(isset($config['syslog']['reverse'])) {
		/* honour reverse logging setting */
		if($counter == 0)
			$activerow = " id=\"firstrow\"";
		else
			$activerow = "";

	} else {
		/* non-reverse logging */
		if($counter == count($filterlog))
			$activerow = " id=\"firstrow\"";
		else
			$activerow = "";
	}
?>
<div class="log-entry-mini" <?php echo $activerow; ?> style="clear:both;">
	<span class="log-action-mini" nowrap>
	<?php
		if (strstr(strtolower($filterent['act']), "p"))
			$img = "/themes/metallic/images/icons/icon_pass.gif";
		else if(strstr(strtolower($filterent['act']), "r"))
			$img = "/themes/metallic/images/icons/icon_reject.gif";
		else
			$img = "/themes/metallic/images/icons/icon_block.gif";
	?>
	&nbsp;<img border="0" src="<?=$img;?>">&nbsp;</span>
	<span class="log-interface-mini" ><?=htmlspecialchars(convert_real_interface_to_friendly_interface_name($filterent['interface']));?>&nbsp;</span>
	<span class="log-source-mini" ><?=htmlspecialchars($filterent['src']);?>&nbsp;</span>
	<span class="log-destination-mini" ><?=htmlspecialchars($filterent['dst']);?>&nbsp;</span>
	<span class="log-protocol-mini" ><?=htmlspecialchars($filterent['proto']);?>&nbsp;</span>
</div>
<?php $counter++; endforeach; ?>