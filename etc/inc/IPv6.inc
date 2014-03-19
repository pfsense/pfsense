<?php

/*
       pfSense_MODULE: utils
*/

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * This file contains the implementation of the Net_IPv6 class
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to the New BSD license, that is
 * available through the world-wide-web at 
 * http://www.opensource.org/licenses/bsd-license.php
 * If you did not receive a copy of the new BSDlicense and are unable
 * to obtain it through the world-wide-web, please send a note to
 * license@php.net so we can mail you a copy immediately
 *
 * @category  Net
 * @package   Net_IPv6
 * @author    Alexander Merz <alexander.merz@web.de>
 * @copyright 2003-2005 The PHP Group
 * @license   BSD License http://www.opensource.org/licenses/bsd-license.php
 * @version   CVS: $Id$
 * @link      http://pear.php.net/package/Net_IPv6
 */

// {{{ constants

/**
 * Error message if netmask bits was not found
 * @see isInNetmask
 */
define("NET_IPV6_NO_NETMASK_MSG", "Netmask length not found");

/**
 * Error code if netmask bits was not found
 * @see isInNetmask
 */
define("NET_IPV6_NO_NETMASK", 10);

/**
 * Address Type: Unassigned (RFC 1884, Section 2.3)
 * @see getAddressType()
 */
define("NET_IPV6_UNASSIGNED", 1);

/**
 * Address Type: Reserved (RFC 1884, Section 2.3)
 * @see getAddressType()
 */
define("NET_IPV6_RESERVED", 11);

/**
 * Address Type: Reserved for NSAP Allocation (RFC 1884, Section 2.3)
 * @see getAddressType()
 */
define("NET_IPV6_RESERVED_NSAP", 12);

/**
 * Address Type: Reserved for IPX Allocation (RFC 1884, Section 2.3)
 * @see getAddressType()
 */
define("NET_IPV6_RESERVED_IPX", 13);

/**
 * Address Type: Reserved for Geographic-Based Unicast Addresses 
 * (RFC 1884, Section 2.3)
 * @see getAddressType()
 */
define("NET_IPV6_RESERVED_UNICAST_GEOGRAPHIC", 14);

/**
 * Address Type: Provider-Based Unicast Address (RFC 1884, Section 2.3)
 * @see getAddressType()
 */
define("NET_IPV6_UNICAST_PROVIDER", 22);

/**
 * Address Type: Multicast Addresses (RFC 1884, Section 2.3)
 * @see getAddressType()
 */
define("NET_IPV6_MULTICAST", 31);

/**
 * Address Type: Link Local Use Addresses (RFC 1884, Section 2.3)
 * @see getAddressType()
 */
define("NET_IPV6_LOCAL_LINK", 42);

/**
 * Address Type: Link Local Use Addresses (RFC 1884, Section 2.3)
 * @see getAddressType()
 */
define("NET_IPV6_LOCAL_SITE", 43);

/**
 * Address Type: Address range to embedded IPv4 ip in an IPv6 address (RFC 4291, Section 2.5.5)
 * @see getAddressType()
 */
define("NET_IPV6_IPV4MAPPING", 51);

/**
 * Address Type: Unspecified (RFC 4291, Section 2.5.2)
 * @see getAddressType()
 */
define("NET_IPV6_UNSPECIFIED", 52);

/**
 * Address Type: Unspecified (RFC 4291, Section 2.5.3)
 * @see getAddressType()
 */
define("NET_IPV6_LOOPBACK", 53);

/**
 * Address Type: address can not assigned to a specific type
 * @see getAddressType()
 */
define("NET_IPV6_UNKNOWN_TYPE", 1001);

// }}}
// {{{ Net_IPv6

