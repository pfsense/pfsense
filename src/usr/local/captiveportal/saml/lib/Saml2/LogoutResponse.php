<?php

/**
 * SAML 2 Logout Response
 *
 */
class OneLogin_Saml2_LogoutResponse
{
    /**
    * Contains the ID of the Logout Response
    * @var string
    */
    public $id;

    /**
     * Object that represents the setting info
     * @var OneLogin_Saml2_Settings
     */
    protected $_settings;

    /**
     * The decoded, unprocessed XML response provided to the constructor.
     * @var string
     */
    protected $_logoutResponse;

    /**
     * A DOMDocument class loaded from the SAML LogoutResponse.
     * @var DomDocument
     */
    public $document;

    /**
    * After execute a validation process, if it fails, this var contains the cause
    * @var string|null
    */
    private $_error;

    /**
     * Constructs a Logout Response object (Initialize params from settings and if provided
     * load the Logout Response.
     *
     * @param OneLogin_Saml2_Settings $settings Settings.
     * @param string|null             $response An UUEncoded SAML Logout response from the IdP.
     *
     * @throws OneLogin_Saml2_Error
     */
    public function __construct(OneLogin_Saml2_Settings $settings, $response = null)
    {
        $this->_settings = $settings;

        $baseURL = $this->_settings->getBaseURL();
        if (!empty($baseURL)) {
            OneLogin_Saml2_Utils::setBaseURL($baseURL);
        }

        if ($response) {
            $decoded = base64_decode($response);
            $inflated = @gzinflate($decoded);
            if ($inflated != false) {
                $this->_logoutResponse = $inflated;
            } else {
                $this->_logoutResponse = $decoded;
            }
            $this->document = new DOMDocument();
            $this->document = OneLogin_Saml2_Utils::loadXML($this->document, $this->_logoutResponse);

            if (false === $this->document) {
                throw new OneLogin_Saml2_Error(
                    "LogoutResponse could not be processed",
                    OneLogin_Saml2_Error::SAML_LOGOUTRESPONSE_INVALID
                );
            }

            if ($this->document->documentElement->hasAttribute('ID')) {
                $this->id = $this->document->documentElement->getAttribute('ID');
            }
        }
    }

    /**
     * Gets the Issuer of the Logout Response.
     *
     * @return string|null $issuer The Issuer
     */
    public function getIssuer()
    {
        $issuer = null;
        $issuerNodes = $this->_query('/samlp:LogoutResponse/saml:Issuer');
        if ($issuerNodes->length == 1) {
            $issuer = $issuerNodes->item(0)->textContent;
        }
        return $issuer;
    }

    /**
     * Gets the Status of the Logout Response.
     *
     * @return string|null The Status
     */
    public function getStatus()
    {
        $entries = $this->_query('/samlp:LogoutResponse/samlp:Status/samlp:StatusCode');
        if ($entries->length != 1) {
            return null;
        }
        $status = $entries->item(0)->getAttribute('Value');
        return $status;
    }

