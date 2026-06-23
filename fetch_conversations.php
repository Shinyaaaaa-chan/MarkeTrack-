<?php
// File: fetch_conversations.php (FINAL VERSION WITH CORRECT PATH)
session_start();
if (!isset($_SESSION['user_id'])) { 
    exit('Access Denied'); 
}

// INAYOS ANG PATH: Dinagdagan ng ../ para mahanap ang file sa labas ng admin folder
include 'db_connection.php';

// SQL para kunin ang listahan ng usapan
$sql = "SELECT 
            m.customer_id, 
            c.fullname, 
            MAX(m.sent_at) as last_message_time,
            (SELECT SUBSTRING(message, 1, 30) FROM messages WHERE customer_id = m.customer_id ORDER BY sent_at DESC LIMIT 1) as last_message,
            (SELECT COUNT(*) FROM messages WHERE customer_id = m.customer_id AND status = 'unread' AND sender_type = 'customer') as unread_count
        FROM 
            messages m
        JOIN 
            customers c ON m.customer_id = c.id
        GROUP BY 
            m.customer_id
        ORDER BY 
            last_message_time DESC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo '<p style="padding:15px; color:red; font-weight:bold;">SQL Error: ' . mysqli_error($conn) . '</p>';
    exit();
}

if (mysqli_num_rows($result) > 0) {
    while ($convo = mysqli_fetch_assoc($result)) {
        $active_class = $convo['unread_count'] > 0 ? 'unread' : '';
        echo '
        <div class="admin-convo-item ' . $active_class . '" data-customer-id="' . $convo['customer_id'] . '">
            <strong>' . htmlspecialchars($convo['fullname']) . '</strong>
            <small>' . htmlspecialchars($convo['last_message']) . '...</small>';
        if ($convo['unread_count'] > 0) {
            echo '<span class="unread-badge">' . $convo['unread_count'] . '</span>';
        }
        echo '</div>';
    }
} else {
    echo '<p style="text-align:center; color:#888; padding:10px;">No conversations yet.</p>';
}

mysqli_close($conn);
?>