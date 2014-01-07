<?php

namespace OAuth\Common\Storage;

use OAuth\Common\Token\TokenInterface;
use OAuth\Common\Storage\Exception\TokenNotFoundException;

/**
 * All token storage providers must implement this interface.
 */
interface TokenStorageInterface
{
    /**
     * @param string $service
     *
     * @return TokenInterface
     *
     * @throws TokenNotFoundException
     */
    public function retrieveAccessToken($service);

    /**
     * @param string         $service
     * @param TokenInterface $token
     *
     * @return TokenStorageInterface
     */
    public function storeAccessToken($service, TokenInterface $token);

    /**
     * @param string $service
     *
     * @return bool
     */
    public function hasAccessToken($service);

    /**
     * Delete the users token. Aka, log out.
     *
     * @param string $service
     *
     * @return TokenStorageInterface
     */
    public function clearToken($service);

    /**
     * Delete *ALL* user tokens. Use with care. Most of the time you will likely
     * want to use clearToken() instead.
     *
     * @return TokenStorageInterface
     */
    public function clearAllTokens();
}
