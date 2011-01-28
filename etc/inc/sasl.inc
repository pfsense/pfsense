<?php
/*
 * sasl.php
 *
 * @(#) $Id: sasl.php,v 1.11 2005/10/31 18:43:27 mlemos Exp $
 *
 */

define("SASL_INTERACT", 2);
define("SASL_CONTINUE", 1);
define("SASL_OK",       0);
define("SASL_FAIL",    -1);
define("SASL_NOMECH",  -4);

class sasl_interact_class
{
	var $id;
	var $challenge;
	var $prompt;
	var $default_result;
	var $result;
};

/*
{metadocument}<?xml version="1.0" encoding="ISO-8859-1" ?>
<class>

	<package>net.manuellemos.sasl</package>

	<version>@(#) $Id: sasl.php,v 1.11 2005/10/31 18:43:27 mlemos Exp $</version>
	<copyright>Copyright © (C) Manuel Lemos 2004</copyright>
	<title>Simple Authentication and Security Layer client</title>
	<author>Manuel Lemos</author>
	<authoraddress>mlemos-at-acm.org</authoraddress>

	<documentation>
		<idiom>en</idiom>
		<purpose>Provide a common interface to plug-in driver classes that
			implement different mechanisms for authentication used by clients of
			standard protocols like SMTP, POP3, IMAP, HTTP, etc.. Currently the
			supported authentication mechanisms are: <tt>PLAIN</tt>,
			<tt>LOGIN</tt>, <tt>CRAM-MD5</tt>, <tt>Digest</tt> and <tt>NTML</tt>
			(Windows or Samba).</purpose>
		<usage>.</usage>
	</documentation>

{/metadocument}
*/

class sasl_client_class
{
	/* Public variables */

/*
{metadocument}
	<variable>
		<name>error</name>
		<type>STRING</type>
		<value></value>
		<documentation>
			<purpose>Store the message that is returned when an error
				occurs.</purpose>
			<usage>Check this variable to understand what happened when a call to
				any of the class functions has failed.<paragraphbreak />
				This class uses cumulative error handling. This means that if one
				class functions that may fail is called and this variable was
				already set to an error message due to a failure in a previous call
				to the same or other function, the function will also fail and does
				not do anything.<paragraphbreak />
				This allows programs using this class to safely call several
				functions that may fail and only check the failure condition after
				the last function call.<paragraphbreak />
				Just set this variable to an empty string to clear the error
				condition.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $error='';

/*
{metadocument}
	<variable>
		<name>mechanism</name>
		<type>STRING</type>
		<value></value>
		<documentation>
			<purpose>Store the name of the mechanism that was selected during the
				call to the <functionlink>Start</functionlink> function.</purpose>
			<usage>You can access this variable but do not change it.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $mechanism='';

/*
{metadocument}
	<variable>
		<name>encode_response</name>
		<type>BOOLEAN</type>
		<value>1</value>
		<documentation>
			<purpose>Let the drivers inform the applications whether responses
				need to be encoded.</purpose>
			<usage>Applications should check this variable before sending
				authentication responses to the server to determine if the
				responses need to be encoded, eventually with base64 algorithm.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $encode_response=1;

	/* Private variables */

	var $driver;
	var $drivers=array(
		"Digest"   => array("digest_sasl_client_class",   "digest_sasl_client.inc"   ),
		"CRAM-MD5" => array("cram_md5_sasl_client_class", "cram_md5_sasl_client.inc" ),
		"LOGIN"    => array("login_sasl_client_class",    "login_sasl_client.inc"    ),
		"NTLM"     => array("ntlm_sasl_client_class",     "ntlm_sasl_client.inc"     ),
		"PLAIN"    => array("plain_sasl_client_class",    "plain_sasl_client.inc"    ),
		"Basic"    => array("basic_sasl_client_class",    "basic_sasl_client.inc"    )
	);
	var $credentials=array();

