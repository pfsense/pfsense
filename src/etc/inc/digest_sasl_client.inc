<?php
/*
 * digest_sasl_client.php
 *
 * @(#) $Id: digest_sasl_client.php,v 1.1 2005/10/27 05:24:15 mlemos Exp $
 *
 */

define('SASL_DIGEST_STATE_START',             0);
define('SASL_DIGEST_STATE_RESPOND_CHALLENGE', 1);
define('SASL_DIGEST_STATE_DONE',              2);

class digest_sasl_client_class
{
	var $credentials=array();
	var $state=SASL_DIGEST_STATE_START;

	Function unq($string)
	{
		return(($string[0]=='"' && $string[strlen($string)-1]=='"') ? substr($string, 1, strlen($string)-2) : $string);
	}

	Function H($data)
	{
		return md5($data);
	}

	Function KD($secret, $data)
	{
		return $this->H($secret.':'.$data);
	}

	Function Initialize(&$client)
	{
		return(1);
	}

	Function Start(&$client, &$message, &$interactions)
	{
		if($this->state!=SASL_DIGEST_STATE_START)
		{
			$client->error='Digest authentication state is not at the start';
			return(SASL_FAIL);
		}
		$this->credentials=array(
			'user'=>'',
			'password'=>'',
			'uri'=>'',
			'method'=>'',
			'session'=>''
		);
		$defaults=array();
		$status=$client->GetCredentials($this->credentials,$defaults,$interactions);
		if($status==SASL_CONTINUE)
			$this->state=SASL_DIGEST_STATE_RESPOND_CHALLENGE;
		Unset($message);
		return($status);
	}

	Function Step(&$client, $response, &$message, &$interactions)
	{
		switch($this->state)
		{
			case SASL_DIGEST_STATE_RESPOND_CHALLENGE:
				$values=explode(',',$response);
				$parameters=array();
				for($v=0; $v<count($values); $v++)
					$parameters[strtok(trim($values[$v]), '=')]=strtok('');

				$message='username="'.$this->credentials['user'].'"';
				if(!IsSet($parameters[$p='realm'])
				&& !IsSet($parameters[$p='nonce']))
				{
					$client->error='Digest authentication parameter '.$p.' is missing from the server response';
					return(SASL_FAIL);
				}
				$message.=', realm='.$parameters['realm'];
				$message.=', nonce='.$parameters['nonce'];
				$message.=', uri="'.$this->credentials['uri'].'"';
				if(IsSet($parameters['algorithm']))
				{
					$algorithm=$this->unq($parameters['algorithm']);
					$message.=', algorithm='.$parameters['algorithm'];
				}
				else
					$algorithm='';

				$realm=$this->unq($parameters['realm']);
				$nonce=$this->unq($parameters['nonce']);
				if(IsSet($parameters['qop']))
				{
					switch($qop=$this->unq($parameters['qop']))
					{
						case "auth":
							$cnonce=$this->credentials['session'];
							break;
						default:
							$client->error='Digest authentication quality of protection '.$qop.' is not yet supported';
							return(SASL_FAIL);
					}
				}
				$nc_value='00000001';
				if(IsSet($parameters['qop'])
				&& !strcmp($algorithm, 'MD5-sess'))
					$A1=$this->H($this->credentials['user'].':'. $realm.':'. $this->credentials['password']).':'.$nonce.':'.$cnonce;
				else
					$A1=$this->credentials['user'].':'. $realm.':'. $this->credentials['password'];
				$A2=$this->credentials['method'].':'.$this->credentials['uri'];
				if(IsSet($parameters['qop']))
					$response=$this->KD($this->H($A1), $nonce.':'. $nc_value.':'. $cnonce.':'. $qop.':'. $this->H($A2));
				else
					$response=$this->KD($this->H($A1), $nonce.':'. $this->H($A2));
				$message.=', response="'.$response.'"';
				if(IsSet($parameters['opaque']))
					$message.=', opaque='.$parameters['opaque'];
				if(IsSet($parameters['qop']))
					$message.=', qop="'.$qop.'"';
				$message.=', nc='.$nc_value;
				if(IsSet($parameters['qop']))
					$message.=', cnonce="'.$cnonce.'"';
				$client->encode_response=0;
				$this->state=SASL_DIGEST_STATE_DONE;
				break;
			case SASL_DIGEST_STATE_DONE:
				$client->error='Digest authentication was finished without success';
				return(SASL_FAIL);
			default:
				$client->error='invalid Digest authentication step state';
				return(SASL_FAIL);
		}
		return(SASL_CONTINUE);
	}
};

?>