<?php
$servername = "localhost";
$username = "root"; // default XAMPP user
$password = "";// default is empty
$dbname = "pharmacy";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
die("Connection failed: " . $conn->connect_error);
}

// ⭐ DITO ANG PINAKA-KRITIKAL NA PAGBABAGO: ALISIN ANG CLOSING TAG! ⭐
