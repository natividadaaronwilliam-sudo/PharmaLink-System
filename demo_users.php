<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pharmacy";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$users = [
  ['admin', 'admin123', 1],
  ['cashier', 'cashier123', 2],
  ['customer', 'customer123', 3]
];

foreach ($users as $u) {
  $hash = password_hash($u[1], PASSWORD_BCRYPT);
  $stmt = $conn->prepare("INSERT INTO users (username, password, role_id) VALUES (?, ?, ?)");
  $stmt->bind_param("ssi", $u[0], $hash, $u[2]);
  $stmt->execute();
}

echo "Demo users added successfully!";
?>