	/* Public functions */

/*
{metadocument}
	<function>
		<name>SetCredential</name>
		<type>VOID</type>
		<documentation>
			<purpose>Store the value of a credential that may be used by any of
			 the supported mechanisms to process the authentication messages and
			 responses.</purpose>
			<usage>Call this function before starting the authentication dialog
				to pass all the credential values that be needed to use the type
				of authentication that the applications may need.</usage>
			<returnvalue>.</returnvalue>
		</documentation>
		<argument>
			<name>key</name>
			<type>STRING</type>
			<documentation>
				<purpose>Specify the name of the credential key.</purpose>
			</documentation>
		</argument>
		<argument>
			<name>value</name>
			<type>STRING</type>
			<documentation>
				<purpose>Specify the value for the credential.</purpose>
			</documentation>
		</argument>
		<do>
{/metadocument}
*/
	Function SetCredential($key,$value)
	{
		$this->credentials[$key]=$value;
	}
/*
{metadocument}
		</do>
	</function>
{/metadocument}
*/

/*
{metadocument}
	<function>
		<name>GetCredentials</name>
		<type>INTEGER</type>
		<documentation>
			<purpose>Retrieve the values of one or more credentials to be used by
				the authentication mechanism classes.</purpose>
			<usage>This is meant to be used by authentication mechanism driver
				classes to retrieve the credentials that may be neede.</usage>
			<returnvalue>The function may return <tt>SASL_CONTINUE</tt> if it
				succeeded, or <tt>SASL_NOMECH</tt> if it was not possible to
				retrieve one of the requested credentials.</returnvalue>
		</documentation>
		<argument>
			<name>credentials</name>
			<type>HASH</type>
			<documentation>
				<purpose>Reference to an associative array variable with all the
					credentials that are being requested. The function initializes
					this associative array values.</purpose>
			</documentation>
		</argument>
		<argument>
			<name>defaults</name>
			<type>HASH</type>
			<documentation>
				<purpose>Associative arrays with default values for credentials
					that may have not been defined.</purpose>
			</documentation>
		</argument>
		<argument>
			<name>interactions</name>
			<type>ARRAY</type>
			<documentation>
				<purpose>Not yet in use. It is meant to provide context
					information to retrieve credentials that may be obtained
					interacting with the user.</purpose>
			</documentation>
		</argument>
		<do>
{/metadocument}
*/
	Function GetCredentials(&$credentials,$defaults,&$interactions)
	{
		Reset($credentials);
		$end=(GetType($key=Key($credentials))!="string");
		for(;!$end;)
		{
			if(!IsSet($this->credentials[$key]))
			{
				if(IsSet($defaults[$key]))
					$credentials[$key]=$defaults[$key];
				else
				{
					$this->error="the requested credential ".$key." is not defined";
					return(SASL_NOMECH);
				}
			}
			else
				$credentials[$key]=$this->credentials[$key];
			Next($credentials);
			$end=(GetType($key=Key($credentials))!="string");
		}
		return(SASL_CONTINUE);
	}
/*
{metadocument}
		</do>
	</function>
{/metadocument}
*/

/*
{metadocument}
	<function>
		<name>Start</name>
		<type>INTEGER</type>
		<documentation>
			<purpose>Process the initial authentication step initializing the
				driver class that implements the first of the list of requested
				mechanisms that is supported by this SASL client library
				implementation.</purpose>
			<usage>Call this function specifying a list of mechanisms that the
				server supports. If the <argumentlink>
					<argument>message</argument>
					<function>Start</function>
				</argumentlink> argument returns a string, it should be sent to
				the server as initial message. Check the
				<variablelink>encode_response</variablelink> variable to determine
				whether the initial message needs to be encoded, eventually with
				base64 algorithm, before it is sent to the server.</usage>
			<returnvalue>The function may return <tt>SASL_CONTINUE</tt> if it
				could start one of the requested authentication mechanisms. It
				may return <tt>SASL_NOMECH</tt> if it was not possible to start
				any of the requested mechanisms. It returns <tt>SASL_FAIL</tt> or
				other value in case of error.</returnvalue>
		</documentation>
		<argument>
			<name>mechanisms</name>
			<type>ARRAY</type>
			<inout />
			<documentation>
				<purpose>Define the list of names of authentication mechanisms
					supported by the that should be tried.</purpose>
			</documentation>
		</argument>
		<argument>
			<name>message</name>
			<type>STRING</type>
			<out />
			<documentation>
				<purpose>Return the initial message that should be sent to the
					server to start the authentication dialog. If this value is
					undefined, no message should be sent to the server.</purpose>
			</documentation>
		</argument>
		<argument>
			<name>interactions</name>
			<type>ARRAY</type>
			<documentation>
				<purpose>Not yet in use. It is meant to provide context
					information to interact with the end user.</purpose>
			</documentation>
		</argument>
		<do>
{/metadocument}
*/
	Function Start($mechanisms, &$message, &$interactions)
	{
		if(strlen($this->error))
			return(SASL_FAIL);
		if(IsSet($this->driver))
			return($this->driver->Start($this,$message,$interactions));
		$no_mechanism_error="";
		for($m=0;$m<count($mechanisms);$m++)
		{
			$mechanism=$mechanisms[$m];
			if(IsSet($this->drivers[$mechanism]))
			{
				if(!class_exists($this->drivers[$mechanism][0]))
					require(dirname(__FILE__)."/".$this->drivers[$mechanism][1]);
				$this->driver=new $this->drivers[$mechanism][0];
				if($this->driver->Initialize($this))
				{
					$this->encode_response=1;
					$status=$this->driver->Start($this,$message,$interactions);
					switch($status)
					{
						case SASL_NOMECH:
							Unset($this->driver);
							if(strlen($no_mechanism_error)==0)
								$no_mechanism_error=$this->error;
							$this->error="";
							break;
						case SASL_CONTINUE:
							$this->mechanism=$mechanism;
							return($status);
						default:
							Unset($this->driver);
							$this->error="";
							return($status);
					}
				}
				else
				{
					Unset($this->driver);
					if(strlen($no_mechanism_error)==0)
						$no_mechanism_error=$this->error;
					$this->error="";
				}
			}
		}
		$this->error=(strlen($no_mechanism_error) ? $no_mechanism_error : "it was not requested any of the authentication mechanisms that are supported");
		return(SASL_NOMECH);
	}
/*
{metadocument}
		</do>
	</function>
{/metadocument}
*/

/*
{metadocument}
	<function>
		<name>Step</name>
		<type>INTEGER</type>
		<documentation>
			<purpose>Process the authentication steps after the initial step,
				until the authetication iteration dialog is complete.</purpose>
			<usage>Call this function iteratively after a successful initial
				step calling the <functionlink>Start</functionlink> function.</usage>
			<returnvalue>The function returns <tt>SASL_CONTINUE</tt> if step was
				processed successfully, or returns <tt>SASL_FAIL</tt> in case of
				error.</returnvalue>
		</documentation>
		<argument>
			<name>response</name>
			<type>STRING</type>
			<in />
			<documentation>
				<purpose>Pass the response returned by the server to the previous
					step.</purpose>
			</documentation>
		</argument>
		<argument>
			<name>message</name>
			<type>STRING</type>
			<out />
			<documentation>
				<purpose>Return the message that should be sent to the server to
					continue the authentication dialog. If this value is undefined,
					no message should be sent to the server.</purpose>
			</documentation>
		</argument>
		<argument>
			<name>interactions</name>
			<type>ARRAY</type>
			<documentation>
				<purpose>Not yet in use. It is meant to provide context
					information to interact with the end user.</purpose>
			</documentation>
		</argument>
		<do>
{/metadocument}
*/
	Function Step($response, &$message, &$interactions)
	{
		if(strlen($this->error))
			return(SASL_FAIL);
		return($this->driver->Step($this,$response,$message,$interactions));
	}
/*
{metadocument}
		</do>
	</function>
{/metadocument}
*/

};

/*

{metadocument}
</class>
{/metadocument}

*/

?>
