<?php
namespace CBOR;

/**
 * CBOR encoder/decoder
 *
 * http://tools.ietf.org/html/rfc7049
 * http://habrahabr.ru/post/208690/ thx man :)
 *
 * Class CBOREncoder
 */
class CBOREncoder
{
    const
        MAJOR_OFFSET = 5,
        HEADER_WIPE = 0b00011111,
        ADDITIONAL_WIPE = 0b11100000,
        MAJOR_TYPE_UNSIGNED_INT = 0b000000, //0
        MAJOR_TYPE_INT = 0b100000, //1
        MAJOR_TYPE_BYTE_STRING = 0b1000000, //2
        MAJOR_TYPE_UTF8_STRING = 0b1100000, //3
        MAJOR_TYPE_ARRAY = 0b10000000, //4
        MAJOR_TYPE_MAP = 0b10100000, //5
        MAJOR_TYPE_TAGS = 0b11000000, //6
        MAJOR_TYPE_SIMPLE_AND_FLOAT = 0b11100000, //7
        MAJOR_TYPE_INFINITE_CLOSE = 0xFF,
        ADDITIONAL_MAX = 23,
        ADDITIONAL_TYPE_INT_FALSE = 20,
        ADDITIONAL_TYPE_INT_TRUE = 21,
        ADDITIONAL_TYPE_INT_NULL = 22,
        ADDITIONAL_TYPE_INT_UNDEFINED = 23,
        ADDITIONAL_TYPE_INT_UINT8 = 24,
        ADDITIONAL_TYPE_INT_UINT16 = 25,
        ADDITIONAL_TYPE_INT_UINT32 = 26,
        ADDITIONAL_TYPE_INT_UINT64 = 27,
        ADDITIONAL_TYPE_FLOAT16 = 25, //not support
        ADDITIONAL_TYPE_FLOAT32 = 26, //encode not support
        ADDITIONAL_TYPE_FLOAT64 = 27,
        ADDITIONAL_TYPE_INFINITE = 31;

    private static $length_pack_type = array(
        self::ADDITIONAL_TYPE_INT_UINT8 => "C",
        self::ADDITIONAL_TYPE_INT_UINT16 => "n",
        self::ADDITIONAL_TYPE_INT_UINT32 => "N",
        self::ADDITIONAL_TYPE_INT_UINT64 => null,
    );

    private static $float_pack_type = array(
        self::ADDITIONAL_TYPE_FLOAT32 => "f",
        self::ADDITIONAL_TYPE_FLOAT64 => "d",
    );

    private static $byte_length = array(
        self::ADDITIONAL_TYPE_INT_UINT8 => 1,
        self::ADDITIONAL_TYPE_INT_UINT16 => 2,
        self::ADDITIONAL_TYPE_INT_UINT32 => 4,
        self::ADDITIONAL_TYPE_INT_UINT64 => 8,
    );

    /**
     * Encode variable to CBOR binary format
     * @param mixed $var
     * @return null|string
     */
    public static function encode($var){
        switch(gettype($var))
        {
            case is_null($var):
            case "boolean":
            case "double":
            case "float":
            case "real":
                return self::encode_simple_float($var);
                break;
            case "integer":
                return self::encode_int($var);
                break;
            case "array":
                return self::encode_array($var);
                break;
            case "string":
                return self::encode_string($var);
                break;
            case get_class($var) == 'CBOR\Types\CBORByteString':
                /**
                 * @var \CBOR\Types\CBORByteString $var
                 */
                return self::encode_string($var->get_byte_string(), true);
                break;
        }
        return null;
    }

