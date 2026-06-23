<?php
// Use the admin session name
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);
session_name('admin_session');
session_start();

include 'db_connection.php'; 

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Authentication failed.']));
}

$admin_id = $_SESSION['user_id']; 
$action = $_POST['action'] ?? '';

switch ($action) {

    // Action to get conversations based on view type (Customer or Staff)
    case 'get_conversations':
        $view_type = $_POST['view_type'] ?? 'customer'; // Default to customer view

        if ($view_type === 'customer') {
            // --- Query ONLY for Customers ---
             $query = "
                SELECT 
                    c.id as user_id, 
                    c.fullname as user_fullname, 
                    'customer' as user_type,
                    MAX(m.sent_at) as last_message_time,
                    SUM(CASE WHEN m.receiver_id = ? AND m.receiver_type = 'admin' AND m.status = 'unread' AND m.sender_type = 'customer' THEN 1 ELSE 0 END) as unread_count
                FROM messages m
                -- Join based on customer being sender OR receiver
                JOIN customers c ON (m.sender_id = c.id AND m.sender_type = 'customer') OR (m.receiver_id = c.id AND m.receiver_type = 'customer')
                -- Filter messages involving the logged-in admin
                WHERE (m.sender_id = ? AND m.sender_type = 'admin') OR (m.receiver_id = ? AND m.receiver_type = 'admin')
                 -- Ensure the other party is a customer
                 AND ((m.sender_type = 'customer') OR (m.receiver_type = 'customer')) 
                GROUP BY c.id, c.fullname
                ORDER BY last_message_time DESC
            ";
             $stmt = $conn->prepare($query);
             $stmt->bind_param("iii", $admin_id, $admin_id, $admin_id);

        } else { // Assumed 'staff' view
            // --- Query ONLY for Staff ---
            $query = "
                 SELECT 
                    u.id as user_id, 
                    u.fullname as user_fullname, 
                    'admin' as user_type, 
                    MAX(m.sent_at) as last_message_time, 
                    SUM(CASE WHEN m.receiver_id = ? AND m.receiver_type = 'admin' AND m.status = 'unread' AND m.sender_type = 'admin' THEN 1 ELSE 0 END) as unread_count
                FROM messages m
                -- Join users table based on user being sender OR receiver (but not the logged-in user)
                JOIN users u ON ((m.sender_id = u.id AND m.sender_type = 'admin') OR (m.receiver_id = u.id AND m.receiver_type = 'admin')) AND u.id != ?
                -- Filter messages involving the logged-in user
                WHERE ((m.sender_id = ? AND m.sender_type = 'admin') OR (m.receiver_id = ? AND m.receiver_type = 'admin'))
                   -- Ensure BOTH sender and receiver are 'admin' type (staff)
                  AND (m.sender_type = 'admin' AND m.receiver_type = 'admin') 
                GROUP BY u.id, u.fullname
                ORDER BY last_message_time DESC
            ";
             $stmt = $conn->prepare($query);
             // Bind the admin_id four times for this query
             $stmt->bind_param("iiii", $admin_id, $admin_id, $admin_id, $admin_id);
        }

        // --- Execute Query and Build Output (Common Logic) ---
        if (!$stmt->execute()) {
             error_log("SQL Execute Error in get_conversations ($view_type): " . $stmt->error); 
             echo '<p style="padding: 15px; color: red;">Error executing query.</p>';
             $stmt->close(); $conn->close(); exit;
        }
        $result = $stmt->get_result();
        
        $output = "";
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $unread_badge = $row['unread_count'] > 0 ? '<span class="unread-badge">' . $row['unread_count'] . '</span>' : '';
                $type_indicator = ($row['user_type'] == 'customer') ? '<small style="color: #888;">(Customer)</small>' : '<small style="color: #007bff;">(Staff)</small>';

                $output .= '
                    <div class="convo-item" 
                         data-user-id="' . $row['user_id'] . '" 
                         data-user-type="' . $row['user_type'] . '" 
                         data-user-name="' . htmlspecialchars($row['user_fullname']) . '">
                        ' . $unread_badge . '
                        <strong>' . htmlspecialchars($row['user_fullname']) . '</strong>
                        ' . $type_indicator . '
                    </div>';
            }
        } else {
             if ($conn->error || $stmt->error) {
                error_log("SQL Error/No Results in get_conversations ($view_type): " . ($stmt->error ?: $conn->error)); 
                $output = '<p style="padding: 15px; color: red;">Error loading conversations.</p>';
             } else {
                 $no_convo_message = ($view_type === 'customer') ? "No customer conversations found." : "No staff conversations found.";
                $output = '<p style="padding: 15px; color: #888;">'.$no_convo_message.'</p>';
             }
        }
        echo $output;
        $stmt->close();
        break;

    // Action to get messages (Remains the same - handles both types)
    case 'get_messages':
        // ... (Keep the existing code for get_messages from the previous combined version) ...
        $other_user_id = $_POST['other_user_id'] ?? 0; 
        $other_user_type = $_POST['other_user_type'] ?? ''; 

        error_log("--- get_messages ---");
        error_log("Logged in Admin ID: " . $admin_id);
        error_log("Requested Other User ID: " . $other_user_id);
        error_log("Requested Other User Type: " . $other_user_type);

        // --- (DULO NG LOGGING) ---
        if ($other_user_id == 0 || empty($other_user_type)) die("Error: Invalid user selected.");

        $updateQuery = "UPDATE messages SET status = 'read' WHERE sender_id = ? AND sender_type = ? AND receiver_id = ? AND receiver_type = 'admin' AND status = 'unread'";
        $stmt_update = $conn->prepare($updateQuery);
        // Add error check for prepare
        if (!$stmt_update) { error_log("Update Prepare failed: (" . $conn->errno . ") " . $conn->error); } 
        else {
            $stmt_update->bind_param("isi", $other_user_id, $other_user_type, $admin_id);
            if (!$stmt_update->execute()) { error_log("Update Execute failed: (" . $stmt_update->errno . ") " . $stmt_update->error); }
            $stmt_update->close();
        }
       
       // Fetch the conversation history
       $query = "
       SELECT * FROM messages 
       WHERE (sender_id = ? AND sender_type = 'admin' AND receiver_id = ? AND receiver_type = ?) 
          OR (sender_id = ? AND sender_type = ? AND receiver_id = ? AND receiver_type = 'admin')
       ORDER BY sent_at ASC
   ";
   
   $stmt = $conn->prepare($query);
   if (!$stmt) {
    error_log("Select Prepare failed: (" . $conn->errno . ") " . $conn->error);
    die('<p style="color:red">Error preparing statement.</p>');
}

