<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/* $Id$ */

/**
 * PHP implementation of the XML-RPC protocol
 *
 * This is a PEAR-ified version of Useful inc's XML-RPC for PHP.
 * It has support for HTTP transport, proxies and authentication.
 *
 * PHP versions 4 and 5
 *
 * LICENSE: License is granted to use or modify this software
 * ("XML-RPC for PHP") for commercial or non-commercial use provided the
 * copyright of the author is preserved in any distributed or derivative work.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESSED OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   Web Services
 * @package    XML_RPC
 * @author     Edd Dumbill <edd@usefulinc.com>
 * @author     Stig Bakken <stig@php.net>
 * @author     Martin Jansen <mj@php.net>
 * @author     Daniel Convissor <danielc@php.net>
 * @copyright  1999-2001 Edd Dumbill
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/XML_RPC
 */


if (!function_exists('xml_parser_create')) {
    // Win 32 fix. From: "Leo West" <lwest@imaginet.fr>
    if ($WINDIR) {
        dl('php_xml.dll');
    } else {
        dl('xml.so');
    }
}

/**#@+
 * Error constants
 */
define('XML_RPC_ERROR_INVALID_TYPE',        101);
define('XML_RPC_ERROR_NON_NUMERIC_FOUND',   102);
define('XML_RPC_ERROR_CONNECTION_FAILED',   103);
define('XML_RPC_ERROR_ALREADY_INITIALIZED', 104);
/**#@-*/


/**
 * Data types
 * @global string $GLOBALS['XML_RPC_I4']
 */
$GLOBALS['XML_RPC_I4'] = 'i4';

/**
 * Data types
 * @global string $GLOBALS['XML_RPC_Int']
 */
$GLOBALS['XML_RPC_Int'] = 'int';

/**
 * Data types
 * @global string $GLOBALS['XML_RPC_Boolean']
 */
$GLOBALS['XML_RPC_Boolean'] = 'boolean';

/**
 * Data types
 * @global string $GLOBALS['XML_RPC_Double']
 */
$GLOBALS['XML_RPC_Double'] = 'double';

/**
 * Data types
 * @global string $GLOBALS['XML_RPC_String']
 */
$GLOBALS['XML_RPC_String'] = 'string';

/**
 * Data types
 * @global string $GLOBALS['XML_RPC_DateTime']
 */
$GLOBALS['XML_RPC_DateTime'] = 'dateTime.iso8601';

/**
 * Data types
 * @global string $GLOBALS['XML_RPC_Base64']
 */
$GLOBALS['XML_RPC_Base64'] = 'base64';

/**
 * Data types
 * @global string $GLOBALS['XML_RPC_Array']
 */
$GLOBALS['XML_RPC_Array'] = 'array';

/**
 * Data types
 * @global string $GLOBALS['XML_RPC_Struct']
 */
$GLOBALS['XML_RPC_Struct'] = 'struct';


/**
 * Data type meta-types
 * @global array $GLOBALS['XML_RPC_Types']
 */
$GLOBALS['XML_RPC_Types'] = array(
    $GLOBALS['XML_RPC_I4']       => 1,
    $GLOBALS['XML_RPC_Int']      => 1,
    $GLOBALS['XML_RPC_Boolean']  => 1,
    $GLOBALS['XML_RPC_String']   => 1,
    $GLOBALS['XML_RPC_Double']   => 1,
    $GLOBALS['XML_RPC_DateTime'] => 1,
    $GLOBALS['XML_RPC_Base64']   => 1,
    $GLOBALS['XML_RPC_Array']    => 2,
    $GLOBALS['XML_RPC_Struct']   => 3,
);


/**
 * Error message numbers
 * @global array $GLOBALS['XML_RPC_err']
 */
$GLOBALS['XML_RPC_err'] = array(
    'unknown_method'      => 1,
    'invalid_return'      => 2,
    'incorrect_params'    => 3,
    'introspect_unknown'  => 4,
    'http_error'          => 5,
);

/**
 * Error message strings
 * @global array $GLOBALS['XML_RPC_str']
 */
$GLOBALS['XML_RPC_str'] = array(
    'unknown_method'      => 'Unknown method',
    'invalid_return'      => 'Invalid return payload: enable debugging to examine incoming payload',
    'incorrect_params'    => 'Incorrect parameters passed to method',
    'introspect_unknown'  => 'Can\'t introspect: method unknown',
    'http_error'          => 'Didn\'t receive 200 OK from remote server.',
);


/**
 * Default XML encoding (ISO-8859-1, UTF-8 or US-ASCII)
 * @global string $GLOBALS['XML_RPC_defencoding']
 */
$GLOBALS['XML_RPC_defencoding'] = 'UTF-8';

/**
 * User error codes start at 800
 * @global int $GLOBALS['XML_RPC_erruser']
 */
$GLOBALS['XML_RPC_erruser'] = 800;

/**
 * XML parse error codes start at 100
 * @global int $GLOBALS['XML_RPC_errxml']
 */
$GLOBALS['XML_RPC_errxml'] = 100;


/**
 * Compose backslashes for escaping regexp
 * @global string $GLOBALS['XML_RPC_backslash']
 */
$GLOBALS['XML_RPC_backslash'] = chr(92) . chr(92);


/**
 * Stores state during parsing
 *
 * quick explanation of components:
 *   + st     = builds up a string for evaluation
 *   + ac     = accumulates values
 *   + qt     = decides if quotes are needed for evaluation
 *   + cm     = denotes struct or array (comma needed)
 *   + isf    = indicates a fault
 *   + lv     = indicates "looking for a value": implements the logic
 *               to allow values with no types to be strings
 *   + params = stores parameters in method calls
 *   + method = stores method name
 *
 * @global array $GLOBALS['XML_RPC_xh']
 */
$GLOBALS['XML_RPC_xh'] = array();


/**
 * Start element handler for the XML parser
 *
 * @return void
 */
