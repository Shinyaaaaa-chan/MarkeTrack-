<?php
session_name('admin_session');
session_start();

require_once 'db_connection.php';

// Set the header to output JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0, 'notifications' => []]); // Return empty if not logged in
    exit();
}

$current_user_id = $_SESSION['user_id'];

// --- Query for the count of all unread notifications ---
$count_query = "SELECT COUNT(id) AS unread_count FROM notifications WHERE user_id = ? AND is_read = 0";
$stmt_count = $conn->prepare($count_query);
$stmt_count->bind_param("i", $current_user_id);
$stmt_count->execute();
$count_result = $stmt_count->get_result()->fetch_assoc();
$unread_count = $count_result['unread_count'];
$stmt_count->close();

// --- Query for the 5 most recent unread notifications for the dropdown panel ---
$notif_query = "
    SELECT id, title, message, link, 
           DATE_FORMAT(created_at, '%b %d, %Y %h:%i %p') AS formatted_date 
    FROM notifications 
    WHERE user_id = ? AND user_type = 'admin' AND is_read = 0 
    ORDER BY created_at DESC
    LIMIT 3";

$stmt_notif = $conn->prepare($notif_query);
$stmt_notif->bind_param("i", $current_user_id);
$stmt_notif->execute();
$result = $stmt_notif->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt_notif->close();
$conn->close();

// Return all data as a single JSON object
echo json_encode([
    'count' => $unread_count,
    'notifications' => $notifications
]);
?>