    /**
     * Decode CBOR byte string
     * @param mixed $var
     * @throws \Exception
     * @return mixed
     */
    public static function decode(&$var){
        $out = null;
        
        //get initial byte
        $unpacked = unpack("C*", substr($var, 0, 1));
        $header_byte = array_shift($unpacked);

        if ($header_byte == self::MAJOR_TYPE_INFINITE_CLOSE) {
            $major_type = $header_byte;
            $additional_info = 0;
        } else {
            //unpack major type
            $major_type = $header_byte & self::ADDITIONAL_WIPE;
            //get additional_info
            $additional_info = self::unpack_additional_info($header_byte);
        }

        $byte_data_offset = 1;
        if(array_key_exists($additional_info, self::$byte_length)){
            $byte_data_offset += self::$byte_length[$additional_info];
        }

        switch($major_type) {
            case self::MAJOR_TYPE_UNSIGNED_INT:
            case self::MAJOR_TYPE_INT:
                //decode int
                $out = self::decode_int($additional_info, $var);

                if($major_type == self::MAJOR_TYPE_INT){
                    $out = -($out+1);
                }

                break;
            case self::MAJOR_TYPE_BYTE_STRING:
            case self::MAJOR_TYPE_UTF8_STRING:
                $string_length = self::decode_int($additional_info, $var);

                $out = substr($var, $byte_data_offset, $string_length);

                if($major_type == self::MAJOR_TYPE_BYTE_STRING) {
                    $out = new \CBOR\Types\CBORByteString($out);
                }

                $byte_data_offset += $string_length;
                break;
            case self::MAJOR_TYPE_ARRAY:
            case self::MAJOR_TYPE_MAP:
                $out = array();

                $elem_count = $additional_info != self::ADDITIONAL_TYPE_INFINITE ?
                    self::decode_int($additional_info, $var) : PHP_INT_MAX;
                $var = substr($var, $byte_data_offset);

                while($elem_count > count($out))
                {
                    $primitive = self::decode($var);
                    if (is_null($primitive)) {
                        break;
                    }
                    if($major_type == self::MAJOR_TYPE_MAP) {
                        $out[$primitive] = self::decode($var);
                    } else {
                        $out[] = $primitive;
                    }
                }

                break;
            case self::MAJOR_TYPE_TAGS:
                throw new \Exception("Not implemented. Sorry");
                break;
            case self::MAJOR_TYPE_SIMPLE_AND_FLOAT:
                $out = self::decode_simple_float($additional_info, $var);
                break;
            case self::MAJOR_TYPE_INFINITE_CLOSE:
                $out = null;
        }

        if(!in_array($major_type, array(self::MAJOR_TYPE_ARRAY, self::MAJOR_TYPE_MAP))){
            $var = substr($var, $byte_data_offset);
        }

        return $out;
    }

    /**
     * Unpack data length/int
     * @param $length_capacity
     * @param $byte_string
     * @throws CBORIncorrectAdditionalInfoException
     * @internal param $length
     * @return int|null
     */
    private static function decode_int($length_capacity, &$byte_string){

        if($length_capacity <= self::ADDITIONAL_MAX) return $length_capacity;
        $decoding_byte_string = substr($byte_string, 1, self::$byte_length[$length_capacity]);
        switch(true)
        {
            case $length_capacity == self::ADDITIONAL_TYPE_INT_UINT64:
                return self::bigint_unpack($decoding_byte_string);
                break;
            case array_key_exists($length_capacity, self::$length_pack_type):
                $typed_int = unpack(self::$length_pack_type[$length_capacity], $decoding_byte_string);
                return array_shift($typed_int);
                break;
            default:
                throw new CBORIncorrectAdditionalInfoException("Incorrect additional info");
                break;
        }

        return null;
    }

    /**
     * Unpack double/bool/null
     * @param $length_capacity
     * @param $byte_string
     * @return null|string
     */
    private static function decode_simple_float($length_capacity, &$byte_string){
        $simple_association = array(
            self::ADDITIONAL_TYPE_INT_FALSE => false,
            self::ADDITIONAL_TYPE_INT_TRUE => true,
            self::ADDITIONAL_TYPE_INT_NULL => null,
            self::ADDITIONAL_TYPE_INT_UNDEFINED => NAN,
        );

        if(array_key_exists($length_capacity, $simple_association))
        {
            return $simple_association[$length_capacity];
        }
        $typed_float = unpack(self::$float_pack_type[$length_capacity], strrev(substr($byte_string, 1, self::$byte_length[$length_capacity])));
        return array_shift($typed_float);
    }

    /**
     * Unpack additional info
     * @param $byte
     * @return int
     */
    private static function unpack_additional_info($byte)
    {
        return $byte & self::HEADER_WIPE;
    }

    /**
     * Encode integer
     * @param $int
     * @return string
     */
    private static function encode_int($int)
    {
        $major_type = self::MAJOR_TYPE_UNSIGNED_INT;

        //for negative numbers
        if($int < 0){
            $major_type = self::MAJOR_TYPE_INT;
            $int = abs($int + 1);
        }

        return self::pack_number($major_type, $int);
    }

