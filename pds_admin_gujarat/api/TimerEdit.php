<?php

require('../util/Connection.php');
require('../util/SessionFunction.php');

if(!SessionCheck()){
	return;
}

require('Header.php');

$date = $_POST['date'];
$time = $_POST['time'];

function isValidDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function isValidTime($time) {
    return preg_match('/^(2[0-3]|[01][0-9]):([0-5][0-9])$/', $time);
}

if (!isValidDate($date) || !isValidTime($time)) {
    echo json_encode(["status" => "error", "message" => "Invalid date or time format."]);
    exit;
}

$query = "UPDATE timer SET deadline_date='$date', deadline_time='$time' WHERE 1";
mysqli_query($con,$query);
mysqli_close($con);

echo "<script>window.location.href = '../Timer.php';</script>";

?>
<?php require('Fullui.php');  ?>