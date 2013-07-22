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

/* Traffic Outbound		Out-P-4,  Out-B-4,  Out-P-6,  Out-B-6 */
$colortrafficup		= array('666666', 'CCCCCC', '2217AA', '625AE7');

/* Traffic Inbound		In-P-4,   In-B-4,    In-P-6,  In-B-6 */
$colortrafficdown	= array('990000', 'CC0000', 'FFC875', 'FF9900');

/* Packets Outbound		Out-P-4,  Out-B-4,  Out-P-6,  Out-B-6 */
$colorpacketsup		= array('666666', 'CCCCCC', '2217AA', '625AE7');

/* Packets Inbound		In-P-4,   In-B-4,    In-P-6,  In-B-6 */
$colorpacketsdown	= array('990000', 'CC0000', 'FFC875', 'FF9900');

/* 95th Percentile Lines	Out,      In */
$colortraffic95		= array('660000', 'FF0000');

/* State Table			pfrate,  pfstates, pfnat,  srcip,   dstip */
$colorstates		= array('00AA00','990000','0000FF','000000','DD9B00');

/* Processor Usage		user,    nice,    system,  int,     processes */
$colorprocessor		= array('00AA00','990000','0000FF','DD9B00','000000');

/* Memory Usage			active,  inact,   free,    cache,   wire */
$colormemory		= array('00AA00','990000','0000FF','666666','DD9B00');

/* MBUF Usage			current, cache,   total,   max */
$colormbuf		= array('0080FF','00E344','FF0000','000000');

/* Traffic Shaper Queues	q1,      q2,      q3,      q4,      q5,      q6,      q7,      q8,      q9 */
$colorqueuesup		= array('000000','7B0000','0080FF','00E344','FF0000','2217AA','FFC875','FF9900','CC0000');
$colorqueuesdown	= array('000000','7B7B7B','999999','BBBBBB','CCCCCC','D9D9D9','EEEEEE','FFFFFF','CCCCCC');

$colorqueuesdropup	= array('000000','7B0000','0080FF','00E344','FF0000','2217AA','FFC875','FF9900','CC0000');
$colorqueuesdropdown	= array('000000','7B7B7B','999999','BBBBBB','CCCCCC','D9D9D9','EEEEEE','FFFFFF','CCCCCC');

/* Quality Graph Delay	>420,    180-420, 60-180,  20-60,   <20,     Delay Avg */
$colorqualityrtt	= array('990000','a83c3c','b36666','bd9090','cccccc','000000');
/* Quality Graph Loss */
$colorqualityloss	= 'ee0000';

/* Wireless Graph		SNR,     Rate,    Channel*/
/* Cellular Graph		RSSI,     */
$colorwireless		= array('333333','a83c3c','999999');

/* SPAMD Times			min area, avg area, max area, Time line */
$colorspamdtime		= array('DDDDFF', 'AAAAFF', 'DDDDFF', '000066');
/* SPAMD Connections		max area,   min area,   min line,   max line,   avg line */
$colorspamdconn		= array('AA00BB', 'FFFFFF', '660088', 'FFFF88', '006600');

/* OpenVPN Users		Online Users */
$colorvpnusers		= array('990000');

/* Captive Portal Total Users	Total Users */
/* Captive Portal Concurrent	Concurrent Users */
$colorcaptiveportalusers = array('990000');

?>
