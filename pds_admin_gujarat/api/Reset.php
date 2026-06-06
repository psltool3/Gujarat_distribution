<?php
require('../util/Connection.php');
require('../structures/Login.php');
require('../util/Security.php');
require ('../util/Encryption.php');
$nonceValue = 'nonce_value';


$person = new Login;
$Encryption = new Encryption();

if (empty($_POST["username"]) || empty($_POST["oldpassword"]) || empty($_POST["newpassword"]) || empty($_POST["confirmpassword"])) {
    die("Something Went Wrong");
}

$person->setUsername($_POST["username"]);
$person->setPassword($Encryption->decrypt($_POST["oldpassword"], $nonceValue));
$newpassword = $Encryption->decrypt($_POST["newpassword"], $nonceValue);
$confirmpassword = $Encryption->decrypt($_POST["confirmpassword"], $nonceValue);



if ($newpassword == "" || $confirmpassword == "") {
    echo "Error: Password is Empty";
    return;
}
if ($newpassword != $confirmpassword) {
    echo "Error: Both Passwords don't match";
    return;
}

// Password validation: Minimum 8 characters, at least 1 uppercase letter, 1 digit, and 1 special character
$passwordPattern = '/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
if (!preg_match($passwordPattern, $newpassword)) {
    echo "Error: Password must be at least 8 characters long, contain one uppercase letter, one digit, and one special character.";
    return;
}

$query = "SELECT * FROM login WHERE username='" . $person->getUsername() . "'";
$result = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($result);

if (!$row) {
    echo "Error: User not found!";
    return;
}

$dbHashedPassword = $row['password'];


if (password_verify($person->getPassword(), $dbHashedPassword)) {
    // FIX: Hash the **new** password instead of the old one
    $newhashedPassword = password_hash($newpassword, PASSWORD_DEFAULT);


    $query1 = "UPDATE login SET password='$newhashedPassword' WHERE username='" . $person->getUsername() . "'";

    if (mysqli_query($con, $query1)) {
       // echo "Password updated successfully!";
        mysqli_close($con);
        echo "<script>window.location.href = '../Login.html';</script>";
    } else {
        echo "Error updating password: " . mysqli_error($con);
    }
} else {
    echo "Error: Password or Username is incorrect";
}
?>

<?php require('Fullui.php'); ?>
