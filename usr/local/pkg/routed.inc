<?php
/* $Id$ */
/*
	routed.inc
	Copyright (C) 2006 Bill Marquette
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

function setup_routed() {
	global $config;
	$gw = "";

	if (!is_array($config['installedpackages']['routed']))
		return;
	if (!is_array($config['installedpackages']['routed']['config']))
		return;
	if (isset($config['installedpackages']['routed']['config'][0]['enable']) &&
		$config['installedpackages']['routed']['config'][0]['enable'] == "on") {
		/* if user selected individual interfaces */
		$ifdescrs = get_configured_interface_with_descr();
		$ifarr = explode(",", $config['installedpackages']['routed']['config'][0]['iface_array']);
                if (count($ifarr) != 0) {
			foreach($ifdescrs as $ifdescr => $ifname) {
				if (in_array($ifname, $ifarr)) {
					$gw .= setup_etc_gateways($ifdescr, 'enable');
				} else {
					$gw .= setup_etc_gateways($ifdescr, 'disable');
				}
			}
		} else {
			/* setup for all interfaces */
			$gw = setup_etc_gateways();
		}
		conf_mount_rw();
		$fd = fopen("/etc/gateways", "w");
		fwrite($fd, $gw);
		fclose($fd);
		conf_mount_ro();
		restart_routed();
	} else {
		stop_routed();
	}
}

function setup_etc_gateways($iface="", $mode="") {
	global $config;

	$ret = "";
	if ($iface != "") {
		$realif=convert_friendly_interface_to_real_interface_name($iface);
		if ($realif)
			$ret = "if={$realif} ";
	}

	switch($mode) {
	case "enable":
		if ($config['installedpackages']['routed']['config'][0]['ripversion'] == "2") {
			$ret .= "ripv2 ";
			$passwd = $config['installedpackages']['routed']['config'][0]['passwd'];
			if ($passwd != "") {
				$ret .= "passwd={$passwd} ";
			}
		}
		break;
	case "disable":
		$ret .= "no_rip ";
		break;

	default:
		break;

	}
	$ret .= "\n";

	return $ret;
}

function start_routed() {
	mwexec("/sbin/routed");
}

function stop_routed() {
	if(isvalidproc("routed"))
		mwexec("killall routed");
}

function restart_routed() {
	stop_routed();
	start_routed();
}

?>
