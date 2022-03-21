<?php

namespace FSWebhooks;

use FSWebhooks\TokenStorage\TokenStorageAdapter;
use Mautic\Auth\ApiAuth;

class MauticAuth
{
    /**
     * @var TokenStorageAdapter
     */
    private $adapter;
    private $settings;

    public function __construct(TokenStorageAdapter $adapter, $mautic_settings){
        $this->adapter = $adapter;
        $this->settings = $mautic_settings;
    }

    /**
     * @param bool $redirect
     * @return \Mautic\Auth\AuthInterface
     */
    public function get_auth(bool $redirect = true): \Mautic\Auth\AuthInterface
    {
        if($stored_token = $this->adapter->load_token_data()){
            $this->settings = array_merge($this->settings, [
                'accessToken'           => $stored_token['access_token'],
                'accessTokenExpires'    => $stored_token['expires'],
                'refreshToken'          => $stored_token['refresh_token'],
            ]);
        }

        $initAuth = new ApiAuth();
        $auth = $initAuth->newAuth($this->settings);

        $auth->validateAccessToken($redirect);

        if ($auth->accessTokenUpdated()) {
            $new_token = $auth->getAccessTokenData();
            $this->adapter->save_token($new_token['access_token'], $new_token['expires'], $new_token['refresh_token']);
        }

        return $auth;
    }

}