<?php
require('../util/Connection.php');
require('../structures/Login.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$newpassword = $_POST['newpassword'];
$confirmpassword = $_POST['confirmpassword'];

if($newpassword=="" || $confirmpassword==""){
    echo "Error: Password is empty";
    return;
}

if($newpassword != $confirmpassword){
    echo "Error: Passwords do not match";
    return;
}

$username = $_POST["username"];
$oldpassword = $_POST["oldpassword"];

// Initialize the Login object
$person = new Login();
$person->setUsername($username);
$person->setPassword($oldpassword);

// Query to check if the username and old password are correct
$query = "SELECT * FROM login WHERE username='" . mysqli_real_escape_string($con, $username) . "' AND password='" . mysqli_real_escape_string($con, $oldpassword) . "'";
$result = mysqli_query($con, $query);
$numrows = mysqli_num_rows($result);

if($numrows == 0){
    echo "Error: Incorrect username or password";
} else {
    // Update the password for the specific user
    $query1 = "UPDATE login SET password='" . mysqli_real_escape_string($con, $newpassword) . "' WHERE username='" . mysqli_real_escape_string($con, $username) . "'";
    if(mysqli_query($con, $query1)){
        echo "<script>window.location.href = '../Login.html';</script>";
    } else {
        echo "Error: Unable to update password";
    }
}

mysqli_close($con);
?>
