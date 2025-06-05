<?php
// index.php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';


// --- Configuration ---
// REPLACE THESE WITH YOUR ACTUAL VALUES FROM Google Cloud Console
$clientId = getenv('GOOGLE_CLIENT_ID');
$clientSecret = getenv('GOOGLE_CLIENT_SECRET');
$redirectUri = getenv('REDIRECT_URI'); // This must match your Google Cloud Console setting 
// Define the path for the token file (MUST MATCH oauth2callback.php)
define('TOKEN_FILE', __DIR__ . '/token.json');

// Google API Client Setup
$client = new Google_Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);

// Add all relevant scopes to ensure comprehensive access
$client->addScope(Google_Service_PeopleService::CONTACTS_READONLY);         // For "My Contacts"
$client->addScope(Google_Service_PeopleService::CONTACTS_OTHER_READONLY);   // For "Other Contacts"
$client->addScope(Google_Service_PeopleService::DIRECTORY_READONLY);       // For searching organizational directories

$client->setAccessType('offline'); // Essential to get a refresh token

// --- Token Handling: Prioritize file storage over session for persistence ---
$accessToken = null;
if (file_exists(TOKEN_FILE)) {
    $tokenData = json_decode(file_get_contents(TOKEN_FILE), true);
    if (is_array($tokenData) && isset($tokenData['access_token'])) {
        $client->setAccessToken($tokenData); // Set the full token array
        $accessToken = $tokenData; // Store it for local checks
    }
}

// If no access token from file, check session (might be from an older session)
if (empty($accessToken) && isset($_SESSION['access_token'])) {
    $client->setAccessToken($_SESSION['access_token']);
    $accessToken = $_SESSION['access_token'];
}

// --- Check for valid access token or attempt refresh ---
if ($accessToken && $client->isAccessTokenExpired()) {
    // If access token is expired, try to refresh using the refresh token from file
    if (isset($accessToken['refresh_token']) && $accessToken['refresh_token']) {
        try {
            $client->fetchAccessTokenWithRefreshToken($accessToken['refresh_token']);
            $newAccessToken = $client->getAccessToken();
            // Store new access token to file for persistence
            file_put_contents(TOKEN_FILE, json_encode($newAccessToken));
            $_SESSION['access_token'] = $newAccessToken; // Also update session
        } catch (Exception $e) {
            // Refresh failed, token might be revoked or invalid. Clear and re-authorize.
            unlink(TOKEN_FILE); // Delete the invalid token file
            unset($_SESSION['access_token']);
            echo 'Error refreshing access token: ' . $e->getMessage() . '<br>';
            echo 'Please re-authorize your account.';
            echo "<a href='" . htmlspecialchars($client->createAuthUrl()) . "'><button>Re-authorize</button></a>";
            exit();
        }
    } else {
        // No refresh token available, force re-authorization
        echo 'Your session has expired or refresh token is missing. Please re-authorize your account.';
        if (file_exists(TOKEN_FILE)) unlink(TOKEN_FILE); // Clear any old token file
        unset($_SESSION['access_token']);
        echo "<a href='" . htmlspecialchars($client->createAuthUrl()) . "'><button>Re-authorize</button></a>";
        exit();
    }
} elseif (empty($accessToken) || !$client->getAccessToken()) {
    // No valid access token found, prompt for initial authorization
    $authUrl = $client->createAuthUrl();
    echo "<h1>Welcome to the Google Contacts Search App</h1>";
    echo "<h2>Please authorize your Google account to proceed:</h2>";
    echo "<a href='" . htmlspecialchars($authUrl) . "'><button>Authorize Google Account</button></a>";
    exit(); // Stop execution here, waiting for authorization
}

// Initialize the Google People Service
$service = new Google_Service_PeopleService($client);

