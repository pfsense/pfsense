<?php
/*
 * login_sasl_client.php
 *
 * @(#) $Id: login_sasl_client.php,v 1.2 2004/11/17 08:00:37 mlemos Exp $
 *
 */

define("SASL_LOGIN_STATE_START",             0);
define("SASL_LOGIN_STATE_IDENTIFY_USER",     1);
define("SASL_LOGIN_STATE_IDENTIFY_PASSWORD", 2);
define("SASL_LOGIN_STATE_DONE",              3);

class login_sasl_client_class
{
	var $credentials=array();
	var $state=SASL_LOGIN_STATE_START;

	Function Initialize(&$client)
	{
		return(1);
	}

	Function Start(&$client, &$message, &$interactions)
	{
		if($this->state!=SASL_LOGIN_STATE_START)
		{
			$client->error="LOGIN authentication state is not at the start";
			return(SASL_FAIL);
		}
		$this->credentials=array(
			"user"=>"",
			"password"=>"",
			"realm"=>""
		);
		$defaults=array(
			"realm"=>""
		);
		$status=$client->GetCredentials($this->credentials,$defaults,$interactions);
		if($status==SASL_CONTINUE)
			$this->state=SASL_LOGIN_STATE_IDENTIFY_USER;
		Unset($message);
		return($status);
	}

	Function Step(&$client, $response, &$message, &$interactions)
	{
		switch($this->state)
		{
			case SASL_LOGIN_STATE_IDENTIFY_USER:
				$message=$this->credentials["user"].(strlen($this->credentials["realm"]) ? "@".$this->credentials["realm"] : "");
				$this->state=SASL_LOGIN_STATE_IDENTIFY_PASSWORD;
				break;
			case SASL_LOGIN_STATE_IDENTIFY_PASSWORD:
				$message=$this->credentials["password"];
				$this->state=SASL_LOGIN_STATE_DONE;
				break;
			case SASL_LOGIN_STATE_DONE:
				$client->error="LOGIN authentication was finished without success";
				break;
			default:
				$client->error="invalid LOGIN authentication step state";
				return(SASL_FAIL);
		}
		return(SASL_CONTINUE);
	}
};

?>