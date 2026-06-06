<?php
// Disable timeouts (can run for several minutes)
@set_time_limit(0);
@ini_set('max_execution_time', '0');

require('../util/Connection.php');
require('../structures/Warehouse.php');
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

function isValidCoordinate($value, $type) {
    if ($value === null || $value === '') return false;
    if (!is_numeric($value)) return false;
    $v = (float)$value;
    return $type === 'latitude' ? ($v >= -90 && $v <= 90) : ($v >= -180 && $v <= 180);
}

// Gujarat API endpoint
$apiUrl = 'https://ipds.gujarat.gov.in/AnnachakraAPI/api/AnnaChakra/WareHouseDetails';
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

// Clear existing data before pushing fresh data
mysqli_query($con, "TRUNCATE TABLE warehouse");

$insertedCount = 0;
$errorCount    = 0;

foreach ($apiResponse as $data) {
    try {
        if (empty($data['id']) || empty($data['name']) || empty($data['district'])) {
            $errorCount++;
            continue;
        }

        $warehouse = new Warehouse;
        $warehouse->setDistrict(formatName($data['district']));
        $warehouse->setName(formatName($data['name']));
        $warehouse->setId($data['id']);
        $warehouse->setWarehousetype($data['type'] ?? 'Other');
        $warehouse->setType($data['block'] ?? 'Motorable'); // Use block as type if type is missing, or default to Motorable
        
        $lat = isset($data['latitude']) && is_numeric($data['latitude']) ? $data['latitude'] : 0;
        $lon = isset($data['longitude']) && is_numeric($data['longitude']) ? $data['longitude'] : 0;
        
        $warehouse->setLatitude($lat);
        $warehouse->setLongitude($lon);
        
        // Map storage directly from the API top-level field
        $storageValue = isset($data['storage']) ? $data['storage'] : 0;
        
        $warehouse->setStorage($storageValue);
        $warehouse->setUniqueid(substr(uniqid("WH_"), 0, 15));
        $warehouse->setActive($data['active'] ?? '1');

        $insertQuery = $warehouse->insert($warehouse);
        if (mysqli_query($con, $insertQuery)) {
            $insertedCount++;
            writeLog("User -> " . ($_SESSION['user'] ?? 'SYSTEM') .
                     " | Warehouse loaded from API -> " . ($data['name'] ?? ''));
        } else {
            $errorCount++;
        }

    } catch (Exception $e) {
        $errorCount++;
        continue;
    }
}

mysqli_close($con);

echo "Data Load Complete\n";
echo "-------------------------\n";
echo "New records inserted : $insertedCount\n";
echo "Records with errors  : $errorCount\n";
echo "-------------------------\n";
echo "Source: WareHouseDetails\n";

echo "<script type='text/javascript'>";
echo "setTimeout(function() {";
echo "window.location.href = '../Warehouse.php';";
echo "}, 3000);";
echo "</script>";

require('Fullui.php');
?>
