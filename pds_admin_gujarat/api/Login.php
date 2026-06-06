<?php
require('../util/Connection.php');
require('../structures/Login.php');
require('../util/Security.php');
require ('../util/Encryption.php');
$nonceValue = 'nonce_value';
session_start();

if(empty($_POST) || empty($_SESSION)){
	die("Invalid request. Please log in again.");
}

if (
    !isset($_SESSION['csrf_token'], $_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    die("Invalid request. Please log in again.");
}

if (
    !isset($_SESSION['captcha'], $_POST['captchainput']) ||
    $_SESSION['captcha'] !== $_POST['captchainput']
) {
    die("Invalid request. Please log in again.");
}

$person = new Login;
$person->setUsername($_POST["username"]);
$Encryption = new Encryption();
$person->setPassword($Encryption->decrypt($_POST["password"], $nonceValue));

$query = "SELECT * FROM login WHERE username='".$person->getUsername()."'";
$result = mysqli_query($con,$query);
$row = mysqli_fetch_assoc($result);

if(empty($row)){
	die("Something went wrong.");
}

$dbHashedPassword = $row['password'];
if(password_verify($person->getPassword(), $dbHashedPassword)){
 if($row['role']=="admin"){
		$count = 1 + $row['count'];
		$uniqueId = uniqid();
		$authToken = md5($uniqueId);
		$currentLoginTime = date("Y-m-d H:i:s");
		$queryUpdate = "UPDATE login SET token='$authToken',lastlogin='$currentLoginTime',count='$count' WHERE username='".$person->getUsername()."'";
		mysqli_query($con,$queryUpdate);
		
		$_SESSION['user'] = $person->getUsername();
		$_SESSION['token'] = $authToken;
		
		// Check if we already fetched the data for the current month
		$shouldFetch = true;
		try {
			// Create status table if not exists
			mysqli_query($con, "CREATE TABLE IF NOT EXISTS latest_month_movement_status (
				last_updated DATETIME
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

			$statusResult = mysqli_query($con, "SELECT last_updated FROM latest_month_movement_status LIMIT 1");
			if ($statusResult && mysqli_num_rows($statusResult) > 0) {
				$statusRow = mysqli_fetch_assoc($statusResult);
				$lastUpdated = $statusRow['last_updated'];
				if ($lastUpdated && date('Y-m', strtotime($lastUpdated)) === date('Y-m')) {
					$shouldFetch = false;
				}
			}
		} catch (Exception $e) {
			require_once('../util/Logger.php');
			writeLog("User " . $person->getUsername() . " -> Check latest_month_movement_status failed: " . $e->getMessage());
		}

		if ($shouldFetch) {
			// Fetch LatestMonthMovementDetails API and store it in database
			try {
				$apiUrl = 'https://ipds.gujarat.gov.in/AnnachakraAPI/api/AnnaChakra/LatestMonthMovementDetails';
				$clientId = "AAfaAa6dfjfaof";
				$clientPassword = "9ztxLKAfqk";

				$apiData = [
					"ClientID" => $clientId,
					"ClientPassword" => $clientPassword
				];

				$curl = curl_init();
				curl_setopt_array($curl, [
					CURLOPT_URL            => $apiUrl,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING       => '',
					CURLOPT_MAXREDIRS      => 10,
					CURLOPT_TIMEOUT        => 30, // 30 seconds timeout
					CURLOPT_CUSTOMREQUEST  => 'POST',
					CURLOPT_POSTFIELDS     => json_encode($apiData),
					CURLOPT_HTTPHEADER     => [
						'Content-Type: application/json',
						'Accept: application/json',
						"ClientID: $clientId",
						"ClientPassword: $clientPassword"
					],
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_SSL_VERIFYHOST => false
				]);

				$response = curl_exec($curl);
				$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
				$curlError = curl_error($curl);
				curl_close($curl);

				if ($response !== false && $httpCode === 200) {
					$apiResponse = json_decode($response, true);
					if (is_array($apiResponse)) {
						// 1. Create table if not exists
						$createTableQuery = "CREATE TABLE IF NOT EXISTS latest_month_movement_details (
							id INT AUTO_INCREMENT PRIMARY KEY,
							gowdown_district VARCHAR(255) NOT NULL,
							godown_id INT NOT NULL,
							area_district VARCHAR(255) NOT NULL,
							area_id INT NOT NULL
						) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
						mysqli_query($con, $createTableQuery);

						// 2. Truncate table to clear previous data
						mysqli_query($con, "TRUNCATE TABLE latest_month_movement_details");

						// 3. Bulk insert
						$values = [];
						$batchSize = 2000;
						foreach ($apiResponse as $apiRow) {
							$gowdown_district = mysqli_real_escape_string($con, $apiRow['Gowdown_District'] ?? '');
							$godown_id = isset($apiRow['Godown_ID']) ? (int)$apiRow['Godown_ID'] : 0;
							$area_district = mysqli_real_escape_string($con, $apiRow['Area_District'] ?? '');
							$area_id = isset($apiRow['AreaID']) ? (int)$apiRow['AreaID'] : 0;

							$values[] = "('$gowdown_district', $godown_id, '$area_district', $area_id)";

							if (count($values) >= $batchSize) {
								$insertQuery = "INSERT INTO latest_month_movement_details (gowdown_district, godown_id, area_district, area_id) VALUES " . implode(',', $values);
								mysqli_query($con, $insertQuery);
								$values = [];
							}
						}
						if (count($values) > 0) {
							$insertQuery = "INSERT INTO latest_month_movement_details (gowdown_district, godown_id, area_district, area_id) VALUES " . implode(',', $values);
							mysqli_query($con, $insertQuery);
						}

						// 4. Update status table
						mysqli_query($con, "TRUNCATE TABLE latest_month_movement_status");
						mysqli_query($con, "INSERT INTO latest_month_movement_status (last_updated) VALUES (NOW())");
						
						require_once('../util/Logger.php');
						writeLog("User " . $person->getUsername() . " -> Successfully called LatestMonthMovementDetails API and stored " . count($apiResponse) . " records.");
					}
				} else {
					require_once('../util/Logger.php');
					writeLog("User " . $person->getUsername() . " -> Failed to fetch LatestMonthMovementDetails: HTTP Code $httpCode, Error: $curlError");
				}
			} catch (Exception $e) {
				require_once('../util/Logger.php');
				writeLog("User " . $person->getUsername() . " -> Exception while fetching/storing LatestMonthMovementDetails: " . $e->getMessage());
			}
		} else {
			require_once('../util/Logger.php');
			writeLog("User " . $person->getUsername() . " -> LatestMonthMovementDetails API check: already fetched this month. Skipped API call.");
		}
		
		mysqli_close($con);
		echo "<script>window.location.href = '../Home.php';</script>";
    }
} 
else{
    echo "Error : Password or Username is incorrect";
}

?>
<?php require('Fullui.php');  ?>