function XML_RPC_se($parser_resource, $name, $attrs)
{
    global $XML_RPC_xh, $XML_RPC_DateTime, $XML_RPC_String;
    $parser = (int) $parser_resource;

    switch ($name) {
    case 'STRUCT':
    case 'ARRAY':
        $XML_RPC_xh[$parser]['st'] .= 'array(';
        $XML_RPC_xh[$parser]['cm']++;
        // this last line turns quoting off
        // this means if we get an empty array we'll
        // simply get a bit of whitespace in the eval
        $XML_RPC_xh[$parser]['qt'] = 0;
        break;

    case 'NAME':
        $XML_RPC_xh[$parser]['st'] .= "'";
        $XML_RPC_xh[$parser]['ac'] = '';
        break;

    case 'FAULT':
        $XML_RPC_xh[$parser]['isf'] = 1;
        break;

    case 'PARAM':
        $XML_RPC_xh[$parser]['st'] = '';
        break;

    case 'VALUE':
        $XML_RPC_xh[$parser]['st'] .= 'new XML_RPC_Value(';
        $XML_RPC_xh[$parser]['lv'] = 1;
        $XML_RPC_xh[$parser]['vt'] = $XML_RPC_String;
        $XML_RPC_xh[$parser]['ac'] = '';
        $XML_RPC_xh[$parser]['qt'] = 0;
        // look for a value: if this is still 1 by the
        // time we reach the first data segment then the type is string
        // by implication and we need to add in a quote
        break;

    case 'I4':
    case 'INT':
    case 'STRING':
    case 'BOOLEAN':
    case 'DOUBLE':
    case 'DATETIME.ISO8601':
    case 'BASE64':
        $XML_RPC_xh[$parser]['ac'] = ''; // reset the accumulator

        if ($name == 'DATETIME.ISO8601' || $name == 'STRING') {
            $XML_RPC_xh[$parser]['qt'] = 1;

            if ($name == 'DATETIME.ISO8601') {
                $XML_RPC_xh[$parser]['vt'] = $XML_RPC_DateTime;
            }

        } elseif ($name == 'BASE64') {
            $XML_RPC_xh[$parser]['qt'] = 2;
        } else {
            // No quoting is required here -- but
            // at the end of the element we must check
            // for data format errors.
            $XML_RPC_xh[$parser]['qt'] = 0;
        }
        break;

    case 'MEMBER':
        $XML_RPC_xh[$parser]['ac'] = '';
    }

    if ($name != 'VALUE') {
        $XML_RPC_xh[$parser]['lv'] = 0;
    }
}

/**
 * End element handler for the XML parser
 *
 * @return void
 */
function XML_RPC_ee($parser_resource, $name)
{
    global $XML_RPC_xh, $XML_RPC_Types, $XML_RPC_String;
    $parser = (int) $parser_resource;

    switch ($name) {
    case 'STRUCT':
    case 'ARRAY':
        if ($XML_RPC_xh[$parser]['cm']
            && substr($XML_RPC_xh[$parser]['st'], -1) == ',')
        {
            $XML_RPC_xh[$parser]['st'] = substr($XML_RPC_xh[$parser]['st'], 0, -1);
        }

        $XML_RPC_xh[$parser]['st'] .= ')';
        $XML_RPC_xh[$parser]['vt'] = strtolower($name);
        $XML_RPC_xh[$parser]['cm']--;
        break;

    case 'NAME':
        $XML_RPC_xh[$parser]['st'] .= $XML_RPC_xh[$parser]['ac'] . "' => ";
        break;

    case 'BOOLEAN':
        // special case here: we translate boolean 1 or 0 into PHP
        // constants true or false
        if ($XML_RPC_xh[$parser]['ac'] == '1') {
            $XML_RPC_xh[$parser]['ac'] = 'true';
        } else {
            $XML_RPC_xh[$parser]['ac'] = 'false';
        }

        $XML_RPC_xh[$parser]['vt'] = strtolower($name);
        // Drop through intentionally.

    case 'I4':
    case 'INT':
    case 'STRING':
    case 'DOUBLE':
    case 'DATETIME.ISO8601':
    case 'BASE64':
        if ($XML_RPC_xh[$parser]['qt'] == 1) {
            // we use double quotes rather than single so backslashification works OK
            $XML_RPC_xh[$parser]['st'] .= '"' . $XML_RPC_xh[$parser]['ac'] . '"';
        } elseif ($XML_RPC_xh[$parser]['qt'] == 2) {
            $XML_RPC_xh[$parser]['st'] .= "base64_decode('"
                                        . $XML_RPC_xh[$parser]['ac'] . "')";
        } elseif ($name == 'BOOLEAN') {
            $XML_RPC_xh[$parser]['st'] .= $XML_RPC_xh[$parser]['ac'];
        } else {
            // we have an I4, INT or a DOUBLE
            // we must check that only 0123456789-.<space> are characters here
            if (!ereg("^[+-]?[0123456789 \t\.]+$", $XML_RPC_xh[$parser]['ac'])) {
                XML_RPC_Base::raiseError('Non-numeric value received in INT or DOUBLE',
                                         XML_RPC_ERROR_NON_NUMERIC_FOUND);
                $XML_RPC_xh[$parser]['st'] .= 'XML_RPC_ERROR_NON_NUMERIC_FOUND';
            } else {
                // it's ok, add it on
                $XML_RPC_xh[$parser]['st'] .= $XML_RPC_xh[$parser]['ac'];
            }
        }

        $XML_RPC_xh[$parser]['ac'] = '';
        $XML_RPC_xh[$parser]['qt'] = 0;
        $XML_RPC_xh[$parser]['lv'] = 3; // indicate we've found a value
        break;

    case 'VALUE':
        // deal with a string value
        if (strlen($XML_RPC_xh[$parser]['ac']) > 0 &&
            $XML_RPC_xh[$parser]['vt'] == $XML_RPC_String) {

            $XML_RPC_xh[$parser]['st'] .= '"' . $XML_RPC_xh[$parser]['ac'] . '"';
        }

        // This if () detects if no scalar was inside <VALUE></VALUE>
        // and pads an empty "".
        if ($XML_RPC_xh[$parser]['st'][strlen($XML_RPC_xh[$parser]['st'])-1] == '(') {
            $XML_RPC_xh[$parser]['st'] .= '""';
        }
        $XML_RPC_xh[$parser]['st'] .= ", '" . $XML_RPC_xh[$parser]['vt'] . "')";
        if ($XML_RPC_xh[$parser]['cm']) {
            $XML_RPC_xh[$parser]['st'] .= ',';
        }
        break;

    case 'MEMBER':
        $XML_RPC_xh[$parser]['ac'] = '';
        $XML_RPC_xh[$parser]['qt'] = 0;
        break;

    case 'DATA':
        $XML_RPC_xh[$parser]['ac'] = '';
        $XML_RPC_xh[$parser]['qt'] = 0;
        break;

    case 'PARAM':
        $XML_RPC_xh[$parser]['params'][] = $XML_RPC_xh[$parser]['st'];
        break;

    case 'METHODNAME':
        $XML_RPC_xh[$parser]['method'] = ereg_replace("^[\n\r\t ]+", '',
                                                      $XML_RPC_xh[$parser]['ac']);
        break;

    case 'BOOLEAN':
        // special case here: we translate boolean 1 or 0 into PHP
        // constants true or false
        if ($XML_RPC_xh[$parser]['ac'] == '1') {
            $XML_RPC_xh[$parser]['ac'] = 'true';
        } else {
            $XML_RPC_xh[$parser]['ac'] = 'false';
        }

        $XML_RPC_xh[$parser]['vt'] = strtolower($name);
    }

    // if it's a valid type name, set the type
    if (isset($XML_RPC_Types[strtolower($name)])) {
        $XML_RPC_xh[$parser]['vt'] = strtolower($name);
    }
}

