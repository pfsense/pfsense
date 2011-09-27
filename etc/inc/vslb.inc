<?php
/* $Id$ */
/*
  vslb.inc
  Copyright (C) 2005-2008 Bill Marquette
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
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/relayd
	pfSense_MODULE:	routing
*/

/* DISABLE_PHP_LINT_CHECKING */

/* include all configuration functions */

class Monitor {
	private $conf = array();
	function __construct($config) {
		$this->conf = $config;
	}

	public function p() {
		return "check {$this->get('proto')}";
	}
	private function get($var) {
		return isset($this->$var) ? $this->$var : "";
	}
	protected function config($element) {
		return isset($this->conf[$element]) ? $this->conf[$element] : "";
	}
}

class TCPMonitor extends Monitor {
	protected $proto = 'tcp';
}

class SSLMonitor extends Monitor {
	protected $proto = 'ssl';
}

class ICMPMonitor extends Monitor {
	protected $proto = 'icmp';
}

class HTTPMonitor extends Monitor {
	protected $proto = 'http';
	function __construct($config) {
		parent::__construct($config);
	}
	public function p() {
		$method = ($this->code() != "") ? $this->code() : $this->digest();
		return "check {$this->proto} {$this->path()} {$this->host()} {$method}";
	}

	private function path() {
		return $this->config('path') != "" ? "'{$this->config('path')}'" : "";
	}

	private function host() {
		return $this->config('host') != "" ? "host {$this->config('host')}" : "";
	}

	private function code() {
		return $this->config('code') != "" ? "code {$this->config('code')}" : "";
	}

	private function digest() {
		return $this->config('digest') != "" ? "digest {$this->config('digest')}" : "";
	}
}

class HTTPSMonitor extends HTTPMonitor {
	protected $proto = 'https';
}

class SendMonitor extends Monitor {
	private $proto = 'send';
	function __construct($config) {
		parent::__construct($config);
	}
	public function p() {
		return "check {$this->proto} {$this->data()} expect {$this->pattern()} {$this->ssl()}";
	}


	private function data() {
		return $this->config('send') != "" ? "{$this->config('send')}" : "";
	}

	private function pattern() {
		return $this->config('expect') != "" ? "{$this->config('expect')}" : "";
	}

	private function ssl() {
		return $this->config('ssl') == true ? "ssl" : "";
	}
}

function echo_lbaction($action) {
  global $config;
  
  // Index actions by name
  $actions_a = array();
  for ($i=0; isset($config['load_balancer']['lbaction'][$i]); $i++)
		$actions_a[$config['load_balancer']['lbaction'][$i]['name']] = $config['load_balancer']['lbaction'][$i];

  $ret = "";
  $ret .=  "{$actions_a[$action]['direction']} {$actions_a[$action]['type']} {$actions_a[$action]['action']}";
  switch($actions_a[$action]['action']) {
    case 'append': {
      $ret .= " \"{$actions_a[$action]['options']['value']}\" to \"{$actions_a[$action]['options']['akey']}\"";
      break;
    }
    case 'change': {
      $ret .= " \"{$actions_a[$action]['options']['akey']}\" to \"{$actions_a[$action]['options']['value']}\"";
      break;
    }
    case 'expect': {
      $ret .= " \"{$actions_a[$action]['options']['value']}\" from \"{$actions_a[$action]['options']['akey']}\"";
      break;
    }
    case 'filter': {
      $ret .= " \"{$actions_a[$action]['options']['value']}\" from \"{$actions_a[$action]['options']['akey']}\"";
      break;
    }
    case 'hash': {
      $ret .= " \"{$actions_a[$action]['options']['akey']}\"";
      break;
    }
    case 'log': {
      $ret .= " \"{$actions_a[$action]['options']['akey']}\"";
      break;
    }
  }
  return $ret;
}

