    <?php
    // File: fetch_message.php (INAYOS NA VERSION)
    session_start();
    if (!isset($_SESSION['user_id'])) { exit('Access Denied'); }
    include 'db_connection.php';

    $customer_id = (int)($_GET['customer_id'] ?? 0);
    if ($customer_id === 0) { exit('Invalid Customer ID.'); }

    // SQL para kunin ang buong usapan
    $sql = "SELECT sender_type, message, sent_at FROM messages WHERE customer_id = ? ORDER BY sent_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($msg = $result->fetch_assoc()) {
            $sentTime = date('M j, h:i A', strtotime($msg['sent_at']));
            if ($msg['sender_type'] === 'admin') {
                // Message from Admin (You)
                echo '
                <div style="text-align: right; margin-bottom: 10px;">
                    <div style="background-color: #007bff; color: white; display: inline-block; padding: 8px 12px; border-radius: 15px 15px 0 15px; max-width: 80%;">
                        ' . htmlspecialchars($msg['message']) . '
                    </div>
                    <div style="font-size: 11px; color: #888; margin-top: 2px;">You &bull; ' . $sentTime . '</div>
                </div>';
            } else {
                // Message from the customer
                echo '
                <div style="text-align: left; margin-bottom: 10px;">
                    <div style="background-color: #e9e9eb; color: #333; display: inline-block; padding: 8px 12px; border-radius: 15px 15px 15px 0; max-width: 80%;">
                        ' . htmlspecialchars($msg['message']) . '
                    </div>
                    <div style="font-size: 11px; color: #888; margin-top: 2px;">Customer &bull; ' . $sentTime . '</div>
                </div>';
            }
        }
    } else {
        echo '<p style="text-align:center; color:#888;">No messages in this conversation yet.</p>';
    }

    $stmt->close();
    $conn->close();
    ?>