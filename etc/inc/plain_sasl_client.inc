<?php
/*
 * plain_sasl_client.php
 *
 * @(#) $Id: plain_sasl_client.php,v 1.2 2004/11/17 08:00:37 mlemos Exp $
 *
 */

define("SASL_PLAIN_STATE_START",    0);
define("SASL_PLAIN_STATE_IDENTIFY", 1);
define("SASL_PLAIN_STATE_DONE",     2);

define("SASL_PLAIN_DEFAULT_MODE",            0);
define("SASL_PLAIN_EXIM_MODE",               1);
define("SASL_PLAIN_EXIM_DOCUMENTATION_MODE", 2);

class plain_sasl_client_class
{
	var $credentials=array();
	var $state=SASL_PLAIN_STATE_START;

	Function Initialize(&$client)
	{
		return(1);
	}

	Function Start(&$client, &$message, &$interactions)
	{
		if($this->state!=SASL_PLAIN_STATE_START)
		{
			$client->error="PLAIN authentication state is not at the start";
			return(SASL_FAIL);
		}
		$this->credentials=array(
			"user"=>"",
			"password"=>"",
			"realm"=>"",
			"mode"=>""
		);
		$defaults=array(
			"realm"=>"",
			"mode"=>""
		);
		$status=$client->GetCredentials($this->credentials,$defaults,$interactions);
		if($status==SASL_CONTINUE)
		{
			switch($this->credentials["mode"])
			{
				case SASL_PLAIN_EXIM_MODE:
					$message=$this->credentials["user"]."\0".$this->credentials["password"]."\0";
					break;
				case SASL_PLAIN_EXIM_DOCUMENTATION_MODE:
					$message="\0".$this->credentials["user"]."\0".$this->credentials["password"];
					break;
				default:
					$message=$this->credentials["user"]."\0".$this->credentials["user"].(strlen($this->credentials["realm"]) ? "@".$this->credentials["realm"] : "")."\0".$this->credentials["password"];
					break;
			}
			$this->state=SASL_PLAIN_STATE_DONE;
		}
		else
			Unset($message);
		return($status);
	}

	Function Step(&$client, $response, &$message, &$interactions)
	{
		switch($this->state)
		{
/*
			case SASL_PLAIN_STATE_IDENTIFY:
				switch($this->credentials["mode"])
				{
					case SASL_PLAIN_EXIM_MODE:
						$message=$this->credentials["user"]."\0".$this->credentials["password"]."\0";
						break;
					case SASL_PLAIN_EXIM_DOCUMENTATION_MODE:
						$message="\0".$this->credentials["user"]."\0".$this->credentials["password"];
						break;
					default:
						$message=$this->credentials["user"]."\0".$this->credentials["user"].(strlen($this->credentials["realm"]) ? "@".$this->credentials["realm"] : "")."\0".$this->credentials["password"];
						break;
				}
				var_dump($message);
				$this->state=SASL_PLAIN_STATE_DONE;
				break;
*/
			case SASL_PLAIN_STATE_DONE:
				$client->error="PLAIN authentication was finished without success";
				return(SASL_FAIL);
			default:
				$client->error="invalid PLAIN authentication step state";
				return(SASL_FAIL);
		}
		return(SASL_CONTINUE);
	}
};

?>