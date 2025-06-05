<?php
// oauth2callback.php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';


// --- Configuration (must match index.php) ---
$clientId = getenv('GOOGLE_CLIENT_ID');
$clientSecret = getenv('GOOGLE_CLIENT_SECRET');
$redirectUri = getenv('REDIRECT_URI');
 
$client = new Google_Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
// Ensure scopes match index.php for consistency
$client->addScope(Google_Service_PeopleService::CONTACTS_READONLY);
$client->addScope(Google_Service_PeopleService::CONTACTS_OTHER_READONLY);
$client->addScope(Google_Service_PeopleService::DIRECTORY_READONLY);
$client->setAccessType('offline'); // Essential to get a refresh token

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    // Also store to session for immediate use, though file storage is for persistence
    $_SESSION['access_token'] = $token;

    // Redirect to index.php to remove the code parameter from the URL
    header('Location: ' . filter_var($redirectUri, FILTER_SANITIZE_URL));
    exit();
} else {
    
    header('Location: ' . filter_var($redirectUri, FILTER_SANITIZE_URL));
    exit();
}