/**
 * Character data handler for the XML parser
 *
 * @return void
 */
function XML_RPC_cd($parser_resource, $data)
{
    global $XML_RPC_xh, $XML_RPC_backslash;
    $parser = (int) $parser_resource;

    if ($XML_RPC_xh[$parser]['lv'] != 3) {
        // "lookforvalue==3" means that we've found an entire value
        // and should discard any further character data

        if ($XML_RPC_xh[$parser]['lv'] == 1) {
            // if we've found text and we're just in a <value> then
            // turn quoting on, as this will be a string
            $XML_RPC_xh[$parser]['qt'] = 1;
            // and say we've found a value
            $XML_RPC_xh[$parser]['lv'] = 2;
        }

        // replace characters that eval would
        // do special things with
        if (!isset($XML_RPC_xh[$parser]['ac'])) {
            $XML_RPC_xh[$parser]['ac'] = '';
        }
        $XML_RPC_xh[$parser]['ac'] .= str_replace('$', '\$',
            str_replace('"', '\"', str_replace(chr(92),
            $XML_RPC_backslash, $data)));
    }
}

/**
 * Base class
 *
 * This class provides common functions for all of the XML_RPC classes.
 *
 * @category   Web Services
 * @package    XML_RPC
 * @author     Edd Dumbill <edd@usefulinc.com>
 * @author     Stig Bakken <stig@php.net>
 * @author     Martin Jansen <mj@php.net>
 * @copyright  1999-2001 Edd Dumbill
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/XML_RPC
 */
class XML_RPC_Base {

    /**
     * PEAR Error handling
     *
     * @return object  PEAR_Error object
     */
    function raiseError($msg, $code)
    {
        include_once 'PEAR.php';
        if (is_object(@$this)) {
            return PEAR::raiseError(get_class($this) . ': ' . $msg, $code);
        } else {
            return PEAR::raiseError('XML_RPC: ' . $msg, $code);
        }
    }

    /**
     * Tell whether something is a PEAR_Error object
     *
     * @param mixed $value  the item to check
     *
     * @return bool  whether $value is a PEAR_Error object or not
     *
     * @access public
     */
    function isError($value)
    {
        return is_a($value, 'PEAR_Error');
    }
}

/**
 *
 *
 * @category   Web Services
 * @package    XML_RPC
 * @author     Edd Dumbill <edd@usefulinc.com>
 * @author     Stig Bakken <stig@php.net>
 * @author     Martin Jansen <mj@php.net>
 * @author     Daniel Convissor <danielc@php.net>
 * @copyright  1999-2001 Edd Dumbill
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/XML_RPC
 */
class XML_RPC_Client extends XML_RPC_Base {

    /**
     * The path and name of the RPC server script you want the request to go to
     * @var string
     */
    var $path = '';

    /**
     * The name of the remote server to connect to
     * @var string
     */
    var $server = '';

    /**
     * The protocol to use in contacting the remote server
     * @var string
     */
    var $protocol = 'http://';

    /**
     * The port for connecting to the remote server
     *
     * The default is 80 for http:// connections
     * and 443 for https:// and ssl:// connections.
     *
     * @var integer
     */
    var $port = 80;

    /**
     * A user name for accessing the RPC server
     * @var string
     * @see XML_RPC_Client::setCredentials()
     */
    var $username = '';

    /**
     * A password for accessing the RPC server
     * @var string
     * @see XML_RPC_Client::setCredentials()
     */
    var $password = '';

    /**
     * The name of the proxy server to use, if any
     * @var string
     */
    var $proxy = '';

    /**
     * The protocol to use in contacting the proxy server, if any
     * @var string
     */
    var $proxy_protocol = 'http://';

    /**
     * The port for connecting to the proxy server
     *
     * The default is 8080 for http:// connections
     * and 443 for https:// and ssl:// connections.
     *
     * @var integer
     */
    var $proxy_port = 8080;

    /**
     * A user name for accessing the proxy server
     * @var string
     */
    var $proxy_user = '';

    /**
     * A password for accessing the proxy server
     * @var string
     */
    var $proxy_pass = '';

    /**
     * The error number, if any
     * @var integer
     */
    var $errno = 0;

    /**
     * The error message, if any
     * @var string
     */
    var $errstring = '';

    /**
     * The current debug mode (1 = on, 0 = off)
     * @var integer
     */
    var $debug = 0;


