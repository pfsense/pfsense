<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

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
 * @copyright  1999-2001 Edd Dumbill
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/XML_RPC
 */


/**
 * Pull in the XML_RPC class
 */
require_once 'xmlrpc_client.php';


/**
 * listMethods: either a string, or nothing
 * @global array $GLOBALS['XML_RPC_Server_listMethods_sig']
 */
$GLOBALS['XML_RPC_Server_listMethods_sig'] = array(
    array($GLOBALS['XML_RPC_Array'],
          $GLOBALS['XML_RPC_String']
    ),
    array($GLOBALS['XML_RPC_Array'])
);

/**
 * @global string $GLOBALS['XML_RPC_Server_listMethods_doc']
 */
$GLOBALS['XML_RPC_Server_listMethods_doc'] = 'This method lists all the'
        . ' methods that the XML-RPC server knows how to dispatch';

/**
 * @global array $GLOBALS['XML_RPC_Server_methodSignature_sig']
 */
$GLOBALS['XML_RPC_Server_methodSignature_sig'] = array(
    array($GLOBALS['XML_RPC_Array'],
          $GLOBALS['XML_RPC_String']
    )
);

/**
 * @global string $GLOBALS['XML_RPC_Server_methodSignature_doc']
 */
$GLOBALS['XML_RPC_Server_methodSignature_doc'] = 'Returns an array of known'
        . ' signatures (an array of arrays) for the method name passed. If'
        . ' no signatures are known, returns a none-array (test for type !='
        . ' array to detect missing signature)';

/**
 * @global array $GLOBALS['XML_RPC_Server_methodHelp_sig']
 */
$GLOBALS['XML_RPC_Server_methodHelp_sig'] = array(
    array($GLOBALS['XML_RPC_String'],
          $GLOBALS['XML_RPC_String']
    )
);

/**
 * @global string $GLOBALS['XML_RPC_Server_methodHelp_doc']
 */
$GLOBALS['XML_RPC_Server_methodHelp_doc'] = 'Returns help text if defined'
        . ' for the method passed, otherwise returns an empty string';

/**
 * @global array $GLOBALS['XML_RPC_Server_dmap']
 */
$GLOBALS['XML_RPC_Server_dmap'] = array(
    'system.listMethods' => array(
        'function'  => 'XML_RPC_Server_listMethods',
        'signature' => $GLOBALS['XML_RPC_Server_listMethods_sig'],
        'docstring' => $GLOBALS['XML_RPC_Server_listMethods_doc']
    ),
    'system.methodHelp' => array(
        'function'  => 'XML_RPC_Server_methodHelp',
        'signature' => $GLOBALS['XML_RPC_Server_methodHelp_sig'],
        'docstring' => $GLOBALS['XML_RPC_Server_methodHelp_doc']
    ),
    'system.methodSignature' => array(
        'function'  => 'XML_RPC_Server_methodSignature',
        'signature' => $GLOBALS['XML_RPC_Server_methodSignature_sig'],
        'docstring' => $GLOBALS['XML_RPC_Server_methodSignature_doc']
    )
);

/**
 * @global string $GLOBALS['XML_RPC_Server_debuginfo']
 */
$GLOBALS['XML_RPC_Server_debuginfo'] = '';


/**
 * Lists all the methods that the XML-RPC server knows how to dispatch
 *
 * @return object  a new XML_RPC_Response object
 */
function XML_RPC_Server_listMethods($server, $m)
{
    global $XML_RPC_err, $XML_RPC_str, $XML_RPC_Server_dmap;

    $v = new XML_RPC_Value();
    $dmap = $server->dmap;
    $outAr = array();
    for (reset($dmap); list($key, $val) = each($dmap); ) {
        $outAr[] = new XML_RPC_Value($key, 'string');
    }
    $dmap = $XML_RPC_Server_dmap;
    for (reset($dmap); list($key, $val) = each($dmap); ) {
        $outAr[] = new XML_RPC_Value($key, 'string');
    }
    $v->addArray($outAr);
    return new XML_RPC_Response($v);
}

/**
 * Returns an array of known signatures (an array of arrays)
 * for the given method
 *
 * If no signatures are known, returns a none-array
 * (test for type != array to detect missing signature)
 *
 * @return object  a new XML_RPC_Response object
 */
