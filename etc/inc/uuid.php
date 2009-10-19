<?php
/*-
 * Copyright (c) 2008 Fredrik Lindberg - http://www.shapeshifter.se
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
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
 */

/*
 * UUID (RFC4122) Generator
 * http://tools.ietf.org/html/rfc4122
 *
 * Implements version 1, 3, 4 and 5
 */
class UUID {
    /* UUID versions */
    const UUID_TIME     = 1;    /* Time based UUID */
    const UUID_NAME_MD5     = 3;    /* Name based (MD5) UUID */
    const UUID_RANDOM     = 4;    /* Random UUID */
    const UUID_NAME_SHA1     = 5;    /* Name based (SHA1) UUID */

    /* UUID formats */
    const FMT_FIELD     = 100;
    const FMT_STRING     = 101;
    const FMT_BINARY     = 102;
    const FMT_QWORD     = 1;    /* Quad-word, 128-bit (not impl.) */
    const FMT_DWORD     = 2;    /* Double-word, 64-bit (not impl.) */
        const FMT_WORD         = 4;    /* Word, 32-bit (not impl.) */
    const FMT_SHORT        = 8;    /* Short (not impl.) */
    const FMT_BYTE        = 16;    /* Byte */
    const FMT_DEFAULT     = 16;

    /* Field UUID representation */
    static private $m_uuid_field = array(
        'time_low' => 0,        /* 32-bit */
        'time_mid' => 0,        /* 16-bit */
        'time_hi' => 0,            /* 16-bit */
        'clock_seq_hi' => 0,        /*  8-bit */
        'clock_seq_low' => 0,        /*  8-bit */
        'node' => array()        /* 48-bit */
    );

    static private $m_generate = array(
        self::UUID_TIME => "generateTime",
        self::UUID_RANDOM => "generateRandom",
        self::UUID_NAME_MD5 => "generateNameMD5",
        self::UUID_NAME_SHA1 => "generateNameSHA1"
    );

    static private $m_convert = array(
        self::FMT_FIELD => array(
            self::FMT_BYTE => "conv_field2byte",
            self::FMT_STRING => "conv_field2string",
            self::FMT_BINARY => "conv_field2binary"
        ),
        self::FMT_BYTE => array(
            self::FMT_FIELD => "conv_byte2field",
            self::FMT_STRING => "conv_byte2string",
            self::FMT_BINARY => "conv_byte2binary"
        ),
        self::FMT_STRING => array(
            self::FMT_BYTE => "conv_string2byte",
            self::FMT_FIELD => "conv_string2field",
            self::FMT_BINARY => "conv_string2binary"
        ),
    );

    /* Swap byte order of a 32-bit number */
    static private function swap32($x) {
        return (($x & 0x000000ff) << 24) | (($x & 0x0000ff00) << 8) |
            (($x & 0x00ff0000) >> 8) | (($x & 0xff000000) >> 24);
    }

    /* Swap byte order of a 16-bit number */
    static private function swap16($x) {
        return (($x & 0x00ff) << 8) | (($x & 0xff00) >> 8);
    }

    /* Auto-detect UUID format */
    static private function detectFormat($src) {
        if (is_string($src))
            return self::FMT_STRING;
        else if (is_array($src)) {
            $len = count($src);
            if ($len == 1 || ($len % 2) == 0)
                return $len;
            else
                return (-1);
        }
        else
            return self::FMT_BINARY;
    }

    /*
     * Public API, generate a UUID of 'type' in format 'fmt' for
     * the given namespace 'ns' and node 'node'
     */
    static public function generate($type, $fmt = self::FMT_BYTE,
        $node = "", $ns = "") {
        $func = self::$m_generate[$type];
        if (!isset($func))
            return null;
        $conv = self::$m_convert[self::FMT_FIELD][$fmt];

        $uuid = self::$func($ns, $node);
        return self::$conv($uuid);
    }

    /*
     * Public API, convert a UUID from one format to another
     */
    static public function convert($uuid, $from, $to) {
        $conv = self::$m_convert[$from][$to];
        if (!isset($conv))
            return ($uuid);

        return (self::$conv($uuid));
    }

    /*
     * Generate an UUID version 4 (pseudo random)
     */
    static private function generateRandom($ns, $node) {
        $uuid = self::$m_uuid_field;

        $uuid['time_hi'] = (4 << 12) | (mt_rand(0, 0x1000));
        $uuid['clock_seq_hi'] = (1 << 7) | mt_rand(0, 128);
        $uuid['time_low'] = mt_rand(0, 0xffffffff);
        $uuid['time_mid'] = mt_rand(0, 0x0000ffff);
        $uuid['clock_seq_low'] = mt_rand(0, 255);
        for ($i = 0; $i < 6; $i++)
            $uuid['node'][$i] = mt_rand(0, 255);
        return ($uuid);
    }

    /*
     * Generate UUID version 3 and 5 (name based)
     */
    static private function generateName($ns, $node, $hash, $version) {
        $ns_fmt = self::detectFormat($ns);
        $field = self::convert($ns, $ns_fmt, self::FMT_FIELD);

        /* Swap byte order to keep it in big endian on all platforms */
        $field['time_low'] = self::swap32($field['time_low']);
        $field['time_mid'] = self::swap16($field['time_mid']);
        $field['time_hi'] = self::swap16($field['time_hi']);

        /* Convert the namespace to binary and concatenate node */
        $raw = self::convert($field, self::FMT_FIELD, self::FMT_BINARY);
        $raw .= $node;

        /* Hash the namespace and node and convert to a byte array */
        $val = $hash($raw, true);    
        $tmp = unpack('C16', $val);
        foreach (array_keys($tmp) as $key)
            $byte[$key - 1] = $tmp[$key];

        /* Convert byte array to a field array */
        $field = self::conv_byte2field($byte);

        $field['time_low'] = self::swap32($field['time_low']);
        $field['time_mid'] = self::swap16($field['time_mid']);
        $field['time_hi'] = self::swap16($field['time_hi']);

        /* Apply version and constants */
        $field['clock_seq_hi'] &= 0x3f;
        $field['clock_seq_hi'] |= (1 << 7);
        $field['time_hi'] &= 0x0fff;
        $field['time_hi'] |= ($version << 12);

        return ($field);    
    }
    static private function generateNameMD5($ns, $node) {
        return self::generateName($ns, $node, "md5",
            self::UUID_NAME_MD5);
    }
    static private function generateNameSHA1($ns, $node) {
        return self::generateName($ns, $node, "sha1",
            self::UUID_NAME_SHA1);
    }