    /**
     * Sets the object's properties
     *
     * @param string  $path        the path and name of the RPC server script
     *                              you want the request to go to
     * @param string  $server      the URL of the remote server to connect to.
     *                              If this parameter doesn't specify a
     *                              protocol and $port is 443, ssl:// is
     *                              assumed.
     * @param integer $port        a port for connecting to the remote server.
     *                              Defaults to 80 for http:// connections and
     *                              443 for https:// and ssl:// connections.
     * @param string  $proxy       the URL of the proxy server to use, if any.
     *                              If this parameter doesn't specify a
     *                              protocol and $port is 443, ssl:// is
     *                              assumed.
     * @param integer $proxy_port  a port for connecting to the remote server.
     *                              Defaults to 8080 for http:// connections and
     *                              443 for https:// and ssl:// connections.
     * @param string  $proxy_user  a user name for accessing the proxy server
     * @param string  $proxy_pass  a password for accessing the proxy server
     *
     * @return void
     */
    function XML_RPC_Client($path, $server, $port = 0,
                            $proxy = '', $proxy_port = 0,
                            $proxy_user = '', $proxy_pass = '')
    {
        $this->path       = $path;
        $this->proxy_user = $proxy_user;
        $this->proxy_pass = $proxy_pass;

        preg_match('@^(http://|https://|ssl://)?(.*)$@', $server, $match);
        if ($match[1] == '') {
            if ($port == 443) {
                $this->server   = $match[2];
                $this->protocol = 'ssl://';
                $this->port     = 443;
            } else {
                $this->server = $match[2];
                if ($port) {
                    $this->port = $port;
                }
            }
        } elseif ($match[1] == 'http://') {
            $this->server = $match[2];
            if ($port) {
                $this->port = $port;
            }
        } else {
            $this->server   = $match[2];
            $this->protocol = 'ssl://';
            if ($port) {
                $this->port = $port;
            } else {
                $this->port = 443;
            }
        }

        if ($proxy) {
            preg_match('@^(http://|https://|ssl://)?(.*)$@', $proxy, $match);
            if ($match[1] == '') {
                if ($proxy_port == 443) {
                    $this->proxy          = $match[2];
                    $this->proxy_protocol = 'ssl://';
                    $this->proxy_port     = 443;
                } else {
                    $this->proxy = $match[2];
                    if ($proxy_port) {
                        $this->proxy_port = $proxy_port;
                    }
                }
            } elseif ($match[1] == 'http://') {
                $this->proxy = $match[2];
                if ($proxy_port) {
                    $this->proxy_port = $proxy_port;
                }
            } else {
                $this->proxy          = $match[2];
                $this->proxy_protocol = 'ssl://';
                if ($proxy_port) {
                    $this->proxy_port = $proxy_port;
                } else {
                    $this->proxy_port = 443;
                }
            }
        }
    }

    /**
     * Change the current debug mode
     *
     * @param int $in  where 1 = on, 0 = off
     *
     * @return void
     */
    function setDebug($in)
    {
        if ($in) {
            $this->debug = 1;
        } else {
            $this->debug = 0;
        }
    }

    /**
     * Set username and password properties for connecting to the RPC server
     *
     * @param string $u  the user name
     * @param string $p  the password
     *
     * @return void
     *
     * @see XML_RPC_Client::$username, XML_RPC_Client::$password
     */
    function setCredentials($u, $p)
    {
        $this->username = $u;
        $this->password = $p;
    }

    /**
     * Transmit the RPC request via HTTP 1.0 protocol
     *
     * @param object $msg       the XML_RPC_Message object
     * @param int    $timeout   how many seconds to wait for the request
     *
     * @return object  an XML_RPC_Response object.  0 is returned if any
     *                  problems happen.
     *
     * @see XML_RPC_Message, XML_RPC_Client::XML_RPC_Client(),
     *      XML_RPC_Client::setCredentials()
     */
    function send($msg, $timeout = 0)
    {
        $msg->debug = $this->debug;
        return $this->sendPayloadHTTP10($msg, $this->server, $this->port,
                                        $timeout, $this->username,
                                        $this->password);
    }

    /**
     * Transmit the RPC request via HTTP 1.0 protocol
     *
     * Requests should be sent using XML_RPC_Client send() rather than
     * calling this method directly.
     *
     * @param object $msg       the XML_RPC_Message object
     * @param string $server    the server to send the request to
     * @param int    $port      the server port send the request to
     * @param int    $timeout   how many seconds to wait for the request
     *                           before giving up
     * @param string $username  a user name for accessing the RPC server
     * @param string $password  a password for accessing the RPC server
     *
     * @return object  an XML_RPC_Response object.  0 is returned if any
     *                  problems happen.
     *
     * @see XML_RPC_Client::send()
     */
    function sendPayloadHTTP10($msg, $server, $port, $timeout = 0,
                               $username = '', $password = '')
    {
        /*
         * If we're using a proxy open a socket to the proxy server
         * instead to the xml-rpc server
         */
        if ($this->proxy) {
            if ($this->proxy_protocol == 'http://') {
                $protocol = '';
            } else {
                $protocol = $this->proxy_protocol;
            }
            if ($timeout > 0) {
                $fp = @fsockopen($protocol . $this->proxy, $this->proxy_port,
                                 $this->errno, $this->errstr, $timeout);
            } else {
                $fp = @fsockopen($protocol . $this->proxy, $this->proxy_port,
                                 $this->errno, $this->errstr);
            }
        } else {
            if ($this->protocol == 'http://') {
                $protocol = '';
            } else {
                $protocol = $this->protocol;
            }
            if ($timeout > 0) {
                $fp = @fsockopen($protocol . $server, $port,
                                 $this->errno, $this->errstr, $timeout);
            } else {
                $fp = @fsockopen($protocol . $server, $port,
                                 $this->errno, $this->errstr);
            }
        }

        /*
         * Just raising the error without returning it is strange,
         * but keep it here for backwards compatibility.
         */
        if (!$fp && $this->proxy) {
            $this->raiseError('Connection to proxy server '
                              . $this->proxy . ':' . $this->proxy_port
                              . ' failed. ' . $this->errstr,
                              XML_RPC_ERROR_CONNECTION_FAILED);
            return 0;
        } elseif (!$fp) {
            $this->raiseError('Connection to RPC server '
                              . $server . ':' . $port
                              . ' failed. ' . $this->errstr,
                              XML_RPC_ERROR_CONNECTION_FAILED);
            return 0;
        }

        // Only create the payload if it was not created previously
        if (empty($msg->payload)) {
            $msg->createPayload();
        }

        // thanks to Grant Rauscher <grant7@firstworld.net> for this
        $credentials = '';
        if ($username != '') {
            $credentials = 'Authorization: Basic ' .
                base64_encode($username . ':' . $password) . "\r\n";
        }

        if ($this->proxy) {
            $op = 'POST ' . $this->protocol . $server;
            if ($this->proxy_port) {
                $op .= ':' . $this->port;
            }
        } else {
           $op = 'POST ';
        }

        $op .= $this->path. " HTTP/1.0\r\n" .
               "User-Agent: PEAR XML_RPC\r\n" .
               'Host: ' . $server . "\r\n";
        if ($this->proxy && $this->proxy_user != '') {
            $op .= 'Proxy-Authorization: Basic ' .
                base64_encode($this->proxy_user . ':' . $this->proxy_pass) .
                "\r\n";
        }
        $op .= $credentials .
               "Content-Type: text/xml\r\n" .
               'Content-Length: ' . strlen($msg->payload) . "\r\n\r\n" .
               $msg->payload;

        if (!fputs($fp, $op, strlen($op))) {
            $this->errstr = 'Write error';
            return 0;
        }
        $resp = $msg->parseResponseFile($fp);
        fclose($fp);
        return $resp;
    }
}

