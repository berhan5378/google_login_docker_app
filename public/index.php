<?php
// index.php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';


// --- Configuration ---
// REPLACE THESE WITH YOUR ACTUAL VALUES FROM Google Cloud Console
$clientId = getenv('GOOGLE_CLIENT_ID');
$clientSecret = getenv('GOOGLE_CLIENT_SECRET');
$redirectUri = getenv('REDIRECT_URI');  
$persistentRefreshToken = getenv('GOOGLE_REFRESH_TOKEN');  

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
 
$accessToken = null;

if ($persistentRefreshToken) {
    $access_token = $client->fetchAccessTokenWithRefreshToken($persistentRefreshToken);

    if (isset($access_token['access_token'])) {
        $client->setAccessToken($access_token);
    } 
} 
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
    <title>Search Contacts</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #f9f9f9; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        form { margin-bottom: 20px;display: flex;align-items: center;gap: 10px; }
        input[type="text"] { width: 70%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        input[type="submit"] { padding: 10px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
        input[type="submit"]:hover { background-color: #45a049; }
        .contact-list { margin-top: 20px; }
        .contact-item { border: 1px solid #eee; padding: 10px; margin-bottom: 10px; border-radius: 4px; background-color: #fff; }
        .contact-name { font-weight: bold; color: #007bff; }
        .contact-detail { font-size: 0.9em; color: #555; margin-left: 15px;}
        .no-results { color: #888; } 
        @media (max-width: 425px) {
            body{
                 margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Search Contacts</h1>

        <form method="POST" action=""> 
            <input type="text" id="search_query" name="search_query" value="<?= htmlspecialchars($search_query); ?>" placeholder="Enter name to search...">
            <input type="submit" value="Search">
        </form>

        <?php if (!empty($search_query)) : ?>
            <h2>Results for "<?= htmlspecialchars($search_query); ?>"</h2>
            <div class="contact-list">
                <?php

                try {
                    // Initialize the Google People Service
                    $service = new Google_Service_PeopleService($client);
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
                        echo "<p class='no-results'>No contacts found matching '" . htmlspecialchars($search_query) . "'.</p>";
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
                    echo "<p style='color: red;'>Error fetching contacts </p>"; 
                } catch (Throwable $e) {
                    echo "<p style='color: red;'>An unexpected error occurred</p>";
                }
                ?>
            </div>
        <?php else : ?>
            <p>Enter a name in the search box above to find contacts.</p>
        <?php endif; ?>
    </div>
</body>
</html>