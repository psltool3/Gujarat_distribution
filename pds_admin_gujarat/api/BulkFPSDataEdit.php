<?php
require('../util/Connection.php');
require('../structures/FPS.php');
require('../util/SessionFunction.php');
require('../util/Logger.php'); 
ini_set('max_execution_time', 3000);
require('../util/Security.php');
require ('../util/Encryption.php');
$nonceValue = 'nonce_value';
require('../structures/Login.php');

if(!SessionCheck()){
	return;
}
require('Header.php');

$person = new Login;
$person->setUsername($_POST["username"]);
$Encryption = new Encryption();
$person->setPassword($Encryption->decrypt($_POST["password"], $nonceValue));

$mapData = [
    "District" => "district",
    "Name of FPS" => "name",
    "FPS ID" => "id",
    "Model FPS/Normal FPS" => "type",
    "Latitude" => "latitude",
    "Longitude" => "longitude",
    "Demand of Wheat" => "demand",
	"Demand of Rice" => "demand_rice",
	"Demand of FRice" => "demand_frice",
	"Active/Not-Active" => "active"
];

// Reverse mapping
$reverseMapData = array_flip($mapData);

$districts = [];
$query = "SELECT name FROM districts WHERE 1";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);
if($numrows>0){
	while($row=mysqli_fetch_assoc($result)){
		array_push($districts,$row["name"]);
	}
}

function formatName($name) {
	$name = preg_replace('/[^a-zA-Z0-9_ ]/', '', $name);
    $name = ucwords(strtolower($name));
    return trim($name);
}

function isValidCoordinate($value, $coordinateType) {
    // Check if the value is a number and not a string
    if (!is_numeric($value)) {
        return false;
    }
	
    // Convert the value to a float
    $coordinate = floatval($value);

    // Check if it's latitude or longitude and validate within the range
    switch ($coordinateType) {
        case 'latitude':
            return ($coordinate >= -90 && $coordinate <= 90);
        case 'longitude':
            return ($coordinate >= -180 && $coordinate <= 180);
        default:
            return false;
    }
}

function isStringNumber($stringValue) {
    return is_numeric($stringValue);
}


// Filter the excel data 
function filterData(&$str){ 
    $str = str_replace("\t", "", $str);
}

$redirect = 1;


$query = "SELECT * FROM login WHERE username='".$person->getUsername()."'";
$result = mysqli_query($con,$query);
$row = mysqli_fetch_assoc($result);