// --- Search Form and Logic ---
$search_query = '';
if (isset($_POST['search_query']) && !empty(trim($_POST['search_query']))) {
    $search_query = trim($_POST['search_query']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Google Contacts</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #f9f9f9; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        form { margin-bottom: 20px; }
        input[type="text"] { width: 70%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        input[type="submit"] { padding: 10px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
        input[type="submit"]:hover { background-color: #45a049; }
        .contact-list { margin-top: 20px; }
        .contact-item { border: 1px solid #eee; padding: 10px; margin-bottom: 10px; border-radius: 4px; background-color: #fff; }
        .contact-name { font-weight: bold; color: #007bff; }
        .contact-detail { font-size: 0.9em; color: #555; margin-left: 15px;}
        .no-results { color: #888; }
        .logout-link { display: block; margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Search Your Google Contacts</h1>

        <form method="POST" action="">
            <label for="search_query">Search by Name:</label><br>
            <input type="text" id="search_query" name="search_query" value="<?= htmlspecialchars($search_query); ?>" placeholder="Enter name to search...">
            <input type="submit" value="Search">
        </form>

        <?php if (!empty($search_query)) : ?>
            <h2>Results for "<?= htmlspecialchars($search_query); ?>"</h2>
            <div class="contact-list">
                <?php
                try {
                    $found_contacts = [];
                    $unique_contacts_map = []; // To store unique contacts by resourceName

                    // 1. Search Personal Contacts (My Contacts & Other Contacts)
                    $personalConnections = $service->people->searchContacts([
                        'query' => $search_query,
                        'readMask' => 'names,phoneNumbers,emailAddresses',
                        'pageSize' => 50,
                    ]);

                    foreach ($personalConnections->getResults() as $searchResult) {
                        $person = $searchResult->getPerson();
                        if ($person && $person->getResourceName()) {
                            $unique_contacts_map[$person->getResourceName()] = $person;
                        }
                    }

                    // 2. Search Directory People (for organization contacts)
                    // Only perform this if a search query is provided
                    if (!empty($search_query)) {
                        $directoryPeople = $service->people->searchDirectoryPeople([
                            'query' => $search_query,
                            'readMask' => 'names,phoneNumbers,emailAddresses,organizations', // 'organizations' can show company details
                            'pageSize' => 50,
                            'sources' => ['DIRECTORY_SOURCE_TYPE_DOMAIN_PROFILE', 'DIRECTORY_SOURCE_TYPE_DOMAIN_CONTACT'],
                        ]);

                        foreach ($directoryPeople->getPeople() as $person) {
                            if ($person && $person->getResourceName()) {
                                // Add to map if not already present (prevents duplicates from personal contacts)
                                if (!isset($unique_contacts_map[$person->getResourceName()])) {
                                    $unique_contacts_map[$person->getResourceName()] = $person;
                                }
                            }
                        }
                    }

                    // Convert map to a flat array for iteration
                    $found_contacts = array_values($unique_contacts_map);


                    if (empty($found_contacts)) {
                        echo "<p class='no-results'>No contacts found matching '" . htmlspecialchars($search_query) . "' in your personal contacts or directory.</p>";
                    } else {
                        foreach ($found_contacts as $person) {
                            $displayName = 'No Name';
                            if ($names = $person->getNames()) {
                                // Prefer givenName + familyName if available, otherwise displayName
                                if (!empty($names[0]->getGivenName()) && !empty($names[0]->getFamilyName())) {
                                    $displayName = $names[0]->getGivenName() . ' ' . $names[0]->getFamilyName();
                                } else {
                                    $displayName = $names[0]->getDisplayName();
                                }
                            }

                            echo "<div class='contact-item'>";
                            echo "<p class='contact-name'>" . htmlspecialchars($displayName) . "</p>";

                            $has_phone = false;
                            if ($phoneNumbers = $person->getPhoneNumbers()) {
                                foreach ($phoneNumbers as $phoneNumber) {
                                    echo "<p class='contact-detail'>Phone: " . htmlspecialchars($phoneNumber->getValue()) . " (" . htmlspecialchars($phoneNumber->getType() ?: 'unknown') . ")</p>";
                                    $has_phone = true;
                                }
                            }

                            if (!$has_phone) {
                                echo "<p class='contact-detail'>No phone number available.</p>";
                            }

                            if ($emails = $person->getEmailAddresses()) {
                                foreach ($emails as $email) {
                                    echo "<p class='contact-detail'>Email: " . htmlspecialchars($email->getValue()) . "</p>";
                                }
                            }

                            // Display organization and department details
                            if ($organizations = $person->getOrganizations()) {
                                foreach ($organizations as $org) {
                                    if ($org->getName()) {
                                        echo "<p class='contact-detail'>Organization: " . htmlspecialchars($org->getName()) . "</p>";
                                    }
                                    if ($org->getDepartment()) { // Check if department exists
                                        echo "<p class='contact-detail'>Department: " . htmlspecialchars($org->getDepartment()) . "</p>";
                                    }
                                    if ($org->getTitle()) { // Also display job title if available
                                        echo "<p class='contact-detail'>Title: " . htmlspecialchars($org->getTitle()) . "</p>";
                                    }
                                }
                            }
                            echo "</div>"; // close contact-item
                        }
                    }
                } catch (Google_Service_Exception $e) {
                    echo "<p style='color: red;'>Error fetching contacts: " . htmlspecialchars($e->getMessage()) . "</p>";
                    if ($e->getCode() == 401 || $e->getCode() == 403) { // Unauthorized or Permission Denied
                         // Clear the token file and session on auth errors
                         if (file_exists(TOKEN_FILE)) unlink(TOKEN_FILE);
                         unset($_SESSION['access_token']);
                         echo "<p><a href='" . htmlspecialchars($client->createAuthUrl()) . "'>Please re-authorize your account.</a></p>";
                    }
                } catch (Exception $e) {
                    echo "<p style='color: red;'>An unexpected error occurred: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
                ?>
            </div>
        <?php else : ?>
            <p>Enter a name in the search box above to find contacts.</p>
        <?php endif; ?>

        <div class="logout-link">
            <p><a href="?logout=true">Log out and clear session</a></p>
        </div>
    </div>
</body>
</html>

<?php
// Handle simple logout
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    session_unset(); // Unset all session variables
    session_destroy(); // Destroy the session
    if (file_exists(TOKEN_FILE)) { // Delete the persistent token file
        unlink(TOKEN_FILE);
    }
    header('Location: ' . filter_var($redirectUri, FILTER_SANITIZE_URL)); // Redirect to clear the URL
    exit(); // Always exit after a header redirect
}
?>