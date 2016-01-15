<?php
/*
 * smtp.php
 *
 * @(#) $Header: /opt2/ena/metal/smtp/smtp.php,v 1.48 2014/11/23 22:45:30 mlemos Exp $
 *
 */

/*
{metadocument}<?xml version="1.0" encoding="ISO-8859-1"?>
<class>

	<package>net.manuellemos.smtp</package>

	<version>@(#) $Id: smtp.php,v 1.48 2014/11/23 22:45:30 mlemos Exp $</version>
	<copyright>Copyright (C) Manuel Lemos 1999-2011</copyright>
	<title>Sending e-mail messages via SMTP protocol</title>
	<author>Manuel Lemos</author>
	<authoraddress>mlemos-at-acm.org</authoraddress>

	<documentation>
		<idiom>en</idiom>
		<purpose>Sending e-mail messages via SMTP protocol</purpose>
		<translation>If you are interested in translating the documentation of
			this class to your own idiom, please <link>
				<data>contact the author</data>
				<url>mailto:<getclassproperty>authoraddress</getclassproperty></url>
			</link>.</translation>
		<support>Technical support for using this class may be obtained in the
			<tt>smtpclass</tt> support forum. Just go to the support forum pages
			page to browse the forum archives and post support request
			messages:<paragraphbreak />
			<link>
				<data>http://www.phpclasses.org/discuss/package/14/</data>
				<url>http://www.phpclasses.org/discuss/package/14/</url>
			</link></support>
		<usage>To use this class just create a new object, set any variables
			to configure its options and call the
			<functionlink>SendMessage</functionlink> function to send a
			message.<paragraphbreak />It is not recommended that you use this
			class alone unless you have deep understanding of Internet mail
			standards on how to compose compliant e-mail messages. Instead, use
			the <link>
				<data>MIME message composing and sending class</data>
				<url>http://www.phpclasses.org/mimemessage</url>
			</link> and its sub-class SMTP message together with this SMTP class
			to properly compose e-mail messages, so your messages are not
			discarded for not being correctly composed.</usage>
	</documentation>

{/metadocument}
*/

