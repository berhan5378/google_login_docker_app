<?php
require_once 'vendor/autoload.php';
session_start();

$client = new Google_Client();
$client->setClientId('YOUR_CLIENT_ID');
$client->setClientSecret('YOUR_CLIENT_SECRET');
$client->setRedirectUri('http://localhost/oauth2callback.php');
$client->addScope('https://www.googleapis.com/auth/admin.directory.user.readonly');
$client->addScope('https://www.googleapis.com/auth/userinfo.email');
$client->setAccessType('offline');
$client->setPrompt('consent');

$authUrl = $client->createAuthUrl();
echo "<a href='$authUrl'>Login with Google (School Gmail)</a>";