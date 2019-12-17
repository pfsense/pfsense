<?php

class OneLogin_Saml_AuthRequest
{

    /**
     * @var OneLogin_Saml2_Auth object
     */
    protected $auth;

    /**
     * Constructs the OneLogin_Saml2_Auth, initializing
     * the SP SAML instance.
     *
     * @param array|object $settings SAML Toolkit Settings
     */
    public function __construct($settings)
    {
        $this->auth = new OneLogin_Saml2_Auth($settings);
    }

    /**
     * Obtains the SSO URL containing the AuthRequest
     * message deflated.
     *
     * @param string|null $returnTo
     *
     * @return string
     *
     * @throws OneLogin_Saml2_Error
     */
    public function getRedirectUrl($returnTo = null)
    {
        $settings = $this->auth->getSettings();
        $authnRequest = new OneLogin_Saml2_AuthnRequest($settings);
        $parameters = array('SAMLRequest' => $authnRequest->getRequest());
        if (!empty($returnTo)) {
            $parameters['RelayState'] = $returnTo;
        } else {
            $parameters['RelayState'] = OneLogin_Saml2_Utils::getSelfRoutedURLNoQuery();
        }
        $url = OneLogin_Saml2_Utils::redirect($this->auth->getSSOurl(), $parameters, true);
        return $url;
    }

    /**
     * @return string
     */
    protected function _generateUniqueID()
    {
        return OneLogin_Saml2_Utils::generateUniqueID();
    }

    /**
     * @return string
     */
    protected function _getTimestamp()
    {
        $date = new DateTime('now', new DateTimeZone('UTC'));
        $timestamp = $date->format("Y-m-d\TH:i:s\Z");
        return $timestamp;
    }
}
