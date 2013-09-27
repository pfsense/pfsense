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

/*
RADIUS AUTHENTICATION
---------------------
*/

require_once("CHAP.inc");

function RADIUS_AUTHENTICATION($username,$password,$radiusservers,$clientip,$clientmac,$ruleno) {

    global $config, $cpzone;

    $retvalue = array();
    $clientmac = mac_format($clientmac);
    $nas_port = $ruleno;
    $radiusvendor = $config['captiveportal'][$cpzone]['radiusvendor'] ? $config['captiveportal'][$cpzone]['radiusvendor'] : null;
    $radius_protocol = $config['captiveportal'][$cpzone]['radius_protocol'];
    // Do we even need to set it to NULL?
    $retvalue['error'] = $retvalue['reply_message'] = $retvalue['url_redirection'] = $retvalue['session_timeout'] = null;
    $retvalue['idle_timeout'] = $retvalue['session_terminate_time'] = $retvalue['interim_interval'] = null;

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
    $classname = 'Auth_RADIUS_' . $radius_protocol;
    $rauth = new $classname($username, $password);

    /*
     * Add support for more then one radiusserver. 
     * At most 10 servers may be specified. 
     * When multiple servers are given, they are tried in round-robin fashion until a valid response is received 
     */
    foreach ($radiusservers as $radsrv) {
        // Add a new server to our instance
        $rauth->addServer($radsrv['ipaddr'], $radsrv['port'], $radsrv['key']);

    }

    // Construct data package
    $rauth->username = $username;
    switch ($radius_protocol) {
        case 'CHAP_MD5':
        case 'MSCHAPv1':
            $classname = $radius_protocol == 'MSCHAPv1' ? 'Crypt_CHAP_MSv1' : 'Crypt_CHAP_MD5';
            $crpt = new $classname;
            $crpt->username = $username;
            $crpt->password = $password;
            $rauth->challenge = $crpt->challenge;
            $rauth->chapid = $crpt->chapid;
            $rauth->response = $crpt->challengeResponse();
            $rauth->flags = 1;
            // If you must use deprecated and weak LAN-Manager-Responses use this:
            //$rauth->lmResponse = $crpt->lmChallengeResponse();
            //$rauth->flags = 0;
            break;

        case 'MSCHAPv2':
            // Construct data package
            $crpt = new Crypt_CHAP_MSv2;
            $crpt->username = $username;
            $crpt->password = $password;
            $rauth->challenge = $crpt->authChallenge;
            $rauth->peerChallenge = $crpt->peerChallenge;
            $rauth->chapid = $crpt->chapid;
            $rauth->response = $crpt->challengeResponse();
            break;

        default:
            $rauth->password = $password;
            break;
    }

    if (PEAR::isError($rauth->start())) {
        $retvalue['auth_val'] = 1;
        $retvalue['error'] = $rauth->getError(); 

        // If we encounter an error immediately stop this function and go back
        $rauth->close();
        return $retvalue;
    }

    // Default attributes
    $rauth->putAttribute(RADIUS_SERVICE_TYPE, RADIUS_LOGIN);
    $rauth->putAttribute(RADIUS_NAS_PORT_TYPE, RADIUS_ETHERNET);
    $rauth->putAttribute(RADIUS_NAS_PORT, $nas_port, 'integer');

    // Extra data to identify the client and nas
    $rauth->putAttribute(RADIUS_FRAMED_IP_ADDRESS, $clientip, addr);
    $rauth->putAttribute(RADIUS_CALLED_STATION_ID, $calledstationid);
    $rauth->putAttribute(RADIUS_CALLING_STATION_ID, $callingstationid);

    // Send request
    $result = $rauth->send();

    // Evaluation of the response
    // 1 -> Access-Request => We will use this value as an error indicator since we can't get a 1 back from the radius
    // 2 -> Access-Accept
    // 3 -> Access-Reject
    // See RFC2865 for this.
    if (PEAR::isError($result)) {
        $retvalue['auth_val'] = 1;
        $retvalue['error'] = $result->getMessage();

    } else if ($result === true) {
        $retvalue['auth_val'] = 2;

    } else {
        $retvalue['auth_val'] = 3;

    }

    // Get attributes, even if auth failed.
    // We will push the results in the retvalue array
    if (!$rauth->getAttributes()) {
        $retvalue['error'] = $rauth->getError();

    } else {
        $retvalue = array_merge($retvalue,$rauth->listAttributes());

        // We convert the session_terminate_time to unixtimestamp if its set before returning the whole array to our caller
        if (!empty($retvalue['session_terminate_time'])) {
        $stt = &$retvalue['session_terminate_time'];
        $stt = strtotime(preg_replace("/\+(\d+):(\d+)$/", " +\${1}\${2}", preg_replace("/(\d+)T(\d+)/", "\${1} \${2}",$stt)));
         }
    }

    // close OO RADIUS_AUTHENTICATION
    $rauth->close();
    unset($rauth);

    return $retvalue;

}

?>
