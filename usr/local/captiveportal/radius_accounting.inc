<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/*

    $Id$

    Copyright (c) 2006, Jonathan De Graeve <jonathan.de.graeve@imelda.be>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions
    are met:

    1. Redistributions of source code must retain the above copyright
       notice, this list of conditions and the following disclaimer.
    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.
    3. The names of the authors may not be used to endorse or promote products
       derived from this software without specific prior written permission.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
    IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
    INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
    BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
    DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
    OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
    NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
    EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

    This code cannot simply be copied and put under the GNU Public License or
    any other GPL-like (LGPL, GPL2) License.

        This code is made possible thx to samples made by Michael Bretterklieber <michael@bretterklieber.com>
        author of the PHP PECL Radius package

*/

/*
	pfSense_MODULE:	captiveportal
*/

define('GIGAWORDS_RIGHT_OPERAND', '4294967296'); // 2^32

/*
RADIUS ACCOUNTING START
-----------------------
*/

PEAR::loadExtension('bcmath');

function RADIUS_ACCOUNTING_START($ruleno, $username, $sessionid, $radiusservers, $clientip, $clientmac) {

    global $config, $cpzone;

    $retvalue = array();
    $nas_mac = mac_format(get_interface_mac("wan"));
    $clientmac = mac_format($clientmac);
    $nas_port = intval($ruleno);
    $radiusvendor = $config['captiveportal'][$cpzone]['radiusvendor'] ? $config['captiveportal'][$cpzone]['radiusvendor'] : null;

    switch($radiusvendor) {

        case 'cisco':
        $calledstationid = $clientmac;
        $callingstationid = $clientip;
        break;

        default:
            if (!function_exists('getNasIP'))
                require_once("captiveportal.inc");
	$calledstationid = getNasIP();
        $callingstationid = $clientmac;
	break;
    }

    // Create our instance
    $racct = new Auth_RADIUS_Acct_Start;

    /* Different Authentication options
     *
     * Its possible todo other authentication methods but still do radius accounting
     *
     * RADIUS_AUTH_RADIUS => authenticated via Radius
     * RADIUS_AUTH_LOCAL  => authenticated local
     * RADIUS_AUTH_REMOTE => authenticated remote
     *
     */
    $racct->authentic = RADIUS_AUTH_RADIUS;

    // Construct data package
    $racct->username = $username;
    /*
    Add support for more then one radiusserver.
    At most 10 servers may be specified.
    When multiple servers are given, they are tried in round-robin fashion until a valid response is received
    */
    foreach ($radiusservers as $radsrv) {
        // Add a new server to our instance
        $racct->addServer($radsrv['ipaddr'], $radsrv['acctport'], $radsrv['key']);
    }

    if (PEAR::isError($racct->start())) {
        $retvalue['acct_val'] = 1;
        $retvalue['error'] = $racct->getMessage();

        // If we encounter an error immediately stop this function and go back
        $racct->close();
        return $retvalue;

        /* Old code:
         * $status = $racct->start();
         * if(PEAR::isError($status)) {
         *    if ($debug)
         *        printf("Radius start: %s<br />\n", $status->getMessage());
         *        exit;
         * }
         */
    }

    /*
     * NAS_PORT_TYPE, int => RADIUS_ETHERNET (15), RADIUS_WIRELESS_OTHER (18), RADIUS_WIRELESS_IEEE_802_11 (19)
     */

    // Default attributes
    $racct->putAttribute(RADIUS_NAS_PORT_TYPE, RADIUS_ETHERNET);
    $racct->putAttribute(RADIUS_NAS_PORT, $nas_port, 'integer');
    $racct->putAttribute(RADIUS_ACCT_SESSION_ID, $sessionid);

    // Extra data to identify the client and nas
    $racct->putAttribute(RADIUS_FRAMED_IP_ADDRESS, $clientip, "addr");
    $racct->putAttribute(RADIUS_CALLED_STATION_ID, $calledstationid);
    $racct->putAttribute(RADIUS_CALLING_STATION_ID, $callingstationid);

    // Send request
    $result = $racct->send();

    // Evaluation of the response
    // 5 -> Accounting-Response
    // See RFC2866 for this.
    if (PEAR::isError($result)) {
        $retvalue['acct_val'] = 1;
        $retvalue['error'] = $result->getMessage();

    } else if ($result === true) {
        $retvalue['acct_val'] = 5 ;

    } else {
        $retvalue['acct_val'] = 1 ;

    }

    // close OO RADIUS_ACCOUNTING
    $racct->close();
    unset($racct);

    return $retvalue ;

}

/*
RADIUS ACCOUNTING STOP/UPDATE
-----------------------------
*/