// Use the corrected bind_param
if (!$stmt->bind_param("iisisi", $admin_id, $other_user_id, $other_user_type, $other_user_id, $other_user_type, $admin_id)) {
    error_log("Select Bind failed: (" . $stmt->errno . ") " . $stmt->error);
    die('<p style="color:red">Error binding parameters.</p>');
}

if (!$stmt->execute()) {
   error_log("Select Execute failed: (" . $stmt->errno . ") " . $stmt->error);
   die('<p style="color:red">Error executing statement.</p>');
}

$result = $stmt->get_result();
if (!$result) {
    error_log("Get Result failed: (" . $stmt->errno . ") " . $stmt->error);
    die('<p style="color:red">Error getting results.</p>');
}

// --- (IDAGDAG) LOG ROW COUNT ---
error_log("Number of messages found: " . $result->num_rows);
// --- (DULO NG LOG ROW COUNT) ---

// Build HTML output 
// Build HTML output 
$output = ""; // Initialize output string
if ($result->num_rows > 0) { 
    // Loop through each message found
    while ($row = $result->fetch_assoc()) {
        // Check if the message was sent by the currently logged-in admin/staff
        $is_sent_by_me = ($row['sender_id'] == $admin_id && $row['sender_type'] == 'admin');
        // Format the timestamp (e.g., 09:01 PM)
        $time = date('h:i A', strtotime($row['sent_at']));
        
        // --- ITO ANG TAMANG CODE PARA GUMAWA NG HTML ---
        if ($is_sent_by_me) { 
            // If sent by me (admin/staff), add 'message-sent' class (right side)
            $output .= '
                <div class="chat-message message-sent">
                    ' . htmlspecialchars($row['message']) . '
                    <span class="message-timestamp">' . $time . '</span>
                </div>';
        } else { 
            // If received from the other user, add 'message-received' class (left side)
            $output .= '
                <div class="chat-message message-received">
                    ' . htmlspecialchars($row['message']) . '
                    <span class="message-timestamp">' . $time . '</span>
                </div>';
        }
        // --- DULO NG TAMANG CODE ---
    } // End of while loop
} else { 
    // This message is shown ONLY if $result->num_rows is actually 0
    $output = '<p style="text-align:center; color: #aaa; margin-top: 50px;">No messages yet.</p>'; 
}
// Echo the final HTML output
echo $output; 
$stmt->close(); // Close the statement
break; // End the 'get_messages' case

    // Action to send message (Remains the same - handles both types)
    case 'send_message':
        // ... (Keep the existing code for send_message from the previous combined version) ...
         $receiver_id = $_POST['receiver_id'] ?? 0; $receiver_type = $_POST['receiver_type'] ?? ''; $message_content = trim($_POST['message_content'] ?? '');
         if (empty($message_content) || $receiver_id == 0 || empty($receiver_type)) { die(json_encode(['success' => false, 'error' => 'Invalid data.'])); }
         if ($receiver_type == 'admin' && $receiver_id == $admin_id) { die(json_encode(['success' => false, 'error' => 'Cannot send message to yourself.'])); }
         $query = "INSERT INTO messages (sender_id, sender_type, receiver_id, receiver_type, message, status) VALUES (?, 'admin', ?, ?, ?, 'unread')";
         $stmt = $conn->prepare($query); $stmt->bind_param("isss", $admin_id, $receiver_id, $receiver_type, $message_content);
         if ($stmt->execute()) { echo json_encode(['success' => true]); } else { echo json_encode(['success' => false, 'error' => 'DB Error: ' . $stmt->error]); }
         $stmt->close();
        break;

    // --- (BAGONG CASE) Get all staff with roles for the modal ---
    case 'get_all_staff_with_roles':
        $staff_list = [];
        $roles = []; // To store unique roles
        
        // Ensure 'role' column exists in your 'users' table
        $query_staff = "SELECT id, fullname, role 
                        FROM users 
                        WHERE id != ? 
                          AND role != 'customer' -- Make sure 'customer' role is excluded if they are in users table
                        ORDER BY fullname ASC";
        $stmt_staff = $conn->prepare($query_staff);
        if(!$stmt_staff){
             die(json_encode(['success' => false, 'error' => 'Prepare failed: '.$conn->error]));
        }
        $stmt_staff->bind_param("i", $admin_id); 
        
        if(!$stmt_staff->execute()){
             die(json_encode(['success' => false, 'error' => 'Execute failed: '.$stmt_staff->error]));
        }
        $result_staff = $stmt_staff->get_result();

        if ($result_staff) {
            while ($staff = $result_staff->fetch_assoc()) {
                $staff_list[] = $staff; // Add staff to the list
                 if (!empty($staff['role']) && !in_array($staff['role'], $roles)) {
                    $roles[] = $staff['role']; // Collect unique roles
                }
            }
             sort($roles); // Sort roles alphabetically
            echo json_encode(['success' => true, 'staff' => $staff_list, 'roles' => $roles]);
        } else {
             echo json_encode(['success' => false, 'error' => 'Failed to fetch staff list. Error: '.$conn->error]);
        }
        $stmt_staff->close();
        break;
    // --- (END OF NEW CASE) ---

    // Get unread count (Remains the same)
    case 'get_unread_count':
        // ... (Keep the existing code for get_unread_count) ...
         $query = "SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND receiver_type = 'admin' AND status = 'unread'";
         $stmt = $conn->prepare($query); $stmt->bind_param("i", $admin_id); $stmt->execute();
         $result = $stmt->get_result(); $count = $result->fetch_assoc()['unread_count'] ?? 0;
         echo json_encode(['count' => $count]); $stmt->close();
        break;

    default:
        die(json_encode(['error' => 'Invalid action specified.']));
}

$conn->close();
?>