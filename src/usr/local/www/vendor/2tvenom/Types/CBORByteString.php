<?php
namespace CBOR\Types;

class CBORByteString {
    private $byte_string = null;

    public function __construct($byte_string)
    {
        $this->byte_string = $byte_string;
    }

    /**
     * @return null
     */
    public function get_byte_string()
    {
        return $this->byte_string;
    }

    /**
     * @param null $byte_string
     */
    public function set_byte_string($byte_string)
    {
        $this->byte_string = $byte_string;
    }
}