/**
 *
 *
 * @category   Web Services
 * @package    XML_RPC
 * @author     Edd Dumbill <edd@usefulinc.com>
 * @author     Stig Bakken <stig@php.net>
 * @author     Martin Jansen <mj@php.net>
 * @copyright  1999-2001 Edd Dumbill
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/XML_RPC
 */
class XML_RPC_Response extends XML_RPC_Base
{
    var $xv;
    var $fn;
    var $fs;
    var $hdrs;

    /**
     * @return void
     */
    function XML_RPC_Response($val, $fcode = 0, $fstr = '')
    {
        if ($fcode != 0) {
            $this->fn = $fcode;
            $this->fs = htmlspecialchars($fstr);
        } else {
            $this->xv = $val;
        }
    }

    /**
     * @return int  the error code
     */
    function faultCode()
    {
        if (isset($this->fn)) {
            return $this->fn;
        } else {
            return 0;
        }
    }

    /**
     * @return string  the error string
     */
    function faultString()
    {
        return $this->fs;
    }

    /**
     * @return mixed  the value
     */
    function value()
    {
        return $this->xv;
    }

    /**
     * @return string  the error message in XML format
     */
    function serialize()
    {
        $rs = "<methodResponse>\n";
        if ($this->fn) {
            $rs .= "<fault>
  <value>
    <struct>
      <member>
        <name>faultCode</name>
        <value><int>" . $this->fn . "</int></value>
      </member>
      <member>
        <name>faultString</name>
        <value><string>" . $this->fs . "</string></value>
      </member>
    </struct>
  </value>
</fault>";
        } else {
            $rs .= "<params>\n<param>\n" . $this->xv->serialize() .
        "</param>\n</params>";
        }
        $rs .= "\n</methodResponse>";
        return $rs;
    }
}

/**
 *
 *
 * @category   Web Services
 * @package    XML_RPC
 * @author     Edd Dumbill <edd@usefulinc.com>
 * @author     Stig Bakken <stig@php.net>
 * @author     Martin Jansen <mj@php.net>
 * @author     Daniel Convissor <danielc@php.net>
 * @copyright  1999-2001 Edd Dumbill
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/XML_RPC
 */
class XML_RPC_Message extends XML_RPC_Base
{
    /**
     * The current debug mode (1 = on, 0 = off)
     * @var integer
     */
    var $debug = 0;

    /**
     * The encoding to be used for outgoing messages
     *
     * Defaults to the value of <var>$GLOBALS['XML_RPC_defencoding']</var>
     *
     * @var string
     * @see XML_RPC_Message::setSendEncoding(),
     *      $GLOBALS['XML_RPC_defencoding'], XML_RPC_Message::xml_header()
     */
    var $send_encoding = '';

    /**
     * The method presently being evaluated
     * @var string
     */
    var $methodname = '';

    /**
     * @var array
     */
    var $params = array();

    /**
     * The XML message being generated
     * @var string
     */
    var $payload = '';

    /**
     * @return void
     */
    function XML_RPC_Message($meth, $pars = 0)
    {
        $this->methodname = $meth;
        if (is_array($pars) && sizeof($pars) > 0) {
            for ($i = 0; $i < sizeof($pars); $i++) {
                $this->addParam($pars[$i]);
            }
        }
    }

    /**
     * Produces the XML declaration including the encoding attribute
     *
     * The encoding is determined by this class' <var>$send_encoding</var>
     * property.  If the <var>$send_encoding</var> property is not set, use
     * <var>$GLOBALS['XML_RPC_defencoding']</var>.
     *
     * @return string  the XML declaration and <methodCall> element
     *
     * @see XML_RPC_Message::setSendEncoding(),
     *      XML_RPC_Message::$send_encoding, $GLOBALS['XML_RPC_defencoding']
     */
    function xml_header()
    {
        global $XML_RPC_defencoding;
        if (!$this->send_encoding) {
            $this->send_encoding = $XML_RPC_defencoding;
        }
        return '<?xml version="1.0" encoding="' . $this->send_encoding . '"?>'
               . "\n<methodCall>\n";
    }

    /**
     * @return string  the closing </methodCall> tag
     */
    function xml_footer()
    {
        return "</methodCall>\n";
    }

    /**
     * @return void
     *
     * @uses XML_RPC_Message::xml_header(), XML_RPC_Message::xml_footer()
     */
    function createPayload()
    {
        $this->payload = $this->xml_header();
        $this->payload .= '<methodName>' . $this->methodname . "</methodName>\n";
        $this->payload .= "<params>\n";
        for ($i = 0; $i < sizeof($this->params); $i++) {
            $p = $this->params[$i];
            $this->payload .= "<param>\n" . $p->serialize() . "</param>\n";
        }
        $this->payload .= "</params>\n";
        $this->payload .= $this->xml_footer();
        $this->payload = ereg_replace("[\r\n]+", "\r\n", $this->payload);
    }

    /**
     * @return string  the name of the method
     */
    function method($meth = '')
    {
        if ($meth != '') {
            $this->methodname = $meth;
        }
        return $this->methodname;
    }

    /**
     * @return string  the payload
     */
    function serialize()
    {
        $this->createPayload();
        return $this->payload;
    }

    /**
     * @return void
     */
    function addParam($par)
    {
        $this->params[] = $par;
    }

    /**
     * @return void
     */
    function getParam($i)
    {
        return $this->params[$i];
    }