/**
 * Class to validate and to work with IPv6 addresses.
 *
 * @category  Net
 * @package   Net_IPv6
 * @author    Alexander Merz <alexander.merz@web.de>
 * @author    <elfrink at introweb dot nl>
 * @author    Josh Peck <jmp at joshpeck dot org>
 * @copyright 2003-2010 The PHP Group
 * @license   BSD License http://www.opensource.org/licenses/bsd-license.php
 * @version   Release: 1.1.0RC5
 * @link      http://pear.php.net/package/Net_IPv6
 */
class Net_IPv6
{

    // {{{ separate()
    /**
     * Separates an IPv6 address into the address and a prefix length part
     *
     * @param String $ip the (compressed) IP as Hex representation
     *
     * @return Array the first element is the IP, the second the prefix length
     * @since  1.2.0
     * @access public
     * @static     
     */
    function separate($ip) 
    {
        
        $addr = $ip;
        $spec = '';

        if(false === strrpos($ip, '/')) {

            return array($addr, $spec);

        }

        $elements = explode('/', $ip);

        if(2 == count($elements)) {

            $addr = $elements[0];
            $spec = $elements[1];

        }

        return array($addr, $spec);

    }
    // }}}

    // {{{ removeNetmaskSpec()

    /**
     * Removes a possible existing prefix length/ netmask specification at an IP address.
     *
     * @param String $ip the (compressed) IP as Hex representation
     *
     * @return String the IP without netmask length
     * @since  1.1.0
     * @access public
     * @static
     */
    function removeNetmaskSpec($ip)
    {

        $elements = Net_IPv6::separate($ip);

        return $elements[0];

    }
    // }}}
    // {{{ removePrefixLength()

    /**
     * Tests for a prefix length specification in the address
     * and removes the prefix length, if exists
     *
     * The method is technically identical to removeNetmaskSpec() and 
     * will be dropped in a future release.
     *
     * @param String $ip a valid ipv6 address
     *
     * @return String the address without a prefix length
     * @access public
     * @static
     * @see removeNetmaskSpec()
     * @deprecated
     */
    function removePrefixLength($ip)
    {
        $pos = strrpos($ip, '/');

        if (false !== $pos) {

            return substr($ip, 0, $pos);

        }

        return $ip;
    }

    // }}}
    // {{{ getNetmaskSpec()

    /**
     * Returns a possible existing prefix length/netmask specification on an IP address.
     *
     * @param String $ip the (compressed) IP as Hex representation
     *
     * @return String the netmask spec
     * @since  1.1.0
     * @access public
     * @static
     */
    function getNetmaskSpec($ip) 
    {

        $elements = Net_IPv6::separate($ip);

        return $elements[1];

    }

    // }}}
    // {{{ getPrefixLength()

    /**
     * Tests for a prefix length specification in the address
     * and returns the prefix length, if exists
     *
     * The method is technically identical to getNetmaskSpec() and 
     * will be dropped in a future release.
     *
     * @param String $ip a valid ipv6 address
     *
     * @return Mixed the prefix as String or false, if no prefix was found
     * @access public
     * @static
     * @deprecated
     */
    function getPrefixLength($ip) 
    {
        if (preg_match("/^([0-9a-fA-F:]{2,39})\/(\d{1,3})*$/", 
                        $ip, $matches)) {

            return $matches[2];

        } else {

            return false;

        }

    }

    // }}}
    // {{{ getNetmask()

    /**
     * Calculates the network prefix based on the netmask bits.
     *
     * @param String $ip   the (compressed) IP in Hex format
     * @param int    $bits if the number of netmask bits is not part of the IP
     *                     you must provide the number of bits
     *
     * @return String the network prefix
     * @since  1.1.0
     * @access public
     * @static
     */
    function getNetmask($ip, $bits = null)
    {
        if (null==$bits) {

            $elements = explode('/', $ip);

            if (2 == count($elements)) {

                $addr = $elements[0];
                $bits = $elements[1];

            } else {

                include_once 'PEAR.inc';

                return PEAR::raiseError(NET_IPV6_NO_NETMASK_MSG,
                                        NET_IPV6_NO_NETMASK);
            }

        } else {

            $addr = $ip;

        }

        $addr       = Net_IPv6::uncompress($addr);
        $binNetmask = str_repeat('1', $bits).str_repeat('0', 128 - $bits);

        return Net_IPv6::_bin2Ip(Net_IPv6::_ip2Bin($addr) & $binNetmask);
    }

