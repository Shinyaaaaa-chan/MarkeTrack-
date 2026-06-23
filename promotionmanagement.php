<?php
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);
session_name('admin_session');

session_start();

include 'db_connection.php';
include 'sidebar.php'; // Siguraduhing tama ang file na ito
require_once 'functions.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Manila');
$currentDate = new DateTime();

$user_role = $_SESSION['role'];
$message = ''; // Para sa success/error messages

// --- START: INAYOS NA LOGIC PARA SA APPROVE / REJECT ---
// Dapat unahin ang logic bago ang anumang HTML output

if ($user_role == 'Brand Manager') {
    // Kung pinindot ang "Approve" button
    if (isset($_POST['approve'])) {
        $promotion_id = (int) $_POST['promotion_id'];

        $update = $conn->prepare("UPDATE promotions SET status = 'approved', approved_at = NOW() WHERE id = ? AND status = 'pending'");
        $update->bind_param("i", $promotion_id);

        if ($update->execute() && $update->affected_rows > 0) {
            // Kunin ang promo title para sa notification
            $promo_info_stmt = $conn->prepare("SELECT promo_title FROM promotions WHERE id = ?");
            $promo_info_stmt->bind_param("i", $promotion_id);
            $promo_info_stmt->execute();
            $promo_info_result = $promo_info_stmt->get_result();

            if ($promo_info = $promo_info_result->fetch_assoc()) {
                $promo_title = $promo_info['promo_title'];

                // 1. I-notify ang TMT (Trade and Marketing Team)
                $tmt_users = get_user_ids_by_role($conn, 'Trade and Marketing Team');
                foreach ($tmt_users as $user_id) {
                    createNotification($conn, $user_id, 'admin', "Promotion Approved", "Your promotion '{$promo_title}' has been approved.", "promotionmanagement.php");
                }

                // 2. I-notify ang ABM (Assistant Brand Manager)
                $abm_users = get_user_ids_by_role($conn, 'Assistant Brand Manager');
                foreach ($abm_users as $user_id) {
                    createNotification($conn, $user_id, 'admin', "New Promotion Active", "A new promotion '{$promo_title}' is now active.", "promotionmanagement.php");
                }

                // 3. I-notify ang LAHAT ng Customers (ITO ANG BAGONG DAGDAG)
                $customer_ids_stmt = $conn->query("SELECT id FROM customers");
                while ($customer = $customer_ids_stmt->fetch_assoc()) {
                    createNotification(
                        $conn,
                        $customer['id'],
                        'customer',
                        "🎉 New Promotion Available!",
                        "Check out our new promo: '{$promo_title}'! Don't miss out.",
                        "promotions.php" // Siguraduhing ito ang tamang link para sa customer
                    );
                }
            }
            $_SESSION['message'] = "<div class='alert alert-success'>Promotion approved and notifications sent to staff and customers!</div>";
        }
        header("Location: promotionmanagement.php");
        exit();
    }
    // Kung pinindot ang "Reject" button
    if (isset($_POST['reject'])) {
        $promotion_id = (int) $_POST['promotion_id'];
        $update = $conn->prepare("UPDATE promotions SET status = 'rejected', approved_at = NOW() WHERE id = ? AND status = 'pending'");
        $update->bind_param("i", $promotion_id);

        if ($update->execute() && $update->affected_rows > 0) {
            // Kunin ang promo title para sa notification
            $promo_info_stmt = $conn->prepare("SELECT promo_title FROM promotions WHERE id = ?");
            $promo_info_stmt->bind_param("i", $promotion_id);
            $promo_info_stmt->execute();
            $promo_info_result = $promo_info_stmt->get_result()->fetch_assoc();
            $promo_title = $promo_info_result['promo_title'];

            // I-notify lang ang TMT na rejected
            $tmt_users = get_user_ids_by_role($conn, 'Trade and Marketing Team');
            foreach ($tmt_users as $user_id) {
                createNotification($conn, $user_id, 'admin', "Promotion Rejected", "Your promotion request '{$promo_title}' has been rejected.", "promotionmanagement.php");
            }

            $_SESSION['message'] = "<div class='alert alert-warning'>Promotion rejected.</div>";
        }
        header("Location: promotionmanagement.php");
        exit();
    }
}
// --- END: INAYOS NA LOGIC ---

// Display message from session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}


// --- INAYOS NA PAG-FETCH NG PROMOTIONS ---
$promotions = [];
$sql = "";
if ($user_role == 'Brand Manager') {
    // Kunin lahat, pero unahin ang 'pending'
    $sql = "SELECT * FROM promotions ORDER BY CASE WHEN status = 'pending' THEN 0 ELSE 1 END, created_at DESC";
    $promotions_result = $conn->query($sql);
} else {
    // Para sa ibang roles, 'approved' lang na active
    $sql = "SELECT * FROM promotions WHERE status = 'approved' AND end_date >= CURDATE() ORDER BY created_at DESC";
    $promotions_result = $conn->query($sql);
}

if ($promotions_result) {
    while ($promotion = $promotions_result->fetch_assoc()) {
        $promotions[] = $promotion;
    }
}

/**
 * Helper function para gawing "human-readable" ang promo types
 */
