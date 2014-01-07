<?php

use OAuth\OAuth2\Service\Google;
use OAuth\Common\Storage\Session;
use OAuth\Common\Consumer\Credentials;

function authenticate($key, $secret) {
  require_once __DIR__ . '/../bootstrap.php';

  $servicesCredentials = array('key' => '',
                               'secret' => '');

  // Session storage
  $storage = new Session();
  $serviceFactory = new \OAuth\ServiceFactory();

  $uriFactory = new \OAuth\Common\Http\Uri\UriFactory();
  $currentUri = $uriFactory->createFromSuperGlobalArray($_SERVER);
  $currentUri->setQuery('');

  // Setup the credentials for the requests
  $credentials = new Credentials($key, $secret, $currentUri->getAbsoluteUri());

  // Instantiate the Google service using the credentials, http client and storage mechanism for the token
  /** @var $googleService Google */
  $googleService = $serviceFactory->createService('google', $credentials,
                                                  $storage, array('userinfo_email', 'userinfo_profile'));

  if (preg_match('/code=([^&]+)/', $_GET['redirurl'], $results)) {
    $token = $results[1];
  }

  if ($token) {
    // This was a callback request from google, get the token
    $googleService->requestAccessToken($token);

    // Send a request with it
    if (json_decode($googleService->request('https://www.googleapis.com/oauth2/v1/userinfo'), true)) {
      return $token;
    }
  } else {
    $url = $googleService->getAuthorizationUri();
    header('Location: ' . $url);
  }
}