function XML_RPC_Server_methodSignature($server, $m)
{
    global $XML_RPC_err, $XML_RPC_str, $XML_RPC_Server_dmap;

    $methName = $m->getParam(0);
    $methName = $methName->scalarval();
    if (strpos($methName, 'system.') === 0) {
        $dmap = $XML_RPC_Server_dmap;
        $sysCall = 1;
    } else {
        $dmap = $server->dmap;
        $sysCall = 0;
    }
    //  print "<!-- ${methName} -->\n";
    if (isset($dmap[$methName])) {
        if ($dmap[$methName]['signature']) {
            $sigs = array();
            $thesigs = $dmap[$methName]['signature'];
            for ($i = 0; $i < sizeof($thesigs); $i++) {
                $cursig = array();
                $inSig = $thesigs[$i];
                for ($j = 0; $j < sizeof($inSig); $j++) {
                    $cursig[] = new XML_RPC_Value($inSig[$j], 'string');
                }
                $sigs[] = new XML_RPC_Value($cursig, 'array');
            }
            $r = new XML_RPC_Response(new XML_RPC_Value($sigs, 'array'));
        } else {
            $r = new XML_RPC_Response(new XML_RPC_Value('undef', 'string'));
        }
    } else {
        $r = new XML_RPC_Response(0, $XML_RPC_err['introspect_unknown'],
                                  $XML_RPC_str['introspect_unknown']);
    }
    return $r;
}

/**
 * Returns help text if defined for the method passed, otherwise returns
 * an empty string
 *
 * @return object  a new XML_RPC_Response object
 */
function XML_RPC_Server_methodHelp($server, $m)
{
    global $XML_RPC_err, $XML_RPC_str, $XML_RPC_Server_dmap;

    $methName = $m->getParam(0);
    $methName = $methName->scalarval();
    if (strpos($methName, 'system.') === 0) {
        $dmap = $XML_RPC_Server_dmap;
        $sysCall = 1;
    } else {
        $dmap = $server->dmap;
        $sysCall = 0;
    }
    //  print "<!-- ${methName} -->\n";
    if (isset($dmap[$methName])) {
        if ($dmap[$methName]['docstring']) {
            $r = new XML_RPC_Response(new XML_RPC_Value($dmap[$methName]['docstring']),
                                                        'string');
        } else {
            $r = new XML_RPC_Response(new XML_RPC_Value('', 'string'));
        }
    } else {
        $r = new XML_RPC_Response(0, $XML_RPC_err['introspect_unknown'],
                                     $XML_RPC_str['introspect_unknown']);
    }
    return $r;
}

/**
 * @return void
 */
