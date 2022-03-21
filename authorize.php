<?php

use FSWebhooks\TokenStorage\GoogleDataStore;

session_start();

$settings = require('config.php');

require_once "vendor/autoload.php";

$token_storage = new GoogleDataStore();
$mautic_auth = new \FSWebhooks\MauticAuth($token_storage, $settings['mautic']);

$auth = $mautic_auth->get_auth();
if($auth->isAuthorized()){
    echo 'Everybody, come out, quick! Look at the lights! - Clark Griswold';
};