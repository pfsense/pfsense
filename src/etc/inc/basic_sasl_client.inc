<?php
/*
 * basic_sasl_client.php
 *
 * @(#) $Id: basic_sasl_client.php,v 1.1 2004/11/17 08:01:23 mlemos Exp $
 *
 */

define("SASL_BASIC_STATE_START",    0);
define("SASL_BASIC_STATE_DONE",     1);

class basic_sasl_client_class
{
	var $credentials=array();
	var $state=SASL_BASIC_STATE_START;

	Function Initialize(&$client)
	{
		return(1);
	}

	Function Start(&$client, &$message, &$interactions)
	{
		if($this->state!=SASL_BASIC_STATE_START)
		{
			$client->error="Basic authentication state is not at the start";
			return(SASL_FAIL);
		}
		$this->credentials=array(
			"user"=>"",
			"password"=>""
		);
		$defaults=array(
		);
		$status=$client->GetCredentials($this->credentials,$defaults,$interactions);
		if($status==SASL_CONTINUE)
		{
			$message=$this->credentials["user"].":".$this->credentials["password"];
			$this->state=SASL_BASIC_STATE_DONE;
		}
		else
			Unset($message);
		return($status);
	}

	Function Step(&$client, $response, &$message, &$interactions)
	{
		switch($this->state)
		{
			case SASL_BASIC_STATE_DONE:
				$client->error="Basic authentication was finished without success";
				return(SASL_FAIL);
			default:
				$client->error="invalid Basic authentication step state";
				return(SASL_FAIL);
		}
		return(SASL_CONTINUE);
	}
};

?>