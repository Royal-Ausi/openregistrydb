<?php
session_start();

// destroy all session data
session_destroy();

// clear all session variables
$_SESSION = array();

// redirect to index page
header("Location: index.php");
exit();
?>





