class smtp_class
{
/*
{metadocument}
	<variable>
		<name>user</name>
		<type>STRING</type>
		<value></value>
		<documentation>
			<purpose>Define the authorized user when sending messages to a SMTP
				server.</purpose>
			<usage>Set this variable to the user name when the SMTP server
				requires authentication.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $user="";

/*
{metadocument}
	<variable>
		<name>realm</name>
		<type>STRING</type>
		<value></value>
		<documentation>
			<purpose>Define the authentication realm when sending messages to a
				SMTP server.</purpose>
			<usage>Set this variable when the SMTP server requires
				authentication and if more than one authentication realm is
				supported.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $realm="";

/*
{metadocument}
	<variable>
		<name>password</name>
		<type>STRING</type>
		<value></value>
		<documentation>
			<purpose>Define the authorized user password when sending messages
				to a SMTP server.</purpose>
			<usage>Set this variable to the user password when the SMTP server
				requires authentication.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $password="";

/*
{metadocument}
	<variable>
		<name>workstation</name>
		<type>STRING</type>
		<value></value>
		<documentation>
			<purpose>Define the client workstation name when sending messages
				to a SMTP server.</purpose>
			<usage>Set this variable to the client workstation when the SMTP
				server requires authentication identifiying the origin workstation
				name.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $workstation="";
	
/*
{metadocument}
	<variable>
		<name>authentication_mechanism</name>
		<type>STRING</type>
		<value></value>
		<documentation>
			<purpose>Force the use of a specific authentication mechanism.</purpose>
			<usage>Set it to an empty string to let the class determine the
				authentication mechanism to use automatically based on the
				supported mechanisms by the server and by the SASL client library
				classes.<paragraphbreak />
				Set this variable to a specific mechanism name if you want to
				override the automatic authentication mechanism selection.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $authentication_mechanism="";

/*
{metadocument}
	<variable>
		<name>host_name</name>
		<type>STRING</type>
		<value></value>
		<documentation>
			<purpose>Define the SMTP server host name.</purpose>
			<usage>Set to the host name of the SMTP server to which you want to
				relay the messages.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $host_name="";

/*
{metadocument}
	<variable>
		<name>host_port</name>
		<type>INTEGER</type>
		<value>25</value>
		<documentation>
			<purpose>Define the SMTP server host port.</purpose>
			<usage>Set to the TCP port of the SMTP server host to connect.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $host_port=25;

/*
{metadocument}
	<variable>
		<name>socks_host_name</name>
		<type>STRING</type>
		<value></value>
		<documentation>
			<purpose>Define the SOCKS server host name.</purpose>
			<usage>Set to the SOCKS server host name through which the SMTP
				connection should be routed. Leave it empty if you do not want the
				connections to be established through a SOCKS server.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $socks_host_name = '';

/*
{metadocument}
	<variable>
		<name>socks_host_port</name>
		<type>INTEGER</type>
		<value>1080</value>
		<documentation>
			<purpose>Define the SOCKS server host port.</purpose>
			<usage>Set to the port of the SOCKS server host through which the
				the SMTP connection should be routed.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $socks_host_port=1080;

/*
{metadocument}
	<variable>
		<name>socks_version</name>
		<type>STRING</type>
		<value>5</value>
		<documentation>
			<purpose>Set the SOCKS protocol version.</purpose>
			<usage>Change this value if SOCKS server you want to use is
				listening to a different port.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $socks_version='5';

/*
{metadocument}
	<variable>
		<name>http_proxy_host_name</name>
		<type>STRING</type>
		<value></value>
		<documentation>
			<purpose>Define the HTTP proxy server host name.</purpose>
			<usage>Set to the HTTP proxy server host name through which the
				SMTP connection should be routed. Leave it empty if you do not
				want the connections to be established through an HTTP proxy.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $http_proxy_host_name = '';

/*
{metadocument}
	<variable>
		<name>http_proxy_host_port</name>
		<type>INTEGER</type>
		<value>80</value>
		<documentation>
			<purpose>Define the HTTP proxy server host port.</purpose>
			<usage>Set to the port of the HTTP proxy server host through which
				the SMTP connection should be routed.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $http_proxy_host_port=80;

/*
{metadocument}
	<variable>
		<name>user_agent</name>
		<type>STRING</type>
		<value>SMTP Class (http://www.phpclasses.org/smtpclass $Revision: 1.48 $)</value>
		<documentation>
			<purpose>Set the user agent used when connecting via an HTTP proxy.</purpose>
			<usage>Change this value only if for some reason you want emulate a
				certain e-mail client.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $user_agent='SMTP Class (http://www.phpclasses.org/smtpclass $Revision: 1.48 $)';

/*
{metadocument}
	<variable>
		<name>ssl</name>
		<type>BOOLEAN</type>
		<value>0</value>
		<documentation>
			<purpose>Define whether the connection to the SMTP server should be
				established securely using SSL protocol.</purpose>
			<usage>Set to <booleanvalue>1</booleanvalue> if the SMTP server
				requires secure connections using SSL protocol.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $ssl=0;

/*
{metadocument}
	<variable>
		<name>start_tls</name>
		<type>BOOLEAN</type>
		<value>0</value>
		<documentation>
			<purpose>Define whether the connection to the SMTP server should use
				encryption after the connection is established using TLS
				protocol.</purpose>
			<usage>Set to <booleanvalue>1</booleanvalue> if the SMTP server
				requires that authentication be done securely starting the TLS
				protocol after the connection is established.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $start_tls = 0;

/*
{metadocument}
	<variable>
		<name>localhost</name>
		<type>STRING</type>
		<value></value>
		<documentation>
			<purpose>Name of the local host computer</purpose>
			<usage>Set to the name of the computer connecting to the SMTP
				server from the local network.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $localhost="";

/*
{metadocument}
	<variable>
		<name>timeout</name>
		<type>INTEGER</type>
		<value>0</value>
		<documentation>
			<purpose>Specify the connection timeout period in seconds.</purpose>
			<usage>Leave it set to <integervalue>0</integervalue> if you want
				the connection attempts to wait forever. Change this value if for
				some reason the timeout period seems insufficient or otherwise it
				seems too long.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $timeout=0;

/*
{metadocument}
	<variable>
		<name>data_timeout</name>
		<type>INTEGER</type>
		<value>0</value>
		<documentation>
			<purpose>Specify the timeout period in seconds to wait for data from
				the server.</purpose>
			<usage>Leave it set to <integervalue>0</integervalue> if you want
				to use the same value defined in the
				<variablelink>timeout</variablelink> variable. Change this value
				if for some reason the default data timeout period seems
				insufficient or otherwise it seems too long.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $data_timeout=0;

/*
{metadocument}
	<variable>
		<name>direct_delivery</name>
		<type>BOOLEAN</type>
		<value>0</value>
		<documentation>
			<purpose>Boolean flag that indicates whether the message should be
				sent in direct delivery mode, i.e. the message is sent to the SMTP
				server associated to the domain of the recipient instead of
				relaying to the server specified by the
				<variablelink>host_name</variablelink> variable.</purpose>
			<usage>Set this to <tt><booleanvalue>1</booleanvalue></tt> if you
				want to send urgent messages directly to the recipient domain SMTP
				server.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $direct_delivery=0;

/*
{metadocument}
	<variable>
		<name>error</name>
		<type>STRING</type>
		<value></value>
		<documentation>
			<purpose>Message that describes the error when a call to a class
				function fails.</purpose>
			<usage>Check this variable when an error occurs to understand what
				happened.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $error="";

/*
{metadocument}
	<variable>
		<name>debug</name>
		<type>BOOLEAN</type>
		<value>0</value>
		<documentation>
			<purpose>Specify whether it is necessary to output SMTP connection
				debug information.</purpose>
			<usage>Set this variable to
				<tt><booleanvalue>1</booleanvalue></tt> if you need to see
				the progress of the SMTP connection and protocol dialog when you
				need to understand the reason for delivery problems.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $debug=0;

/*
{metadocument}
	<variable>
		<name>html_debug</name>
		<type>BOOLEAN</type>
		<value>0</value>
		<documentation>
			<purpose>Specify whether the debug information should be outputted in
				HTML format.</purpose>
			<usage>Set this variable to
				<tt><booleanvalue>1</booleanvalue></tt> if you need to see
				the debug output in a Web page.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $html_debug=0;

/*
{metadocument}
	<variable>
		<name>esmtp</name>
		<type>BOOLEAN</type>
		<value>1</value>
		<documentation>
			<purpose>Specify whether the class should attempt to use ESMTP
				extensions supported by the server.</purpose>
			<usage>Set this variable to
				<tt><booleanvalue>0</booleanvalue></tt> if for some reason you
				want to avoid benefitting from ESMTP extensions.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $esmtp=1;

/*
{metadocument}
	<variable>
		<name>esmtp_extensions</name>
		<type>HASH</type>
		<value></value>
		<documentation>
			<purpose>Associative array with the list of ESMTP extensions
				supported by the SMTP server.</purpose>
			<usage>Check this variable after connecting to the SMTP server to
				determine which ESMTP extensions are supported.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $esmtp_extensions=array();

/*
{metadocument}
	<variable>
		<name>exclude_address</name>
		<type>STRING</type>
		<value></value>
		<documentation>
			<purpose>Specify an address that should be considered invalid
				when resolving host name addresses.</purpose>
			<usage>In some networks any domain name that does not exist is
				resolved as a sub-domain of the default local domain. If the DNS is
				configured in such way that it always resolves any sub-domain of
				the default local domain to a given address, it is hard to
				determine whether a given domain does not exist.<paragraphbreak />
				If your network is configured this way, you may set this variable
				to the address that all sub-domains of the default local domain
				resolves, so the class can assume that such address is invalid.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $exclude_address="";

/*
{metadocument}
	<variable>
		<name>getmxrr</name>
		<type>STRING</type>
		<value>getmxrr</value>
		<documentation>
			<purpose>Specify the name of the function that is called to determine
				the SMTP server address of a given domain.</purpose>
			<usage>Change this to a working replacement of the PHP
				<tt>getmxrr()</tt> function if this is not working in your system
					and you want to send messages in direct delivery mode.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $getmxrr="GetMXRR";

/*
{metadocument}
	<variable>
		<name>pop3_auth_host</name>
		<type>STRING</type>
		<value></value>
		<documentation>
			<purpose>Specify the server address for POP3 based authentication.</purpose>
			<usage>Set this variable to the address of the POP3 server if the
				SMTP server requires POP3 based authentication.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $pop3_auth_host="";

/*
{metadocument}
	<variable>
		<name>pop3_auth_port</name>
		<type>INTEGER</type>
		<value>110</value>
		<documentation>
			<purpose>Specify the server port for POP3 based authentication.</purpose>
			<usage>Set this variable to the port of the POP3 server if the
				SMTP server requires POP3 based authentication.</usage>
		</documentation>
	</variable>
{/metadocument}
*/
	var $pop3_auth_port=110;

