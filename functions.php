<?php

/**
 * Creates a notification for a user and handles potential errors.
 * Ang function na ito ay mas "robust" o matatag dahil nagche-check ito ng errors.
 *
 * @param mysqli $conn The database connection object.
 * @param int $user_id The ID of the user to notify.
 * @param string $user_type The type of user (e.g., 'admin', 'customer').
 * @param string $title The title of the notification.
 * @param string $message The main content of the notification.
 * @param string|null $link An optional link for the notification.
 * @return bool Returns true on success, false on failure.
 */
function createNotification($conn, $user_id, $user_type, $title, $message, $link = null) {
    // INAYOS: Idinagdag ang `created_at` at `is_read` sa query.
    // Ang database na ang bahala maglagay ng value sa kanila (NOW() para sa petsa, 0 para sa is_read).
    $sql = "INSERT INTO notifications (user_id, user_type, title, message, link, created_at, is_read) 
            VALUES (?, ?, ?, ?, ?, NOW(), 0)";
    
    $stmt = $conn->prepare($sql);

    // BAGO: Error handling kung sakaling mag-fail ang pag-prepare ng query.
    if ($stmt === false) {
        error_log("SQL prepare failed: " . $conn->error); // Nagsusulat sa error log ng server
        return false;
    }
    
    // Ang bind_param ay para sa 5 placeholders (?) sa taas.
    $stmt->bind_param("issss", $user_id, $user_type, $title, $message, $link);
    
    // BAGO: Nagche-check kung naging successful ang pag-execute.
    if ($stmt->execute()) {
        $stmt->close();
        return true; // Nagsasabing successful
    } else {
        error_log("SQL execute failed: " . $stmt->error); // Nagsusulat sa error log ng server
        $stmt->close();
        return false; // Nagsasabing failed
    }
}

/**
 * Gets all user IDs for a given role with error handling.
 *
 * @param mysqli $conn The database connection object.
 * @param string $role The role to search for (e.g., 'Assistant Brand Manager').
 * @return array Returns an array of user IDs or an empty array on failure.
 */
function get_user_ids_by_role($conn, $role) {
    $ids = [];
    $sql = "SELECT id FROM users WHERE role = ?";
    
    $stmt = $conn->prepare($sql);

    // BAGO: Error handling para sa mas matatag na code.
    if ($stmt === false) {
        error_log("SQL prepare failed for get_user_ids_by_role: " . $conn->error);
        return []; // Nagbabalik ng empty array kung may error
    }

    $stmt->bind_param("s", $role);
    
    if (!$stmt->execute()) {
        error_log("SQL execute failed for get_user_ids_by_role: " . $stmt->error);
        $stmt->close();
        return []; // Nagbabalik ng empty array kung may error
    }
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $ids[] = $row['id'];
    }
    
    $stmt->close();
    return $ids;
}

?>