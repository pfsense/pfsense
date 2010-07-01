<?php
/* $Id$ */
/*
    globals.inc
    part of pfSense (www.pfsense.com)
    Copyright (C) 2004-2006 Scott Ullrich

    Originally Part of m0n0wall
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
	"base_packages" => "AutoConfigBackup, siproxd", 
	"factory_shipped_username" => "admin",	
	"factory_shipped_password" => "pfsense",	
	"product_name" => "pfSense",
	"product_copyright" => "BSD Perimeter LLC",
	"product_copyright_url" => "http://www.bsdperimeter.com",
	"product_copyright_years" => "2004 - 2009",
	"product_website" => "www.pfsense.org",
	"product_website_footer" => "http://www.pfsense.org/?gui12",
	"product_email" => "coreteam@pfsense.org",
	"hideplatform" => false,
	"disablethemeselection" => false,
	"upload_path" => "/root",
	"dhcpd_chroot_path" => "/var/dhcpd",
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
	"xml_rootobj" => "pfsense",
	"pppoe_interface" => "ng0",
	"n_pptp_units" => 16,  /* this value can be overriden in pptp->n_pptp_units */
	"pptp_subnet" => 28,   /* this value can be overriden in pptp->pptp_subnet  */
	"n_pppoe_units" => 16, /* this value can be overriden in pppoe->n_pppoe_units */
	"pppoe_subnet" => 28,  /* this value can be overriden in pppoe->pppoe_subnet  */
	"debug" => false,
	"latest_config" => "3.0",
	"nopkg_platforms" => array("cdrom"),
	"nopccard_platforms" => array("wrap", "net48xx"),
	"xmlrpcbaseurl" => "www.pfsense.com",
	"captiveportal_path" => "/usr/local/captiveportal",
	"captiveportal_element_path" => "/var/db/cpelements",
	"captiveportal_element_sizelimit" => 262144,
	"xmlrpcpath" => "/pfSense/xmlrpc.php",
	"embeddedbootupslice" => "/dev/ad0a",
	"firmware_update_text" => "pfSense-*.tgz",
	"wireless_regex" => "/^(ndis|wi|ath|an|ral|ural|wai|iwi|awi|wlan|rum)/",
	"vlan_native_supp" => array("vge", "bfe", "dc", "fxp", "gem", "hme", "rl", "sis", "ste", "tl", "tx", "xl"),
	"vlan_long_frame" =>  array("vge", "bfe", "bge", "dc", "em", "fxp", "gem", "hme", "ixgb", "le", "msk", "nge", "re", "rl", "sis", "sk", "ste", "ti", "tl", "tx", "txp", "vr", "xl", "bce", "ixgbe" )
);

/* IP TOS flags */
$iptos = array("lowdelay", "throughput", "reliability");

/* TCP flags */
$tcpflags = array("syn", "ack", "fin", "rst", "psh", "urg");

if(file_exists("/etc/platform")) {
	$g['$platform'] = trim(file_get_contents("/etc/platform"));
	if($g['platform'] == "nanobsd") {
		$g['update_url']="http://updates.pfSense.com/nanobsd/_updaters";
		$g['update_manifest']="http://updates.pfSense.com/nanobsd/manifest";
	} else {
		$g['update_url']="http://updates.pfSense.com/_updaters";
		$g['update_manifest']="http://updates.pfSense.com/manifest";
	}
}


?>