	/* private variables - DO NOT ACCESS */

	var $state="Disconnected";
	var $connection=0;
	var $pending_recipients=0;
	var $next_token="";
	var $direct_sender="";
	var $connected_domain="";
	var $result_code;
	var $disconnected_error=0;
	var $esmtp_host="";
	var $maximum_piped_recipients=100;

	/* Private methods - DO NOT CALL */

	Function Tokenize($string,$separator="")
	{
		if(!strcmp($separator,""))
		{
			$separator=$string;
			$string=$this->next_token;
		}
		for($character=0;$character<strlen($separator);$character++)
		{
			if(GetType($position=strpos($string,$separator[$character]))=="integer")
				$found=(IsSet($found) ? min($found,$position) : $position);
		}
		if(IsSet($found))
		{
			$this->next_token=substr($string,$found+1);
			return(substr($string,0,$found));
		}
		else
		{
			$this->next_token="";
			return($string);
		}
	}

	Function OutputDebug($message)
	{
		$message.="\n";
		if($this->html_debug)
			$message=str_replace("\n","<br />\n",HtmlEntities($message));
		echo $message;
		flush();
	}

	Function SetDataAccessError($error)
	{
		$this->error=$error;
		if(function_exists("socket_get_status"))
		{
			$status=socket_get_status($this->connection);
			if($status["timed_out"])
				$this->error.=": data access time out";
			elseif($status["eof"])
			{
				$this->error.=": the server disconnected";
				$this->disconnected_error=1;
			}
		}
		return($this->error);
	}

	Function SetError($error)
	{
		return($this->error=$error);
	}

	Function GetLine()
	{
		for($line="";;)
		{
			if(feof($this->connection))
			{
				$this->error="reached the end of data while reading from the SMTP server conection";
				return("");
			}
			if(GetType($data=@fgets($this->connection,100))!="string"
			|| strlen($data)==0)
			{
				$this->SetDataAccessError("it was not possible to read line from the SMTP server");
				return("");
			}
			$line.=$data;
			$length=strlen($line);
			if($length>=2
			&& substr($line,$length-2,2)=="\r\n")
			{
				$line=substr($line,0,$length-2);
				if($this->debug)
					$this->OutputDebug("S $line");
				return($line);
			}
		}
	}

	Function PutLine($line)
	{
		if($this->debug)
			$this->OutputDebug("C $line");
		if(!@fputs($this->connection,"$line\r\n"))
		{
			$this->SetDataAccessError("it was not possible to send a line to the SMTP server");
			return(0);
		}
		return(1);
	}

	Function PutData(&$data)
	{
		if(strlen($data))
		{
			if($this->debug)
				$this->OutputDebug("C $data");
			if(!@fputs($this->connection,$data))
			{
				$this->SetDataAccessError("it was not possible to send data to the SMTP server");
				return(0);
			}
		}
		return(1);
	}

	Function VerifyResultLines($code,&$responses)
	{
		$responses=array();
		Unset($this->result_code);
		while(strlen($line=$this->GetLine($this->connection)))
		{
			if(IsSet($this->result_code))
			{
				if(strcmp($this->Tokenize($line," -"),$this->result_code))
				{
					$this->error=$line;
					return(0);
				}
			}
			else
			{
				$this->result_code=$this->Tokenize($line," -");
				if(GetType($code)=="array")
				{
					for($codes=0;$codes<count($code) && strcmp($this->result_code,$code[$codes]);$codes++);
					if($codes>=count($code))
					{
						$this->error=$line;
						return(0);
					}
				}
				else
				{
					if(strcmp($this->result_code,$code))
					{
						$this->error=$line;
						return(0);
					}
				}
			}
			$responses[]=$this->Tokenize("");
			if(!strcmp($this->result_code,$this->Tokenize($line," ")))
				return(1);
		}
		return(-1);
	}

	Function FlushRecipients()
	{
		if($this->pending_sender)
		{
			if($this->VerifyResultLines("250",$responses)<=0)
				return(0);
			$this->pending_sender=0;
		}
		for(;$this->pending_recipients;$this->pending_recipients--)
		{
			if($this->VerifyResultLines(array("250","251"),$responses)<=0)
				return(0);
		}
		return(1);
	}

