<?php
// Disable timeouts (can run for several minutes)
@set_time_limit(0);
@ini_set('max_execution_time', '0');

require('../util/Connection.php');
require('../structures/FPS.php');
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
$apiUrl = 'https://ipds.gujarat.gov.in/AnnachakraAPI/api/AnnaChakra/FpsDetails';
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
mysqli_query($con, "TRUNCATE TABLE fps");

$insertedCount = 0;
$errorCount    = 0;

foreach ($apiResponse as $data) {
    try {
        if (empty($data['id']) || empty($data['name']) || empty($data['district'])) {
            $errorCount++;
            continue;
        }

        $fps = new FPS;
        $fps->setDistrict(formatName($data['district']));
        $fps->setName($data['name']); // Keep the name exactly as it is (preserves Gujarati)
        $fps->setId($data['id']);
        $fps->setType($data['type'] ?? 'Normal FPS');
        
        $lat = isset($data['latitude']) && is_numeric($data['latitude']) ? $data['latitude'] : 0;
        $lon = isset($data['longitude']) && is_numeric($data['longitude']) ? $data['longitude'] : 0;
        
        $fps->setLatitude($lat);
        $fps->setLongitude($lon);
        
        $demandWheat = 0;
        $demandFrice = 0;
        $demandRice = 0;
        
        if (isset($data['demands']) && is_array($data['demands'])) {
            foreach ($data['demands'] as $d) {
                $commodity = $d['commodity'] ?? '';
                if (stripos($commodity, 'Frice') !== false) {
                    $demandFrice = $d['demand'] ?? 0;
                } elseif (stripos($commodity, 'Wheat') !== false) {
                    $demandWheat = $d['demand'] ?? 0;
                } elseif (stripos($commodity, 'Rice') !== false) {
                    // This will catch 'Rice' but NOT 'Frice' because Frice is handled first
                    $demandRice = $d['demand'] ?? 0;
                }
            }
        }
        
        $fps->setDemand($demandWheat); 
        $fps->setDemandrice($demandRice); 
        $fps->setDemandfrice($demandFrice);
        $fps->setUniqueid(substr(uniqid("FPS_"), 0, 15));
        $fps->setActive($data['active'] ?? '1');

        $insertQuery = $fps->insert($fps);
        if (mysqli_query($con, $insertQuery)) {
            $insertedCount++;
            writeLog("User -> " . ($_SESSION['user'] ?? 'SYSTEM') .
                     " | FPS loaded from API -> " . ($data['name'] ?? ''));
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
echo "Source: FPSDetails\n";

echo "<script type='text/javascript'>";
echo "setTimeout(function() {";
echo "window.location.href = '../FPS.php';";
echo "}, 3000);";
echo "</script>";

require('Fullui.php');
?>
