<?php
/* $Id$ */
/*
	status_rrd_graph.inc
	Part of pfSense
	Copyright (C) 2009 Seth Mos <seth.mos@dds.nl>
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

/* Specify and return length of each graph (seconds).  */
function create_graph_length_array() {
	$graph_length = array(
		"8hour" => 28800,		#                    8 x 60 x 60
		"day" => 86400,			#              1  x 24 x 60 x 60
		"week" => 604800,		#              7  x 24 x 60 x 60
		"month" => 2678400,		#             31  x 24 x 60 x 60
		"quarter" => 7948800,	#  (30 + 31 + 31) x 24 x 60 x 60
		"year" => 31622400,		#  (    365  + 1) x 24 x 60 x 60
		"4year" => 126230400);	# ((4 x 365) + 1) x 24 x 60 x 60

	return $graph_length;
}

/* Calculate and return graph start and end date based on period and graph. */
function get_dates($period, $graph) {
	global $graph_length;
	$now = time();
	$end = $now;

	if($period == "absolute") {
		$start = $end - $graph_length[$graph] + 1;
	} else {
		$curyear = date('Y', $now);
		$curmonth = date('m', $now);
		$curweek = date('W', $now);
		$curweekday = date('N', $now) - 1; // We want to start on monday
		$curday = date('d', $now);
		$curhour = date('G', $now);

		switch($period) {
			case "previous":
				$offset = -1;
				break;
			default:
				$offset = 0;
		}
		switch($graph) {
			case "8hour":
				if($curhour < 24)
					$starthour = 16;
				if($curhour < 16)
					$starthour = 8;
				if($curhour < 8)
					$starthour = 0;

				switch($offset) {
					case 0:
						$houroffset = $starthour;
						break;
					default:
						$houroffset = $starthour + ($offset * 8);
						break;
				}
				$start = mktime($houroffset, 0, 0, $curmonth, $curday, $curyear);
				$end = mktime(($houroffset + 7), 59, 59, $curmonth, $curday, $curyear);
				break;
			case "day":
				$start = mktime(0, 0, 0, $curmonth, ($curday + $offset), $curyear);
				$end = mktime(23, 59, 59, $curmonth, ($curday + $offset), $curyear);
				break;
			case "week":
				switch($offset) {
					case 0:
						$weekoffset = 0;
						break;
					default:
						$weekoffset = ($offset * 7);
						break;
				}
				$start = mktime(0, 0, 0, $curmonth, (($curday - $curweekday) + $weekoffset), $curyear);
				$end = mktime(23, 59, 59, $curmonth, (($curday - $curweekday) + $weekoffset + 6), $curyear);
				break;
			case "month":
				$start = mktime(0, 0, 0, ($curmonth + $offset), 1, $curyear);
				$end = mktime(23, 59, 59, (($curmonth + $offset) + 1), 0, $curyear);
				break;
			case "quarter":
				$start = mktime(0, 0, 0, (($curmonth - 2) + $offset), 1, $curyear);
				$end = mktime(23, 59, 59, (($curmonth + $offset) + 1), 0, $curyear);
				break;
			case "year":
				$start = mktime(0, 0, 0, 1, 1, ($curyear + $offset));
				$end = mktime(23, 59, 59, 1, 0, (($curyear + $offset) +1));
				break;
			case "4year":
				$start = mktime(0, 0, 0, 1, 1, (($curyear - 3) + $offset));
				$end = mktime(23, 59, 59, 1, 0, (($curyear + $offset) +1));
				break;
		}
		if($offset == 0) $end = now;
	}
	// echo "start $start ". date('l jS \of F Y h:i:s A', $start) .", end $end ". date('l jS \of F Y h:i:s A', $end) ."<br>";
	$dates = array();
	$dates['start'] = $start;
	$dates['end'] = $end;
	return $dates;
}

?>