function formatPromoType($type) {
    switch ($type) {
        case 'percentage_discount':
            return 'Percentage Discount';
        case 'buy1take1':
            return 'Buy 1 Take 1';
        case 'fixed_discount':
            return 'Fixed Amount Discount';
        case 'bundle':
            return 'Bundle';
        case 'other':
            return 'Other';
        default:
            // Para sa anumang custom type na maaaring idagdag sa hinaharap
            return ucfirst(str_replace('_', ' ', htmlspecialchars($type)));
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Promotion Management</title>
    <link href="css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
     .sidebar {
         width: 200px; /* fixed width */
         flex-shrink: 0; /* wag lumiit */
     }
     /* Nagdagdag ng style para sa description cell */
     .promo-description {
        max-width: 250px; /* Limitahan ang lapad */
       
     }
    </style>
</head>

<body style="background-color: #f8f9fa;">
<div class="container py-4 bg-white rounded shadow" style="max-height: 100vh; overflow-y: auto;">
    
    <?= $message // Ipapakita ang success/warning message dito ?>

    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div class="alert alert-success">✅ Promotion submitted successfully and is pending approval.</div>
    <?php endif; ?>

    <?php if ($user_role === 'Trade and Marketing Team'): ?>
        <div class="mb-4 text-end">
            <a href="createpromotion.php" class="btn btn-danger">
                <i class="fas fa-plus"></i> Create New Promotion
            </a>
        </div>
    <?php endif; ?>

    <h2 class="mb-4 text-danger fw-bold d-flex justify-content-between align-items-center">
        <span><i class="fas fa-tasks"></i> Promotion Management</span>
        <a href="promo_history.php" class="btn btn-outline-secondary">
            <i class="fas fa-clock-rotate-left"></i> Promo History
        </a>
    </h2>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Promo Title</th>
                        
                        <th>Promo Type</th>
                        <th>Description</th> 
                        <th>Validity Period</th>
                        <th>Applicable Products</th>
                        <th>Status</th>
                        <?php if ($user_role === 'Brand Manager'): ?>
                            <th style="width: 150px;">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($promotions)): ?>
                    <tr><td colspan="7" class="text-center">No promotions found.</td></tr>
                <?php else: ?>
                    <?php foreach ($promotions as $promotion): ?>
                        <tr>
                            <td><?= htmlspecialchars($promotion['promo_title']) ?></td>
                         

                            <td>
                                <?= formatPromoType($promotion['promotion_type']) ?>
                            </td>
                               
                            <td class="promo-description">
                                <?= htmlspecialchars($promotion['promo_description']) ?>
                            </td>

                            <td>
                                <?= date('M d, Y', strtotime($promotion['start_date'])) ?> - 
                                <?= date('M d, Y', strtotime($promotion['end_date'])) ?>
                            </td>
                            
                            <td>
                                <ul class="mb-0 ps-3">
                                    <?php
                                        $pid = $promotion['id'];
                                        
                                        // Ang query na ito ay TAMA na dahil inayos na natin ang database foreign key
                                        $query_string = "SELECT p.name, pv.flavor, pv.pack_size 
                                                         FROM promotion_products pp 
                                                         JOIN product_variations pv ON pv.id = pp.product_id 
                                                         JOIN products p ON p.id = pv.product_id 
                                                         WHERE pp.promotion_id = $pid";
                                        
                                        $products_result = $conn->query($query_string);

                                        if ($products_result && $products_result->num_rows > 0) {
                                            while ($prod = $products_result->fetch_assoc()):
                                                $display_name = htmlspecialchars($prod['name']) . 
                                                                " (" . htmlspecialchars($prod['flavor']) . 
                                                                " - " . htmlspecialchars($prod['pack_size']) . ")";
                                                echo "<li>" . $display_name . "</li>";
                                            endwhile;
                                        } else {
                                            echo "<li>No specific products listed.</li>";
                                        }
                                    ?>
                                </ul>
                            </td>

                            <td>
                                <?php
                                    $startDate = new DateTime($promotion['start_date']);
                                    $endDate = new DateTime($promotion['end_date']);
                                    $status = $promotion['status'];
                                    $badge_class = 'secondary';
                                    $display_status = ucfirst($status);

                                    if ($status === 'approved') {
                                        if ($currentDate < $startDate) {
                                            $display_status = 'Upcoming'; $badge_class = 'info';
                                        } elseif ($currentDate >= $startDate && $currentDate <= $endDate) {
                                            $display_status = 'Active'; $badge_class = 'success';
                                        } else {
                                            $display_status = 'Expired'; $badge_class = 'light text-dark';
                                        }
                                    } elseif ($status === 'rejected') {
                                        $display_status = 'Rejected'; $badge_class = 'danger';
                                    } elseif ($status === 'pending') {
                                        $display_status = 'Pending'; $badge_class = 'warning';
                                    }
                                ?>
                                <span class="badge bg-<?= $badge_class ?>"><?= $display_status ?></span>
                            </td>

                            <?php if ($user_role === 'Brand Manager'): ?>
                            <td>
                                <?php if ($status === 'pending'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="promotion_id" value="<?= $promotion['id'] ?>">
                                        <button type="submit" name="approve" class="btn btn-success btn-sm" title="Approve">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button type="submit" name="reject" class="btn btn-danger btn-sm" title="Reject">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Done</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>