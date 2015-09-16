<?php
/* $Id$ */
/*
	diag_ipsec_xml.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved. 
 *  Copyright (c)  2010 Seth Mos
 *
 *  Redistribution and use in source and binary forms, with or without modification, 
 *  are permitted provided that the following conditions are met: 
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution. 
 *
 *  3. All advertising materials mentioning features or use of this software 
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/). 
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
  *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
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

if (!is_array($config['ipsec']['phase2'])) {
	$config['ipsec']['phase2'] = array();
}

$ipsec_status = array();

$a_phase2 = &$config['ipsec']['phase2'];

$status = ipsec_smp_dump_status();

if (is_array($status['query']) && $status['query']['ikesalist'] && $status['query']['ikesalist']['ikesa']) {
	foreach ($a_phase2 as $ph2ent) {
		ipsec_lookup_phase1($ph2ent, $ph1ent);
		$tunnel = array();
		if (!isset($ph2ent['disabled']) && !isset($ph1ent['disabled'])) {
			if (ipsec_phase1_status($status['query']['ikesalist']['ikesa'], $ph1ent['ikeid'])) {
				$tunnel['state'] = "up";
			} elseif (!isset($config['ipsec']['enable'])) {
				$tunnel['state'] = "disabled";
			} else {
				$tunnel['state'] = "down";
			}

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
