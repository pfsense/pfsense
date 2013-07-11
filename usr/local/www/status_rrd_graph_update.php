<?php
/* $Id$ */
/*
	status_rrd_graph_update.php
	Part of pfSense
	Copyright (C) 2013 NOYB <NOYB at NOYB dot com>
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
	pfSense_BUILDER_BINARIES:	none
	pfSense_MODULE:	system
*/

require_once("status_rrd_graph.inc");

$graph_length = create_graph_length_array();

status_rrd_graph_update_set_start_end();

/* Now update the graph. */
include("status_rrd_graph_img.php");

/* Update start and end times for graph update based on period and graph. */
function status_rrd_graph_update_set_start_end() {

	$status_rrd_graph_update_vars = status_rrd_graph_update_get_set_vars();
	$graph  = $status_rrd_graph_update_vars['graph'];
	$period = $status_rrd_graph_update_vars['period'];

	$dates = get_dates($period, $graph);

	$_GET['start'] = $dates['start'];
	$_GET['end'] = $dates['end'];
}

/* Get and return needed vars from URL query string. */
function status_rrd_graph_update_get_set_vars() {

	if ($_GET['graph']) {
		$curgraph = $_GET['graph'];
	} else {
		$curgraph = "custom";
	}

	if ($_GET['period']) {
		$curperiod = $_GET['period'];
	} else {
		if(! empty($config['rrd']['period'])) {
			$curperiod = $config['rrd']['period'];
		} else {
			$curperiod = "absolute";
		}
	}

	$result = array();
	$result['graph'] = $curgraph;
	$result['period'] = $curperiod;
	return $result;
}

?>
