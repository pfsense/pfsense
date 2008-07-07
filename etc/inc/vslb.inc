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

/* include all configuration functions */
require_once("functions.inc");
require_once("pkg-utils.inc");
require_once("notices.inc");

function relayd_configure() {
  global $config, $g;

  $vs_a = &$config['load_balancer']['virtual_server'];
  $pool_a = &$config['load_balancer']['lbpool'];

  $fd = fopen("{$g['varetc_path']}/relayd.conf", "w");

  /* reindex pools by name as we loop through the pools array */
  $pools = array();
  /* Virtual server pools */
  if(is_array($pool_a)) {
    for ($i = 0; isset($pool_a[$i]); $i++) {
      /* Don't deal with gateway pools */
      if ($pool_a[$i]['type'] == "gateway")
        continue;
      
      if(is_array($pool_a[$i]['servers'])) {
        $srvtxt = implode(", ", $pool_a[$i]['servers']);
        $conf .= "table <{$pool_a[$i]['name']}> { $srvtxt }\n";
        /* Index by name for easier fetching when we loop through the virtual servers */
        $pools[$pool_a[$i]['name']] = $pool_a[$i];
      }
    }
  }

  if(is_array($vs_a)) {
    for ($i = 0; isset($vs_a[$i]); $i++) {
      $conf .= "redirect \"{$vs_a[$i]['name']}\" {\n";
      $conf .= "  listen on {$vs_a[$i]['ipaddr']} port {$vs_a[$i]['port']}\n";
      $conf .= "  forward to <{$vs_a[$i]['pool']}> port {$pools[$vs_a[$i]['pool']]['port']} check tcp\n";
      /* XXX - this needs to use the backup pool aka sitedown - but that isn't converted yet */
      if (isset($vs_a[$i]['sitedown']) &&  $vs_a[$i]['sitedown'] != "")
        $conf .= "  forward to <{$vs_a[$i]['sitedown']}> port {$pools[$vs_a[$i]['pool']]['port']} check tcp\n";
      $conf .= "}\n";
    }
  }
  fwrite($fd, $conf);
  fclose($fd);

  if (is_process_running('relayd: parent')) {
    /*
     * XXX: Something breaks our control connection with relayd and makes relayctl stop working
     * rule reloads are the current suspect
     * mwexec('/usr/local/bin/relayctl stop');
     */
     mwexec('pkill relayd');
  }
  mwexec("/usr/local/sbin/relayd -f {$g['varetc_path']}/relayd.conf");

}

?>
