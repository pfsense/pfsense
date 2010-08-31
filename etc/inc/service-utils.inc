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
 * Copyright (C) 2005-2006 Colin Smith (ethethlay@gmail.com)
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

/*
	pfSense_BUILDER_BINARIES:	/usr/bin/killall	/bin/sh	/bin/ps	
	pfSense_MODULE:	utils
*/

function write_rcfile($params) {
	$fileprefix = "/usr/local/etc/rc.d/";
	if(!(is_writable($fileprefix . $params['file']) or $params['start'])) return false;
	$towrite .= "#!/bin/sh\n# This file was automatically generated\n# by the {$g['product_website']} service handler.\n\n";
	/* write our rc functions */
	$towrite .= "rc_start() {\n\t" . $params['start'] . "\n}\n\n";
	if($params['stop']) {
		$tokill =& $params['stop'];
	} elseif($params['executable']) {
		/* just nuke the executable */
		$tokill = "/usr/bin/killall {$params['executable']}";
	} else {
		/* make an educated guess (bad) */
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
	global $config;
	/* make sure service is stopped before starting */
	stop_service($name);
	sleep(2);
	if(file_exists("/usr/local/etc/rc.d/{$name}.sh")) {
		exec("/bin/sh /usr/local/etc/rc.d/{$name}.sh start");
		return;
	}
	if($config['installedpackages']['service']) {
		foreach($config['installedpackages']['service'] as $service) {
			if(strtolower($service['name']) == strtolower($name)) {
				if($service['rcfile']) {
					if($service['prefix']) {
						$prefix =& $service['prefix'];
					} else {
						$prefix = "/usr/local/etc/rc.d/";
					}
					if(file_exists($prefix . $service['rcfile'])) {
						mwexec_bg($prefix . $service['rcfile'] . " start");
					} else {
						if(file_exists("/usr/local/etc/rc.d/{$name}.sh"))
							mwexec_bg("/usr/local/etc/rc.d/{$name}.sh start");
					}
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
	global $config;

	if ($config['installedpackages']['service']) {
		foreach($config['installedpackages']['service'] as $service) {
			if(strtolower($service['name']) == strtolower($name)) {
				if($service['rcfile']) {
					if($service['prefix']) {
						$prefix =& $service['prefix'];
					} else {
						$prefix = "/usr/local/etc/rc.d/";
					}
					mwexec_bg("{$prefix}{$service['rcfile']} stop");
				}
				if($service['stopcmd']) {
					eval($service['stopcmd']);
				}
				if(!($service['rcfile'] or $service['stopcmd'])) {
					if(is_process_running("{$service['executable']}"))
						mwexec_bg("/usr/bin/killall {$service['executable']}");
					return;
				}
				break;
			}
		}
	}
	/* finally if we get here lets simply kill the service name */
	if(is_process_running("{$name}"))
		mwexec_bg("/usr/bin/killall {$name}");
}

function restart_service($name) {
	global $config;
	stop_service($name);
	start_service($name);
	if($config['installedpackages']['service']) {
		foreach($config['installedpackages']['service'] as $service) {
			if(strtolower($service['name']) == strtolower($name)) {
				if($service['restartcmd']) {
					eval($service['restartcmd']);
				}
				break;
			}
		}
	}
}

function is_pid_running($pidfile) {
	$pid = trim(file_get_contents($pidfile));
	$running = (trim(shell_exec("/usr/bin/procstat {$pid} 2>/dev/null")) != '');
	return $running;
}

function is_dhcp_running($interface) {
	$status = find_dhclient_process($interface);
	if($status <> "")
		return true;
	return false;
}

function restart_service_if_running($service) {
	global $config;
	if(is_service_running($service))
		restart_service($service);
	return;
}

function is_service_running($service, $ps = "") {
	global $config;
	/*
	if(!$ps) {
		exec("/bin/ps ax | awk '{ print $5 }'", $psout);
	}
	*/
	if(is_array($config['installedpackages']['service'])) {
		foreach($config['installedpackages']['service'] as $aservice) {
			if(strtolower($service) == strtolower($aservice['name'])) {
				if($aservice['custom_php_service_status_command'] <> "") {
					$_cmd=explode(';', $aservice['custom_php_service_status_command']);
					foreach($_cmd as $_acmd) {
						if($_acmd) eval('$rc='.$_acmd.';');
					}
					return $rc;
				}
				if(!$aservice['executable']) return false;
				/*
				if(count(preg_grep("/{$aservice['executable']}/i", $ps))) {
					return true;
				} else {
					return false;
				}
				*/
				return is_process_running($aservice['executable']) ? true : false;
			}
		}
	}
}

?>
