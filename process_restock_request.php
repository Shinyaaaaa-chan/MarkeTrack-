<?php
session_name('admin_session');
session_start();

include 'db_connection.php';
require_once 'functions.php';

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? '';

// Security check: Dapat ABM lang at POST request
if (!$user_id || $user_role !== 'Assistant Brand Manager' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

$variation_id = filter_input(INPUT_POST, 'variation_id', FILTER_VALIDATE_INT);
$notes = trim($_POST['notes']);

if ($variation_id) {
    $conn->begin_transaction();
    try {
        // 1. I-save ang request sa `restock_requests` table
        $stmt = $conn->prepare("INSERT INTO restock_requests (product_variation_id, requested_by_id, notes) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $variation_id, $user_id, $notes);
        $stmt->execute();
        
        // 2. Magpadala ng notification sa Merchandising & Marketing Team (MMT)
        
        // Kunin ang pangalan ng produkto para sa message
        $product_info_stmt = $conn->prepare("SELECT p.name, pv.flavor, pv.pack_size FROM product_variations pv JOIN products p ON pv.product_id = p.id WHERE pv.id = ?");
        $product_info_stmt->bind_param("i", $variation_id);
        $product_info_stmt->execute();
        $info = $product_info_stmt->get_result()->fetch_assoc();
        $product_name_full = $info['name'] . ' (' . $info['flavor'] . ' / ' . $info['pack_size'] . ')';
        
        // Kunin ang MMT users
        $mmt_users = get_user_ids_by_role($conn, 'Merchandising Marketing Team');
        $abm_name = $_SESSION['fullname'] ?? 'the ABM';
        
        $message = "{$abm_name} requested a restock for: {$product_name_full}.";
        if (!empty($notes)) {
            $message .= " Notes: \"{$notes}\"";
        }
        
        foreach ($mmt_users as $mmt_user_id) {
            createNotification(
                $conn,
                $mmt_user_id,
                'admin',
                '📦 New Restock Request',
                $message,
                'mmt_restock_requests.php' // Page kung saan makikita ng MMT ang requests (gagawin sa susunod)
            );
        }
        
        $conn->commit();
        $_SESSION['success_message'] = "Restock request for {$product_name_full} has been sent!";
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error sending request: " . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = "Invalid product selected.";
}

header("Location: inventoryoverview.php");
exit();
?>