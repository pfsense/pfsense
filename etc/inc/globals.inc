<?php
/*
    globals.inc
    part of m0n0wall (http://m0n0.ch/wall)
    
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

$g = array(
    "varrun_path" => "/var/run",
    "varetc_path" => "/var/etc",
    "vardb_path" => "/var/db",
    "varlog_path" => "/var/log",
    "etc_path" => "/etc",
    "tmp_path" => "/tmp",
    "conf_path" => "/conf",
    "ftmp_path" => "/ftmp",
    "conf_default_path" => "/conf.default",
    "cf_path" => "/cf",
    "cf_conf_path" => "/cf/conf",
    "www_path" => "/usr/local/www",
    "captiveportal_path" => "/usr/local/captiveportal",
    "xml_rootobj" => "m0n0wall",
    "pppoe_interface" => "ng0",
    "n_pptp_units" => 16,
    "pptp_subnet" => 28,
    "debug" => false,
    "latest_config" => "1.4",
    "nopccard_platforms" => array("wrap", "net48xx")
);

?>