function relayd_configure() {
  global $config, $g;

  $vs_a = $config['load_balancer']['virtual_server'];
  $pool_a = $config['load_balancer']['lbpool'];
  $protocol_a = $config['load_balancer']['lbprotocol'];

  $check_a = array();

  foreach ((array)$config['load_balancer']['monitor_type'] as $type) {
    switch($type['type']) {
      case 'icmp': {
        $mon = new ICMPMonitor($type['options']);
	break;
      }
      case 'tcp': {
        $mon = new TCPMonitor($type['options']);
	break;
      }
      case 'http': {
        $mon = new HTTPMonitor($type['options']);
	break;
      }
      case 'https': {
        $mon = new HTTPSMonitor($type['options']);
	break;
      }
      case 'send': {
        $mon = new SendMonitor($type['options']);
	break;
      }
    }
    if($mon) {
      $check_a[$type['name']] = $mon->p();
    }
  }


  $fd = fopen("{$g['varetc_path']}/relayd.conf", "w");

  /* reindex pools by name as we loop through the pools array */
  $pools = array();
  $conf .= "log updates \n";
  $conf .= "timeout 1000 \n";
  /* Virtual server pools */
  if(is_array($pool_a)) {
    for ($i = 0; isset($pool_a[$i]); $i++) {
      if(is_array($pool_a[$i]['servers'])) {
	if (!empty($pool_a[$i]['retry'])) {
		$retrytext = " retry {$pool_a[$i]['retry']}";
		$srvtxt = implode("{$retrytext}, ", $pool_a[$i]['servers']) . "{$retrytext}";
	} else {
		$srvtxt = implode(", ", $pool_a[$i]['servers']);
	}
        $conf .= "table <{$pool_a[$i]['name']}> { $srvtxt }\n";
        /* Index by name for easier fetching when we loop through the virtual servers */
        $pools[$pool_a[$i]['name']] = $pool_a[$i];
      }
    }
  }
//  if(is_array($protocol_a)) {
//    for ($i = 0; isset($protocol_a[$i]); $i++) {
//      $proto = "{$protocol_a[$i]['type']} protocol \"{$protocol_a[$i]['name']}\" {\n";
//      if(is_array($protocol_a[$i]['lbaction'])) {
//        if($protocol_a[$i]['lbaction'][0] == "") {
//          continue;
//        }
//        for ($a = 0; isset($protocol_a[$i]['lbaction'][$a]); $a++) {
//          $proto .= "  " . echo_lbaction($protocol_a[$i]['lbaction'][$a]) . "\n";
//        }
//      }
//      $proto .= "}\n";
//      $conf .= $proto;
//    }
//  }

	$conf .= "dns protocol \"dnsproto\" {\n";
	$conf .= "	tcp { nodelay, sack, socket buffer 1024, backlog 1000 }\n";
	$conf .= "}\n";

	if(is_array($vs_a)) {
		for ($i = 0; isset($vs_a[$i]); $i++) {
			if (($vs_a[$i]['mode'] == 'relay') || ($vs_a[$i]['relay_protocol'] == 'dns')) {
				$conf .= "relay \"{$vs_a[$i]['name']}\" {\n";
				$conf .= "  listen on {$vs_a[$i]['ipaddr']} port {$vs_a[$i]['port']}\n";

				if ($vs_a[$i]['relay_protocol'] == "dns") {
					$conf .= "  protocol \"dnsproto\"\n";
				} else {
					$conf .= "  protocol \"{$vs_a[$i]['relay_protocol']}\"\n";
				}
				$lbmode = "";
				if ( $pools[$vs_a[$i]['pool']]['mode'] == "loadbalance" ) {
					$lbmode = "mode loadbalance";
				}

				$conf .= "  forward to <{$vs_a[$i]['pool']}> port {$pools[$vs_a[$i]['pool']]['port']} {$lbmode} {$check_a[$pools[$vs_a[$i]['pool']]['monitor']]} \n";

				if (isset($vs_a[$i]['sitedown']) &&  strlen($vs_a[$i]['sitedown']) > 0)
					$conf .= "  forward to <{$vs_a[$i]['sitedown']}> port {$pools[$vs_a[$i]['pool']]['port']} {$lbmode} {$check_a[$pools[$vs_a[$i]['pool']]['monitor']]} \n";
				$conf .= "}\n";
			} else  {
				$conf .= "redirect \"{$vs_a[$i]['name']}\" {\n";
				$conf .= "  listen on {$vs_a[$i]['ipaddr']} port {$vs_a[$i]['port']}\n";
				$conf .= "  forward to <{$vs_a[$i]['pool']}> port {$pools[$vs_a[$i]['pool']]['port']} {$check_a[$pools[$vs_a[$i]['pool']]['monitor']]} \n";

				if (isset($config['system']['lb_use_sticky']))
					$conf .= "  sticky-address\n";

				# sitedown MUST use the same port as the primary pool - sucks, but it's a relayd thing
				if (isset($vs_a[$i]['sitedown']) && strlen($vs_a[$i]['sitedown']) > 0)
					$conf .= "  forward to <{$vs_a[$i]['sitedown']}> port {$pools[$vs_a[$i]['pool']]['port']} {$check_a[$pools[$vs_a[$i]['pool']]['monitor']]} \n";

				$conf .= "}\n";
			}
		}
	}
	fwrite($fd, $conf);
	fclose($fd);

	if (is_process_running('relayd')) {
		if (! empty($vs_a)) {
			// it's running and there is a config, just reload
			mwexec("/usr/local/sbin/relayctl reload");
		} else {
			/*
			 * XXX: Something breaks our control connection with relayd
			 * and makes 'relayctl stop' not work
			 * rule reloads are the current suspect
			 * mwexec('/usr/local/sbin/relayctl stop');
			 *  returns "command failed"
			 */
			mwexec('pkill relayd');
		}
	} else {
		if (! empty($vs_a)) {
			// not running and there is a config, start it
			mwexec("/usr/local/sbin/relayd -f {$g['varetc_path']}/relayd.conf");
		}
	}
}

