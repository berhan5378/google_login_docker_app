<?php
require_once 'vendor/autoload.php';
session_start();

$client = new Google_Client(); 
$client->setClientId(getenv('GOOGLE_CLIENT_ID'));
$client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
$client->setRedirectUri(getenv('REDIRECT_URI'));

if (!isset($_SESSION['access_token'])) {
    header('Location: index.php');
    exit;
}

$client->setAccessToken($_SESSION['access_token']);

if ($client->isAccessTokenExpired()) {
    if (!empty($_SESSION['refresh_token'])) {
        $client->fetchAccessTokenWithRefreshToken($_SESSION['refresh_token']);
        $_SESSION['access_token'] = $client->getAccessToken();
    } else {
        session_destroy();
        header('Location: index.php');
        exit;
    }
}

$service = new Google_Service_Directory($client);
?>

<form method="GET">
    <input type="text" name="q" placeholder="Search name" />
    <button type="submit">Search</button>
</form>

<?php
if (!empty($_GET['q'])) {
    $query = $_GET['q'];

    try {
        $results = $service->users->listUsers([
            'customer' => 'my_customer',
            'query' => "name:$query",
            'maxResults' => 10,
        ]);

        foreach ($results->getUsers() as $user) {
            echo "<p><strong>" . $user->getName()->getFullName() . "</strong> - " . $user->getPrimaryEmail() . "</p>";
        }

    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}