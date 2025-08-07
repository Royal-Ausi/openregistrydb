<?php
$host = "localhost";
$user = "root";
$password = "";
$dbname = "open_registrydb";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