function get_lb_redirects() {
/*
# relayctl show summary
Id   Type      Name                      Avlblty Status
1    redirect  testvs2                           active
5    table     test2:80                          active (3 hosts up)
11   host      192.168.1.2               91.55%  up
10   host      192.168.1.3               100.00% up
9    host      192.168.1.4               88.73%  up
3    table     test:80                           active (1 hosts up)
7    host      192.168.1.2               66.20%  down
6    host      192.168.1.3               97.18%  up
0    redirect  testvs                            active
3    table     test:80                           active (1 hosts up)
7    host      192.168.1.2               66.20%  down
6    host      192.168.1.3               97.18%  up
4    table     testvs-sitedown:80                active (1 hosts up)
8    host      192.168.1.4               84.51%  up
# relayctl show redirects
Id   Type      Name                      Avlblty Status
1    redirect  testvs2                           active
0    redirect  testvs                            active
# relayctl show redirects
Id   Type      Name                      Avlblty Status
1    redirect  testvs2                           active
           total: 2 sessions
           last: 2/60s 2/h 2/d sessions
           average: 1/60s 0/h 0/d sessions
0    redirect  testvs                            active
*/
	$rdr_a = array();
	exec('/usr/local/sbin/relayctl show redirects 2>&1', $rdr_a);
	$relay_a = array();
	exec('/usr/local/sbin/relayctl show relays 2>&1', $relay_a);
	$vs = array();
	for ($i = 0; isset($rdr_a[$i]); $i++) {
		$line = $rdr_a[$i];
		if (preg_match("/^[0-9]+/", $line)) {
			$regs = array();
			if($x = preg_match("/^[0-9]+\s+redirect\s+([^\s]+)\s+([^\s]+)/", $line, $regs)) {
				$vs[trim($regs[1])] = array();
				$vs[trim($regs[1])]['status'] = trim($regs[2]);
			}
		}
	}
	for ($i = 0; isset($relay_a[$i]); $i++) {
		$line = $relay_a[$i];
		if (preg_match("/^[0-9]+/", $line)) {
			$regs = array();
			if($x = preg_match("/^[0-9]+\s+relay\s+([^\s]+)\s+([^\s]+)/", $line, $regs)) {
				$vs[trim($regs[1])] = array();
				$vs[trim($regs[1])]['status'] = trim($regs[2]);
			}
		}
	}
	return $vs;
}

function get_lb_summary() {
	$relayctl = array();
	exec('/usr/local/sbin/relayctl show summary 2>&1', $relayctl);
	$relay_hosts=Array();
	foreach( (array) $relayctl as $line) {
		$t=split("\t", $line);
		switch (trim($t[1])) {
			case "table":
				$curpool=trim($t[2]);
			break;
			case "host":
				$curhost=trim($t[2]);
				$relay_hosts[$curpool][$curhost]['avail']=trim($t[3]);
				$relay_hosts[$curpool][$curhost]['state']=trim($t[4]);
			break;
		}
	}
	return $relay_hosts;
}

?>
