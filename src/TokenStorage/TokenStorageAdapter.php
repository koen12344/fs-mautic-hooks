<?php

namespace FSWebhooks\TokenStorage;

interface TokenStorageAdapter
{
    /**
     * @param string $access_token
     * @param int $expires
     * @param string $refresh_token
     * @return mixed
     */
    public function save_token(string $access_token, int $expires, string $refresh_token): bool;

    /**
     * @return array|bool Array containing token data
     * [
     *  'access_token'  => '1234abcd',
     *  'expires'       => 1234,
     *  'refresh_token' => '1234abcd',
     * ]
     */
    public function load_token_data(): ?array;
}