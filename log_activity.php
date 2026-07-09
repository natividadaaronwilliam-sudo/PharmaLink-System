<?php
function logActivity($conn, $user_id, $activity) {
    $stmt = $conn->prepare("INSERT INTO logs (user_id, activity) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $activity);
    $stmt->execute();
    $stmt->close();
}
?>