    // }}}
    // {{{ isInNetmask()

    /**
     * Checks if an (compressed) IP is in a specific address space.
     *
     * If the IP does not contain the number of netmask bits (F8000::FFFF/16)
     * then you have to use the $bits parameter.
     *
     * @param String $ip      the IP to check (eg. F800::FFFF)
     * @param String $netmask the netmask (eg F800::)
     * @param int    $bits    the number of netmask bits to compare,
     *                        if not given in $ip
     *
     * @return boolean true if $ip is in the netmask
     * @since  1.1.0
     * @access public
     * @static
     */
    function isInNetmask($ip, $netmask, $bits=null)
    {
        // try to get the bit count

        if (null == $bits) {

            $elements = explode('/', $ip);

            if (2 == count($elements)) {

                $ip   = $elements[0];
                $bits = $elements[1];

            } else if (null == $bits) {

                $elements = explode('/', $netmask);

                if (2 == count($elements)) {

                     $netmask = $elements[0];
                     $bits    = $elements[1];

                }

                if (null == $bits) {

                    include_once 'PEAR.inc';
                    return PEAR::raiseError(NET_IPV6_NO_NETMASK_MSG,
                                            NET_IPV6_NO_NETMASK);

                }

            }

        }

        $binIp      = Net_IPv6::_ip2Bin(Net_IPv6::removeNetmaskSpec($ip));
        $binNetmask = Net_IPv6::_ip2Bin(Net_IPv6::removeNetmaskSpec($netmask));

        if (null != $bits
            && "" != $bits
            && 0 == strncmp($binNetmask, $binIp, $bits)) {

            return true;

        }

        return false;
    }

    // }}}
    // {{{ getAddressType()

    /**
     * Returns the type of an IPv6 address.
     *
     * RFC 2373, Section 2.3 describes several types of addresses in
     * the IPv6 address space.
     * Several address types are markers for reserved spaces and as
     * a consequence are subject to change.
     *
     * @param String $ip the IP address in Hex format,
     *                    compressed IPs are allowed
     *
     * @return int one of the address type constants
     * @access public
     * @since  1.1.0
     * @static
     *
     * @see    NET_IPV6_UNASSIGNED
     * @see    NET_IPV6_RESERVED
     * @see    NET_IPV6_RESERVED_NSAP
     * @see    NET_IPV6_RESERVED_IPX
     * @see    NET_IPV6_RESERVED_UNICAST_GEOGRAPHIC
     * @see    NET_IPV6_UNICAST_PROVIDER
     * @see    NET_IPV6_MULTICAST
     * @see    NET_IPV6_LOCAL_LINK
     * @see    NET_IPV6_LOCAL_SITE
     * @see    NET_IPV6_IPV4MAPPING  
     * @see    NET_IPV6_UNSPECIFIED  
     * @see    NET_IPV6_LOOPBACK  
     * @see    NET_IPV6_UNKNOWN_TYPE
     */
    function getAddressType($ip) 
    {
        $ip    = Net_IPv6::removeNetmaskSpec($ip);
        $binip = Net_IPv6::_ip2Bin($ip);

        if(0 == strncmp(str_repeat('0', 128), $binip, 128)) { // ::/128

            return NET_IPV6_UNSPECIFIED;

        } else if(0 == strncmp(str_repeat('0', 127).'1', $binip, 128)) { // ::/128

            return NET_IPV6_LOOPBACK;

        } else if (0 == strncmp(str_repeat('0', 80).str_repeat('1', 16), $binip, 96)) { // ::ffff/96

            return NET_IPV6_IPV4MAPPING; 

        } else if (0 == strncmp('1111111010', $binip, 10)) {

            return NET_IPV6_LOCAL_LINK;

        } else if (0 == strncmp('1111111011', $binip, 10)) {

            return NET_IPV6_LOCAL_SITE;

        } else if (0 == strncmp('111111100', $binip, 9)) {

            return NET_IPV6_UNASSIGNED;

        } else if (0 == strncmp('11111111', $binip, 8)) {

            return NET_IPV6_MULTICAST;

        } else if (0 == strncmp('00000000', $binip, 8)) { 

            return NET_IPV6_RESERVED;

        } else if (0 == strncmp('00000001', $binip, 8)
                    || 0 == strncmp('1111110', $binip, 7)) {

            return NET_IPV6_UNASSIGNED;

        } else if (0 == strncmp('0000001', $binip, 7)) {

            return NET_IPV6_RESERVED_NSAP;

        } else if (0 == strncmp('0000010', $binip, 7)) {

            return NET_IPV6_RESERVED_IPX;

        } else if (0 == strncmp('0000011', $binip, 7) ||
                    0 == strncmp('111110', $binip, 6) ||
                    0 == strncmp('11110', $binip, 5) ||
                    0 == strncmp('00001', $binip, 5) ||
                    0 == strncmp('1110', $binip, 4) ||
                    0 == strncmp('0001', $binip, 4) ||
                    0 == strncmp('001', $binip, 3) ||
                    0 == strncmp('011', $binip, 3) ||
                    0 == strncmp('101', $binip, 3) ||
                    0 == strncmp('110', $binip, 3)) {

            return NET_IPV6_UNASSIGNED;

        } else if (0 == strncmp('010', $binip, 3)) {

            return NET_IPV6_UNICAST_PROVIDER;

        } else if (0 == strncmp('100', $binip, 3)) {

            return NET_IPV6_RESERVED_UNICAST_GEOGRAPHIC;

        }

        return NET_IPV6_UNKNOWN_TYPE;
    }

