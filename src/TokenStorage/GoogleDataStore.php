<?php

namespace FSWebhooks\TokenStorage;

use Google\Cloud\Datastore\DatastoreClient;

class GoogleDataStore implements TokenStorageAdapter
{
    private $datastore;
    private $config_key;
    public function __construct(){
        $this->datastore = new DatastoreClient();
        $this->config_key = $this->datastore->key('MauticToken', 'token_data');
    }

    public function save_token(string $access_token, int $expires, string $refresh_token): bool
    {
        $token_data = $this->datastore->entity($this->config_key, [
            'access_token'  => $access_token,
            'expires'       => $expires,
            'refresh_token' => $refresh_token,
        ]);
        $this->datastore->upsert($token_data);
        return true;
    }

    public function load_token_data(): ?array
    {
        $token_data = $this->datastore->lookup($this->config_key);
        if(!is_null($token_data)){
            return [
                'access_token'  => $token_data['access_token'],
                'expires'       => $token_data['expires'],
                'refresh_token' => $token_data['refresh_token'],
            ];
        }
        return null;
    }
}