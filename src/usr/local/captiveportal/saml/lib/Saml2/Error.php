<?php
 
/**
 * Error class of OneLogin PHP Toolkit
 *
 * Defines the Error class
 */
class OneLogin_Saml2_Error extends Exception
{
    // Errors
    const SETTINGS_FILE_NOT_FOUND = 0;
    const SETTINGS_INVALID_SYNTAX = 1;
    const SETTINGS_INVALID = 2;
    const METADATA_SP_INVALID = 3;
    const SP_CERTS_NOT_FOUND = 4;
    // SP_CERTS_NOT_FOUND is deprecated, use CERT_NOT_FOUND instead
    const CERT_NOT_FOUND = 4;
    const REDIRECT_INVALID_URL = 5;
    const PUBLIC_CERT_FILE_NOT_FOUND = 6;
    const PRIVATE_KEY_FILE_NOT_FOUND = 7;
    const SAML_RESPONSE_NOT_FOUND = 8;
    const SAML_LOGOUTMESSAGE_NOT_FOUND = 9;
    const SAML_LOGOUTREQUEST_INVALID = 10;
    const SAML_LOGOUTRESPONSE_INVALID  = 11;
    const SAML_SINGLE_LOGOUT_NOT_SUPPORTED = 12;
    const PRIVATE_KEY_NOT_FOUND = 13;
    const UNSUPPORTED_SETTINGS_OBJECT = 14;

    /**
     * Constructor
     *
     * @param string     $msg  Describes the error.
     * @param int        $code The code error (defined in the error class).
     * @param array|null $args Arguments used in the message that describes the error.
     */
    public function __construct($msg, $code = 0, $args = null)
    {
        assert('is_string($msg)');
        assert('is_int($code)');

        $message = OneLogin_Saml2_Utils::t($msg, $args);

        parent::__construct($message, $code);
    }
}

/**
 * This class implements another custom Exception handler,
 * related to exceptions that happens during validation process.
 */
class OneLogin_Saml2_ValidationError extends Exception
{
    # Validation Errors
    const UNSUPPORTED_SAML_VERSION = 0;
    const MISSING_ID = 1;
    const WRONG_NUMBER_OF_ASSERTIONS = 2;
    const MISSING_STATUS = 3;
    const MISSING_STATUS_CODE = 4;
    const STATUS_CODE_IS_NOT_SUCCESS = 5;
    const WRONG_SIGNED_ELEMENT = 6;
    const ID_NOT_FOUND_IN_SIGNED_ELEMENT = 7;
    const DUPLICATED_ID_IN_SIGNED_ELEMENTS = 8;
    const INVALID_SIGNED_ELEMENT = 9;
    const DUPLICATED_REFERENCE_IN_SIGNED_ELEMENTS = 10;
    const UNEXPECTED_SIGNED_ELEMENTS = 11;
    const WRONG_NUMBER_OF_SIGNATURES_IN_RESPONSE = 12;
    const WRONG_NUMBER_OF_SIGNATURES_IN_ASSERTION = 13;
    const INVALID_XML_FORMAT = 14;
    const WRONG_INRESPONSETO = 15;
    const NO_ENCRYPTED_ASSERTION = 16;
    const NO_ENCRYPTED_NAMEID = 17;
    const MISSING_CONDITIONS = 18;
    const ASSERTION_TOO_EARLY = 19;
    const ASSERTION_EXPIRED = 20;
    const WRONG_NUMBER_OF_AUTHSTATEMENTS = 21;
    const NO_ATTRIBUTESTATEMENT = 22;
    const ENCRYPTED_ATTRIBUTES = 23;
    const WRONG_DESTINATION = 24;
    const EMPTY_DESTINATION = 25;
    const WRONG_AUDIENCE = 26;
    const ISSUER_MULTIPLE_IN_RESPONSE = 27;
    const ISSUER_NOT_FOUND_IN_ASSERTION = 28;
    const WRONG_ISSUER = 29;
    const SESSION_EXPIRED = 30;
    const WRONG_SUBJECTCONFIRMATION = 31;
    const NO_SIGNED_MESSAGE = 32;
    const NO_SIGNED_ASSERTION = 33;
    const NO_SIGNATURE_FOUND = 34;
    const KEYINFO_NOT_FOUND_IN_ENCRYPTED_DATA = 35;
    const CHILDREN_NODE_NOT_FOUND_IN_KEYINFO = 36;
    const UNSUPPORTED_RETRIEVAL_METHOD = 37;
    const NO_NAMEID = 38;
    const EMPTY_NAMEID = 39;
    const SP_NAME_QUALIFIER_NAME_MISMATCH = 40;
    const DUPLICATED_ATTRIBUTE_NAME_FOUND = 41;
    const INVALID_SIGNATURE = 42;
    const WRONG_NUMBER_OF_SIGNATURES = 43;
    const RESPONSE_EXPIRED = 44;
    const UNEXPECTED_REFERENCE = 45;
    const NOT_SUPPORTED = 46;
    const KEY_ALGORITHM_ERROR = 47;
    const MISSING_ENCRYPTED_ELEMENT = 48;


    /**
     * Constructor
     *
     * @param string     $msg  Describes the error.
     * @param int        $code The code error (defined in the error class).
     * @param array|null $args Arguments used in the message that describes the error.
     */
    public function __construct($msg, $code = 0, $args = null)
    {
        assert('is_string($msg)');
        assert('is_int($code)');

        $message = OneLogin_Saml2_Utils::t($msg, $args);

        parent::__construct($message, $code);
    }
}
