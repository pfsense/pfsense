<?php
/*
 * cram_md5_sasl_client.php
 *
 * @(#) $Id: cram_md5_sasl_client.php,v 1.3 2004/11/17 08:00:37 mlemos Exp $
 *
 */

define("SASL_CRAM_MD5_STATE_START",             0);
define("SASL_CRAM_MD5_STATE_RESPOND_CHALLENGE", 1);
define("SASL_CRAM_MD5_STATE_DONE",              2);

class cram_md5_sasl_client_class
{
	var $credentials=array();
	var $state=SASL_CRAM_MD5_STATE_START;

	Function Initialize(&$client)
	{
		return(1);
	}

	Function HMACMD5($key,$text)
	{
		$key=(strlen($key)<64 ? str_pad($key,64,"\0") : substr($key,0,64));
		return(md5((str_repeat("\x5c", 64)^$key).pack("H32", md5((str_repeat("\x36", 64)^$key).$text))));
	}

	Function Start(&$client, &$message, &$interactions)
	{
		if($this->state!=SASL_CRAM_MD5_STATE_START)
		{
			$client->error="CRAM-MD5 authentication state is not at the start";
			return(SASL_FAIL);
		}
		$this->credentials=array(
			"user"=>"",
			"password"=>""
		);
		$defaults=array();
		$status=$client->GetCredentials($this->credentials,$defaults,$interactions);
		if($status==SASL_CONTINUE)
			$this->state=SASL_CRAM_MD5_STATE_RESPOND_CHALLENGE;
		Unset($message);
		return($status);
	}

	Function Step(&$client, $response, &$message, &$interactions)
	{
		switch($this->state)
		{
			case SASL_CRAM_MD5_STATE_RESPOND_CHALLENGE:
				$message=$this->credentials["user"]." ".$this->HMACMD5($this->credentials["password"], $response);
				$this->state=SASL_CRAM_MD5_STATE_DONE;
				break;
			case SASL_CRAM_MD5_STATE_DONE:
				$client->error="CRAM-MD5 authentication was finished without success";
				return(SASL_FAIL);
			default:
				$client->error="invalid CRAM-MD5 authentication step state";
				return(SASL_FAIL);
		}
		return(SASL_CONTINUE);
	}
};

?>