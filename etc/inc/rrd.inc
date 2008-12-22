<?php
/* $Id$ */
/*
  Copyright (C) 2008 Seth Mos
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

/* include all configuration functions */
require_once("functions.inc");
require_once("pkg-utils.inc");
require_once("notices.inc");
require_once("globals.inc");

function dump_rrd_to_xml($rrddatabase, $xmldumpfile) {
	$rrdtool = "/usr/local/bin/rrdtool";
	exec("rm {$xmldumpfile}");
	exec("$rrdtool dump {$rrddatabase} {$xmldumpfile} 2>&1", $dumpout, $dumpret);
	if ($dumpret <> 0) {
		$dumpout = implode(" ", $dumpout);
		log_error("RRD dump failed exited with $dumpret, the error is: $dumpout");
	}
	return($dumpret);
}

function create_new_rrd($rrdcreatecmd) {
	$rrdcreateoutput = array();
	$rrdcreatereturn = 0;

	exec("$rrdcreatecmd 2>&1", $rrdcreateoutput, $rrdcreatereturn);
	if ($rrdcreatereturn <> 0) {
		$rrdcreateoutput = implode(" ", $rrdcreateoutput);
		log_error("RRD create failed exited with $rrdcreatereturn, the error is: $rrdcreateoutput");
	}
	return $rrdcreatereturn;
}

function migrate_rrd_format($rrdoldxml, $rrdnewxml) {
	$numrraold = count($rrdoldxml['rra']);
	$numdsold = count($rrdoldxml['ds']);
	$numrranew = count($rrdnewxml['rra']);
	$numdsnew = count($rrdnewxml['ds']);
	log_error("Import RRD has $numdsold DS values and $numrraold RRA databases, new format RRD has $numdsnew DS values and $numrranew RRA databases");
	
	/* add data sources not found in the old array from the new array */
	$i = 0;
	foreach($rrdnewxml['ds'] as $ds) {
		if(!is_array($rrdoldxml['ds'][$i])) {
			$rrdoldxml['ds'][$i] = $rrdnewxml['ds'][$i];
		}
		$i++;
	}

	$i = 0;
	$rracountold = count($rrdoldxml['rra']);
	$rracountnew = count($rrdnewxml['rra']);
	/* process each RRA, which contain a database */
	foreach($rrdnewxml['rra'] as $rra) {
		if(!is_array($rrdoldxml['rra'][$i])) {
			$rrdoldxml['rra'][$i] = $rrdnewxml['rra'][$i];
		}

		$d = 0;
		/* process cdp_prep */
		$cdp_prep = $rra['cdp_prep'];
		foreach($cdp_prep['ds'] as $ds) {
			if(!is_array($rrdoldxml['rra'][$i]['cdp_prep']['ds'][$d])) {
				$rrdoldxml['rra'][$i]['cdp_prep']['ds'][$d] = $rrdnewxml['rra'][$i]['cdp_prep']['ds'][$d];
			}
			$d++;
		}

		/* process database */
		$rows = $rra['database'];
		$k = 0;
		$rowcountold = count($rrdoldxml['rra'][$i]['database']['row']);
		$rowcountnew = count($rrdnewxml['rra'][$i]['database']['row']);
		/* now foreach the rows in the database */
		foreach($rows['row'] as $row) {
			if(!is_array($rrdoldxml['rra'][$i]['database']['row'][$k])) {
				$rrdoldxml['rra'][$i]['database']['row'][$k] = $rrdnewxml['rra'][$i]['database']['row'][$k];
			}
			$m = 0;
			$vcountold = count($rrdoldxml['rra'][$i]['database']['row'][$k]['v']);
			$vcountnew = count($rrdnewxml['rra'][$i]['database']['row'][$k]['v']);
			foreach($row['v'] as $value) {
				if(empty($rrdoldxml['rra'][$i]['database']['row'][$k]['v'][$m])) {
					if(isset($valid)) {
						$rrdoldxml['rra'][$i]['database']['row'][$k]['v'][$m] = "0.0000000000e+00 ";
					} else {
						$rrdoldxml['rra'][$i]['database']['row'][$k]['v'][$m] = $rrdnewxml['rra'][$i]['database']['row'][$k]['v'][$m];
					}
				} else {
					if($value <> " NaN ") {
						$valid = true;
					} else {
						$valid = false;
					}
				}
				$m++;
			}
			$k++;
		}
		$i++;
	}

	$numrranew = count($rrdoldxml['rra']);
	$numdsnew = count($rrdoldxml['ds']);
	log_error("The new RRD now has $numdsnew DS values and $numrranew RRA databases");
	return $rrdoldxml;
}

