<?php

session_start();
$_SESSION['district_name'] = null;
$_SESSION['district_user'] = null;
session_destroy();

header("Location:../Login.html");

?>