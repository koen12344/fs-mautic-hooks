<?php

use FSWebhooks\MauticHooks;
use FSWebhooks\TokenStorage\GoogleDataStore;
use Mautic\MauticApi;


header('Content-type: application/json');

$settings = require('config.php');

if(empty($_REQUEST['token']) || $_REQUEST['token'] != $settings['security_token']){
    die(json_encode(['error' => 'The security token is incorrect']));
}

require_once "vendor/autoload.php";

$token_storage = new GoogleDataStore();
$mautic_auth = new \FSWebhooks\MauticAuth($token_storage, $settings['mautic']);

if($json = json_decode(file_get_contents("php://input"))){
    $auth = $mautic_auth->get_auth(false);
    $mautic_api = new MauticApi();

    $api = new MauticHooks($json, $settings['mautic']['baseUrl'], $auth);
    echo $api->process();
}