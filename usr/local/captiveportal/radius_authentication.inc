<?php
    //
    // $Id$
    //
    // radius authentication v1.0 by Edwin Groothuis (edwin@mavetju.org)
    //
    // If you didn't get this file via http://www.mavetju.org, please
    // check for the availability of newer versions.
    //
    // See LICENSE for distribution issues. If this file isn't in
    // the distribution, please inform me about it.
    //
    // If you want to use this script, fill in the configuration in
    // radius_authentication.conf and call the function
    // RADIUS_AUTHENTICATION() with the username and password
    // provided by the user. If it returns a 2, the authentication
    // was successfull!

    // If you want to use this, make sure that you have raw sockets
    // enabled during compile-time: "./configure --enable-sockets".

	// This version has been modified by Dinesh Nair <dinesh@alphaque.com>
	// for use in the m0n0wall distribution http://m0n0.ch/wall/
	//
	// Changes include moving from raw sockets to fsockopen
	// and the removal of dependency on external conf file
	// An existing bug which resulted in a malformed RADIUS packet
	// was also fixed and patches submitted to Edwin. This bug would
	// have caused authentication to fail on every access.

function RADIUS_AUTHENTICATION($username,$password,$port_type,$radiusip,$radiusport,$radiuskey) {
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

	$RA=pack("CCCCCCCCCCCCCCCC",				// auth code
	    1+rand()%255, 1+rand()%255, 1+rand()%255, 1+rand()%255,
	    1+rand()%255, 1+rand()%255, 1+rand()%255, 1+rand()%255,
	    1+rand()%255, 1+rand()%255, 1+rand()%255, 1+rand()%255,
	    1+rand()%255, 1+rand()%255, 1+rand()%255, 1+rand()%255);

	$encryptedpassword=Encrypt($password,$sharedsecret,$RA);

	$length=4+				// header
		16+				// auth code
		6+				// service type
		2+strlen($username)+		// username
		2+strlen($encryptedpassword)+	// userpassword
		2+strlen($nasHostname[0])+			// nasIdentifier
		6+				// nasPort
		6;				// nasPortType

	$thisidentifier=rand()%256;
	//          v   v v     v   v   v   v     v     v
	// Line #   1   2 3     4   5   6   7     8     E
	$data=pack("CCCCa*CCCCCCCCa*CCa*CCa*CCCCCCCCCCCC",
	    1,$thisidentifier,$length/256,$length%256,		// header
	    $RA,						// authcode
	    6,6,0,0,0,1,					// service type
	    1,2+strlen($username),$username,			// username
	    2,2+strlen($encryptedpassword),$encryptedpassword,	// userpassword
	    32,2+strlen($nasHostname[0]),$nasHostname[0],	// nasIdentifier
	    5,6,0,0,0,0,						// nasPort
	    61,6,0,0,0,$port_type				// nasPortType
	    );

	if($debug) {
		echo "username is $username with len " . strlen($username) ."\n" ;
		echo "encryptedpassword is $encryptedpassword with len " . strlen($encryptedpassword) ."\n" ;
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
	// 2 -> Access-Accept
	// 3 -> Access-Reject
	// See RFC2865 for this.
}

function Encrypt($password,$key,$RA) {
	global $debug;

	$keyRA=$key.$RA;

	if ($debug)
	    echo "<br>key: $key<br>password: $password<hr>\n";

	$md5checksum=md5($keyRA);
	$output="";

	for ($i=0;$i<=15;$i++) {
	    if (2*$i>strlen($md5checksum)) $m=0; else $m=hexdec(substr($md5checksum,2*$i,2));
	    if ($i>strlen($keyRA)) $k=0; else $k=ord(substr($keyRA,$i,1));
	    if ($i>strlen($password)) $p=0; else $p=ord(substr($password,$i,1));
	    $c=$m^$p;
	    $output.=chr($c);
	}
	return $output;
}
?>