    // }}}
    // {{{ Uncompress()

    /**
     * Uncompresses an IPv6 address
     *
     * RFC 2373 allows you to compress zeros in an address to '::'. This
     * function expects a valid IPv6 address and expands the '::' to
     * the required zeros.
     *
     * Example:  FF01::101  ->  FF01:0:0:0:0:0:0:101
     *           ::1        ->  0:0:0:0:0:0:0:1
     *
     * Note: You can also pass an invalid IPv6 address (usually as part of the process
     * of validation by checkIPv6, which will validate the return string).
     * This function will insert the "0" between "::" even if this results in too many
     * numbers in total. It is NOT the purpose of this function to validate your input.
     *
     * Example of calling with invalid input: 1::2:3:4:5:6:7:8:9 -> 1:0:2:3:4:5:6:7:8:9
     *
     * @param String $ip a (possibly) valid IPv6-address (hex format)
     * @param Boolean $leadingZeros if true, leading zeros are added to each 
     *                              block of the address 
     *                              (FF01::101  ->  
     *                               FF01:0000:0000:0000:0000:0000:0000:0101) 
     *
     * @return String the uncompressed IPv6-address (hex format)
     * @access public
     * @see Compress()
     * @static
     * @author Pascal Uhlmann
     */
    function uncompress($ip, $leadingZeros = false)
    {

        $prefix = Net_IPv6::getPrefixLength($ip);

        if (false === $prefix) {

            $prefix = '';

        } else {

            $ip     = Net_IPv6::removePrefixLength($ip);
            $prefix = '/'.$prefix;

        }

        $netmask = Net_IPv6::getNetmaskSpec($ip);
        $uip     = Net_IPv6::removeNetmaskSpec($ip);

        $c1 = -1;
        $c2 = -1;

        if (false !== strpos($uip, '::') ) {

            list($ip1, $ip2) = explode('::', $uip);

            if ("" == $ip1) {

                $c1 = -1;

            } else {

                $pos = 0;

                if (0 < ($pos = substr_count($ip1, ':'))) {

                    $c1 = $pos;

                } else {

                    $c1 = 0;

                }
            }
            if ("" == $ip2) {

                $c2 = -1;

            } else {

                $pos = 0;

                if (0 < ($pos = substr_count($ip2, ':'))) {

                    $c2 = $pos;

                } else {

                    $c2 = 0;

                }

            }

            if (strstr($ip2, '.')) {

                $c2++;

            }
            if (-1 == $c1 && -1 == $c2) { // ::

                $uip = "0:0:0:0:0:0:0:0";

            } else if (-1 == $c1) {              // ::xxx

                $fill = str_repeat('0:', 7-$c2);
                $uip  = str_replace('::', $fill, $uip);

            } else if (-1 == $c2) {              // xxx::

                $fill = str_repeat(':0', 7-$c1);
                $uip  = str_replace('::', $fill, $uip);

            } else {                          // xxx::xxx

                $fill = str_repeat(':0:', max(1, 6-$c2-$c1));
                $uip  = str_replace('::', $fill, $uip);
                $uip  = str_replace('::', ':', $uip);

            }
        }

        if(true == $leadingZeros) {
            
            $uipT    = array();
            $uiparts = explode(':', $uip);

            foreach($uiparts as $p) {

                $uipT[] = sprintf('%04s', $p);
            
            }

            $uip = implode(':', $uipT);
        }

        if ('' != $netmask) {

                $uip = $uip.'/'.$netmask;

        }

        return $uip.$prefix;
    }

