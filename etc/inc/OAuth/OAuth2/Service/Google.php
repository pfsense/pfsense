<?php

namespace OAuth\OAuth2\Service;

use OAuth\OAuth2\Token\StdOAuth2Token;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;

class Google extends AbstractService
{
    /**
     * Defined scopes -- Google has way too many Application Programming Interfaces
     */
    const SCOPE_ADSENSE = 'https://www.googleapis.com/auth/adsense';
    const SCOPE_ADWORDS = 'https://adwords.google.com/api/adwords/';
    const SCOPE_GAN = 'https://www.googleapis.com/auth/gan'; // google affiliate network...?
    const SCOPE_ANALYTICS = 'https://www.googleapis.com/auth/analytics.readonly';
    const SCOPE_BOOKS = 'https://www.googleapis.com/auth/books';
    const SCOPE_BLOGGER = 'https://www.googleapis.com/auth/blogger';
    const SCOPE_CALENDAR = 'https://www.googleapis.com/auth/calendar';
    const SCOPE_CLOUDSTORAGE = 'https://www.googleapis.com/auth/devstorage.read_write';
    const SCOPE_CONTACT = 'https://www.google.com/m8/feeds/';
    const SCOPE_CONTENTFORSHOPPING = 'https://www.googleapis.com/auth/structuredcontent'; // what even is this
    const SCOPE_CHROMEWEBSTORE = 'https://www.googleapis.com/auth/chromewebstore.readonly';
    const SCOPE_DOCUMENTSLIST = 'https://docs.google.com/feeds/';
    const SCOPE_GOOGLEDRIVE = 'https://www.googleapis.com/auth/drive';
    const SCOPE_GOOGLEDRIVE_FILES = 'https://www.googleapis.com/auth/drive.file';
    const SCOPE_GMAIL = 'https://mail.google.com/mail/feed/atom';
    const SCOPE_GPLUS_ME = 'https://www.googleapis.com/auth/plus.me';
    const SCOPE_GPLUS_LOGIN = 'https://www.googleapis.com/auth/plus.login';
    const SCOPE_GROUPS_PROVISIONING = 'https://apps-apis.google.com/a/feeds/groups/';
    const SCOPE_GOOGLELATITUDE =
        'https://www.googleapis.com/auth/latitude.all.best https://www.googleapis.com/auth/latitude.all.city';
        // creepy stalker api...
    const SCOPE_MODERATOR = 'https://www.googleapis.com/auth/moderator';
    const SCOPE_NICKNAME_PROVISIONING = 'https://apps-apis.google.com/a/feeds/alias/';
    const SCOPE_ORKUT = 'https://www.googleapis.com/auth/orkut'; // evidently orkut still exists. who knew?
    const SCOPE_PICASAWEB = 'https://picasaweb.google.com/data/';
    const SCOPE_SITES = 'https://sites.google.com/feeds/';
    const SCOPE_SPREADSHEETS = 'https://spreadsheets.google.com/feeds/';
    const SCOPE_TASKS = 'https://www.googleapis.com/auth/tasks';
    const SCOPE_URLSHORTENER = 'https://www.googleapis.com/auth/urlshortener';
    const SCOPE_USERINFO_EMAIL = 'https://www.googleapis.com/auth/userinfo.email';
    const SCOPE_USERINFO_PROFILE = 'https://www.googleapis.com/auth/userinfo.profile';
    const SCOPE_USER_PROVISIONING = 'https://apps-apis.google.com/a/feeds/user/';
    const SCOPE_WEBMASTERTOOLS = 'https://www.google.com/webmasters/tools/feeds/';
    const SCOPE_YOUTUBE = 'https://gdata.youtube.com';
    
    const SCOPE_GLASS_TIMELINE = 'https://www.googleapis.com/auth/glass.timeline';
    const SCOPE_GLASS_LOCATION = 'https://www.googleapis.com/auth/glass.location';
    


    /**
     * {@inheritdoc}
     */
    public function getAuthorizationEndpoint()
    {
        return new Uri('https://accounts.google.com/o/oauth2/auth');
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessTokenEndpoint()
    {
        return new Uri('https://accounts.google.com/o/oauth2/token');
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
        $token->setLifetime($data['expires_in']);

        if (isset($data['refresh_token'])) {
            $token->setRefreshToken($data['refresh_token']);
            unset($data['refresh_token']);
        }

        unset($data['access_token']);
        unset($data['expires_in']);

        $token->setExtraParams($data);

        return $token;
    }
}
