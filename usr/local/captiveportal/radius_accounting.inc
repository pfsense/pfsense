<?php
/*
	radius_accounting.inc
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2004 Dinesh Nair <dinesh@alphaque.com>
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


function RADIUS_ACCOUNTING_START($username,$sessionid,$radiusip,$radiusport,$radiuskey) {
	$sharedsecret=$radiuskey ;
	# $debug = 1 ;

	exec("/bin/hostname", $nasHostname) ;
	if(!$nasHostname[0])
		$nasHostname[0] = "m0n0wall" ;

	$fd = @fsockopen("udp://$radiusip",$radiusport,$errno,$errstr,3) ;
	if(!$fd) 
		return 1 ; /* error return */
	
	/* set 5 second timeout on socket i/o */
	stream_set_timeout($fd, 5) ;

	if ($debug)
	    echo "<br>radius-port: $radiusport<br>radius-host: $radiusip<br>username: $username<hr>\n";

	$thisidentifier=rand()%256;

	$length=4+				// header
		16+				// auth code
		6+				// service type
		2+strlen($username)+		// username
		2+strlen($nasHostname[0])+			// nasIdentifier
		6+				// nasPort
		6+				// nasPortType
		6+				// Acct Status Type
		6+				// Acct RADIUS Authenticated
		2+strlen($sessionid);	// Acct SessionID

	//          v   v   v     v   v   v     v     v     v     1   v
	// Line #   1   2   3     4   5   6     7     8     9     0   E
	$data=pack("CCCCNNNNCCCCCCCCa*CCa*CCCCCCCCCCCCCCCCCCCCCCCCCCa*",
	    4,$thisidentifier,$length/256,$length%256,		// header
	    0,0,0,0,						// authcode
	    6,6,0,0,0,1,					// service type
	    1,2+strlen($username),$username,			// username
	    32,2+strlen($nasHostname[0]),$nasHostname[0],	// nasIdentifier
	    5,6,0,0,0,0,						// nasPort
	    61,6,0,0,0,15,						// nasPortType = Ethernet
		40,6,0,0,0,1,						// Acct Status Type = Start
		45,6,0,0,0,1,						// Acct RADIUS Authenticated
		44,2+strlen($sessionid),$sessionid	// Acct Session ID
	    );

	/* Generate Accounting Request Authenticator */
	$RA = md5($data.$radiuskey) ;

	//          v   v v     v   v   v     v     v     v     1   v
	// Line #   1   2 3     4   5   6     7     8     9     0   E
	$data=pack("CCCCH*CCCCCCCCa*CCa*CCCCCCCCCCCCCCCCCCCCCCCCCCa*",
	    4,$thisidentifier,$length/256,$length%256,		// header
	    $RA,						// authcode
	    6,6,0,0,0,1,					// service type
	    1,2+strlen($username),$username,			// username
	    32,2+strlen($nasHostname[0]),$nasHostname[0],	// nasIdentifier
	    5,6,0,0,0,0,						// nasPort
	    61,6,0,0,0,15,						// nasPortType = Ethernet
		40,6,0,0,0,1,						// Acct Status Type = Start
		45,6,0,0,0,1,						// Acct RADIUS Authenticated
		44,2+strlen($sessionid),$sessionid	// Acct Session ID
	    );

	if($debug) {
		echo "username is $username with len " . strlen($username) ."\n" ;
		echo "nasHostname is {$nasHostname[0]} with len " . strlen($nasHostname[0]) ."\n" ;
	}	

	$ret = fwrite($fd,$data) ;
	if( !$ret || ($ret != $length) ) 
		return 1; /* error return */

	if ($debug)
	    echo "<br>writing $length bytes<hr>\n";

	$readdata = fgets($fd,2) ; /* read 1 byte */
	$status = socket_get_status($fd) ;
	fclose($fd) ;

	if($status['timed_out'])
		$retvalue = 1 ;
	else
		$retvalue = ord($readdata) ;

	return $retvalue ;
	// 5 -> Accounting-Response
	// See RFC2866 for this.
}

