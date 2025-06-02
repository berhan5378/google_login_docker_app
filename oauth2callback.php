<?php
require_once 'vendor/autoload.php';
session_start();

$client = new Google_Client();
$client->setClientId('YOUR_CLIENT_ID');
$client->setClientSecret('YOUR_CLIENT_SECRET');
$client->setRedirectUri('http://localhost/oauth2callback.php');
$client->addScope('https://www.googleapis.com/auth/admin.directory.user.readonly');
$client->addScope('https://www.googleapis.com/auth/userinfo.email');

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        die('Login error: ' . $token['error_description']);
    }

    $_SESSION['access_token'] = $token;

    if (!empty($token['refresh_token'])) {
        $_SESSION['refresh_token'] = $token['refresh_token'];
    }

    header('Location: search.php');
    exit;
} else {
    echo "No code returned. <a href='index.php'>Try again</a>";
}