    // }}}
    // {{{ Compress()

    /**
     * Compresses an IPv6 address
     *
     * RFC 2373 allows you to compress zeros in an address to '::'. This
     * function expects a valid IPv6 address and compresses successive zeros
     * to '::'
     *
     * Example:  FF01:0:0:0:0:0:0:101   -> FF01::101
     *           0:0:0:0:0:0:0:1        -> ::1
     *
     * When $ip is an already compressed address and $force is false, the method returns 
     * the value as is, even if the address can be compressed further.
     *
     * Example: FF01::0:1 -> FF01::0:1
     *
     * To enforce maximum compression, you can set the second argument $force to true.
     *
     * Example: FF01::0:1 -> FF01::1 
     *
     * @param String  $ip    a valid IPv6-address (hex format)
     * @param boolean $force if true the address will be compressed as best as possible (since 1.2.0)
     *
     * @return String the compressed IPv6-address (hex format)
     * @access public
     * @see    Uncompress()
     * @static
     * @author elfrink at introweb dot nl
     */
    function compress($ip, $force = false)  
    {
        
        if(false !== strpos($ip, '::')) { // its already compressed

            if(true == $force) {

                $ip = Net_IPv6::uncompress($ip); 

            } else {

                return $ip;

            }

        }

        $prefix = Net_IPv6::getPrefixLength($ip);

        if (false === $prefix) {

            $prefix = '';

        } else {

            $ip     = Net_IPv6::removePrefixLength($ip);
            $prefix = '/'.$prefix;

        }

        $netmask = Net_IPv6::getNetmaskSpec($ip);
        $ip      = Net_IPv6::removeNetmaskSpec($ip);

        $ipp = explode(':', $ip);

        for ($i = 0; $i < count($ipp); $i++) {

            $ipp[$i] = dechex(hexdec($ipp[$i]));

        }

        $cip = ':' . join(':', $ipp) . ':';

        preg_match_all("/(:0)(:0)+/", $cip, $zeros);

        if (count($zeros[0]) > 0) {

            $match = '';

            foreach ($zeros[0] as $zero) {

                if (strlen($zero) > strlen($match)) {

                    $match = $zero;

                }
            }

            $cip = preg_replace('/' . $match . '/', ':', $cip, 1);

        }

        $cip = preg_replace('/((^:)|(:$))/', '', $cip);
        $cip = preg_replace('/((^:)|(:$))/', '::', $cip);

        if ('' != $netmask) {

            $cip = $cip.'/'.$netmask;

        }

        return $cip.$prefix;

    }