    /*
     * Generate UUID version 1 (time based)
     */
    static private function generateTime($ns, $node) {
        $uuid = self::$m_uuid_field;

        /*
         * Get current time in 100 ns intervals. The magic value
         * is the offset between UNIX epoch and the UUID UTC
         * time base October 15, 1582.
         */
            $tp = gettimeofday();
        $time = ($tp['sec'] * 10000000) + ($tp['usec'] * 10) +
            0x01B21DD213814000;

        /* Work around PHP 32-bit bit-operation limits */
                $q = intval($time / 0xffffffff);
                $low = $time - ($q * (0xffffffff + 1));
                $high = intval(($time - $low) / 0xffffffff);

        $uuid['time_low'] = $low;
        $uuid['time_mid'] = $high & 0x0000ffff;
        $uuid['time_hi'] = ($high & 0x0fff) | (self::UUID_TIME << 12);
        
        /*
         * We don't support saved state information and generate
         * a random clock sequence each time.
         */
        $uuid['clock_seq_hi'] = (1 << 7) | mt_rand(0, 128);
        $uuid['clock_seq_low'] = mt_rand(0, 255);

        /*
         * Node should be set to the 48-bit IEEE node identifier, but
         * we leave it for the user to supply the node.
         */
        for ($i = 0; $i < 6; $i++)
            $uuid['node'][$i] = ord(substr($node, $i, 1));

        return ($uuid);
    }

    /* Assumes correct byte order */
    static private function conv_field2byte($src) {
        $uuid[0] = ($src['time_low'] & 0xff000000) >> 24;
        $uuid[1] = ($src['time_low'] & 0x00ff0000) >> 16;
        $uuid[2] = ($src['time_low'] & 0x0000ff00) >> 8;
        $uuid[3] = ($src['time_low'] & 0x000000ff);
        $uuid[4] = ($src['time_mid'] & 0xff00) >> 8;
        $uuid[5] = ($src['time_mid'] & 0x00ff);
        $uuid[6] = ($src['time_hi'] & 0xff00) >> 8;
        $uuid[7] = ($src['time_hi'] & 0x00ff);
        $uuid[8] = $src['clock_seq_hi'];
        $uuid[9] = $src['clock_seq_low'];

        for ($i = 0; $i < 6; $i++)
            $uuid[10+$i] = $src['node'][$i];

        return ($uuid);
    }

    static private function conv_field2string($src) {
        $str = sprintf(
            '%08x-%04x-%04x-%02x%02x-%02x%02x%02x%02x%02x%02x',
            ($src['time_low']), ($src['time_mid']), ($src['time_hi']),
            $src['clock_seq_hi'], $src['clock_seq_low'],
            $src['node'][0], $src['node'][1], $src['node'][2],
            $src['node'][3], $src['node'][4], $src['node'][5]);
        return ($str);
    }

    static private function conv_field2binary($src) {
        $byte = self::conv_field2byte($src);
        return self::conv_byte2binary($byte);
    }

    static private function conv_byte2field($uuid) {
        $field = self::$m_uuid_field;
        $field['time_low'] = ($uuid[0] << 24) | ($uuid[1] << 16) |
            ($uuid[2] << 8) | $uuid[3];
        $field['time_mid'] = ($uuid[4] << 8) | $uuid[5];
        $field['time_hi'] = ($uuid[6] << 8) | $uuid[7];
        $field['clock_seq_hi'] = $uuid[8];
        $field['clock_seq_low'] = $uuid[9];

        for ($i = 0; $i < 6; $i++)
            $field['node'][$i] = $uuid[10+$i];
        return ($field);
    }

    static public function conv_byte2string($src) {
        $field = self::conv_byte2field($src);
        return self::conv_field2string($field);
    }

    static private function conv_byte2binary($src) {
        $raw = pack('C16', $src[0], $src[1], $src[2], $src[3],
            $src[4], $src[5], $src[6], $src[7], $src[8], $src[9],
            $src[10], $src[11], $src[12], $src[13], $src[14], $src[15]);
        return ($raw);
    }

    static private function conv_string2field($src) {
        $parts = sscanf($src, '%x-%x-%x-%x-%02x%02x%02x%02x%02x%02x');
        $field = self::$m_uuid_field;
        $field['time_low'] = ($parts[0]);
        $field['time_mid'] = ($parts[1]);
        $field['time_hi'] = ($parts[2]);
        $field['clock_seq_hi'] = ($parts[3] & 0xff00) >> 8;
        $field['clock_seq_low'] = $parts[3] & 0x00ff;
        for ($i = 0; $i < 6; $i++)
            $field['node'][$i] = $parts[4+$i];

        return ($field);
    }

    static private function conv_string2byte($src) {
        $field = self::conv_string2field($src);
        return self::conv_field2byte($field);
    }

    static private function conv_string2binary($src) {
        $byte = self::conv_string2byte($src);
        return self::conv_byte2binary($byte);
    }
}

?>