    /**
     * Encode string
     * @param $string
     * @param bool $byte_string
     * @return string
     */
    private static function encode_string($string, $byte_string = false)
    {
        $length = strlen($string);
        $major_type = self::MAJOR_TYPE_UTF8_STRING;;
        //check byte string or utf8

        if($byte_string){
            $major_type = self::MAJOR_TYPE_BYTE_STRING;
        }

        return self::pack_number($major_type, $length) . $string;
    }

    /**
     * Encode array
     * @param $array
     * @return string
     */
    private static function encode_array($array){
        $array_length = count($array);

        $is_map = self::is_assoc($array);

        $major_type = self::MAJOR_TYPE_ARRAY;

        if($is_map){
            $major_type = self::MAJOR_TYPE_MAP;
        }

        $header = self::pack_number($major_type, $array_length);

        foreach($array as $key => $elem) {

            $encode_list = array($key, $elem);
            if(!$is_map) {
                $encode_list = array($encode_list[1]);
            }

            foreach($encode_list as $encode_elem) {
                $header .= self::encode($encode_elem);
            }
        }

        return $header;
    }

    /**
     * Pack float and simple
     * @param bool|null
     * @return string
     */
    private static function encode_simple_float($value){
        $major_type = self::MAJOR_TYPE_SIMPLE_AND_FLOAT;

        if(is_bool($value) || is_null($value) || is_nan($value))
        {
            $additional_info = null;
            switch(true)
            {
                case $value === true:
                    $additional_info = self::ADDITIONAL_TYPE_INT_TRUE;
                    break;
                case $value === false:
                    $additional_info = self::ADDITIONAL_TYPE_INT_FALSE;
                    break;
                case is_null($value):
                    $additional_info = self::ADDITIONAL_TYPE_INT_NULL;
                    break;
                case is_nan($value):
                    $additional_info = self::ADDITIONAL_TYPE_INT_UNDEFINED;
                    break;
            }
            return self::pack_init_byte($major_type, $additional_info);
        } elseif(is_double($value)) {
            //strrev - double pack not have endian byte order, need reverse string

            return self::pack_init_byte($major_type, self::ADDITIONAL_TYPE_FLOAT64) . strrev(pack(self::$float_pack_type[self::ADDITIONAL_TYPE_FLOAT64], $value));
        }

        return null;
    }

    /**
     * Pack initial byte
     * @param $major_type
     * @param $additional_info
     * @return string
     */
    private static function pack_init_byte($major_type, $additional_info)
    {
        return pack("c", $major_type | $additional_info);
    }

    /**
     * Pack number
     * @param $major_type
     * @param $int
     * @return string
     */
    private static function pack_number($major_type, $int)
    {
        if($int <= self::ADDITIONAL_MAX) return self::pack_init_byte($major_type, $int);

        $length = self::get_length($int);

        //custom big int pack
        if(is_null($length))
        {
            return self::pack_init_byte($major_type, self::ADDITIONAL_TYPE_INT_UINT64) . self::bigint_pack($int);
        }

        return self::pack_init_byte($major_type, $length) . pack(self::$length_pack_type[$length], $int);
    }

    /**
     * Get length of int
     * @param $int
     * @return int|null
     */
    private static function get_length($int)
    {
        switch(true)
        {
            case $int < 256:
                return self::ADDITIONAL_TYPE_INT_UINT8;
                break;
            case $int < 65536:
                return self::ADDITIONAL_TYPE_INT_UINT16;
                break;
            case $int < 4294967296:
                return self::ADDITIONAL_TYPE_INT_UINT32;
                break;
            //are you seriously?
            case $int < 9223372036854775807:
                return null;
                break;
        }
        return null;
    }

    /**
     * Array is associative or not
     *
     * @param $arr
     * @return bool
     */
    private static function is_assoc(&$arr)
    {
        return array_keys($arr) !== range(0, count($arr) -1);
    }

    /**
     * Split big int in two 32 bit parts and pack
     * @param $big_int
     * @return string
     */
    private static function bigint_unpack($big_int)
    {
        list($higher, $lower) = array_values(unpack("N2", $big_int));
        return $higher << 32 | $lower;
    }

    private static function bigint_pack($big_int)
    {
        return pack("NN", ($big_int & 0xffffffff00000000) >> 32, ($big_int & 0x00000000ffffffff));
    }
}
