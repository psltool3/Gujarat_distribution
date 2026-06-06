<?php

require('../util/Connection.php');
require('../structures/Warehouse.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');
require('../util/Logger.php'); 
require('../util/Security.php');
require ('../util/Encryption.php');
$nonceValue = 'nonce_value';

if(!SessionCheck()){
	return;
}

require('Header.php');

function formatName($name) {
    $name = preg_replace('/[^a-zA-Z ]/', '', $name);
    $name = ucwords(strtolower($name));
    return trim($name);
}

function isValidCoordinate($value, $coordinateType) {
    // Check if the value is a number and not a string
    if (!is_numeric($value)) {
        return false;
    }
	
	if ($_POST['storage'] === false)
{
    $_POST['storage'] = 0;
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

$person = new Login;
$person->setUsername($_POST["username"]);
$Encryption = new Encryption();
$person->setPassword($Encryption->decrypt($_POST["password"], $nonceValue));

if($_SESSION['district_user']!=$person->getUsername()){
	echo "User is logged in with different username and password";
	return;
}

if(!isValidCoordinate($_POST["latitude"],'latitude') or !isValidCoordinate($_POST["longitude"],'longitude')){
	echo "Error : Check Latitude and Longitude Value";
	exit();
}

if(!isStringNumber($_POST["storage"])){
	echo "Error : Check Storage Value";
	exit();
}

if (
    !isset($_POST["id"]) ||
    !preg_match('/^[A-Za-z0-9]+$/', $_POST["id"])
) {
    echo "Error: Check Warehouse ID value (only letters and numbers allowed, no spaces or special characters)";
    exit();
}
if (!is_numeric($column[$latitude]) || $column[$latitude] >= 40) {
	echo "Error : Latitude must be less than 40. Given: " . $column[$latitude];
	echo "</br>";
	$redirect = 0;
}

// Longitude check (must be more than 65)
if (!is_numeric($column[$longitude]) || $column[$longitude] <= 65) {
	echo "Error : Longitude must be more than 65. Given: " . $column[$longitude];
	echo "</br>";
	$redirect = 0;
}

$query = "SELECT * FROM login WHERE username='".$person->getUsername()."'";
$result = mysqli_query($con,$query);
$row = mysqli_fetch_assoc($result);

$dbHashedPassword = $row['password'];
if(password_verify($person->getPassword(), $dbHashedPassword)){
    $district = formatName($_POST["district"]);
    $latitude = $_POST["latitude"];
    $longitude = $_POST["longitude"];
    $name = formatName($_POST["name"]);
    $id = $_POST["id"];
    $type = $_POST["type"];
    $storage = $_POST["storage"];
    $warehousetype = $_POST["warehousetype"];
    $uniqueid = $_POST["uniqueid"];
    $active = $_POST["active"];
    
    $Warehouse = new Warehouse;
    $Warehouse->setUniqueid($uniqueid);
    $Warehouse->setDistrict($district);
    $Warehouse->setLatitude($latitude);
    $Warehouse->setLongitude($longitude);
    $Warehouse->setName($name);
    $Warehouse->setId($id);
    $Warehouse->setType($type);
    $Warehouse->setStorage($storage);
    $Warehouse->setWarehousetype($warehousetype);
    $Warehouse->setActive($active);
    
    $query = $Warehouse->update($Warehouse);
    
    mysqli_query($con, $query);
    
    mysqli_close($con);
    
    $filteredPost = $_POST;
    unset($filteredPost['username'], $filteredPost['password']);
    writeLog("District User ->" ." Warehouse Edit->". $_SESSION['district_user'] . "| Requested JSON -> " . json_encode($filteredPost));
    
    echo "<script>window.location.href = '../Warehouse.php';</script>";

} 
else{
    echo "Error : Password or Username is incorrect";
}


?>
<?php require('Fullui.php');  ?>