    /**
     * @return int  the number of parameters
     */
    function getNumParams()
    {
        return sizeof($this->params);
    }

    /**
     * Sets the XML declaration's encoding attribute
     *
     * @param string $type  the encoding type (ISO-8859-1, UTF-8 or US-ASCII)
     *
     * @return void
     *
     * @see XML_RPC_Message::$send_encoding, XML_RPC_Message::xml_header()
     * @since Method available since Release 1.2.0
     */
    function setSendEncoding($type)
    {
        $this->send_encoding = $type;
    }

    /**
     * Determine the XML's encoding via the encoding attribute
     * in the XML declaration
     *
     * If the encoding parameter is not set or is not ISO-8859-1, UTF-8
     * or US-ASCII, $XML_RPC_defencoding will be returned.
     *
     * @param string $data  the XML that will be parsed
     *
     * @return string  the encoding to be used
     *
     * @link   http://php.net/xml_parser_create
     * @since  Method available since Release 1.2.0
     */
    function getEncoding($data)
    {
        global $XML_RPC_defencoding;

        if (preg_match('/<\?xml[^>]*\s*encoding\s*=\s*[\'"]([^"\']*)[\'"]/i',
                       $data, $match))
        {
            $match[1] = trim(strtoupper($match[1]));
            switch ($match[1]) {
                case 'ISO-8859-1':
                case 'UTF-8':
                case 'US-ASCII':
                    return $match[1];
                    break;

                default:
                    return $XML_RPC_defencoding;
            }
        } else {
            return $XML_RPC_defencoding;
        }
    }

    /**
     * @return object  a new XML_RPC_Response object
     */
    function parseResponseFile($fp)
    {
        $ipd = '';
        while ($data = @fread($fp, 8192)) {
            $ipd .= $data;
        }
        return $this->parseResponse($ipd);
    }

    /**
     * @return object  a new XML_RPC_Response object
     */
    function parseResponse($data = '')
    {
        global $XML_RPC_xh, $XML_RPC_err, $XML_RPC_str, $XML_RPC_defencoding;

        $encoding = $this->getEncoding($data);
        $parser_resource = xml_parser_create($encoding);
        $parser = (int) $parser_resource;

        $XML_RPC_xh[$parser] = array();

        $XML_RPC_xh[$parser]['st'] = '';
        $XML_RPC_xh[$parser]['cm'] = 0;
        $XML_RPC_xh[$parser]['isf'] = 0;
        $XML_RPC_xh[$parser]['ac'] = '';
        $XML_RPC_xh[$parser]['qt'] = '';

        xml_parser_set_option($parser_resource, XML_OPTION_CASE_FOLDING, true);
        xml_set_element_handler($parser_resource, 'XML_RPC_se', 'XML_RPC_ee');
        xml_set_character_data_handler($parser_resource, 'XML_RPC_cd');

        $hdrfnd = 0;
        if ($this->debug) {
            print "<PRE>---GOT---\n";
            print isset($_SERVER['SERVER_PROTOCOL']) ? htmlspecialchars($data) : $data;
            print "\n---END---\n</PRE>";
        }

        // see if we got an HTTP 200 OK, else bomb
        // but only do this if we're using the HTTP protocol.
        if (ereg('^HTTP', $data) &&
            !ereg('^HTTP/[0-9\.]+ 200 ', $data)) {
                $errstr = substr($data, 0, strpos($data, "\n") - 1);
                error_log('HTTP error, got response: ' . $errstr);
                $r = new XML_RPC_Response(0, $XML_RPC_err['http_error'],
                                          $XML_RPC_str['http_error'] . ' (' .
                                          $errstr . ')');
                xml_parser_free($parser_resource);
                return $r;
        }
        // gotta get rid of headers here


        if ((!$hdrfnd) && ($brpos = strpos($data,"\r\n\r\n"))) {
            $XML_RPC_xh[$parser]['ha'] = substr($data, 0, $brpos);
            $data = substr($data, $brpos + 4);
            $hdrfnd = 1;
        }

        /*
         * be tolerant of junk after methodResponse
         * (e.g. javascript automatically inserted by free hosts)
         * thanks to Luca Mariano <luca.mariano@email.it>
         */
        $data = substr($data, 0, strpos($data, "</methodResponse>") + 17);

        if (!xml_parse($parser_resource, $data, sizeof($data))) {
            // thanks to Peter Kocks <peter.kocks@baygate.com>
            if ((xml_get_current_line_number($parser_resource)) == 1) {
                $errstr = 'XML error at line 1, check URL';
            } else {
                $errstr = sprintf('XML error: %s at line %d',
                                  xml_error_string(xml_get_error_code($parser_resource)),
                                  xml_get_current_line_number($parser_resource));
            }
            error_log($errstr);
            $r = new XML_RPC_Response(0, $XML_RPC_err['invalid_return'],
                                      $XML_RPC_str['invalid_return']);
            xml_parser_free($parser_resource);
            return $r;
        }
        xml_parser_free($parser_resource);
        if ($this->debug) {
            print '<PRE>---EVALING---[' .
            strlen($XML_RPC_xh[$parser]['st']) . " chars]---\n" .
            htmlspecialchars($XML_RPC_xh[$parser]['st']) . ";\n---END---</PRE>";
        }
        if (strlen($XML_RPC_xh[$parser]['st']) == 0) {
            // then something odd has happened
            // and it's time to generate a client side error
            // indicating something odd went on
            $r = new XML_RPC_Response(0, $XML_RPC_err['invalid_return'],
                                      $XML_RPC_str['invalid_return']);
        } else {
            eval('$v=' . $XML_RPC_xh[$parser]['st'] . '; $allOK=1;');
            if ($XML_RPC_xh[$parser]['isf']) {
                $f = $v->structmem('faultCode');
                $fs = $v->structmem('faultString');
                $r = new XML_RPC_Response($v, $f->scalarval(),
                                          $fs->scalarval());
            } else {
                $r = new XML_RPC_Response($v);
            }
        }
        $r->hdrs = split("\r?\n", $XML_RPC_xh[$parser]['ha'][1]);
        return $r;
    }
}

/**
 *
 *
 * @category   Web Services
 * @package    XML_RPC
 * @author     Edd Dumbill <edd@usefulinc.com>
 * @author     Stig Bakken <stig@php.net>
 * @author     Martin Jansen <mj@php.net>
 * @copyright  1999-2001 Edd Dumbill
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/XML_RPC
 */
