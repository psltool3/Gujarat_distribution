<?php
session_start();

if (isset($_POST['captcha'])) {
    $_SESSION['captcha'] = $_POST['captcha']; // Store in session
    echo "Stored: " . $_SESSION['captcha'];
} else {
    echo "Error: No CAPTCHA received!";
}
?>