    /**
     * Determines if the SAML LogoutResponse is valid
     *
     * @param string|null $requestId The ID of the LogoutRequest sent by this SP to the IdP
     * @param bool $retrieveParametersFromServer
     *
     * @return bool Returns if the SAML LogoutResponse is or not valid
     */
    public function isValid($requestId = null, $retrieveParametersFromServer = false)
    {
        $this->_error = null;
        try {
            $idpData = $this->_settings->getIdPData();
            $idPEntityId = $idpData['entityId'];

            if ($this->_settings->isStrict()) {
                $security = $this->_settings->getSecurityData();

                if ($security['wantXMLValidation']) {
                    $res = OneLogin_Saml2_Utils::validateXML($this->document, 'saml-schema-protocol-2.0.xsd', $this->_settings->isDebugActive(), $this->_settings->getSchemasPath());
                    if (!$res instanceof DOMDocument) {
                        throw new OneLogin_Saml2_ValidationError(
                            "Invalid SAML Logout Response. Not match the saml-schema-protocol-2.0.xsd",
                            OneLogin_Saml2_ValidationError::INVALID_XML_FORMAT
                        );
                    }
                }

                // Check if the InResponseTo of the Logout Response matchs the ID of the Logout Request (requestId) if provided
                if (isset($requestId) && $this->document->documentElement->hasAttribute('InResponseTo')) {
                    $inResponseTo = $this->document->documentElement->getAttribute('InResponseTo');
                    if ($requestId != $inResponseTo) {
                        throw new OneLogin_Saml2_ValidationError(
                            "The InResponseTo of the Logout Response: $inResponseTo, does not match the ID of the Logout request sent by the SP: $requestId",
                            OneLogin_Saml2_ValidationError::WRONG_INRESPONSETO
                        );
                    }
                }

                // Check issuer
                $issuer = $this->getIssuer();
                if (!empty($issuer) && $issuer != $idPEntityId) {
                    throw new OneLogin_Saml2_ValidationError(
                        "Invalid issuer in the Logout Response",
                        OneLogin_Saml2_ValidationError::WRONG_ISSUER
                    );
                }

                $currentURL = OneLogin_Saml2_Utils::getSelfRoutedURLNoQuery();

                // Check destination
                if ($this->document->documentElement->hasAttribute('Destination')) {
                    $destination = $this->document->documentElement->getAttribute('Destination');
                    if (empty($destination)) {
                        if (!$security['relaxDestinationValidation']) {
                            throw new OneLogin_Saml2_ValidationError(
                                "The LogoutResponse has an empty Destination value",
                                OneLogin_Saml2_ValidationError::EMPTY_DESTINATION
                            );
                        }
                    } else {
                        $urlComparisonLength = $security['destinationStrictlyMatches'] ? strlen($destination) : strlen($currentURL);
                        if (strncmp($destination, $currentURL, $urlComparisonLength) !== 0) {
                            $currentURLNoRouted = OneLogin_Saml2_Utils::getSelfURLNoQuery();
                            $urlComparisonLength = $security['destinationStrictlyMatches'] ? strlen($destination) : strlen($currentURLNoRouted);

                            if (strncmp($destination, $currentURLNoRouted, $urlComparisonLength) !== 0) {
                                throw new OneLogin_Saml2_ValidationError(
                                    "The LogoutResponse was received at $currentURL instead of $destination",
                                    OneLogin_Saml2_ValidationError::WRONG_DESTINATION
                                );
                            }
                        }
                    }
                }

                if ($security['wantMessagesSigned'] && !isset($_GET['Signature'])) {
                    throw new OneLogin_Saml2_ValidationError(
                        "The Message of the Logout Response is not signed and the SP requires it",
                        OneLogin_Saml2_ValidationError::NO_SIGNED_MESSAGE
                    );
                }
            }

            if (isset($_GET['Signature'])) {
                $signatureValid = OneLogin_Saml2_Utils::validateBinarySign("SAMLResponse", $_GET, $idpData, $retrieveParametersFromServer);
                if (!$signatureValid) {
                    throw new OneLogin_Saml2_ValidationError(
                        "Signature validation failed. Logout Response rejected",
                        OneLogin_Saml2_ValidationError::INVALID_SIGNATURE
                    );
                }
            }
            return true;
        } catch (Exception $e) {
            $this->_error = $e->getMessage();
            $debug = $this->_settings->isDebugActive();
            if ($debug) {
                echo htmlentities($this->_error);
            }
            return false;
        }
    }

    /**
     * Extracts a node from the DOMDocument (Logout Response Menssage)
     *
     * @param string $query Xpath Expresion
     *
     * @return DOMNodeList The queried node
     */
    private function _query($query)
    {
        return OneLogin_Saml2_Utils::query($this->document, $query);

    }

    /**
     * Generates a Logout Response object.
     *
     * @param string $inResponseTo InResponseTo value for the Logout Response.
     */
    public function build($inResponseTo)
    {

        $spData = $this->_settings->getSPData();
        $idpData = $this->_settings->getIdPData();

        $this->id = OneLogin_Saml2_Utils::generateUniqueID();
        $issueInstant = OneLogin_Saml2_Utils::parseTime2SAML(time());

        $spEntityId = htmlspecialchars($spData['entityId'], ENT_QUOTES);
        $logoutResponse = <<<LOGOUTRESPONSE
<samlp:LogoutResponse xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                  xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                  ID="{$this->id}"
                  Version="2.0"
                  IssueInstant="{$issueInstant}"
                  Destination="{$idpData['singleLogoutService']['url']}"
                  InResponseTo="{$inResponseTo}"
                  >
    <saml:Issuer>{$spEntityId}</saml:Issuer>
    <samlp:Status>
        <samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success" />
    </samlp:Status>
</samlp:LogoutResponse>
LOGOUTRESPONSE;
        $this->_logoutResponse = $logoutResponse;
    }

    /**
     * Returns a Logout Response object.
     *
     * @param bool|null $deflate Whether or not we should 'gzdeflate' the response body before we return it.
     *
     * @return string Logout Response deflated and base64 encoded
     */
    public function getResponse($deflate = null)
    {
        $subject = $this->_logoutResponse;

        if (is_null($deflate)) {
            $deflate = $this->_settings->shouldCompressResponses();
        }

        if ($deflate) {
            $subject = gzdeflate($this->_logoutResponse);
        }
        return base64_encode($subject);
    }

    /**
     * After execute a validation process, if fails this method returns the cause.
     *
     * @return string Cause
     */
    public function getError()
    {
        return $this->_error;
    }

   /**
    * @return string the ID of the Response
    */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns the XML that will be sent as part of the response
     * or that was received at the SP
     *
     * @return string
     */
    public function getXML()
    {
        return $this->_logoutResponse;
    }
}
