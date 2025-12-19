<?php
$host = "localhost";      // Database host
$user = "root";           // Database username
$pass = "";               // Database password (keep empty for XAMPP)
$db   = "warrantymaintenance";  // Your database name

// Create connection
$conn = mysqli_connect($host, $user, $pass, $db);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>