$dbHashedPassword = $row['password'];
if(password_verify($person->getPassword(), $dbHashedPassword)){

	try{
		//if (isset($_POST["submit"])){
			$fileName = $_FILES["file"]["tmp_name"];
			if ($_FILES["file"]["size"] > 0) {
				
				$file = fopen($fileName, "r");
				$i = 0;
				$district = -1;
				$name = -1;
				$id = -1;
				$type = -1;
				$demand = -1;
				$demand_rice = -1;
				$demand_frice = -1;
				$longitude = -1;
				$latitude = -1;
				$active = -1;
				while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
					if($i>0){
						if($district<0 or $name<0 or $id<0 or $type<0 or $demand<0 or $demand_rice<0 or $demand_frice<0 or $latitude<0 or $longitude<0 or $active<0){
							echo "Error : You have modified Template Header, please check";
							exit();
						}
						if(!isValidCoordinate($column[$latitude],'latitude') or !isValidCoordinate($column[$longitude],'longitude')){
							echo "Error : Check Latitude and Longitude Value Latitude: ".$column[$latitude]." Longitude: ".$column[$longitude];
							echo "</br>";
							$redirect = 0;
						}
	
						if(!isStringNumber($column[$demand])){
							echo "Error : Check DemandWheat Value: ".$column[$demand];
							echo "</br>";
							$redirect = 0;
						}	
						if(!isStringNumber($column[$demand_rice])){
							echo "Error : Check DemandRice Value: ".$column[$demand_rice];
							echo "</br>";
							$redirect = 0;
						}	
						if(!isStringNumber($column[$demand_frice])){
							echo "Error : Check DemandFRice Value: ".$column[$demand_frice];
							echo "</br>";
							$redirect = 0;
						}	
						
						if(!in_array($column[$district], $districts)){
							echo "Error : Check District Name: ".$column[$district];
							echo "</br>";
							$redirect = 0;
						}
						if(!($column[$active]==0 || $column[$active]==1)){
							echo "Error : Check value of active/inactive column: ".$column[$active];
							echo "</br>";
							$redirect = 0;
						}
						
						if (!is_numeric($column[$latitude]) || $column[$latitude] >= 40) {
							echo "Error : Latitude must be less than 40. Given: " . $column[$latitude];
							echo "</br>";
							$redirect = 0;
						}
						
						if (
							!isset($column[$id]) ||
							!preg_match('/^[A-Za-z0-9]+$/', $column[$id])
						) {
							echo "Error: FPS ID should not contain spaces or any special characters: " . ($column[$id] ?? 'Missing');
							echo "<br>";
							$redirect = 0;
						}
						

						// Longitude check (must be more than 65)
						if (!is_numeric($column[$longitude]) || $column[$longitude] <= 65) {
							echo "Error : Longitude must be more than 65. Given: " . $column[$longitude];
							echo "</br>";
							$redirect = 0;
						}
					
						$FPS = new FPS;
						filterData($column[$district]);
						filterData($column[$latitude]);
						filterData($column[$longitude]);
						filterData($column[$name]);
						filterData($column[$id]);
						filterData($column[$type]);
						filterData($column[$demand]);
						filterData($column[$demand_rice]);
						filterData($column[$demand_frice]);
						filterData($column[$active]);
						$uniqueid = uniqid("FPS_",);
						$FPS->setUniqueid(substr($uniqueid,0,15));
						$FPS->setDistrict(ucwords(strtolower($column[$district])));
						$FPS->setLatitude($column[$latitude]);
						$FPS->setLongitude($column[$longitude]);
						$FPS->setName($column[$name]);
						$FPS->setId($column[$id]);
						$FPS->setType($column[$type]);
						$FPS->setDemand($column[$demand]);
						$FPS->setDemandrice($column[$demand_rice]);
						$FPS->setDemandfrice($column[$demand_frice]);
						$FPS->setActive($column[$active]);
						$query_check = $FPS->checkEdit($FPS);
						$query_result = mysqli_query($con, $query_check);
						$numrows = mysqli_num_rows($query_result);
						if($numrows==0){
							echo "Error : Error in loading data as FPS id doesn't exist : ".$column[$id];
							echo "</br>";
							$redirect = 0;
						}
					}
					else{
                        $column[0] = preg_replace('/^\xEF\xBB\xBF/', '', $column[0]);
						for($j=0;$j<count($column);$j++){
							switch($column[$j]){
								case $reverseMapData["district"]:
									$district = $j;
									break;
								case $reverseMapData["latitude"]:
									$latitude = $j;
									break;
								case $reverseMapData["longitude"]:
									$longitude = $j;
									break;
								case $reverseMapData["name"]:
									$name = $j;
									break;
								case $reverseMapData["id"]:
									$id = $j;
									break;
								case $reverseMapData["type"]:
									$type = $j;
									break;
								case $reverseMapData["demand"]:
									$demand = $j;
									break;
								case $reverseMapData["demand_rice"]:
									$demand_rice = $j;
									break;
								case $reverseMapData["demand_frice"]:
									$demand_frice = $j;
									break;
								case $reverseMapData["active"]:
									$active = $j;
									break;
							}
						}
					}
					$i = $i+1;
				}
			}
		//}
		//else{
		//	echo "Error Please Select .csv file";
		//}
	}
	catch(Exception $e){
		echo "Error : Error Please check data in  .csv file";
	}
	
	if($redirect==0){
		exit();
	}
	
	try{
		//if (isset($_POST["submit"])){
			$fileName = $_FILES["file"]["tmp_name"];
			if ($_FILES["file"]["size"] > 0) {
				
				$file = fopen($fileName, "r");
				$i = 0;
				$district = 0;
				$name = 1;
				$id = 2;
				$type = 3;
				$demand = 6;
				$demand_rice = 8;
				$demand_frice = 9;
				$longitude = 5;
				$latitude = 4;
				$active = 10;
				while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
					if($i>0){
						$FPS = new FPS;
						filterData($column[$district]);
						filterData($column[$latitude]);
						filterData($column[$longitude]);
						filterData($column[$name]);
						filterData($column[$id]);
						filterData($column[$type]);
						filterData($column[$demand]);
						filterData($column[$demand_rice]);
						filterData($column[$demand_frice]);
						filterData($column[$active]);
						$uniqueid = uniqid("FPS_",);
						$FPS->setUniqueid(substr($uniqueid,0,15));
						$FPS->setDistrict($column[$district]);
						$FPS->setLatitude($column[$latitude]);
						$FPS->setLongitude($column[$longitude]);
						$FPS->setName($column[$name]);
						$FPS->setId($column[$id]);
						$FPS->setType($column[$type]);
						$FPS->setDemand($column[$demand]);
						$FPS->setDemandrice($column[$demand_rice]);
						$FPS->setDemandfrice($column[$demand_frice]);
						$FPS->setActive($column[$active]);
						$query_check = $FPS->checkEdit($FPS);
						$query_result = mysqli_query($con, $query_check);
						$numrows = mysqli_num_rows($query_result);
						if($numrows==0){
							echo "Error : in loading data as FPS id doesn't exist : ".$column[$id];
							echo "</br>";
							$redirect = 0;
						}
	
						writeLog("User ->" ." FPS Edit -> ". $_SESSION['user'] . "| " . $FPS->getName());
						$query_update = $FPS->updateEdit($FPS);
						mysqli_query($con, $query_update);
	
						$query_update = $FPS->updateEdit($FPS);
						mysqli_query($con, $query_update);
					}
					else{
                        $column[0] = preg_replace('/^\xEF\xBB\xBF/', '', $column[0]);
						for($j=0;$j<count($column);$j++){
							switch($column[$j]){
								case $reverseMapData["district"]:
									$district = $j;
									break;
								case $reverseMapData["latitude"]:
									$latitude = $j;
									break;
								case $reverseMapData["longitude"]:
									$longitude = $j;
									break;
								case $reverseMapData["name"]:
									$name = $j;
									break;
								case $reverseMapData["id"]:
									$id = $j;
									break;
								case $reverseMapData["type"]:
									$type = $j;
									break;
								case $reverseMapData["demand"]:
									$demand = $j;
									break;
								case $reverseMapData["demand_rice"]:
									$demand_rice = $j;
									break;
								case $reverseMapData["demand_frice"]:
									$demand_frice = $j;
									break;
								case $reverseMapData["active"]:
									$active = $j;
									break;
							}
						}
					}
					$i = $i+1;
				}
				if($redirect==1){
					echo "<script>window.location.href = '../FPS.php';</script>";
				}
			}
		//}
		//else{
		//	echo "Error Please Select .csv file";
		//}
	}
	catch(Exception $e){
		echo "Error : Please check data in  .csv file";
	}
} 
else{
    echo "Error : Password or Username is incorrect";
}


?>
<?php require('Fullui.php');  ?>