class XML_RPC_Value extends XML_RPC_Base
{
    var $me = array();
    var $mytype = 0;

    /**
     * @return void
     */
    function XML_RPC_Value($val = -1, $type = '')
    {
        global $XML_RPC_Types;
        $this->me = array();
        $this->mytype = 0;
        if ($val != -1 || $type != '') {
            if ($type == '') {
                $type = 'string';
            }
            if (!array_key_exists($type, $XML_RPC_Types)) {
                // XXX
                // need some way to report this error
            } elseif ($XML_RPC_Types[$type] == 1) {
                $this->addScalar($val, $type);
            } elseif ($XML_RPC_Types[$type] == 2) {
                $this->addArray($val);
            } elseif ($XML_RPC_Types[$type] == 3) {
                $this->addStruct($val);
            }
        }
    }

    /**
     * @return int  returns 1 if successful or 0 if there are problems
     */
    function addScalar($val, $type = 'string')
    {
        global $XML_RPC_Types, $XML_RPC_Boolean;

        if ($this->mytype == 1) {
            $this->raiseError('Scalar can have only one value',
                              XML_RPC_ERROR_INVALID_TYPE);
            return 0;
        }
        $typeof = $XML_RPC_Types[$type];
        if ($typeof != 1) {
            $this->raiseError("Not a scalar type (${typeof})",
                              XML_RPC_ERROR_INVALID_TYPE);
            return 0;
        }

        if ($type == $XML_RPC_Boolean) {
            if (strcasecmp($val, 'true') == 0
                || $val == 1
                || ($val == true && strcasecmp($val, 'false')))
            {
                $val = 1;
            } else {
                $val = 0;
            }
        }

        if ($this->mytype == 2) {
            // we're adding to an array here
            $ar = $this->me['array'];
            $ar[] = new XML_RPC_Value($val, $type);
            $this->me['array'] = $ar;
        } else {
            // a scalar, so set the value and remember we're scalar
            $this->me[$type] = $val;
            $this->mytype = $typeof;
        }
        return 1;
    }

    /**
     * @return int  returns 1 if successful or 0 if there are problems
     */
    function addArray($vals)
    {
        global $XML_RPC_Types;
        if ($this->mytype != 0) {
            $this->raiseError(
                    'Already initialized as a [' . $this->kindOf() . ']',
                    XML_RPC_ERROR_ALREADY_INITIALIZED);
            return 0;
        }
        $this->mytype = $XML_RPC_Types['array'];
        $this->me['array'] = $vals;
        return 1;
    }

    /**
     * @return int  returns 1 if successful or 0 if there are problems
     */
    function addStruct($vals)
    {
        global $XML_RPC_Types;
        if ($this->mytype != 0) {
            $this->raiseError(
                    'Already initialized as a [' . $this->kindOf() . ']',
                    XML_RPC_ERROR_ALREADY_INITIALIZED);
            return 0;
        }
        $this->mytype = $XML_RPC_Types['struct'];
        $this->me['struct'] = $vals;
        return 1;
    }

    /**
     * @return void
     */
    function dump($ar)
    {
        reset($ar);
        while (list($key, $val) = each($ar)) {
            echo "$key => $val<br>";
            if ($key == 'array') {
                while (list($key2, $val2) = each($val)) {
                    echo "-- $key2 => $val2<br>";
                }
            }
        }
    }

    /**
     * @return string  the data type of the current value
     */
    function kindOf()
    {
        switch ($this->mytype) {
        case 3:
            return 'struct';

        case 2:
            return 'array';

        case 1:
            return 'scalar';

        default:
            return 'undef';
        }
    }

    /**
     * @return string  the data in XML format
     */
    function serializedata($typ, $val)
    {
        $rs = '';
        global $XML_RPC_Types, $XML_RPC_Base64, $XML_RPC_String, $XML_RPC_Boolean;
        if (!array_key_exists($typ, $XML_RPC_Types)) {
            // XXX
            // need some way to report this error
            return;
        }
        switch ($XML_RPC_Types[$typ]) {
        case 3:
            // struct
            $rs .= "<struct>\n";
            reset($val);
            while (list($key2, $val2) = each($val)) {
                $rs .= "<member><name>${key2}</name>\n";
                $rs .= $this->serializeval($val2);
                $rs .= "</member>\n";
            }
            $rs .= '</struct>';
            break;

        case 2:
            // array
            $rs .= "<array>\n<data>\n";
            for ($i = 0; $i < sizeof($val); $i++) {
                $rs .= $this->serializeval($val[$i]);
            }
            $rs .= "</data>\n</array>";
            break;

        case 1:
            switch ($typ) {
            case $XML_RPC_Base64:
                $rs .= "<${typ}>" . base64_encode($val) . "</${typ}>";
                break;
            case $XML_RPC_Boolean:
                $rs .= "<${typ}>" . ($val ? '1' : '0') . "</${typ}>";
                break;
            case $XML_RPC_String:
		if(is_array($val)) $val = 'array';
                $rs .= "<${typ}>" . htmlspecialchars($val). "</${typ}>";
                break;
            default:
                $rs .= "<${typ}>${val}</${typ}>";
            }
        }
        return $rs;
    }

    /**
     * @return string  the data in XML format
     */
    function serialize()
    {
        return $this->serializeval($this);
    }

    /**
     * @return string  the data in XML format
     */
    function serializeval($o)
    {
        $rs = '';
        $ar = $o->me;
        reset($ar);
        list($typ, $val) = each($ar);
        $rs .= '<value>';
        $rs .= $this->serializedata($typ, $val);
        $rs .= "</value>\n";
        return $rs;
    }

    /**
     * @return mixed  the contents of the element requested
     */
    function structmem($m)
    {
        return $this->me['struct'][$m];
    }

    /**
     * @return void
     */
    function structreset()
    {
        reset($this->me['struct']);
    }

    /**
     * @return  the key/value pair of the struct's current element
     */
    function structeach()
    {
        return each($this->me['struct']);
    }

