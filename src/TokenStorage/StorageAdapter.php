<?php

namespace FSWebhooks\TokenStorage;

interface StorageAdapter
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

    /**
     * Get the ID of a Mautic custom item by passing the Freemius Install ID
     *
     * @param $freemius_id
     * @param $type
     * @return int|null Mautic custom item ID
     */
    public function get_mautic_id_by_freemius_id($freemius_id, $type): ?int;

    /**
     * Store a matching Freemius and Mautic custom item ID
     *
     * @param $freemius_id
     * @param $mautic_item_id
     * @param $type
     * @return bool Success
     */
    public function store_id_match($freemius_id, $mautic_item_id, $type): bool;
}