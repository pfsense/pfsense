<?php 
/*
	$Id$
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2003-2006 Manuel Kasper <mk@neon1.net>.
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
/*	
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-status-cpuload
##|*NAME=Status: CPU load page
##|*DESCR=Allow access to the 'Status: CPU load' page.
##|*MATCH=status_graph_cpu.php*
##|-PRIV

$pgtitle = array(gettext("Status"), gettext("CPU load"));
require("guiconfig.inc");
include("head.inc");
include("fbegin.inc");

$pgtitle = gettext("Status: CPU Graph");

?>
<div align="center">
<embed src="graph_cpu.php" type="image/svg+xml"
		width="550" height="275" pluginspage="http://www.adobe.com/svg/viewer/install/auto" />
</div>
<br><span class="red"><strong><?=gettext("Note"); ?>:</strong></span> <?=gettext("if you can't see the graph, you may have to install the"); ?> <a href="http://www.adobe.com/svg/viewer/install/" target="_blank"><?=gettext("Adobe SVG viewer"); ?></a>.
<?php include("fend.inc"); ?>