function RADIUS_ACCOUNTING_STOP($ruleno,$username,$sessionid,$start_time,$radiusip,$radiusport,$radiuskey) {
	$sharedsecret=$radiuskey ;
	# $debug = 1 ;

	exec("/bin/hostname", $nasHostname) ;
	if(!$nasHostname[0])
		$nasHostname[0] = "quewall" ;

	$input_pkts = $input_bytes = $output_pkts = $output_bytes = 0 ;

	exec("/sbin/ipfw show {$ruleno}", $ipfw) ;	
	preg_match("/(\d+)\s+(\d+)\s+(\d+)\s+skipto/", $ipfw[0], $matches) ;
	$output_pkts = $matches[2] ;
	$output_bytes = $matches[3] ;

	unset($matches) ;
	preg_match("/(\d+)\s+(\d+)\s+(\d+)\s+skipto/", $ipfw[1], $matches) ;
	$input_pkts = $matches[2] ;
	$input_bytes = $matches[3] ;

	$fd = @fsockopen("udp://$radiusip",$radiusport,$errno,$errstr,3) ;
	if(!$fd) 
		return 1 ; /* error return */
	
	/* set 5 second timeout on socket i/o */
	stream_set_timeout($fd, 5) ;

	if ($debug)
	    echo "<br>radius-port: $radiusport<br>radius-host: $radiusip<br>username: $username<hr>\n";

	$thisidentifier=rand()%256;

	$length=4+				// header
		16+				// auth code
		6+				// service type
		2+strlen($username)+		// username
		2+strlen($nasHostname[0])+			// nasIdentifier
		6+				// nasPort
		6+				// nasPortType
		6+				// Acct Status Type
		6+				// Acct RADIUS Authenticated
		2+strlen($sessionid)+	// Acct SessionID
		6+				// Acct terminate
		6+				// Session time
		6+				// input bytes
		6+				// input packets
		6+				// output bytes
		6;				// output packets

	//          v   v   v     v   v   v     v     v     v     1   1  1  1  1  1  1  v
	// Line #   1   2   3     4   5   6     7     8     9     0   1  2  3  4  5  6  E
	$data=pack("CCCCNNNNCCCCCCCCa*CCa*CCCCCCCCCCCCCCCCCCCCCCCCCCa*CCNCCNCCNCCNCCNCCN",
	    4,$thisidentifier,$length/256,$length%256,		// header
	    0,0,0,0,						// authcode
	    6,6,0,0,0,1,					// service type
	    1,2+strlen($username),$username,			// username
	    32,2+strlen($nasHostname[0]),$nasHostname[0],	// nasIdentifier
	    5,6,0,0,0,0,						// nasPort
	    61,6,0,0,0,15,						// nasPortType = Ethernet
		40,6,0,0,0,2,						// Acct Status Type = Stop
		45,6,0,0,0,1,						// Acct RADIUS Authenticated
		44,2+strlen($sessionid),$sessionid,	// Acct Session ID
		49,6,1,		// Acct Terminate = User Request
		46,6,time() - $start_time,			// Session Time
		42,6,$input_bytes,	// Input Octets
		47,6,$input_pkts,	// Input Packets
		43,6,$output_bytes, // Output Octets
		48,6,$output_pkts	// Output Packets
	    );

	/* Generate Accounting Request Authenticator */
	$RA = md5($data.$radiuskey) ;

	//          v   v v     v   v   v     v     v     v     1   1  1  1  1  1  1  v
	// Line #   1   2 3     4   5   6     7     8     9     0   1  2  3  4  5  6  E
	$data=pack("CCCCH*CCCCCCCCa*CCa*CCCCCCCCCCCCCCCCCCCCCCCCCCa*CCNCCNCCNCCNCCNCCN",
	    4,$thisidentifier,$length/256,$length%256,		// header
	    $RA,						// authcode
	    6,6,0,0,0,1,					// service type
	    1,2+strlen($username),$username,			// username
	    32,2+strlen($nasHostname[0]),$nasHostname[0],	// nasIdentifier
	    5,6,0,0,0,0,						// nasPort
	    61,6,0,0,0,15,						// nasPortType = Ethernet
		40,6,0,0,0,2,						// Acct Status Type = Stop
		45,6,0,0,0,1,						// Acct RADIUS Authenticated
		44,2+strlen($sessionid),$sessionid,	// Acct Session ID
		49,6,1,		// Acct Terminate = User Request
		46,6,time() - $start_time,			// Session Time
		42,6,$input_bytes,	// Input Octets
		47,6,$input_pkts,	// Input Packets
		43,6,$output_bytes, // Output Octets
		48,6,$output_pkts	// Output Packets
	    );

	if($debug) {
		echo "username is $username with len " . strlen($username) ."\n" ;
		echo "nasHostname is {$nasHostname[0]} with len " . strlen($nasHostname[0]) ."\n" ;
	}	

	$ret = fwrite($fd,$data) ;
	if( !$ret || ($ret != $length) ) 
		return 1; /* error return */

	if ($debug)
	    echo "<br>writing $length bytes<hr>\n";

	$readdata = fgets($fd,2) ; /* read 1 byte */
	$status = socket_get_status($fd) ;
	fclose($fd) ;

	if($status['timed_out'])
		$retvalue = 1 ;
	else
		$retvalue = ord($readdata) ;

	return $retvalue ;
	// 5 -> Accounting-Response
	// See RFC2866 for this.
}
?>
