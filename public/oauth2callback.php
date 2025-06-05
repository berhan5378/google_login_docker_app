<?php
// oauth2callback.php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';


// --- Configuration (must match index.php) ---
$clientId = getenv('GOOGLE_CLIENT_ID');
$clientSecret = getenv('GOOGLE_CLIENT_SECRET');
$redirectUri = getenv('REDIRECT_URI');

// Define the path for the token file
define('TOKEN_FILE', __DIR__ . '/token.json'); // Adjust path if necessary

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

    // --- IMPORTANT: Store full token data to file ---
    // If a refresh token is present, ensure it's saved.
    // The access_token array received from fetchAccessTokenWithAuthCode will contain refresh_token
    // ONLY on the very first authorization with access_type 'offline'.
    // Subsequent calls will only contain access_token and related expiry info.
    file_put_contents(TOKEN_FILE, json_encode($token));
    // --- END IMPORTANT ---

    // Also store to session for immediate use, though file storage is for persistence
    $_SESSION['access_token'] = $token;

    // Redirect to index.php to remove the code parameter from the URL
    header('Location: ' . filter_var('index.php', FILTER_SANITIZE_URL));
    exit();
} else {
    echo "Authorization failed!";
    // You might want to redirect to index.php or display an error
    header('Location: ' . filter_var($redirectUri, FILTER_SANITIZE_URL));
    exit();
}