    /**
     * @return mixed  the current value
     */
    function getval() {
        // UNSTABLE
        global $XML_RPC_BOOLEAN, $XML_RPC_Base64;

        reset($this->me);
        list($a, $b) = each($this->me);

        // contributed by I Sofer, 2001-03-24
        // add support for nested arrays to scalarval
        // i've created a new method here, so as to
        // preserve back compatibility

        if (is_array($b)) {
            foreach ($b as $id => $cont) {
                $b[$id] = $cont->scalarval();
            }
        }

        // add support for structures directly encoding php objects
        if (is_object($b)) {
            $t = get_object_vars($b);
            foreach ($t as $id => $cont) {
                $t[$id] = $cont->scalarval();
            }
            foreach ($t as $id => $cont) {
                eval('$b->'.$id.' = $cont;');
            }
        }

        // end contrib
        return $b;
    }

    /**
     * @return mixed
     */
    function scalarval()
    {
        global $XML_RPC_Boolean, $XML_RPC_Base64;
        reset($this->me);
        list($a, $b) = each($this->me);
        return $b;
    }

    /**
     * @return string
     */
    function scalartyp()
    {
        global $XML_RPC_I4, $XML_RPC_Int;
        reset($this->me);
        list($a, $b) = each($this->me);
        if ($a == $XML_RPC_I4) {
            $a = $XML_RPC_Int;
        }
        return $a;
    }

    /**
     * @return mixed  the struct's current element
     */
    function arraymem($m)
    {
        return $this->me['array'][$m];
    }

    /**
     * @return int  the number of elements in the array
     */
    function arraysize()
    {
        reset($this->me);
        list($a, $b) = each($this->me);
        return sizeof($b);
    }
}

/**
 * Return an ISO8601 encoded string
 *
 * While timezones ought to be supported, the XML-RPC spec says:
 *
 * "Don't assume a timezone. It should be specified by the server in its
 * documentation what assumptions it makes about timezones."
 *
 * This routine always assumes localtime unless $utc is set to 1, in which
 * case UTC is assumed and an adjustment for locale is made when encoding.
 *
 * @return string  the formatted date
 */
function XML_RPC_iso8601_encode($timet, $utc = 0) {
    if (!$utc) {
        $t = strftime('%Y%m%dT%H:%M:%S', $timet);
    } else {
        if (function_exists('gmstrftime')) {
            // gmstrftime doesn't exist in some versions
            // of PHP
            $t = gmstrftime('%Y%m%dT%H:%M:%S', $timet);
        } else {
            $t = strftime('%Y%m%dT%H:%M:%S', $timet - date('Z'));
        }
    }
    return $t;
}

/**
 * Convert a datetime string into a Unix timestamp
 *
 * While timezones ought to be supported, the XML-RPC spec says:
 *
 * "Don't assume a timezone. It should be specified by the server in its
 * documentation what assumptions it makes about timezones."
 *
 * This routine always assumes localtime unless $utc is set to 1, in which
 * case UTC is assumed and an adjustment for locale is made when encoding.
 *
 * @return int  the unix timestamp of the date submitted
 */
function XML_RPC_iso8601_decode($idate, $utc = 0) {
    $t = 0;
    if (ereg('([0-9]{4})([0-9]{2})([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})', $idate, $regs)) {
        if ($utc) {
            $t = gmmktime($regs[4], $regs[5], $regs[6], $regs[2], $regs[3], $regs[1]);
        } else {
            $t = mktime($regs[4], $regs[5], $regs[6], $regs[2], $regs[3], $regs[1]);
        }
    }
    return $t;
}

/**
 * Takes a message in PHP XML_RPC object format and translates it into
 * native PHP types
 *
 * @return mixed
 *
 * @author Dan Libby <dan@libby.com>
 */
function XML_RPC_decode($XML_RPC_val)
{
    $kind = $XML_RPC_val->kindOf();

    if ($kind == 'scalar') {
        return $XML_RPC_val->scalarval();

    } elseif ($kind == 'array') {
        $size = $XML_RPC_val->arraysize();
        $arr = array();
        for ($i = 0; $i < $size; $i++) {
            $arr[] = XML_RPC_decode($XML_RPC_val->arraymem($i));
        }
        return $arr;

    } elseif ($kind == 'struct') {
        $XML_RPC_val->structreset();
        $arr = array();
        while (list($key, $value) = $XML_RPC_val->structeach()) {
            $arr[$key] = XML_RPC_decode($value);
        }
        return $arr;
    }
}

/**
 * Takes native php types and encodes them into XML_RPC PHP object format
 *
 * Feature creep -- could support more types via optional type argument.
 *
 * @return string
 *
 * @author Dan Libby <dan@libby.com>
 */
function XML_RPC_encode($php_val) {
    global $XML_RPC_Boolean, $XML_RPC_Int, $XML_RPC_Double, $XML_RPC_String,
            $XML_RPC_Array, $XML_RPC_Struct;

    $type = gettype($php_val);
    $XML_RPC_val = new XML_RPC_Value;

    switch ($type) {
    case 'array':
        if (empty($php_val)) {
            $XML_RPC_val->addArray($php_val);
            break;
        }
        $tmp = array_diff(array_keys($php_val), range(0, count($php_val)-1));
        if (empty($tmp)) {
           $arr = array();
           foreach ($php_val as $k => $v) {
               $arr[$k] = XML_RPC_encode($v);
           }
           $XML_RPC_val->addArray($arr);
           break;
        }
        // fall though if it's not an enumerated array

    case 'object':
        $arr = array();
        foreach ($php_val as $k => $v) {
            $arr[$k] = XML_RPC_encode($v);
        }
        $XML_RPC_val->addStruct($arr);
        break;

    case 'integer':
        $XML_RPC_val->addScalar($php_val, $XML_RPC_Int);
        break;

    case 'double':
        $XML_RPC_val->addScalar($php_val, $XML_RPC_Double);
        break;

    case 'string':
    case 'NULL':
        $XML_RPC_val->addScalar($php_val, $XML_RPC_String);
        break;

    case 'boolean':
        // Add support for encoding/decoding of booleans, since they
        // are supported in PHP
        // by <G_Giunta_2001-02-29>
        $XML_RPC_val->addScalar($php_val, $XML_RPC_Boolean);
        break;

    case 'unknown type':
    default:
        $XML_RPC_val = false;
    }
    return $XML_RPC_val;
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */

?>
