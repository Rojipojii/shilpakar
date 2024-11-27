<?php
$servername = "ml.shilpakar.co";
$username = "shilpakar";
$password = "RC7sHwW39KsVUDPz";
$port = "3306";
$database = "shilpakar";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
error_reporting(E_ALL);
ini_set("display_errors", "On");
?>
