<?php
session_start();
session_destroy(); // Ito ang nagwawakas sa lahat
header("Location: index.php");
exit;
?>