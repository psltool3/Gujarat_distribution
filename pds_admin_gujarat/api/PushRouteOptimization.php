<?php
require('../util/Connection.php');
require('../util/SessionCheck.php');
require('../util/Logger.php');

// Increase script execution time and memory limits for large datasets
ini_set('memory_limit', '1G');
set_time_limit(600); // 10 minutes

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
    exit;
}

if (!isset($_POST['month']) || !isset($_POST['year'])) {
    echo json_encode(["status" => "error", "message" => "Month and year are required parameters."]);
    exit;
}

$month = mysqli_real_escape_string($con, $_POST['month']);
$year = mysqli_real_escape_string($con, $_POST['year']);

// 1. Find the latest run ID from optimised_table for the given month and year
$query = "SELECT id FROM optimised_table WHERE month='$month' AND year='$year' ORDER BY last_updated DESC LIMIT 1";
$result = mysqli_query($con, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    echo json_encode(["status" => "error", "message" => "No optimization data found for month: $month, year: $year."]);
    exit;
}

$row = mysqli_fetch_assoc($result);
$id = $row['id'];
$tablename = "optimiseddata_" . $id;

// 2. Check if the detail table exists
$checkTableQuery = "SHOW TABLES LIKE '$tablename'";
$checkTableResult = mysqli_query($con, $checkTableQuery);

if (!$checkTableResult || mysqli_num_rows($checkTableResult) === 0) {
    echo json_encode(["status" => "error", "message" => "Optimized route details table ($tablename) does not exist."]);
    exit;
}

// 3. Query all route movement rows from the detail table
$dataQuery = "SELECT * FROM `$tablename`";
$dataResult = mysqli_query($con, $dataQuery);

if (!$dataResult) {
    echo json_encode(["status" => "error", "message" => "Failed to retrieve route optimization details from the database."]);
    exit;
}

$routeData = [];
while ($rowDetail = mysqli_fetch_assoc($dataResult)) {
    $routeData[] = [
        "scenario" => isset($rowDetail['scenario']) ? (string)$rowDetail['scenario'] : "",
        "from" => isset($rowDetail['from']) ? (string)$rowDetail['from'] : "",
        "from_state" => isset($rowDetail['from_state']) ? (string)$rowDetail['from_state'] : "",
        "from_id" => isset($rowDetail['from_id']) ? (string)$rowDetail['from_id'] : "",
        "from_name" => isset($rowDetail['from_name']) ? (string)$rowDetail['from_name'] : "",
        "from_district" => isset($rowDetail['from_district']) ? (string)$rowDetail['from_district'] : "",
        "from_lat" => isset($rowDetail['from_lat']) ? (float)$rowDetail['from_lat'] : 0.0,
        "from_long" => isset($rowDetail['from_long']) ? (float)$rowDetail['from_long'] : 0.0,
        "to" => isset($rowDetail['to']) ? (string)$rowDetail['to'] : "",
        "to_state" => isset($rowDetail['to_state']) ? (string)$rowDetail['to_state'] : "",
        "to_id" => isset($rowDetail['to_id']) ? (string)$rowDetail['to_id'] : "",
        "to_name" => isset($rowDetail['to_name']) ? (string)$rowDetail['to_name'] : "",
        "to_district" => isset($rowDetail['to_district']) ? (string)$rowDetail['to_district'] : "",
        "to_lat" => isset($rowDetail['to_lat']) ? (float)$rowDetail['to_lat'] : 0.0,
        "to_long" => isset($rowDetail['to_long']) ? (float)$rowDetail['to_long'] : 0.0,
        "commodity" => isset($rowDetail['commodity']) ? (string)$rowDetail['commodity'] : "",
        "quantity" => isset($rowDetail['quantity']) ? (float)$rowDetail['quantity'] : 0.0,
        "distance" => isset($rowDetail['distance']) ? (float)$rowDetail['distance'] : 0.0,
        "status" => isset($rowDetail['status']) ? (string)$rowDetail['status'] : ""
    ];
}

mysqli_free_result($dataResult);
mysqli_close($con);

if (empty($routeData)) {
    echo json_encode(["status" => "error", "message" => "No route data records found in table: $tablename."]);
    exit;
}

// 4. Construct the API JSON payload
$clientId = "AAfaAa6dfjfaof";
$clientPassword = "9ztxLKAfqk";

$payload = [
    "ClientID" => $clientId,
    "ClientPassword" => $clientPassword,
    "data" => $routeData
];
$jsonPayload = json_encode($payload);

// Log the push attempt
$username = isset($_SESSION['user']) ? $_SESSION['user'] : 'unknown';
writeLog("User -> Push Route Optimization API | Month: $month, Year: $year | Count: " . count($routeData) . " | User: $username");

// 5. Send POST request via cURL
$apiUrl = "https://ipds.gujarat.gov.in/AnnachakraAPI/api/AnnaChakra/RouteOptimization";
$ch = curl_init($apiUrl);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Content-Length: ' . strlen($jsonPayload),
    "ClientID: $clientId",
    "ClientPassword: $clientPassword"
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ignore SSL verification to prevent certificate handshake failures in different environments
curl_setopt($ch, CURLOPT_TIMEOUT, 120);          // Timeout of 2 minutes for processing on receiver side

$apiResponse = curl_exec($ch);
$httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($apiResponse === false) {
    writeLog("Error -> Push Route Optimization API Failed | Month: $month, Year: $year | cURL Error: $curlError");
    echo json_encode(["status" => "error", "message" => "cURL Request Failed: " . $curlError]);
} else {
    writeLog("Response -> Push Route Optimization API Response | Month: $month, Year: $year | HTTP: $httpStatusCode | Response: $apiResponse");
    if ($httpStatusCode >= 200 && $httpStatusCode < 300) {
        echo json_encode(["status" => "success", "message" => $apiResponse]);
    } else {
        echo json_encode(["status" => "error", "message" => "API returned HTTP status code $httpStatusCode. Response: $apiResponse"]);
    }
}
?>