function RADIUS_ACCOUNTING_STOP($ruleno,$username,$sessionid,$start_time,$radiusservers,$clientip,$clientmac, $term_cause = 1, $interimupdate=false,$stop_time = null) {

    global $config, $cpzone;

    $retvalue = array();
    $nas_mac = mac_format(get_interface_mac("wan"));
    $clientmac = mac_format($clientmac);
    $nas_port = intval($ruleno);
    $radiusvendor = $config['captiveportal'][$cpzone]['radiusvendor'] ? $config['captiveportal'][$cpzone]['radiusvendor'] : null;
    $stop_time = (empty($stop_time)) ? time() : $stop_time;
    $session_time = $stop_time - $start_time;
    $volume = getVolume($clientip, $clientmac);
    $volume['input_bytes_radius'] = remainder($volume['input_bytes']);
    $volume['input_gigawords'] = gigawords($volume['input_bytes']);
    $volume['output_bytes_radius'] = remainder($volume['output_bytes']);
    $volume['output_gigawords'] = gigawords($volume['output_bytes']);

    switch($radiusvendor) {

        case 'cisco':
        $calledstationid = $clientmac;
        $callingstationid = $clientip;
        break;

        default:
	$calledstationid = getNasIP();
        $callingstationid = $clientmac;
	break;
    }

    // Create our instance, see if we should use Accounting Interim Updates or Accounting STOP messages
    if ($interimupdate)
        $racct = new Auth_RADIUS_Acct_Update;
    else
        $racct = new Auth_RADIUS_Acct_Stop;

    /*
    Add support for more then one radiusserver.
    At most 10 servers may be specified.
    When multiple servers are given, they are tried in round-robin fashion until a valid response is received
    */
    foreach ($radiusservers as $radsrv) {
        // Add a new server to our instance
        $racct->addServer($radsrv['ipaddr'], $radsrv['acctport'], $radsrv['key']);
    }

    // See RADIUS_ACCOUNTING_START for info
    $racct->authentic = RADIUS_AUTH_RADIUS;

    // Construct data package
    $racct->username = $username;
    // Set session_time
    $racct->session_time = $session_time;

    if (PEAR::isError($racct->start())) {
        $retvalue['acct_val'] = 1;
        $retvalue['error'] = $racct->getMessage();

        // If we encounter an error immediately stop this function and go back
        $racct->close();
        return $retvalue;
    }

    // The RADIUS PECL Package doesn't have this vars so we create them ourself
    define("RADIUS_ACCT_INPUT_GIGAWORDS", "52");
    define("RADIUS_ACCT_OUTPUT_GIGAWORDS", "53");

    // Default attributes
    $racct->putAttribute(RADIUS_NAS_PORT_TYPE, RADIUS_ETHERNET);
    $racct->putAttribute(RADIUS_NAS_PORT, $nas_port, 'integer');
    $racct->putAttribute(RADIUS_ACCT_SESSION_ID, $sessionid);

    // Extra data to identify the client and nas
    $racct->putAttribute(RADIUS_FRAMED_IP_ADDRESS, $clientip, "addr");
    $racct->putAttribute(RADIUS_CALLED_STATION_ID, $calledstationid);
    $racct->putAttribute(RADIUS_CALLING_STATION_ID, $callingstationid);

    // Volume stuff: Ingress
    $racct->putAttribute(RADIUS_ACCT_INPUT_PACKETS, $volume['input_pkts'], "integer");
    $racct->putAttribute(RADIUS_ACCT_INPUT_OCTETS, $volume['input_bytes_radius'], "integer");
    $racct->putAttribute(RADIUS_ACCT_INPUT_GIGAWORDS, $volume['input_gigawords'], "integer");
    // Volume stuff: Outgress
    $racct->putAttribute(RADIUS_ACCT_OUTPUT_PACKETS, $volume['output_pkts'], "integer");
    $racct->putAttribute(RADIUS_ACCT_OUTPUT_OCTETS, $volume['output_bytes_radius'], "integer");
    $racct->putAttribute(RADIUS_ACCT_OUTPUT_GIGAWORDS, $volume['output_gigawords'], "integer");
	$racct->putAttribute(RADIUS_ACCT_SESSION_TIME, $session_time, "integer");

    if (!$interimupdate)
        $racct->putAttribute(RADIUS_ACCT_TERMINATE_CAUSE, $term_cause);

    // Send request
    $result = $racct->send();

    // Evaluation of the response
    // 5 -> Accounting-Response
    // See RFC2866 for this.
    if (PEAR::isError($result)) {
        $retvalue['acct_val'] = 1;
        $retvalue['error'] = $result->getMessage();

    } else if ($result === true) {
        $retvalue['acct_val'] = 5 ;

    } else {
        $retvalue['acct_val'] = 1 ;

    }

    // close OO RADIUS_ACCOUNTING
    $racct->close();

    return $retvalue;

}


/**
 * Radius Volume Helpers
 *
 */

function gigawords($bytes) {


    /*
     * RFC2866 Specifies a 32bit unsigned integer, which is a max of 4294967295
     * Currently there is a fault in the PECL radius_put_int function which can handle only 32bit signed integer.
     */

    // We use BCMath functions since normal integers don't work with so large numbers
    $gigawords = bcdiv( bcsub( $bytes, remainder($bytes) ) , GIGAWORDS_RIGHT_OPERAND) ;

    // We need to manually set this to a zero instead of NULL for put_int() safety
    if (is_null($gigawords)) {
        $gigawords = 0;
    }

    return $gigawords;

}

function remainder($bytes) {

    // Calculate the bytes we are going to send to the radius
    $bytes = bcmod($bytes, GIGAWORDS_RIGHT_OPERAND);

    if (is_null($bytes)) {
        $bytes = 0;
    }


    return $bytes;

}

?>
