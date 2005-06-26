<?php
/****h* pfSense/service-utils
 * NAME
 *   service-utils.inc - Service facility
 * DESCRIPTION
 *   This file contains various functions used by the pfSense service facility.
 * HISTORY
 *   $Id$
 ******
 *
 * Copyright (C) 2005 Colin Smith (ethethlay@gmail.com)
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * RISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 */

function write_rcfile($params) {
	global $config, $g;
	$fileprefix = "/usr/local/etc/rc.d/";
	if(!(is_writable($fileprefix . $params['file']) or $params['start'])) return false;
	$towrite .= "#!/bin/sh\n# This file was automatically generated\n# by the pfSense service handler.\n\n";
	/* write our rc functions */
	$towrite .= "rc_start() {\n\t" . $params['start'] . "\n}\n\n";
	if($params['stop']) {
		$tokill =& $params['stop'];
	} elseif($params['executable']) {
		// just nuke the executable
		$tokill = "/usr/bin/killall {$params['executable']}";
	} else {
		// make an educated guess (bad)
		$tokill = array_pop(explode('/', array_shift(explode(' ', $params['start']))));
	}
	$towrite .= "rc_stop() {\n\t" . $tokill . "\n}\n\n";
	
	/* begin rcfile logic */
	$towrite .= "case $1 in\n\tstart)\n\t\trc_start\n\t\t;;\n\tstop)\n\t\trc_stop\n\t\t;;\n\trestart)\n\t\trc_stop\n\t\trc_start\n\t\t;;\nesac\n\n";
	$fout = fopen($fileprefix . $params['file'], "w");
	fwrite($fout, $towrite);
	fclose($fout);
	chmod($fileprefix . $params['file'], 0755);
	return;
}

function start_service($name) {
	global $config, $g;
	if($config['installedpackages']['service']) {
		foreach($config['installedpackages']['service'] as $service) {
			if($service['name'] == $name) {
				if($service['rcfile']) {
					if($service['prefix']) {
						$prefix =& $service['prefix'];
					} else {
						$prefix = "/usr/local/etc/rc.d/";
					}
					mwexec_bg($prefix . $service['rcfile'] . " start");
				}
				if($service['startcmd']) {
					eval($service['startcmd']);
				}
				break;
			}
		}
	}
}

function stop_service($name) {
        global $config, $g;
        if($config['installedpackages']['service']) {
                foreach($config['installedpackages']['service'] as $service) {
                        if($service['name'] == $name) {
                                if($service['rcfile']) {
                                        if($service['prefix']) {
                                                $prefix =& $service['prefix'];
                                        } else {
                                                $prefix = "/usr/local/etc/rc.d/";
                                        }
                                        mwexec_bg($prefix . $service['rcfile'] . " stop");
                                }
                                if($service['stopcmd']) {
                                        eval($service['stopcmd']);
                                }
				if(!($service['rcfile'] or $service['stopcmd'])) {
					mwexec_bg("/usr/bin/killall {$service['executable']}");
				}
                                break;
                        }
                }
        }
}

function restart_service($name) {
        global $config, $g;
	stop_service($name);
	start_service($name);
        if($config['installedpackages']['service']) {
                foreach($config['installedpackages']['service'] as $service) {
                        if($service['name'] == $name) {
                                if($service['restartcmd']) {
                                        eval($service['restartcmd']);
                                }
                                break;
                        }
                }
        }
}

function is_process_running($process, $ps = "") {
	if(!$ps) {
                exec("/bin/ps ax | awk '{ print $5 }'", $psout);
                array_shift($psout);
                foreach($psout as $line) {
                        $ps[] = trim(array_pop(explode(' ', array_pop(explode('/', $line)))));
                }
		print_r($ps);
        }
	if(in_array($process, $ps)) { 
		return true;
	} else {
		return false;
	}
	return false;
}

function is_service_running($service, $ps = "") {
	global $config;
	if($config['installedpackages']['service']) {
                foreach($config['installedpackages']['service'] as $aservice) {
                        if($service == $aservice['name']) {
				if(!$aservice['executable']) return false;
				if(is_process_running($aservice['executable'], $ps)) {
					return true;
				} else {
					return false;
				}
				break;
                        }
                }
        }
	return false;
}
