<?php
// Disable timeouts (can run for several minutes)
@set_time_limit(0);
@ini_set('max_execution_time', '0');

require('../util/Connection.php');
require('../structures/District.php');
require('../util/SessionFunction.php');
require('../util/SessionCheck.php');
require('../util/Logger.php');
require('../util/Security.php');
require('Header.php');

function formatName($name) {
    if (!$name) return '';
    $name = preg_replace('/[^a-zA-Z0-9_ ]/', '', $name);
    $name = ucwords(strtolower($name));
    return trim($name);
}

// Gujarat API endpoint
$apiUrl = 'https://ipds.gujarat.gov.in/AnnachakraAPI/api/AnnaChakra/DistrictDetails';
$clientId = "AAfaAa6dfjfaof";
$clientPassword = "9ztxLKAfqk";

$apiData = [
    "ClientID" => $clientId,
    "ClientPassword" => $clientPassword
];

// Initialize cURL (no timeout)
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL            => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING       => '',
    CURLOPT_MAXREDIRS      => 10,
    CURLOPT_TIMEOUT        => 0,
    CURLOPT_CONNECTTIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_POSTFIELDS     => json_encode($apiData),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        "ClientID: $clientId",
        "ClientPassword: $clientPassword"
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
    CURLOPT_PROXY          => ''
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error    = curl_error($curl);
curl_close($curl);

if ($error) {
    echo "Error connecting to API: " . $error . "\n";
    exit();
}
if ($httpCode !== 200) {
    echo "API returned error code: " . $httpCode . "\n";
    exit();
}

$apiResponse = json_decode($response, true);
if (!$apiResponse || !is_array($apiResponse)) {
    echo "Invalid API response or API returned error.\n";
    exit();
}

// Clear existing data before pushing fresh data to prevent duplicates
mysqli_query($con, "TRUNCATE TABLE districts");

$totalRecords  = count($apiResponse);
$insertedCount = 0;
$errorCount    = 0;

foreach ($apiResponse as $data) {
    try {
        if (empty($data['id']) || empty($data['district'])) {
            $errorCount++;
            continue;
        }

        $district = new District;
        $district->setId($data['id']);
        $district->setName(formatName($data['district']));

        $insertQuery = $district->insert($district);
        if (mysqli_query($con, $insertQuery)) {
            $insertedCount++;
            writeLog("User -> " . ($_SESSION['user'] ?? 'SYSTEM') .
                     " | District loaded from API -> " . ($data['district'] ?? ''));
        } else {
            $errorCount++;
        }

    } catch (Exception $e) {
        $errorCount++;
        continue;
    }
}

mysqli_close($con);

// Plain text summary (no scripts)
echo "Data Load Complete\n";
echo "-------------------------\n";
echo "New records inserted : $insertedCount\n";
echo "Records with errors  : $errorCount\n";
echo "-------------------------\n";
echo "Source: DistrictDetails\n";

// Redirect to District page after completion
echo "<script type='text/javascript'>";
echo "setTimeout(function() {";
echo "window.location.href = '../District.php';";
echo "}, 3000);"; // Wait 3 seconds to show the summary
echo "</script>";

require('Fullui.php');
?>
