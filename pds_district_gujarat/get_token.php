<?php
session_start();

// Check if CSRF token exists in session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Generate a new CSRF token
}

// Set CSRF token in a secure cookie (expires in 1 day)
setcookie("csrf_token", $_SESSION['csrf_token'], time() + 86400, "/", "", true, true); // Secure & HttpOnly

echo $_SESSION['csrf_token']; // Output token
?>