function XML_RPC_Server_debugmsg($m)
{
    global $XML_RPC_Server_debuginfo;
    $XML_RPC_Server_debuginfo = $XML_RPC_Server_debuginfo . $m . "\n";
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
class XML_RPC_Server
{
    var $dmap = array();
    var $encoding = '';
    var $debug = 0;

    /**
     * @return void
     */
    function XML_RPC_Server($dispMap, $serviceNow = 1, $debug = 0)
    {
        global $HTTP_RAW_POST_DATA;

        if ($debug) {
            $this->debug = 1;
        } else {
            $this->debug = 0;
        }

        // dispMap is a despatch array of methods
        // mapped to function names and signatures
        // if a method
        // doesn't appear in the map then an unknown
        // method error is generated
        $this->dmap = $dispMap;
        if ($serviceNow) {
            $this->service();
        }
    }

    /**
     * @return string  the debug information if debug debug mode is on
     */
    function serializeDebug()
    {
        global $XML_RPC_Server_debuginfo, $HTTP_RAW_POST_DATA;

        if ($this->debug) {
            XML_RPC_Server_debugmsg('vvv POST DATA RECEIVED BY SERVER vvv' . "\n"
                                    . $HTTP_RAW_POST_DATA
                                    . "\n" . '^^^ END POST DATA ^^^');
        }

        if ($XML_RPC_Server_debuginfo != '') {
            return "<!-- PEAR XML_RPC SERVER DEBUG INFO:\n\n"
                   . preg_replace('/-(?=-)/', '- ', $XML_RPC_Server_debuginfo)
                   . "-->\n";
        } else {
            return '';
        }
    }

    /**
     * Print out the result
     *
     * The encoding and content-type are determined by
     * XML_RPC_Message::getEncoding()
     *
     * @return void
     *
     * @see XML_RPC_Message::getEncoding()
     */
    function service()
    {
        $r = $this->parseRequest();
        $payload = '<?xml version="1.0" encoding="'
                 . $this->encoding . '"?>' . "\n"
                 . $this->serializeDebug()
                 . $r->serialize();
        header('Content-Length: ' . strlen($payload));
        header('Content-Type: text/xml; charset=' . $this->encoding);
        print $payload;
    }

    /**
     * @return array
     */
    function verifySignature($in, $sig)
    {
        for ($i = 0; $i < sizeof($sig); $i++) {
            // check each possible signature in turn
            $cursig = $sig[$i];
            if (sizeof($cursig) == $in->getNumParams() + 1) {
                $itsOK = 1;
                for ($n = 0; $n < $in->getNumParams(); $n++) {
                    $p = $in->getParam($n);
                    // print "<!-- $p -->\n";
                    if ($p->kindOf() == 'scalar') {
                        $pt = $p->scalartyp();
                    } else {
                        $pt = $p->kindOf();
                    }
                    // $n+1 as first type of sig is return type
                    if ($pt != $cursig[$n+1]) {
                        $itsOK = 0;
                        $pno = $n+1;
                        $wanted = $cursig[$n+1];
                        $got = $pt;
                        break;
                    }
                }
                if ($itsOK) {
                    return array(1);
                }
            }
        }
        return array(0, "Wanted ${wanted}, got ${got} at param ${pno})");
    }

    /**
     * @return object  a new XML_RPC_Response object
     */
    function parseRequest($data = '')
    {
        global $XML_RPC_xh, $HTTP_RAW_POST_DATA,
                $XML_RPC_err, $XML_RPC_str, $XML_RPC_errxml,
                $XML_RPC_defencoding, $XML_RPC_Server_dmap;

        if ($data == '') {
            $data = $HTTP_RAW_POST_DATA;
        }

        $this->encoding = XML_RPC_Message::getEncoding($data);
        $parser_resource = xml_parser_create($this->encoding);
        $parser = (int) $parser_resource;

        $XML_RPC_xh[$parser] = array();
        $XML_RPC_xh[$parser]['st']     = '';
        $XML_RPC_xh[$parser]['cm']     = 0;
        $XML_RPC_xh[$parser]['isf']    = 0;
        $XML_RPC_xh[$parser]['params'] = array();
        $XML_RPC_xh[$parser]['method'] = '';

        $plist = '';

        // decompose incoming XML into request structure

        xml_parser_set_option($parser_resource, XML_OPTION_CASE_FOLDING, true);
        xml_set_element_handler($parser_resource, 'XML_RPC_se', 'XML_RPC_ee');
        xml_set_character_data_handler($parser_resource, 'XML_RPC_cd');
        if (!xml_parse($parser_resource, $data, 1)) {
            // return XML error as a faultCode
            $r = new XML_RPC_Response(0,
                                      $XML_RPC_errxml+xml_get_error_code($parser_resource),
                                      sprintf('XML error: %s at line %d',
                                              xml_error_string(xml_get_error_code($parser_resource)),
                                              xml_get_current_line_number($parser_resource)));
            xml_parser_free($parser_resource);
        } else {
            xml_parser_free($parser_resource);
            $m = new XML_RPC_Message($XML_RPC_xh[$parser]['method']);
            // now add parameters in
            for ($i = 0; $i < sizeof($XML_RPC_xh[$parser]['params']); $i++) {
                // print '<!-- ' . $XML_RPC_xh[$parser]['params'][$i]. "-->\n";
                $plist .= "$i - " . $XML_RPC_xh[$parser]['params'][$i] . " \n";
                eval('$m->addParam(' . $XML_RPC_xh[$parser]['params'][$i] . ');');
            }
            XML_RPC_Server_debugmsg($plist);

            // now to deal with the method
            $methName = $XML_RPC_xh[$parser]['method'];
            if (strpos($methName, 'system.') === 0) {
                $dmap = $XML_RPC_Server_dmap;
                $sysCall = 1;
            } else {
                $dmap = $this->dmap;
                $sysCall = 0;
            }

            if (isset($dmap[$methName]['function'])
                && is_string($dmap[$methName]['function'])
                && strpos($dmap[$methName]['function'], '::') !== false)
            {
                $dmap[$methName]['function'] =
                        explode('::', $dmap[$methName]['function']);
            }

            if (isset($dmap[$methName]['function'])
                && is_callable($dmap[$methName]['function']))
            {
                // dispatch if exists
                if (isset($dmap[$methName]['signature'])) {
                    $sr = $this->verifySignature($m,
                                                 $dmap[$methName]['signature'] );
                }
                if ( (!isset($dmap[$methName]['signature'])) || $sr[0]) {
                    // if no signature or correct signature
                    if ($sysCall) {
                        $r = call_user_func($dmap[$methName]['function'], $this, $m);
                    } else {
                        $r = call_user_func($dmap[$methName]['function'], $m);
                    }
                } else {
                    $r = new XML_RPC_Response(0, $XML_RPC_err['incorrect_params'],
                                              $XML_RPC_str['incorrect_params']
                                              . ': ' . $sr[1]);
                }
            } else {
                // else prepare error response
                $r = new XML_RPC_Response(0, $XML_RPC_err['unknown_method'],
                                          $XML_RPC_str['unknown_method']);
            }
        }
        return $r;
    }

    /**
     * Echos back the input packet as a string value
     *
     * @return void
     *
     * Useful for debugging.
     */
    function echoInput() {
        global $HTTP_RAW_POST_DATA;

        $r = new XML_RPC_Response(0);
        $r->xv = new XML_RPC_Value("'Aha said I: '" . $HTTP_RAW_POST_DATA, 'string');
        print $r->serialize();
    }
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */

?>
