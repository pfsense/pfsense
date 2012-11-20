<?php
/* $Id$ */
/*
	diag_ipsec_xml.php
	Copyright (C) 2007 pfSense Project
	Copyright (C) 2010 Seth Mos
	All rights reserved.

	Parts of this code was originally based on vpn_ipsec_sad.php
	Copyright (C) 2003-2004 Manuel Kasper

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

##|+PRIV
##|*IDENT=page-ipsecxml
##|*NAME=Diag IPsec XML page
##|*DESCR=Allow access to the 'Diag IPsec XML' page.
##|*MATCH=diag_ipsec_xml.php
##|-PRIV

global $g;

require("guiconfig.inc");
require("ipsec.inc");

if (!is_array($config['ipsec']['phase2']))
    $config['ipsec']['phase2'] = array();

$ipsec_status = array();

$a_phase2 = &$config['ipsec']['phase2'];

$spd = ipsec_dump_spd();
$sad = ipsec_dump_sad();

if(is_array($a_phase2)) {
	foreach ($a_phase2 as $ph2ent) {
		ipsec_lookup_phase1($ph2ent,$ph1ent);
		$tunnel = array();
		if (!isset($ph2ent['disabled']) && !isset($ph1ent['disabled'])) {
			if(ipsec_phase2_status($spd,$sad,$ph1ent,$ph2ent))
				$tunnel['state'] = "up";
			elseif(!isset($config['ipsec']['enable']))
				$tunnel['state'] = "disabled";
			else
				$tunnel['state'] = "down";

			$tunnel['src'] = ipsec_get_phase1_src($ph1ent);
			$tunnel['endpoint'] = $ph1ent['remote-gateway'];
			$tunnel['local'] = ipsec_idinfo_to_text($ph2ent['localid']);
			$tunnel['remote'] = ipsec_idinfo_to_text($ph2ent['remoteid']);
			$tunnel['name'] = "{$ph2ent['descr']}";
			$ipsec_status['tunnel'][] = $tunnel;
		}
	}
}

$listtags = array("tunnel");
$xml = dump_xml_config($ipsec_status, "ipsec");

echo $xml;
?>
