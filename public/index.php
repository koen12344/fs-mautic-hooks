<?php
switch (@parse_url($_SERVER['REQUEST_URI'])['path']) {
    case '/':
        require '../hook.php';
        break;
    case '/authorize':
        require '../authorize.php';
        break;
    default:
        http_response_code(404);
        exit('Not Found');
}