	Function Resolve($domain, &$ip, $server_type)
	{
		if(preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/',$domain))
			$ip=$domain;
		else
		{
			if($this->debug)
				$this->OutputDebug('Resolving '.$server_type.' server domain "'.$domain.'"...');
			if(!strcmp($ip=@gethostbyname($domain),$domain))
				$ip="";
		}
		if(strlen($ip)==0
		|| (strlen($this->exclude_address)
		&& !strcmp(@gethostbyname($this->exclude_address),$ip)))
			return($this->SetError("could not resolve the host domain \"".$domain."\""));
		return('');
	}

	Function ConnectToHost($domain, $port, $resolve_message)
	{
		if($this->ssl)
		{
			$version=explode(".",function_exists("phpversion") ? phpversion() : "3.0.7");
			$php_version=intval($version[0])*1000000+intval($version[1])*1000+intval($version[2]);
			if($php_version<4003000)
				return("establishing SSL connections requires at least PHP version 4.3.0");
			if(!function_exists("extension_loaded")
			|| !extension_loaded("openssl"))
				return("establishing SSL connections requires the OpenSSL extension enabled");
		}
		if(strlen($this->Resolve($domain, $ip, 'SMTP')))
			return($this->error);
		if(strlen($this->socks_host_name))
		{
			switch($this->socks_version)
			{
				case '4':
					$version = 4;
					break;
				case '5':
					$version = 5;
					break;
				default:
					return('it was not specified a supported SOCKS protocol version');
					break;
			}
			$host_ip = $ip;
			$host_port = $port;
			if(strlen($this->error = $this->Resolve($this->socks_host_name, $ip, 'SOCKS')))
				return($this->error);
			if($this->ssl)
				$ip="ssl://".($socks_host = $this->socks_host_name);
			else
				$socks_host = $ip;
			if($this->debug)
				$this->OutputDebug("Connecting to SOCKS server \"".$socks_host."\" port ".$this->http_proxy_host_port."...");
			if(($this->connection=($this->timeout ? fsockopen($ip, $this->socks_host_port, $errno, $error, $this->timeout) : fsockopen($ip, $this->socks_host_port, $errno, $error))))
			{
				$timeout=($this->data_timeout ? $this->data_timeout : $this->timeout);
				if($timeout
				&& function_exists("socket_set_timeout"))
					socket_set_timeout($this->connection,$timeout,0);
				if(strlen($this->socks_host_name))
				{
					if($this->debug)
						$this->OutputDebug('Connected to the SOCKS server '.$this->socks_host_name);
					$send_error = 'it was not possible to send data to the SOCKS server';
					$receive_error = 'it was not possible to receive data from the SOCKS server';
					switch($version)
					{
						case 4:
							$command = 1;
							$user = '';
							if(!fputs($this->connection, chr($version).chr($command).pack('nN', $host_port, ip2long($host_ip)).$user.Chr(0)))
								$error = $this->SetDataAccessError($send_error);
							else
							{
								$response = fgets($this->connection, 9);
								if(strlen($response) != 8)
									$error = $this->SetDataAccessError($receive_error);
								else
								{
									$socks_errors = array(
										"\x5a"=>'',
										"\x5b"=>'request rejected',
										"\x5c"=>'request failed because client is not running identd (or not reachable from the server)',
										"\x5d"=>'request failed because client\'s identd could not confirm the user ID string in the request',
									);
									$error_code = $response[1];
									$error = (IsSet($socks_errors[$error_code]) ? $socks_errors[$error_code] : 'unknown');
									if(strlen($error))
										$error = 'SOCKS error: '.$error;
								}
							}
							break;
						case 5:
							if($this->debug)
								$this->OutputDebug('Negotiating the authentication method ...');
							$methods = 1;
							$method = 0;
							if(!fputs($this->connection, chr($version).chr($methods).chr($method)))
								$error = $this->SetDataAccessError($send_error);
							else
							{
								$response = fgets($this->connection, 3);
								if(strlen($response) != 2)
									$error = $this->SetDataAccessError($receive_error);
								elseif(Ord($response[1]) != $method)
									$error = 'the SOCKS server requires an authentication method that is not yet supported';
								else
								{
									if($this->debug)
										$this->OutputDebug('Connecting to SMTP server IP '.$host_ip.' port '.$host_port.'...');
									$command = 1;
									$address_type = 1;
									if(!fputs($this->connection, chr($version).chr($command)."\x00".chr($address_type).pack('Nn', ip2long($host_ip), $host_port)))
										$error = $this->SetDataAccessError($send_error);
									else
									{
										$response = fgets($this->connection, 11);
										if(strlen($response) != 10)
											$error = $this->SetDataAccessError($receive_error);
										else
										{
											$socks_errors = array(
												"\x00"=>'',
												"\x01"=>'general SOCKS server failure',
												"\x02"=>'connection not allowed by ruleset',
												"\x03"=>'Network unreachable',
												"\x04"=>'Host unreachable',
												"\x05"=>'Connection refused',
												"\x06"=>'TTL expired',
												"\x07"=>'Command not supported',
												"\x08"=>'Address type not supported'
											);
											$error_code = $response[1];
											$error = (IsSet($socks_errors[$error_code]) ? $socks_errors[$error_code] : 'unknown');
											if(strlen($error))
												$error = 'SOCKS error: '.$error;
										}
									}
								}
							}
							break;
						default:
							$error = 'support for SOCKS protocol version '.$this->socks_version.' is not yet implemented';
							break;
					}
					if(strlen($this->error = $error))
					{
						fclose($this->connection);
						return($error);
					}
				}
				return('');
			}
		}
		elseif(strlen($this->http_proxy_host_name))
		{
			if(strlen($error = $this->Resolve($this->http_proxy_host_name, $ip, 'SMTP')))
				return($error);
			if($this->ssl)
				$ip = 'ssl://'.($proxy_host = $this->http_proxy_host_name);
			else
				$proxy_host = $ip;
			if($this->debug)
				$this->OutputDebug("Connecting to HTTP proxy server \"".$ip."\" port ".$this->http_proxy_host_port."...");
			if(($this->connection=($this->timeout ? @fsockopen($ip, $this->http_proxy_host_port, $errno, $error, $this->timeout) : @fsockopen($ip, $this->http_proxy_host_port, $errno, $error))))
			{
				if($this->debug)
					$this->OutputDebug('Connected to HTTP proxy host "'.$this->http_proxy_host_name.'".');
				$timeout=($this->data_timeout ? $this->data_timeout : $this->timeout);
				if($timeout
				&& function_exists("socket_set_timeout"))
					socket_set_timeout($this->connection,$timeout,0);
				if($this->PutLine('CONNECT '.$domain.':'.$port.' HTTP/1.0')
				&& $this->PutLine('User-Agent: '.$this->user_agent)
				&& $this->PutLine(''))
				{
					if(GetType($response = $this->GetLine()) == 'string')
					{
						if(!preg_match('/^http\\/[0-9]+\\.[0-9]+[ \t]+([0-9]+)[ \t]*(.*)$/i', $response,$matches))
							return($this->SetError("3 it was received an unexpected HTTP response status"));
						$error = $matches[1];
						switch($error)
						{
							case '200':
								for(;;)
								{
									if(GetType($response = $this->GetLine()) != 'string')
										break;
									if(strlen($response) == 0)
										return('');
								}
								break;
							default:
								$this->error = 'the HTTP proxy returned error '.$error.' '.$matches[2];
								break;
						}
					}
				}
				if($this->debug)
					$this->OutputDebug("Disconnected.");
				fclose($this->connection);
				$this->connection = 0;
				return($this->error);
			}
		}
		else
		{
			if($this->ssl)
				$ip = 'ssl://'.($host = $domain);
			elseif($this->start_tls)
				$ip = $host = $domain;
			else
				$host = $ip;
			if($this->debug)
				$this->OutputDebug("Connecting to SMTP server \"".$host."\" port ".$port."...");
			if(($this->connection=($this->timeout ? @fsockopen($ip, $port, $errno, $error, $this->timeout) : @fsockopen($ip, $port, $errno, $error))))
				return("");
		}
		$error=($this->timeout ? strval($error) : "??");
		switch($error)
		{
			case "-3":
				return("-3 socket could not be created");
			case "-4":
				return("-4 dns lookup on hostname \"".$domain."\" failed");
			case "-5":
				return("-5 connection refused or timed out");
			case "-6":
				return("-6 fdopen() call failed");
			case "-7":
				return("-7 setvbuf() call failed");
		}
		return("could not connect to the host \"".$domain."\": ".$error);
	}

	Function SASLAuthenticate($mechanisms, $credentials, &$authenticated, &$mechanism)
	{
		$authenticated=0;
		if(!function_exists("class_exists")
		|| !class_exists("sasl_client_class"))
		{
			$this->error="it is not possible to authenticate using the specified mechanism because the SASL library class is not loaded";
			return(0);
		}
		$sasl=new sasl_client_class;
		$sasl->SetCredential("user",$credentials["user"]);
		$sasl->SetCredential("password",$credentials["password"]);
		if(IsSet($credentials["realm"]))
			$sasl->SetCredential("realm",$credentials["realm"]);
		if(IsSet($credentials["workstation"]))
			$sasl->SetCredential("workstation",$credentials["workstation"]);
		if(IsSet($credentials["mode"]))
			$sasl->SetCredential("mode",$credentials["mode"]);
		do
		{
			$status=$sasl->Start($mechanisms,$message,$interactions);
		}
		while($status==SASL_INTERACT);
		switch($status)
		{
			case SASL_CONTINUE:
				break;
			case SASL_NOMECH:
				if(strlen($this->authentication_mechanism))
				{
					$this->error="authenticated mechanism ".$this->authentication_mechanism." may not be used: ".$sasl->error;
					return(0);
				}
				break;
			default:
				$this->error="Could not start the SASL authentication client: ".$sasl->error;
				return(0);
		}
		if(strlen($mechanism=$sasl->mechanism))
		{
			if($this->PutLine("AUTH ".$sasl->mechanism.(IsSet($message) ? " ".base64_encode($message) : ""))==0)
			{
				$this->error="Could not send the AUTH command";
				return(0);
			}
			if(!$this->VerifyResultLines(array("235","334"),$responses))
				return(0);
			switch($this->result_code)
			{
				case "235":
					$response="";
					$authenticated=1;
					break;
				case "334":
					$response=base64_decode($responses[0]);
					break;
				default:
					$this->error="Authentication error: ".$responses[0];
					return(0);
			}
			for(;!$authenticated;)
			{
				do
				{
					$status=$sasl->Step($response,$message,$interactions);
				}
				while($status==SASL_INTERACT);
				switch($status)
				{
					case SASL_CONTINUE:
						if($this->PutLine(base64_encode($message))==0)
						{
							$this->error="Could not send the authentication step message";
							return(0);
						}
						if(!$this->VerifyResultLines(array("235","334"),$responses))
							return(0);
						switch($this->result_code)
						{
							case "235":
								$response="";
								$authenticated=1;
								break;
							case "334":
								$response=base64_decode($responses[0]);
								break;
							default:
								$this->error="Authentication error: ".$responses[0];
								return(0);
						}
						break;
					default:
						$this->error="Could not process the SASL authentication step: ".$sasl->error;
						return(0);
				}
			}
		}
		return(1);
	}
	
	Function StartSMTP($localhost)
	{
		$success = 1;
		$this->esmtp_extensions = array();
		$fallback=1;
		if($this->esmtp
		|| strlen($this->user))
		{
			if($this->PutLine('EHLO '.$localhost))
			{
				if(($success_code=$this->VerifyResultLines('250',$responses))>0)
				{
					$this->esmtp_host=$this->Tokenize($responses[0]," ");
					for($response=1;$response<count($responses);$response++)
					{
						$extension=strtoupper($this->Tokenize($responses[$response]," "));
						$this->esmtp_extensions[$extension]=$this->Tokenize("");
					}
					$success=1;
					$fallback=0;
				}
				else
				{
					if($success_code==0)
					{
						$code=$this->Tokenize($this->error," -");
						switch($code)
						{
							case "421":
								$fallback=0;
								break;
						}
					}
				}
			}
			else
				$fallback=0;
		}
		if($fallback)
		{
			if($this->PutLine("HELO $localhost")
			&& $this->VerifyResultLines("250",$responses)>0)
				$success=1;
		}
		return($success);
	}

	/* Public methods */

/*
{metadocument}
	<function>
		<name>Connect</name>
		<type>BOOLEAN</type>
		<documentation>
			<purpose>Connect to an SMTP server.</purpose>
			<usage>Call this function as first step to send e-mail messages.</usage>
			<returnvalue>The function returns
				<tt><booleanvalue>1</booleanvalue></tt> if the connection is
					successfully established.</returnvalue>
		</documentation>
		<argument>
			<name>domain</name>
			<type>STRING</type>
			<defaultvalue></defaultvalue>
			<documentation>
				<purpose>Specify the domain of the recipient when using the direct
					delivery mode.</purpose>
			</documentation>
		</argument>
		<do>
{/metadocument}
*/
	Function Connect($domain="")
	{
		if(strcmp($this->state,"Disconnected"))
		{
			$this->error="connection is already established";
			return(0);
		}
		$this->disconnected_error=0;
		$this->error=$error="";
		$this->esmtp_host="";
		$this->esmtp_extensions=array();
		$hosts=array();
		if($this->direct_delivery)
		{
			if(strlen($domain)==0)
				return(1);
			$hosts=$weights=$mxhosts=array();
			$getmxrr=$this->getmxrr;
			if(function_exists($getmxrr)
			&& $getmxrr($domain,$hosts,$weights))
			{
				for($host=0;$host<count($hosts);$host++)
					$mxhosts[$weights[$host]]=$hosts[$host];
				KSort($mxhosts);
				for(Reset($mxhosts),$host=0;$host<count($mxhosts);Next($mxhosts),$host++)
					$hosts[$host]=$mxhosts[Key($mxhosts)];
			}
			else
			{
				if(strcmp(@gethostbyname($domain),$domain)!=0)
					$hosts[]=$domain;
			}
		}
		else
		{
			if(strlen($this->host_name))
				$hosts[]=$this->host_name;
			if(strlen($this->pop3_auth_host))
			{
				$user=$this->user;
				if(strlen($user)==0)
				{
					$this->error="it was not specified the POP3 authentication user";
					return(0);
				}
				$password=$this->password;
				if(strlen($password)==0)
				{
					$this->error="it was not specified the POP3 authentication password";
					return(0);
				}
				$domain=$this->pop3_auth_host;
				$this->error=$this->ConnectToHost($domain, $this->pop3_auth_port, "Resolving POP3 authentication host \"".$domain."\"...");
				if(strlen($this->error))
					return(0);
				if(strlen($response=$this->GetLine())==0)
					return(0);
				if(strcmp($this->Tokenize($response," "),"+OK"))
				{
					$this->error="POP3 authentication server greeting was not found";
					return(0);
				}
				if(!$this->PutLine("USER ".$this->user)
				|| strlen($response=$this->GetLine())==0)
					return(0);
				if(strcmp($this->Tokenize($response," "),"+OK"))
				{
					$this->error="POP3 authentication user was not accepted: ".$this->Tokenize("\r\n");
					return(0);
				}
				if(!$this->PutLine("PASS ".$password)
				|| strlen($response=$this->GetLine())==0)
					return(0);
				if(strcmp($this->Tokenize($response," "),"+OK"))
				{
					$this->error="POP3 authentication password was not accepted: ".$this->Tokenize("\r\n");
					return(0);
				}
				fclose($this->connection);
				$this->connection=0;
			}
		}
		if(count($hosts)==0)
		{
			$this->error="could not determine the SMTP to connect";
			return(0);
		}
		for($host=0, $error="not connected";strlen($error) && $host<count($hosts);$host++)
		{
			$domain=$hosts[$host];
			$error=$this->ConnectToHost($domain, $this->host_port, "Resolving SMTP server domain \"$domain\"...");
		}
		if(strlen($error))
		{
			$this->error=$error;
			return(0);
		}
		$timeout=($this->data_timeout ? $this->data_timeout : $this->timeout);
		if($timeout
		&& function_exists("socket_set_timeout"))
			socket_set_timeout($this->connection,$timeout,0);
		if($this->debug)
			$this->OutputDebug("Connected to SMTP server \"".$domain."\".");
		if(!strcmp($localhost=$this->localhost,"")
		&& !strcmp($localhost=getenv("SERVER_NAME"),"")
		&& !strcmp($localhost=getenv("HOST"),""))
			$localhost="localhost";
		$success=0;
		if($this->VerifyResultLines("220",$responses)>0)
		{
			$success = $this->StartSMTP($localhost);
			if($this->start_tls)
			{
				if(!IsSet($this->esmtp_extensions["STARTTLS"]))
				{
					$this->error="server does not support starting TLS";
					$success=0;
				}
				elseif(!function_exists('stream_socket_enable_crypto'))
				{
					$this->error="this PHP installation or version does not support starting TLS";
					$success=0;
				}
				elseif($success = ($this->PutLine('STARTTLS')
				&& $this->VerifyResultLines('220',$responses)>0))
				{
					$this->OutputDebug('Starting TLS cryptograpic protocol');
					if(!($success = stream_socket_enable_crypto($this->connection, 1, STREAM_CRYPTO_METHOD_TLS_CLIENT)))
						$this->error = 'could not start TLS connection encryption protocol';
					else
					{
						$this->OutputDebug('TLS started');
						$success = $this->StartSMTP($localhost);
					}
				}
			}
			if($success
			&& strlen($this->user)
			&& strlen($this->pop3_auth_host)==0)
			{
				if(!IsSet($this->esmtp_extensions["AUTH"]))
				{
					$this->error="server does not require authentication";
					if(IsSet($this->esmtp_extensions["STARTTLS"]))
						$this->error .= ', it probably requires starting TLS';
					$success=0;
				}
				else
				{
					if(strlen($this->authentication_mechanism))
						$mechanisms=array($this->authentication_mechanism);
					else
					{
						$mechanisms=array();
						for($authentication=$this->Tokenize($this->esmtp_extensions["AUTH"]," ");strlen($authentication);$authentication=$this->Tokenize(" "))
							$mechanisms[]=$authentication;
					}
					$credentials=array(
						"user"=>$this->user,
						"password"=>$this->password
					);
					if(strlen($this->realm))
						$credentials["realm"]=$this->realm;
					if(strlen($this->workstation))
						$credentials["workstation"]=$this->workstation;
					$success=$this->SASLAuthenticate($mechanisms,$credentials,$authenticated,$mechanism);
					if(!$success
					&& !strcmp($mechanism,"PLAIN"))
					{
						/*
						 * Author:  Russell Robinson, 25 May 2003, http://www.tectite.com/
						 * Purpose: Try various AUTH PLAIN authentication methods.
						 */
						$mechanisms=array("PLAIN");
						$credentials=array(
							"user"=>$this->user,
							"password"=>$this->password
						);
						if(strlen($this->realm))
						{
							/*
							 * According to: http://www.sendmail.org/~ca/email/authrealms.html#authpwcheck_method
							 * some sendmails won't accept the realm, so try again without it
							 */
							$success=$this->SASLAuthenticate($mechanisms,$credentials,$authenticated,$mechanism);
						}
						if(!$success)
						{
							/*
							 * It was seen an EXIM configuration like this:
							 * user^password^unused
							 */
							$credentials["mode"]=SASL_PLAIN_EXIM_DOCUMENTATION_MODE;
							$success=$this->SASLAuthenticate($mechanisms,$credentials,$authenticated,$mechanism);
						}
						if(!$success)
						{
							/*
							 * ... though: http://exim.work.de/exim-html-3.20/doc/html/spec_36.html
							 * specifies: ^user^password
							 */
							$credentials["mode"]=SASL_PLAIN_EXIM_MODE;
							$success=$this->SASLAuthenticate($mechanisms,$credentials,$authenticated,$mechanism);
						}
					}
					if($success
					&& strlen($mechanism)==0)
					{
						$this->error="it is not supported any of the authentication mechanisms required by the server";
						$success=0;
					}
				}
			}
		}
		if($success)
		{
			$this->state="Connected";
			$this->connected_domain=$domain;
		}
		else
		{
			fclose($this->connection);
			$this->connection=0;
		}
		return($success);
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
		<name>MailFrom</name>
		<type>BOOLEAN</type>
		<documentation>
			<purpose>Set the address of the message sender.</purpose>
			<usage>Call this function right after establishing a connection with
				the <functionlink>Connect</functionlink> function.</usage>
			<returnvalue>The function returns
				<tt><booleanvalue>1</booleanvalue></tt> if the sender address is
					successfully set.</returnvalue>
		</documentation>
		<argument>
			<name>sender</name>
			<type>STRING</type>
			<documentation>
				<purpose>E-mail address of the sender.</purpose>
			</documentation>
		</argument>
		<do>
{/metadocument}
*/
	Function MailFrom($sender)
	{
		if($this->direct_delivery)
		{
			switch($this->state)
			{
				case "Disconnected":
					$this->direct_sender=$sender;
					return(1);
				case "Connected":
					$sender=$this->direct_sender;
					break;
				default:
					$this->error="direct delivery connection is already established and sender is already set";
					return(0);
			}
		}
		else
		{
			if(strcmp($this->state,"Connected"))
			{
				$this->error="connection is not in the initial state";
				return(0);
			}
		}
		$this->error="";
		if(!$this->PutLine("MAIL FROM:<$sender>"))
			return(0);
		if(!IsSet($this->esmtp_extensions["PIPELINING"])
		&& $this->VerifyResultLines("250",$responses)<=0)
			return(0);
		$this->state="SenderSet";
		if(IsSet($this->esmtp_extensions["PIPELINING"]))
			$this->pending_sender=1;
		$this->pending_recipients=0;
		return(1);
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
		<name>SetRecipient</name>
		<type>BOOLEAN</type>
		<documentation>
			<purpose>Set the address of a message recipient.</purpose>
			<usage>Call this function repeatedly for each recipient right after
				setting the message sender with the
				<functionlink>MailFrom</functionlink> function.</usage>
			<returnvalue>The function returns
				<tt><booleanvalue>1</booleanvalue></tt> if the recipient address is
					successfully set.</returnvalue>
		</documentation>
		<argument>
			<name>recipient</name>
			<type>STRING</type>
			<documentation>
				<purpose>E-mail address of a recipient.</purpose>
			</documentation>
		</argument>
		<do>
{/metadocument}
*/
	Function SetRecipient($recipient)
	{
		if($this->direct_delivery)
		{
			if(GetType($at=strrpos($recipient,"@"))!="integer")
				return("it was not specified a valid direct recipient");
			$domain=substr($recipient,$at+1);
			switch($this->state)
			{
				case "Disconnected":
					if(!$this->Connect($domain))
						return(0);
					if(!$this->MailFrom(""))
					{
						$error=$this->error;
						$this->Disconnect();
						$this->error=$error;
						return(0);
					}
					break;
				case "SenderSet":
				case "RecipientSet":
					if(strcmp($this->connected_domain,$domain))
					{
						$this->error="it is not possible to deliver directly to recipients of different domains";
						return(0);
					}
					break;
				default:
					$this->error="connection is already established and the recipient is already set";
					return(0);
			}
		}
		else
		{
			switch($this->state)
			{
				case "SenderSet":
				case "RecipientSet":
					break;
				default:
					$this->error="connection is not in the recipient setting state";
					return(0);
			}
		}
		$this->error="";
		if(!$this->PutLine("RCPT TO:<$recipient>"))
			return(0);
		if(IsSet($this->esmtp_extensions["PIPELINING"]))
		{
			$this->pending_recipients++;
			if($this->pending_recipients>=$this->maximum_piped_recipients)
			{
				if(!$this->FlushRecipients())
					return(0);
			}
		}
		else
		{
			if($this->VerifyResultLines(array("250","251"),$responses)<=0)
				return(0);
		}
		$this->state="RecipientSet";
		return(1);
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
		<name>StartData</name>
		<type>BOOLEAN</type>
		<documentation>
			<purpose>Tell the SMTP server that the message data will start being
				sent.</purpose>
			<usage>Call this function right after you are done setting all the
				message recipients with the
				<functionlink>SetRecipient</functionlink> function.</usage>
			<returnvalue>The function returns
				<tt><booleanvalue>1</booleanvalue></tt> if the server is ready to
				start receiving the message data.</returnvalue>
		</documentation>
		<do>
{/metadocument}
*/
	Function StartData()
	{
		if(strcmp($this->state,"RecipientSet"))
		{
			$this->error="connection is not in the start sending data state";
			return(0);
		}
		$this->error="";
		if(!$this->PutLine("DATA"))
			return(0);
		if($this->pending_recipients)
		{
			if(!$this->FlushRecipients())
				return(0);
		}
		if($this->VerifyResultLines("354",$responses)<=0)
			return(0);
		$this->state="SendingData";
		return(1);
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
		<name>PrepareData</name>
		<type>STRING</type>
		<documentation>
			<purpose>Prepare message data to normalize line breaks and escaping
				lines that contain single dots.</purpose>
			<usage>Call this function if the message data you want to send may
				contain line breaks that are not the
				<stringvalue>&#13;&#10;</stringvalue> sequence or it may contain
				lines that just have a single dot.</usage>
			<returnvalue>Resulting normalized messages data.</returnvalue>
		</documentation>
		<argument>
			<name>data</name>
			<type>STRING</type>
			<documentation>
				<purpose>Message data to be prepared.</purpose>
			</documentation>
		</argument>
		<do>
{/metadocument}
*/
	Function PrepareData($data)
	{
		return(preg_replace(array("/\n\n|\r\r/","/(^|[^\r])\n/","/\r([^\n]|\$)/D","/(^|\n)\\./"),array("\r\n\r\n","\\1\r\n","\r\n\\1","\\1.."),$data));
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
		<name>SendData</name>
		<type>BOOLEAN</type>
		<documentation>
			<purpose>Send message data.</purpose>
			<usage>Call this function repeatedly for all message data blocks
				to be sent right after	start sending message data with the
				<functionlink>StartData</functionlink> function.</usage>
			<returnvalue>The function returns
				<tt><booleanvalue>1</booleanvalue></tt> if the message data was
				sent to the SMTP server successfully.</returnvalue>
		</documentation>
		<argument>
			<name>data</name>
			<type>STRING</type>
			<documentation>
				<purpose>Message data to be sent.</purpose>
			</documentation>
		</argument>
		<do>
{/metadocument}
*/
	Function SendData($data)
	{
		if(strcmp($this->state,"SendingData"))
		{
			$this->error="connection is not in the sending data state";
			return(0);
		}
		$this->error="";
		return($this->PutData($data));
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
		<name>EndSendingData</name>
		<type>BOOLEAN</type>
		<documentation>
			<purpose>Tell the server that all the message data was sent.</purpose>
			<usage>Call this function when you are done with sending the message
				data with the	<functionlink>SendData</functionlink> function.</usage>
			<returnvalue>The function returns
				<tt><booleanvalue>1</booleanvalue></tt> if the server accepted the
				message.</returnvalue>
		</documentation>
		<do>
{/metadocument}
*/
	Function EndSendingData()
	{
		if(strcmp($this->state,"SendingData"))
		{
			$this->error="connection is not in the sending data state";
			return(0);
		}
		$this->error="";
		if(!$this->PutLine("\r\n.")
		|| $this->VerifyResultLines("250",$responses)<=0)
			return(0);
		$this->state="Connected";
		return(1);
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
		<name>ResetConnection</name>
		<type>BOOLEAN</type>
		<documentation>
			<purpose>Reset an already established SMTP connection to the initial
				state.</purpose>
			<usage>Call this function when there was an error sending a message
				and you need to skip to sending another message without
				disconnecting.</usage>
			<returnvalue>The function returns
				<tt><booleanvalue>1</booleanvalue></tt> if the connection was
				resetted successfully.</returnvalue>
		</documentation>
		<do>
{/metadocument}
*/
	Function ResetConnection()
	{
		switch($this->state)
		{
			case "Connected":
				return(1);
			case "SendingData":
				$this->error="can not reset the connection while sending data";
				return(0);
			case "Disconnected":
				$this->error="can not reset the connection before it is established";
				return(0);
		}
		$this->error="";
		if(!$this->PutLine("RSET")
		|| $this->VerifyResultLines("250",$responses)<=0)
			return(0);
		$this->state="Connected";
		return(1);
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
		<name>Disconnect</name>
		<type>BOOLEAN</type>
		<documentation>
			<purpose>Terminate a previously opened connection.</purpose>
			<usage>Call this function after you are done sending your
				messages.</usage>
			<returnvalue>The function returns
				<tt><booleanvalue>1</booleanvalue></tt> if the connection was
					successfully closed.</returnvalue>
		</documentation>
		<argument>
			<name>quit</name>
			<type>BOOLEAN</type>
			<defaultvalue>1</defaultvalue>
			<documentation>
				<purpose>Boolean option that tells whether the class should
					perform the final connection quit handshake, or just close the
					connection without waiting.</purpose>
			</documentation>
		</argument>
		<do>
{/metadocument}
*/
	Function Disconnect($quit=1)
	{
		if(!strcmp($this->state,"Disconnected"))
		{
			$this->error="it was not previously established a SMTP connection";
			return(0);
		}
		$this->error="";
		if(!strcmp($this->state,"Connected")
		&& $quit
		&& (!$this->PutLine("QUIT")
		|| ($this->VerifyResultLines("221",$responses)<=0
		&& !$this->disconnected_error)))
			return(0);
		if($this->disconnected_error)
			$this->disconnected_error=0;
		else
			fclose($this->connection);
		$this->connection=0;
		$this->state="Disconnected";
		if($this->debug)
			$this->OutputDebug("Disconnected.");
		return(1);
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
		<name>SendMessage</name>
		<type>BOOLEAN</type>
		<documentation>
			<purpose>Send a message in a single call.</purpose>
			<usage>Call this function if you want to send a single messages to a
				small number of recipients in a single call.</usage>
			<returnvalue>The function returns
				<tt><booleanvalue>1</booleanvalue></tt> if the message was sent
				successfully.</returnvalue>
		</documentation>
		<argument>
			<name>sender</name>
			<type>STRING</type>
			<documentation>
				<purpose>E-mail address of the sender.</purpose>
			</documentation>
		</argument>
		<argument>
			<name>recipients</name>
			<type>STRING</type>
			<documentation>
				<purpose>Array with a list of the e-mail addresses of the
					recipients of the message.</purpose>
			</documentation>
		</argument>
		<argument>
			<name>headers</name>
			<type>ARRAY</type>
			<documentation>
				<purpose>Array with a list of the header lines of the message.</purpose>
			</documentation>
		</argument>
		<argument>
			<name>body</name>
			<type>STRING</type>
			<documentation>
				<purpose>Body data of the message.</purpose>
			</documentation>
		</argument>
		<do>
{/metadocument}
*/
	Function SendMessage($sender,$recipients,$headers,$body)
	{
		if(($success=$this->Connect()))
		{
			if(($success=$this->MailFrom($sender)))
			{
				for($recipient=0;$recipient<count($recipients);$recipient++)
				{
					if(!($success=$this->SetRecipient($recipients[$recipient])))
						break;
				}
				if($success
				&& ($success=$this->StartData()))
				{
					for($header_data="",$header=0;$header<count($headers);$header++)
						$header_data.=$headers[$header]."\r\n";
					$success=($this->SendData($header_data."\r\n")
						&& $this->SendData($this->PrepareData($body))
						&& $this->EndSendingData());
				}
			}
			$error=$this->error;
			$disconnect_success=$this->Disconnect($success);
			if($success)
				$success=$disconnect_success;
			else
				$this->error=$error;
		}
		return($success);
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