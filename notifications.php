<?php
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);
session_name('admin_session');
session_start();

include 'db_connection.php';

// Redirect kung di naka-login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = 'admin'; // Tukuyin na admin ang hinahanap

// 1. Kunin muna ang notifications
$query = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND user_type = ? ORDER BY created_at DESC");
$query->bind_param('is', $user_id, $user_type);
$query->execute();
$result = $query->get_result();

// 2. BAGO: I-mark as read ang mga nakuha, pagkatapos i-fetch
$update_stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND user_type = ? AND is_read = 0");
$update_stmt->bind_param('is', $user_id, $user_type);
$update_stmt->execute();
$update_stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications | MarkeTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* (Walang pagbabago sa CSS, pareho na sila) */
        body {
            background-color: #fff8f6;
            font-family: 'Poppins', sans-serif;
        }
        .notif-container {
            max-width: 700px;
            margin: 50px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            padding: 20px 30px;
        }
        .notif-item {
            border-bottom: 1px solid #eee;
            padding: 12px 0;
        }
        .notif-item:last-child {
            border-bottom: none;
        }
        .notif-item.unread {
            background-color: #fff4f4;
        }
        .notif-title {
            font-weight: 600;
            color: #b71c1c;
        }
        .notif-message {
            margin-top: 4px;
        }
        .notif-time {
            font-size: 13px;
            color: #888;
        }
        .back-btn {
            display: inline-block;
            margin-bottom: 15px;
            color: #b71c1c;
            text-decoration: none;
            font-weight: 600;
        }
        .back-btn:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="notif-container">
        <a href="index.php" class="back-btn">← Back to Dashboard</a>
        <h3 style="color:#b71c1c; font-weight:700;">Your Notifications</h3>
        <hr>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="notif-item <?php echo $row['is_read'] == 0 ? 'unread' : ''; ?>">
                    <div class="notif-title"><?php echo htmlspecialchars($row['title']); ?></div>
                    <div class="notif-message"><?php echo htmlspecialchars($row['message']); ?></div>
                    <div class="notif-time"><?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?></div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="text-align:center; color:#888;">No notifications yet.</p>
        <?php endif; ?>
        <?php $query->close(); ?>
    </div>
</body>
</html>