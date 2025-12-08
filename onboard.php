<?php
/**
 * Xavier Platform - Onboarding API Test Script
 * ---------------------------------------------
 * Usage: php test_onboard.php
 * Make sure Laravel is running (php artisan serve)
 * and the /api/onboard route is available.
 */

$apiUrl = 'http://127.0.0.1:8000/api/onboard'; // Local API URL
date_default_timezone_set('Africa/Lagos');

// === Generate unique email for each test ===
$uniqueEmail = 'aisha' . rand(1000, 9999) . '@example.test';

// === Payload (switch between BVN or vNIN) ===
$payload = [
    "first_name" => "Aisha",
    "surname" => "Ogunleye",
    "email" => $uniqueEmail,
    "mobile" => "+2348089001122",
    "dob" => "1994-07-15",
    "id_type" => "vnin",            // 'bvn' or 'vnin'
    "id_value" => "12345678901",    // sample fake NIN for test
    "password" => "SecurePass2025!"
];

// === Encode as JSON ===
$dataJson = json_encode($payload, JSON_PRETTY_PRINT);

// === Initialize CURL ===
$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_POSTFIELDS => $dataJson,
    CURLOPT_TIMEOUT => 60
]);

// === Execute request ===
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// === Decode JSON response ===
$result = json_decode($response, true);

// === Display summary ===
echo "-------------------------------------\n";
echo " POST $apiUrl\n";
echo "-------------------------------------\n";
echo "HTTP Status: $httpCode\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "-------------------------------------\n";
echo "Request Payload:\n";
print_r($payload);
echo "-------------------------------------\n";

if ($error) {
    echo "CURL ERROR: $error\n";
    exit;
}

echo "Response:\n";
print_r($result);
echo "-------------------------------------\n";

// === Interpretation ===
if ($httpCode >= 200 && $httpCode < 300 && !empty($result['success'])) {
    echo "✅ SUCCESS: Verification passed and user onboarded.\n";
} elseif ($httpCode === 422) {
    echo "⚠️ VALIDATION ERROR: Missing or invalid fields.\n";
} elseif ($httpCode === 401 || $httpCode === 403) {
    echo "🚫 AUTH ERROR: Invalid QoreID credentials or token.\n";
} elseif ($httpCode >= 500) {
    echo "❌ SERVER ERROR: Check Laravel logs for backend trace.\n";
} else {
    echo "❌ FAILED: HTTP $httpCode. See response above for details.\n";
}

echo PHP_EOL;
