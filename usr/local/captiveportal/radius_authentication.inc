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

	// This version of radius_authentication.inc has been modified by
	// Rob Parker <rob.parker@keycom.co.uk>. Changes made include:
	//	* move to fread() from fgets() to ensure binary safety
	//  * ability to read back specific attributes from a
	//		RADIUS Access-Accept packet
	//  * these attributes (in this version, Nomadix-Bw-Up and -Down,
	//	   which are Nomadix vendor specific attributes to be passed back
	//     to index.php of m0n0wall to create dummynet rules for per-user
	//     radius-based bandwidth restriction.
	//  * IMPORTANT NOTE: this function no longer returns a simple integer
	//     of '2' for Access-Accept, and '3' for Access-Deny. It will return
	//     x/y/z, where x = 2 or 3 (Accept or Deny), y = up bandwidth, if
	//	   enabled in web gui, and z = down bandwidth. These will be empty if
	//     per user bw is disabled in webgui.
	//	* these changes are (c) 2004 Keycom PLC.

function RADIUS_AUTHENTICATION($username,$password,$radiusip,$radiusport,$radiuskey) {
	global $config;

	//radius database, hack this if we need to
	
	$radius_db[1]=="Nomadix-Bw-Up";
	$radius_db[2]=="Nomadix-Bw-Down";
	$radius_db[5]=="Nomadix-Expiration";
	
	$sharedsecret=$radiuskey ;
	#$debug = 1 ;

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
	    61,6,0,0,0,15						// nasPortType = Ethernet
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

	
	//RADIUS attributes returned in Access-Accept packet.

	#turn off magic quotes so we're binary-safe on fread.
	set_magic_quotes_runtime(0);
	$readdata = fread($fd,1024);
	$pack_upack = unpack("Ctype/Cuid/nlength/A16resp/A*payload",$readdata);
	if($pack_upack[type]==2) {
		//only for 'Access-Accept' packets, otherwise throw back the number so error page is shown
		$payload_upack = unpack("Cnum/Clen/C*value",$pack_upack[payload]);
		$used_upack = $payload_upack;

		while(count($used_upack)>=1) {
			//the payload contains two initial packets we need to record (number, and payload)
			$attribute_number++;
			$packet_type=array_shift($used_upack);		//push the type off
			$attributes[$attribute_number][]=$packet_type;
			$packet_length=array_shift($used_upack);	//push the length off
			$attributes[$attribute_number][]=$packet_length;
			//iterate until the end of this attribute
			for($n=1;$n<=$packet_length-2;$n+=1) {
				$attributes[$attribute_number][]=array_shift($used_upack);
			}
		}

		//at this stage, $attribute contains a list of ALL attributes that were sent (well, the first 1kbyte of them anyway,
		//change fread above to alter the quantity of data read from the socket.
		//we're only interested in two specific nomadix (3309) attributes (1 and 2, Bw-Up and Bw-Down)

		for($n=1;$n<=count($attributes);$n+=1) {
			if($attributes[$n][0]=="26") {											//VSA attribs
				if((($attributes[$n][4]*256)+$attributes[$n][5])=="3309") {			//just nomadix
						switch($attributes[$n][6]) {								//nomadix packet type
							//we do this *256 because otherwise we'd need to unpack the packet
							//again with a different packet format. which is a waste of time for now.
							case "1":
								$bw_up = 0;
								$bw_up += $attributes[$n][10]*256;
								$bw_up += $attributes[$n][11];
								if ($debug) {echo ">>VSA: Nomadix-Bw-Up=" . $bw_up . "kbit\n";}
								break;
							case "2":
								$bw_down = 0;
								$bw_down += $attributes[$n][10]*256;
								$bw_down += $attributes[$n][11];
								if ($debug) {echo ">>VSA: Nomadix-Bw-Down=" . $bw_down . "kbit\n";}
								break;
							default:
								if ($debug) {echo ">>VSA: Unknown Nomadix Packet (" . $attributes[$n][6] . ")!\n";}
						}
				}
			}
		}
		//end RADIUS attribute return code.
		
		$status = socket_get_status($fd) ;
		fclose($fd) ;

		if($status['timed_out'])
			$retvalue = 1 ;
		else
			$retvalue = $pack_upack[type];

		if($debug) {
			switch($retvalue) {
				case 1:
					echo "Socket Failure!\n";
					break;
				case 2:
					echo "Access-Accept!\n";
					break;
				case 3:
					echo "Access-Reject!\n";
					break;
				default:
					echo "Unknown Reply!\n";
			}
		}
		
		//what happens if there's no Nomadix attributes set, but the user has this turned on?
		//we give them a default of 64kbit. this should be an option in the webgui too.
		if(!isset($bw_up)) {
			//go for default bw up
			$bw_up==$config['captiveportal']['bwdefaultup'];
			if(!isset($bw_up)) {
				$bw_up=64;
			}
		}
		if(!isset($bw_down)) {
			//go for default bw down
			$bw_down==$config['captiveportal']['bwdefaultdn'];
			if(!isset($bw_down)) {
				$bw_down=64;
			}
		}

		//whilst we're debugging, we're also going to syslog this
		syslog(LOG_INFO,"Authenticated user $username. Setting bandwidth to $bwdown/$bwup KBit/s");

		return $retvalue . "/" . $bw_up . "/" . $bw_down;
	} else {
		//we're returning 5kbit/s each way here, but really it doesn't matter
		//if it's a 3, it's Access-Reject anyway, so the user will actually get
		//nothing at all. :)
		syslog(LOG_INFO,"Authentication failed for $username");
		return "3/5/5";
	}
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