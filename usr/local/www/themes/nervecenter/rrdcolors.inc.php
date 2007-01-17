<?php
/* $Id$ */
/*
	rrdcolors.inc.php
	Part of pfSense
	Copyright (C) 2006 Seth Mos <seth.mos@xs4all.nl>
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

/* This file is included by the RRD graphing page and sets the colors */

$colortrafficup = "666666";
$colortrafficdown = "990000";
$colorpacketsup = "666666";
$colorpacketsdown = "990000";
$colorstates = array('990000','a83c3c','b36666','bd9090','cccccc','000000');
$colorprocessor = array('990000','a83c3c','b36666','bd9090','cccccc','000000');
$colorqueuesup = array('000000','7B0000','990000','BB0000','CC0000','D90000','EE0000','FF0000','CC0000');
$colorqueuesdown = array('000000','7B7B7B','999999','BBBBBB','CCCCCC','D9D9D9','EEEEEE','FFFFFF','CCCCCC');
$colorqueuesdropup = array('000000','7B0000','990000','BB0000','CC0000','D90000','EE0000','FF0000','CC0000');
$colorqueuesdropdown = array('000000','7B7B7B','999999','BBBBBB','CCCCCC','D9D9D9','EEEEEE','FFFFFF','CCCCCC');
$colorqualityrtt = array('990000','a83c3c','b36666','bd9090','cccccc','000000');
$colorqualityloss = "ee0000";
$colorspamdtime = array('DDDDFF', 'AAAAFF', 'DDDDFF', '000066');
$colorspamdconn = array('00AA00BB', 'FFFFFFFF', '00660088', 'FFFFFF88', '006600');

?>
