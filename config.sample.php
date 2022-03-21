<?php

$security_token = '1234abcd'; //Security token to be appended to the URL to prevent unauthorized requests

$mautic_settings = [
    'baseUrl'       => 'https://url.to-your-mautic-install.com', //Without trailing slash
    'clientKey'     => '1234abcd', //Mautic client key
    'clientSecret'  => '1234abcd', //Mautic client secret
    /**
     * Url to /authorize. https://path-to-your-app-engine-app.appspot.com/authorize
     * It may be easiest to deploy your app first to get the URL to it, then update this file with the appropriate URL
     * and redeploy
     */
    'callback'      => '',
];