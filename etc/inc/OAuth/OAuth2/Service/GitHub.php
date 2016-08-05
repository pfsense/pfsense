<?php

namespace OAuth\OAuth2\Service;

use OAuth\OAuth2\Token\StdOAuth2Token;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use OAuth\Common\Consumer\CredentialsInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Storage\TokenStorageInterface;
use OAuth\Common\Http\Uri\UriInterface;

class GitHub extends AbstractService
{
    /**
     * Defined scopes, see http://developer.github.com/v3/oauth/ for definitions.
     */

    /**
     * Public read-only access (includes public user profile info, public repo info, and gists)
     */
    const SCOPE_READONLY = '';

    /**
     * Read/write access to profile info only.
     *
     * Includes SCOPE_USER_EMAIL and SCOPE_USER_FOLLOW.
     */
    const SCOPE_USER = 'user';

    /**
     * Read access to a user’s email addresses.
     */
    const SCOPE_USER_EMAIL = 'user:email';

    /**
     * Access to follow or unfollow other users.
     */
    const SCOPE_USER_FOLLOW = 'user:follow';

    /**
     * Read/write access to public repos and organizations.
     */
    const SCOPE_PUBLIC_REPO = 'public_repo';

    /**
     * Read/write access to public and private repos and organizations.
     *
     * Includes SCOPE_REPO_STATUS.
     */
    const SCOPE_REPO = 'repo';

    /**
     * Read/write access to public and private repository commit statuses. This scope is only necessary to grant other
     * users or services access to private repository commit statuses without granting access to the code. The repo and
     * public_repo scopes already include access to commit status for private and public repositories, respectively.
     */
    const SCOPE_REPO_STATUS = 'repo:status';

    /**
     * Delete access to adminable repositories.
     */
    const SCOPE_DELETE_REPO = 'delete_repo';

    /**
     * Read access to a user’s notifications. repo is accepted too.
     */
    const SCOPE_NOTIFICATIONS = 'notifications';

    /**
     * Write access to gists.
     */
    const SCOPE_GIST = 'gist';

    public function __construct(
        CredentialsInterface $credentials,
        ClientInterface $httpClient,
        TokenStorageInterface $storage,
        $scopes = array(),
        UriInterface $baseApiUri = null
    ) {
        parent::__construct($credentials, $httpClient, $storage, $scopes, $baseApiUri);

        if (null === $baseApiUri) {
            $this->baseApiUri = new Uri('https://api.github.com/');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorizationEndpoint()
    {
        return new Uri('https://github.com/login/oauth/authorize');
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessTokenEndpoint()
    {
        return new Uri('https://github.com/login/oauth/access_token');
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthorizationMethod()
    {
        return static::AUTHORIZATION_METHOD_QUERY_STRING;
    }

    /**
     * {@inheritdoc}
     */
    protected function parseAccessTokenResponse($responseBody)
    {
        $data = json_decode($responseBody, true);

        if (null === $data || !is_array($data)) {
            throw new TokenResponseException('Unable to parse response.');
        } elseif (isset($data['error'])) {
            throw new TokenResponseException('Error in retrieving token: "' . $data['error'] . '"');
        }

        $token = new StdOAuth2Token();
        $token->setAccessToken($data['access_token']);
        // Github tokens evidently never expire...
        $token->setEndOfLife(StdOAuth2Token::EOL_NEVER_EXPIRES);
        unset($data['access_token']);

        $token->setExtraParams($data);

        return $token;
    }

    /**
     * Used to configure response type -- we want JSON from github, default is query string format
     *
     * @return array
     */
    protected function getExtraOAuthHeaders()
    {
        return array('Accept' => 'application/json');
    }

    /**
     * Required for GitHub API calls.
     *
     * @return array
     */
    protected function getExtraApiHeaders()
    {
        return array('Accept' => 'application/vnd.github.beta+json');
    }
}