    // }}}
    // {{{ recommendedFormat()
    /**
     * Represent IPv6 address in RFC5952 format.
     *
     * @param String  $ip a valid IPv6-address (hex format)
     *
     * @return String the recommended representation of IPv6-address (hex format)
     * @access public
     * @see    compress()
     * @static
     * @author koyama at hoge dot org
     * @todo This method may become a part of compress() in a further releases
     */
    function recommendedFormat($ip)
    {
        $compressed = self::compress($ip, true);
        // RFC5952 4.2.2
        // The symbol "::" MUST NOT be used to shorten just one
        // 16-bit 0 field.
        if ((substr_count($compressed, ':') == 7) &&
            (strpos($compressed, '::') !== false)) {
            $compressed = str_replace('::', ':0:', $compressed);
        }
        return $compressed;
    }
    // }}}

    // {{{ isCompressible()

    /**
     * Checks, if an IPv6 address can be compressed
     *
     * @param String $ip a valid IPv6 address
     * 
     * @return Boolean true, if address can be compressed
     * 
     * @access public
     * @since 1.2.0b
     * @static
     * @author Manuel Schmitt
     */
    function isCompressible($ip) 
    {

        return (bool)($ip != Net_IPv6::compress($address));

    }    

    // }}}
    // {{{ SplitV64()

    /**
     * Splits an IPv6 address into the IPv6 and a possible IPv4 part
     *
     * RFC 2373 allows you to note the last two parts of an IPv6 address as
     * an IPv4 compatible address
     *
     * Example:  0:0:0:0:0:0:13.1.68.3
     *           0:0:0:0:0:FFFF:129.144.52.38
     *
     * @param String  $ip         a valid IPv6-address (hex format)
     * @param Boolean $uncompress if true, the address will be uncompressed 
     *                            before processing
     *
     * @return Array  [0] contains the IPv6 part,
     *                [1] the IPv4 part (hex format)
     * @access public
     * @static
     */
    function SplitV64($ip, $uncompress = true)
    {
        $ip = Net_IPv6::removeNetmaskSpec($ip);

        if ($uncompress) {

            $ip = Net_IPv6::Uncompress($ip);

        }

        if (strstr($ip, '.')) {

            $pos      = strrpos($ip, ':');
            $ip{$pos} = '_';
            $ipPart   = explode('_', $ip);

            return $ipPart;

        } else {

            return array($ip, "");

        }
    }

    // }}}
    // {{{ checkIPv6()

    /**
     * Checks an IPv6 address
     *
     * Checks if the given IP is IPv6-compatible
     *
     * @param String $ip a valid IPv6-address
     *
     * @return Boolean true if $ip is an IPv6 address
     * @access public
     * @static
     */
    function checkIPv6($ip)
    {

        $elements = Net_IPv6::separate($ip);
    
        $ip = $elements[0];

        if('' != $elements[1] && ( !is_numeric($elements[1]) || 0 > $elements[1] || 128 < $elements[1])) {

            return false;

        } 

        $ipPart = Net_IPv6::SplitV64($ip);
        $count  = 0;

        if (!empty($ipPart[0])) {
            $ipv6 = explode(':', $ipPart[0]);

            foreach($ipv6 as $element) { // made a validate precheck
                if(!preg_match('/[0-9a-fA-F]*/', $element)) {
                    return false;
                }
            }

            for ($i = 0; $i < count($ipv6); $i++) {

                if(4 < strlen($ipv6[$i])) {
                    
                    return false;

                }

                $dec = hexdec($ipv6[$i]);
                $hex = strtoupper(preg_replace("/^[0]{1,3}(.*[0-9a-fA-F])$/",
                                                "\\1", 
                                                $ipv6[$i]));

                if ($ipv6[$i] >= 0 && $dec <= 65535
                    && $hex == strtoupper(dechex($dec))) {

                    $count++;

                }

            }

            if (8 == $count) {

                return true;

            } else if (6 == $count and !empty($ipPart[1])) {

                $ipv4  = explode('.', $ipPart[1]);
                $count = 0;

                for ($i = 0; $i < count($ipv4); $i++) {

                    if ($ipv4[$i] >= 0 && (integer)$ipv4[$i] <= 255
                        && preg_match("/^\d{1,3}$/", $ipv4[$i])) {

                        $count++;

                    }

                }

                if (4 == $count) {

                    return true;

                }

            } else {

                return false;

            }

        } else {

            return false;

        }

    }

