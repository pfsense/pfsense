<?php

namespace OAuth\OAuth2\Service;

use OAuth\OAuth2\Token\StdOAuth2Token;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use OAuth\Common\Consumer\CredentialsInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Storage\TokenStorageInterface;
use OAuth\Common\Http\Uri\UriInterface;

class Facebook extends AbstractService
{
    /**
     * Defined scopes
     *
     * If you don't think this is scary you should not be allowed on the web at all
     *
     * @link https://developers.facebook.com/docs/reference/login/
     */
    // email scopes
    const SCOPE_EMAIL                         = 'email';
    // extended permissions
    const SCOPE_READ_FRIENDLIST               = 'read_friendlists';
    const SCOPE_READ_INSIGHTS                 = 'read_insights';
    const SCOPE_READ_MAILBOX                  = 'read_mailbox';
    const SCOPE_READ_REQUESTS                 = 'read_requests';
    const SCOPE_READ_STREAM                   = 'read_stream';
    const SCOPE_XMPP_LOGIN                    = 'xmpp_login';
    const SCOPE_USER_ONLINE_PRESENCE          = 'user_online_presence';
    const SCOPE_FRIENDS_ONLINE_PRESENCE       = 'friends_online_presence';
    const SCOPE_ADS_MANAGEMENT                = 'ads_management';
    const SCOPE_CREATE_EVENT                  = 'create_event';
    const SCOPE_MANAGE_FRIENDLIST             = 'manage_friendlists';
    const SCOPE_MANAGE_NOTIFICATIONS          = 'manage_notifications';
    const SCOPE_PUBLISH_ACTIONS               = 'publish_actions';
    const SCOPE_PUBLISH_STREAM                = 'publish_stream';
    const SCOPE_RSVP_EVENT                    = 'rsvp_event';
    // Extended Profile Properties
    const SCOPE_USER_ABOUT                    = 'user_about_me';
    const SCOPE_FRIENDS_ABOUT                 = 'friends_about_me';
    const SCOPE_USER_ACTIVITIES               = 'user_activities';
    const SCOPE_FRIENDS_ACTIVITIES            = 'friends_activities';
    const SCOPE_USER_BIRTHDAY                 = 'user_birthday';
    const SCOPE_FRIENDS_BIRTHDAY              = 'friends_birthday';
    const SCOPE_USER_CHECKINS                 = 'user_checkins';
    const SCOPE_FRIENDS_CHECKINS              = 'friends_checkins';
    const SCOPE_USER_EDUCATION                = 'user_education_history';
    const SCOPE_FRIENDS_EDUCATION             = 'friends_education_history';
    const SCOPE_USER_EVENTS                   = 'user_events';
    const SCOPE_FRIENDS_EVENTS                = 'friends_events';
    const SCOPE_USER_GROUPS                   = 'user_groups';
    const SCOPE_FRIENDS_GROUPS                = 'friends_groups';
    const SCOPE_USER_HOMETOWN                 = 'user_hometown';
    const SCOPE_FRIENDS_HOMETOWN              = 'friends_hometown';
    const SCOPE_USER_INTERESTS                = 'user_interests';
    const SCOPE_FRIEND_INTERESTS              = 'friends_interests';
    const SCOPE_USER_LIKES                    = 'user_likes';
    const SCOPE_FRIENDS_LIKES                 = 'friends_likes';
    const SCOPE_USER_LOCATION                 = 'user_location';
    const SCOPE_FRIENDS_LOCATION              = 'friends_location';
    const SCOPE_USER_NOTES                    = 'user_notes';
    const SCOPE_FRIENDS_NOTES                 = 'friends_notes';
    const SCOPE_USER_PHOTOS                   = 'user_photos';
    const SCOPE_FRIENDS_PHOTOS                = 'friends_photos';
    const SCOPE_USER_QUESTIONS                = 'user_questions';
    const SCOPE_FRIENDS_QUESTIONS             = 'friends_questions';
    const SCOPE_USER_RELATIONSHIPS            = 'user_relationships';
    const SCOPE_FRIENDS_RELATIONSHIPS         = 'friends_relationships';
    const SCOPE_USER_RELATIONSHIPS_DETAILS    = 'user_relationship_details';
    const SCOPE_FRIENDS_RELATIONSHIPS_DETAILS = 'friends_relationship_details';
    const SCOPE_USER_RELIGION                 = 'user_religion_politics';
    const SCOPE_FRIENDS_RELIGION              = 'friends_religion_politics';
    const SCOPE_USER_STATUS                   = 'user_status';
    const SCOPE_FRIENDS_STATUS                = 'friends_status';
    const SCOPE_USER_SUBSCRIPTIONS            = 'user_subscriptions';
    const SCOPE_FRIENDS_SUBSCRIPTIONS         = 'friends_subscriptions';
    const SCOPE_USER_VIDEOS                   = 'user_videos';
    const SCOPE_FRIENDS_VIDEOS                = 'friends_videos';
    const SCOPE_USER_WEBSITE                  = 'user_website';
    const SCOPE_FRIENDS_WEBSITE               = 'friends_website';
    const SCOPE_USER_WORK                     = 'user_work_history';
    const SCOPE_FRIENDS_WORK                  = 'friends_work_history';
    // Open Graph Permissions
    const SCOPE_USER_MUSIC                    = 'user_actions.music';
    const SCOPE_FRIENDS_MUSIC                 = 'friends_actions.music';
    const SCOPE_USER_NEWS                     = 'user_actions.news';
    const SCOPE_FRIENDS_NEWS                  = 'friends_actions.news';
    const SCOPE_USER_VIDEO                    = 'user_actions.video';
    const SCOPE_FREINDS_VIDEO                 = 'friends_actions.video';
    const SCOPE_USER_APP                      = 'user_actions:APP_NAMESPACE';
    const SCOPE_FRIENDS_APP                   = 'friends_actions:APP_NAMESPACE';
    const SCOPE_USER_GAMES                    = 'user_games_activity';
    const SCOPE_FRIENDS_GAMES                 = 'friends_games_activity';
    //Page Permissions
    const SCOPE_PAGES                         = 'manage_pages';

    public function __construct(
        CredentialsInterface $credentials,
        ClientInterface $httpClient,
        TokenStorageInterface $storage,
        $scopes = array(),
        UriInterface $baseApiUri = null
    ) {
        parent::__construct($credentials, $httpClient, $storage, $scopes, $baseApiUri);

        if (null === $baseApiUri) {
            $this->baseApiUri = new Uri('https://graph.facebook.com/');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorizationEndpoint()
    {
        return new Uri('https://www.facebook.com/dialog/oauth');
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessTokenEndpoint()
    {
        return new Uri('https://graph.facebook.com/oauth/access_token');
    }

    /**
     * {@inheritdoc}
     */
    protected function parseAccessTokenResponse($responseBody)
    {
        // Facebook gives us a query string ... Oh wait. JSON is too simple, understand ?
        parse_str($responseBody, $data);

        if (null === $data || !is_array($data)) {
            throw new TokenResponseException('Unable to parse response.');
        } elseif (isset($data['error'])) {
            throw new TokenResponseException('Error in retrieving token: "' . $data['error'] . '"');
        }

        $token = new StdOAuth2Token();
        $token->setAccessToken($data['access_token']);
        $token->setLifeTime($data['expires']);

        if (isset($data['refresh_token'])) {
            $token->setRefreshToken($data['refresh_token']);
            unset($data['refresh_token']);
        }

        unset($data['access_token']);
        unset($data['expires']);

        $token->setExtraParams($data);

        return $token;
    }
}
