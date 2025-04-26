<?php
// API Middleware for WPJobBoard - JSON version

// Allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Authorization");
header("Content-Type: application/json");

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log incoming requests
$logFile = 'api-requests.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . ' - Request received: ' . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);

// API Configuration
define('API_URL_HOME', 'https://kdx3d1839g-staging.onrocket.site');
define('API_URL', 'https://kdx3d1839g-staging.onrocket.site/wpjobboard');
define('API_ENCRYPTION_KEY', '33a8909bf6e588582400b0e2700cd9f5');
define('API_CYPHER', 'aes-128-cbc');
define('API_ACCESS_TOKEN', '9ee91cb39702e4d7b1f1969e03621bb960b8d195');

// Encryption function
function encrypt($key, $plaintext) {
    return bin2hex(openssl_encrypt(
        $plaintext,
        API_CYPHER,
        $key,
        0,
        substr(hash('sha256', API_URL_HOME), 0, 16)
    ));
}

// Get JSON input
$jsonInput = file_get_contents('php://input');
$inputData = json_decode($jsonInput, true);

// Log the JSON input
file_put_contents($logFile, 'JSON Input: ' . $jsonInput . "\n", FILE_APPEND);

// Determine the endpoint
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : 'jobs';
$url = API_URL . '/api/' . $endpoint . '/';

// Set up the headers with encrypted token
$headers = array(
    "X-Authorization: " . encrypt(API_ENCRYPTION_KEY, API_ACCESS_TOKEN),
    "Content-Type: application/x-www-form-urlencoded"
);

// Initialize cURL session
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_VERBOSE, false);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

// Handle OPTIONS requests (for CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit;
}

// Handle HTTP method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    curl_setopt($ch, CURLOPT_POST, 1);
    
    // Prepare the form data from JSON input
    $postData = array();
    
    if ($inputData && is_array($inputData)) {
        foreach ($inputData as $key => $value) {
            if (strpos($key, 'wpjb-job') === 0) {
                $postData[$key] = $value;
            } else {
                $postData['wpjb-job[' . $key . ']'] = $value;
            }
        }
    }
    
    // Log the prepared data
    file_put_contents($logFile, 'Prepared Post Data: ' . print_r($postData, true) . "\n", FILE_APPEND);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // No additional settings needed for GET
}

// Execute the cURL request
$result = curl_exec($ch);

// Log the raw response
file_put_contents($logFile, 'Raw API Response: ' . $result . "\n\n", FILE_APPEND);

// Check for cURL errors
if (curl_errno($ch)) {
    $errorResponse = ['error' => 'cURL Error: ' . curl_error($ch)];
    echo json_encode($errorResponse);
} else {
    // Try to parse the result as JSON
    $jsonResult = json_decode($result, true);
    
    // If it's valid JSON, return it as-is, otherwise wrap it in a JSON structure
    if ($jsonResult !== null || trim($result) === '[]' || trim($result) === '{}') {
        echo $result;
    } else {
        $wrappedResponse = ['data' => $result, 'raw_html' => true];
        echo json_encode($wrappedResponse);
    }
}

// Additional debugging info
$info = curl_getinfo($ch);
file_put_contents($logFile, 'cURL Info: ' . print_r($info, true) . "\n\n", FILE_APPEND);

curl_close($ch);
