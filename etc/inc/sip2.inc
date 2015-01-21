<?php
/*
    Copyright (c) 2009, Niles Ingalls <niles@atheos.net>
	2015 updated for PfSense 2.2
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

	This is based on the SIP2 protocol described here:
	http://solutions.3m.com/wps/portal/3M/en_US/library/home/resources/protocols/

	This script has only been tested with Evergreen 1.2.3 using Open NSIP.
	If I followed the standard properly, it might work with your ILS too!

*/

/*
SIP2 AUTHENTICATION
---------------------
*/


class sip2
{
	var $seqno = -1;
	var $language = '001';
	var $retry = '5';
	var $timeout = '5';
	var $skipcrc = '1';
	var $userid;
	var $password;
	var $login;

	function login()
	{ 

		global $config, $cpzone;
		$this->skipcrc = $config['captiveportal'][$cpzone]['sip2skipcrc'];
		$command = sprintf("9300CN%s|CO%s|CP%s|AY%sAZ", $config['captiveportal'][$cpzone]['sip2login'], $config['captiveportal'][$cpzone]['sip2password'], $config['captiveportal'][$cpzone]['sip2location'], $this->seqno = $this->seqno());
		$command = $command . $this->crc($command) . "\r";

		fwrite($this->connection, $command);

			// we don't need the data back from our Login, but if we don't go through the motion, the next fread() gets this info instead :/

			while ($term != "\x0D") { 

		 		$term = fread($this->connection, 1);

			}

		$this->login = true;
	}
	
	function authenticate()
	{

		if ($this->login == true) {

			$patron_array = $this->return_patron_array($this->patron_information());

			if ((isset($patron_array['BL']) && $patron_array['BL'] == 'Y') && (isset($patron_array['CQ']) && $patron_array['CQ'] == 'Y' )) return true;

		} else { // authenticate() was called before login

			$this->login();		
			return $this->authenticate();

		}

	}

	function fines()
	{

		global $config, $cpzone;

		if ($this->login == true) {

			$patron_array = $this->return_patron_array($this->patron_information());
			
			if (!($config['captiveportal'][$cpzone]['sip2threshold'] > 0 && $patron_array['BV'] < $config['captiveportal'][$cpzone]['sip2threshold'])) return $patron_array['BV'];

		} else { // fines() was called before login

		        $this->login();
				$this->authenticate();
				return $this->fines();

		}

	}	

	function patron_information()
	{

		global $config, $cpzone;	
		$command = sprintf("63001%s   Y      AO%s|AA%s|AD%s|BP1|BQ5|AY%sAZ", date('Ymd    His'), $config['captiveportal'][$cpzone]['sip2institution'], $this->userid, $this->password, $this->seqno = $this->seqno());
		$command = $command . $this->crc($command) . "\r";

		fwrite($this->connection, $command);	
		stream_set_timeout($this->connection, $this->timeout);

				while ($term != "\x0D") {

					$info = stream_get_meta_data($this->connection);
					if ($info['timed_out']) die("Connection timed out!\n");
					
					$term = fread($this->connection, 1);
					$result .= $term;

				}

				if ($this->skipcrc || $this->crc_validate(trim($result))) {

					return trim($result);

				} else {

					// CRC error, request resend.
					return $this->acs_resend(); 

				}

	}

	function return_patron_array($message) {

		$result = explode("|", $message);
		array_shift($result);

			foreach($result AS $value) {

				$response[substr($value,0,2)] = substr($value, 2);

			}

		return $response;

	}

	function acs_resend()
	{

		if ($this->retry-- > 0) {

			$command = sprintf("97|AY%sAZ", $this->seqno = $this->seqno());
			$command = $command . $this->crc($command) . "\r";

			fwrite($this->connection, $command);

			while ($term != "\x0D") {

				$term = fread($this->connection, 1);
				$result .= $term;

			}

			$result = trim($result);
			
			if ($this->skipcrc || $this->crc_validate(trim($result))) {

				return trim($result);

			} else {

					// CRC error, request resend.
				return $this->acs_resend();

			}
			
		} else {

			echo "exceeded all CRC retries, giving up\n";  //Log this somewhere.

		}

	}

	function connect()
	{

                global $config, $cpzone;
		if (!$this->connection = fsockopen($config['captiveportal'][$cpzone]['sip2server'], $config['captiveportal'][$cpzone]['sip2port'])) die("Cannot Connect\n");

	}

	function disconnect()
	{

		fclose($this->connection);

	}

	function crc($msg) {

		for($i = 0; $i < strlen($msg); $i++) {

			$total = $total + ord($msg[$i]);

		}

		return substr(sprintf("%X", ($total) * -1), -4, 4);

	}

	function crc_validate($message)
	{

		if ($this->crc(substr($message, 0, -4)) == substr($message, -4)) return true;

	}

	function seqno() 
    {

        $this->seqno++;

        if ($this->seqno > 9 ) $this->seqno = 0;
 
        return ($this->seqno);
 
   }

}
?>
