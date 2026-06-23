<?php

ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);
session_name('admin_session');

session_start();

require_once 'db_connection.php';
// We will include the sidebar inside the body
// include 'sidebar.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Allow only Brand Manager
if ($_SESSION['role'] !== 'Brand Manager') {
    die("Access Denied! This page is only for Brand Manager.");
}
// Fetch price history
$sql = "SELECT ph.id, p.name AS product_name, pv.flavor, pv.pack_size, ph.old_price, ph.new_price, ph.created_at
        FROM price_change_history ph
        JOIN product_variations pv ON ph.variation_id = pv.id
        JOIN products p ON pv.product_id = p.id
        ORDER BY ph.created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Price History - MarkeTrack</title>
<link href="css/style.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
body { background-color: #f4f4f7; font-family: 'Segoe UI', sans-serif; }
.card { border-radius: 12px; }
.card-header { background: #ff6b6b; color: #fff; font-weight: 600; border-top-left-radius: 12px; border-top-right-radius: 12px; }
.table th, .table td { vertical-align: middle !important; }
.btn-back { background: #57606f; color: #fff; border-radius: 8px; }
</style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-history me-2"></i> Price Change History</h3>
        <a href="dynamicpricing.php" class="btn btn-back">
            <i class="fas fa-arrow-left me-1"></i> Back to Dynamic Pricing
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body table-responsive">
            <table class="table table-hover table-bordered text-center">
                <thead class="table-light">
                    <tr>
                        <th>Product</th>
                        <th>Flavor</th>
                        <th>Pack/Size</th>
                        <th>Old Price</th>
                        <th>New Price</th>
                        <th>Date Changed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['product_name'] ?></td>
                            <td><?= $row['flavor'] ?></td>
                            <td><?= $row['pack_size'] ?></td>
                            <td>₱<?= number_format($row['old_price'], 2) ?></td>
                            <td>₱<?= number_format($row['new_price'], 2) ?></td>
                            <td><?= date("M d, Y h:i A", strtotime($row['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-muted">No price change history found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