    // }}}

    // {{{ _parseAddress()

    /**
     * Returns the lowest and highest IPv6 address
     * for a given IP and netmask specification
     * 
     * The netmask may be a part of the $ip or 
     * the number of netmask bits is provided via $bits
     *
     * The result is an indexed array. The key 'start'
     * contains the lowest possible IP address. The key
     * 'end' the highest address.
     *
     * @param String $ipToParse the IPv6 address
     * @param String $bits      the optional count of netmask bits
     *
     * @return Array ['start', 'end'] the lowest and highest IPv6 address
     * @access public
     * @static
     * @author Nicholas Williams
     */

    function parseAddress($ipToParse, $bits = null)
    {

        $ip      = null;
        $bitmask = null;

        if ( null == $bits ) {  

            $elements = explode('/', $ipToParse);

            if ( 2 == count($elements) ) {

                $ip      = Net_IPv6::uncompress($elements[0]);
                $bitmask = $elements[1];

            } else {

                include_once 'PEAR.inc';

                return PEAR::raiseError(NET_IPV6_NO_NETMASK_MSG,
                                        NET_IPV6_NO_NETMASK);
            }
        } else {

            $ip      = Net_IPv6::uncompress($ipToParse);
            $bitmask = $bits;

        }

        $binNetmask = str_repeat('1', $bitmask).
                      str_repeat('0', 128 - $bitmask);
        $maxNetmask = str_repeat('1', 128);
        $netmask    = Net_IPv6::_bin2Ip($binNetmask);

        $startAddress = Net_IPv6::_bin2Ip(Net_IPv6::_ip2Bin($ip)
                                          & $binNetmask);
        $endAddress   = Net_IPv6::_bin2Ip(Net_IPv6::_ip2Bin($ip)
                                          | ($binNetmask ^ $maxNetmask));

        return array('start' => $startAddress, 'end' => $endAddress);
    }

    // }}}

    // {{{ _ip2Bin()

    /**
     * Converts an IPv6 address from Hex into Binary representation.
     *
     * @param String $ip the IP to convert (a:b:c:d:e:f:g:h), 
     *                   compressed IPs are allowed
     *
     * @return String the binary representation
     * @access private
     @ @since 1.1.0
     */
    function _ip2Bin($ip) 
    {
        $binstr = '';

        $ip = Net_IPv6::removeNetmaskSpec($ip);
        $ip = Net_IPv6::Uncompress($ip);

        $parts = explode(':', $ip);

        foreach ( $parts as $v ) {

            $str     = base_convert($v, 16, 2);
            $binstr .= str_pad($str, 16, '0', STR_PAD_LEFT);

        }

        return $binstr;
    }

    // }}}
    // {{{ _bin2Ip()

    /**
     * Converts an IPv6 address from Binary into Hex representation.
     *
     * @param String $bin the IP address as binary
     *
     * @return String the uncompressed Hex representation
     * @access private
     @ @since 1.1.0
     */
    function _bin2Ip($bin)
    {
        $ip = "";

        if (strlen($bin) < 128) {

            $bin = str_pad($bin, 128, '0', STR_PAD_LEFT);

        }

        $parts = str_split($bin, "16");

        foreach ( $parts as $v ) {

            $str = base_convert($v, 2, 16);
            $ip .= $str.":";

        }

        $ip = substr($ip, 0, -1);

        return $ip;
    }

    // }}}
}
// }}}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */

?>
