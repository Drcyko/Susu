<?php
session_start();
$host = "localhost";
$user = "root";
$pass = "";
$db   = "savings_loan_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
