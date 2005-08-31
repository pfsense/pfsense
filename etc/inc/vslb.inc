<?php
/* $Id$ */
/*
	vslb.inc
        Copyright (C) 2005 Bill Marquette
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

function slbd_configure() {
	global $config, $g;
	
	$a_vs = &$config['load_balancer']['virtual_server'];
	$a_pool = &$config['load_balancer']['pool'];

	$should_start=0;

	$fd = fopen("{$g['varetc_path']}/slbd.conf", "w");

	if(is_array($a_vs)) {
		foreach ($a_vs as $vsent) {
			if ($vsent['desc'] == "")
				$slbdconf .= "{$vsent['name']}:\\\n";
			else
				$slbdconf .= "{$vsent['name']}|{$vsent['desc']}:\\\n";
	
			/* pool name */
			$slbdconf .= "\t:poolname={$vsent['name']}:\\\n";
			/* virtual IP */
			$slbdconf .= "\t:vip={$vsent['ipaddr']}:\\\n";
			/* virtual port */
			$slbdconf .= "\t:vip-port={$vsent['port']}:\\\n";
			/* fallback IP */
			$slbdconf .= "\t:sitedown={$vsent['sitedown']}:\\\n";
			/* fallback port */
			$slbdconf .= "\t:sitedown-port={$vsent['port']}:\\\n";
	
			for ($i = 0; isset($config['load_balancer']['lbpool'][$i]); $i++) {
				if ($config['load_balancer']['lbpool'][$i]['name'] == $vsent['pool']) {
					$svrcnt = 0;
					$svrtxt = "";
					$svrtxt = "\t:service-port={$config['load_balancer']['lbpool'][$i]['port']}:\\\n";
					foreach ($config['load_balancer']['lbpool'][$i]['servers'] as $lbsvr) {
						$svrtxt .= "\t:{$svrcnt}={$lbsvr}:\\\n";
						$svrcnt++;
					}
					$slbdconf .= "\t:services={$svrcnt}:\\\n";
					$slbdconf .= $svrtxt;
				}
			}
			
			$slbdconf .= "\t:tcppoll:send=:expect=:\n";
			
			$should_start=1;
		}
	}
	
	if($should_start == 1) {
		fwrite($fd, $slbdconf);
		fclose($fd);
		mwexec("/usr/bin/pkill -9 slbd");
		/* startup slbd pointing it's config at /var/etc/slbd.conf with a polling interval of 5 seconds */
		mwexec("/usr/local/sbin/slbd -c{$g['varetc_path']}/slbd.conf -r5000");
	} else {
		fclose($fd);	
	}
}

?>