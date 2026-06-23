<?php

ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);
session_name('admin_session');

session_start();

require_once 'db_connection.php';
include 'sidebar.php';
require_once 'functions.php';     // For createNotification()

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$user_role = $_SESSION['role'] ?? 'guest';
$role_status_access = [
    'Assistant Brand Manager' => ['pending', 'processing', 'declined', 'completed', 'cancelled'],
    'Brand Manager' => ['processing', 'completed'],
    'Trade And Marketing Team' => ['processing', 'completed'],
    'Merchandising Marketing Team' => ['processing', 'completed'],
];


$allowed_statuses = $role_status_access[$user_role] ?? ['processing', 'completed'];
$status_filter = isset($_GET['status']) && in_array($_GET['status'], $allowed_statuses)
    ? $_GET['status']
    : $allowed_statuses[0];

// --- Handles All Form Submissions (Approve/Decline) ---
if ($user_role === 'Assistant Brand Manager') {

    // --- APPROVE LOGIC ---
    if (isset($_POST['approve'])) {
        $order_id = (int)$_POST['order_id'];

        try {
            // 1. Begin Transaction
            $conn->begin_transaction();

            // 2. Update order status to 'processing'
            $stmt = $conn->prepare("UPDATE orders SET status = 'processing' WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();

            // 3. Get all items in the order
            $stmt_items = $conn->prepare("SELECT variation_id, quantity FROM order_items WHERE order_id = ?");
            $stmt_items->bind_param("i", $order_id);
            $stmt_items->execute();
            $result_items = $stmt_items->get_result();

// ... (simula ng approve logic) ...

// 4. Loop through each item and deduct stock from batches (FIFO)
while ($item = $result_items->fetch_assoc()) {
    $variation_id = $item['variation_id'];
    $quantity_needed = $item['quantity'];

    // CHANGE 1: Select the new 'original_stock' column
    $stmt_batches = $conn->prepare("SELECT id, stock, original_stock FROM stock_batches WHERE variation_id = ? AND stock > 0 ORDER BY date_added ASC");
    $stmt_batches->bind_param("i", $variation_id);
    $stmt_batches->execute();
    $result_batches = $stmt_batches->get_result();

    while ($quantity_needed > 0 && ($batch = $result_batches->fetch_assoc())) {
        $take_from_batch = min($quantity_needed, $batch['stock']);
        $new_stock = $batch['stock'] - $take_from_batch;
        $quantity_needed -= $take_from_batch;

        $stmt_update_batch = $conn->prepare("UPDATE stock_batches SET stock = ? WHERE id = ?");
        $stmt_update_batch->bind_param("ii", $new_stock, $batch['id']);
        $stmt_update_batch->execute();
        // ... sa loob ng while loop pagkatapos ng $stmt_update_batch->execute(); ...

// START: INAYOS NA LOW STOCK / OUT OF STOCK LOGIC
// =========================================================================

// A. Kunin ang TOTAL na natitirang stock para sa variation na ito (hindi lang sa batch)
$stmt_check_total_stock = $conn->prepare("SELECT SUM(stock) AS total_stock FROM stock_batches WHERE variation_id = ?");
$stmt_check_total_stock->bind_param("i", $variation_id);
$stmt_check_total_stock->execute();
$total_stock_result = $stmt_check_total_stock->get_result()->fetch_assoc();
$total_remaining_stock = $total_stock_result['total_stock'] ?? 0;
$stmt_check_total_stock->close();

// B. Kunin ang product info at low stock threshold
// (Yung code mo para dito ay okay na, i-assume natin na andiyan pa)
// ... $product_name, $variation_name, $low_stock_threshold ...

// C. I-check kung OUT OF STOCK o LOW STOCK na ba
$assistant_ids = get_user_ids_by_role($conn, 'Assistant Brand Manager');

if ($total_remaining_stock <= 0) {
    // --- OUT OF STOCK NOTIFICATION ---
    $notification_message = "Out of Stock: Ang produktong $product_name ($variation_name) ay tuluyan nang naubos.";
    foreach ($assistant_ids as $assistant_id) {
        createNotification(
            $conn, 
            $assistant_id, 
            'admin', 
            'Out of Stock Alert', 
            $notification_message, 
            'inventory.php' 
        );
    }
} else if ($total_remaining_stock <= $low_stock_threshold) {
    // --- LOW STOCK NOTIFICATION (ito yung dati mong code) ---
    $notification_message = "Low Stock Alert: Ang $product_name ($variation_name) ay mababa na sa stock. Natitira: $total_remaining_stock.";
    foreach ($assistant_ids as $assistant_id) {
        createNotification(
            $conn, 
            $assistant_id, 
            'admin', 
            'Low Stock Alert', 
            $notification_message, 
            'inventory.php' 
        );
    }
}
// =========================================================================
// END: INAYOS NA LOW STOCK / OUT OF STOCK LOGIC
        
        // START: LOW STOCK NOTIFICATION LOGIC (based on 1/4 of original)
        // =========================================================================
        
        // Calculate the 1/4 threshold for this specific batch
        $low_stock_threshold = $batch['original_stock'] / 4;

        // Check if the new stock level has crossed the threshold
        // We also check if the previous stock was above the threshold to avoid spamming notifications
        if ($new_stock <= $low_stock_threshold && $batch['stock'] > $low_stock_threshold) {
            
            // Get product info for a clear notification message
            $stmt_product_info = $conn->prepare("
                SELECT p.name, pv.flavor, pv.pack_size
                FROM product_variations pv 
                JOIN products p ON pv.product_id = p.id 
                WHERE pv.id = ?
            ");
            $stmt_product_info->bind_param("i", $variation_id);
            $stmt_product_info->execute();
            $product_info = $stmt_product_info->get_result()->fetch_assoc();
            
            $product_name = $product_info['name'] ?? 'Unknown Product';
            $variation_name = trim(($product_info['flavor'] ?? '') . ' ' . ($product_info['pack_size'] ?? ''));

            // Get Assistant IDs and send notification
            $assistant_ids = get_user_ids_by_role($conn, 'Assistant Brand Manager');
            $notification_message = "Low stock: $product_name ($variation_name) is now at 25% or less of its batch capacity. Remaining stock in batch: $new_stock.";
            
            foreach ($assistant_ids as $assistant_id) {
                createNotification(
                    $conn, 
                    $assistant_id,      // 1. user_id ng tatanggap
                    'admin',             // 2. user_type (ITO ANG MADALAS NAKAKALIMUTAN)
                    'Low Stock Alert',   // 3. title
                    $notification_message, // 4. message
                    'inventory.php'      // 5. link
                );
            }
        }
        // =========================================================================
        // END: LOW STOCK NOTIFICATION LOGIC
    }
}

// 5. If all steps are successful, commit the changes
$conn->commit();

// ... (iyong code para sa notifications sa customer at iba pang staff) ...

            // --- Start sending notifications AFTER successful commit ---

            // 6. Notify the Customer
            $stmtCustomer = $conn->prepare("SELECT user_id FROM orders WHERE id = ?");
            $stmtCustomer->bind_param("i", $order_id);
            $stmtCustomer->execute();
            $resultCustomer = $stmtCustomer->get_result();
            if ($customer = $resultCustomer->fetch_assoc()) {
                createNotification($conn, $customer['user_id'],'customer',  "Order Approved", "Your order #$order_id has been approved and is now being processed.", "orders.php");
            }
            $stmtCustomer->close();
            
            // 7. Notify Internal Staff Members
            $staff_roles_to_notify = ['Brand Manager', 'Trade And Marketing Team', 'Merchandising Marketing Team'];
            foreach ($staff_roles_to_notify as $role) {
                $user_ids = get_user_ids_by_role($conn, $role);
                foreach ($user_ids as $user_id) {
                    createNotification($conn, $user_id, "Order Processing", "Order #$order_id has been approved and is now being processed.", "orders.php?status=processing");
                }
            }

        } catch (Exception $e) {
            // If any error occurs, roll back all database changes
            $conn->rollback();
            // Optional: Log the detailed error for debugging purposes
            error_log("Order approval failed for order #$order_id: " . $e->getMessage());
            // Show a generic error message to the user
            echo "An error occurred while approving the order. Please try again.";
            exit(); // Stop execution on error
        }

        // 8. Redirect after the entire successful operation
        header("Location: orders.php");
        exit();
    }

    // --- DECLINE LOGIC ---
    if (isset($_POST['decline'])) {
        $order_id = (int)$_POST['order_id'];

        // Update order status
        $stmt_decline = $conn->prepare("UPDATE orders SET status = 'declined' WHERE id = ?");
        $stmt_decline->bind_param("i", $order_id);
        $stmt_decline->execute();

        // Send notification to the customer
        $stmtCustomer = $conn->prepare("SELECT user_id FROM orders WHERE id = ?");
        $stmtCustomer->bind_param("i", $order_id);
        $stmtCustomer->execute();
        $resultCustomer = $stmtCustomer->get_result();

      // ... (sa loob ng DECLINE LOGIC) ...

    if ($customer = $resultCustomer->fetch_assoc()) {
        createNotification(
            $conn,
            $customer['user_id'],
            'customer',          // <-- IDAGDAG ITO. Ito ang user_type
            "Order Declined",
            "Unfortunately, your order #{$order_id} has been declined. Please contact us for more details.",
            "orders.php"
        );
    }
// ...

        $stmtCustomer->close();

        header("Location: orders.php");
        exit();
    }
}

// --- Fetches and prepares order data for display on the page ---
$stmt = $conn->prepare("
    SELECT 
    o.id AS order_id,
    o.order_date,
    o.status,
    pr.promo_title,
    GROUP_CONCAT(DISTINCT p.name SEPARATOR '|') AS product_names,
    GROUP_CONCAT(
        CASE 
            WHEN pv.flavor IS NOT NULL OR pv.pack_size IS NOT NULL 
            THEN CONCAT_WS(' - ', pv.flavor, pv.pack_size)
            ELSE 'Standard' 
        END 
        SEPARATOR '|'
    ) AS variations,
    GROUP_CONCAT(DISTINCT oi.quantity SEPARATOR '|') AS quantities,
    GROUP_CONCAT(DISTINCT oi.price SEPARATOR '|') AS unit_prices,
    c.fullname,
    c.store_name,
    c.address
FROM orders o
INNER JOIN order_items oi ON o.id = oi.order_id
INNER JOIN products p ON oi.product_id = p.id
LEFT JOIN product_variations pv ON oi.variation_id = pv.id
LEFT JOIN customers c ON o.user_id = c.id
LEFT JOIN promotions pr ON o.promotion_id = pr.id
WHERE o.status = ?
GROUP BY o.id
ORDER BY o.order_date DESC
");

$stmt->bind_param("s", $status_filter);

if ($stmt->execute()) {
    $result = $stmt->get_result();

    if ($result) {
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $items = [];
            $product_names = explode('|', $row['product_names']);
            $variations = explode('|', $row['variations']);
            $quantities = explode('|', $row['quantities']);
            $unit_prices = explode('|', $row['unit_prices']);

            for ($i = 0; $i < count($product_names); $i++) {
                $items[] = [
                    'product_name' => $product_names[$i],
                    'variation' => $variations[$i] ?? 'Standard',
                    'quantity' => $quantities[$i],
                    'unit_price' => $unit_prices[$i],
                    'total_price' => $quantities[$i] * $unit_prices[$i]
                ];
            }

            $orders[$row['order_id']] = [
                'order_date' => $row['order_date'],
                'status' => $row['status'],
                'promo_title' => $row['promo_title'],
                'fullname' => $row['fullname'],
                'store_name' => $row['store_name'],
                'address' => $row['address'],
                'items' => $items
            ];
        }
    } else {
        echo "No orders found or query failed.";
    }
} else {
    echo "Query failed: " . $stmt->error;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders</title>
    <link href="css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
/* Make body and html fixed layout (no outer scroll) */
html, body {
    height: 100%;
    overflow: hidden; /* disable body scroll */
    margin: 0;
    padding: 0;
}

.main-container {
    margin-left: 0px; /* same as your sidebar width */
    padding: 2rem;
    overflow-y: auto;
    min-height: 100vh; /* full height of viewport */
    background-color: #f8f9fc;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: calc(100% - 250px); /* fill the rest beside sidebar */
    box-sizing: border-box;
}
/* Order Card */
.order-card {
    width: 100%; /* ← full width */
    max-width: 1100px;
    margin: 0 auto 25px auto; /* center horizontally */
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    padding: 1.8rem;
    border: 1px solid #eee;
}

/* Title */
h1 {
    text-align: center;
    color: #222;
    margin-bottom: 25px;
    font-size: 2.2rem;
}
/* --- Status Filter Bar --- */


/* Scrollbar for Chrome */
.status-filter::-webkit-scrollbar {
    height: 6px;
}
.status-filter::-webkit-scrollbar-thumb {
    background: #007bff;
    border-radius: 10px;
}
.status-filter::-webkit-scrollbar-track {
    background: #f1f1f1;
}

/* Status Buttons */
.status-filter a {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 30px;
    text-decoration: none;
    color: #007bff;
    background: #fff;
    border: 2px solid #007bff;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.status-filter a.active {
    background: #007bff;
    color: #fff;
    box-shadow: 0 3px 6px rgba(0, 123, 255, 0.3);
}

.status-filter a:hover {
    background: #0056b3;
    color: #fff;
    border-color: #0056b3;
}

.order-header {
    font-size: 1.1rem;
    font-weight: bold;
    margin-bottom: 10px;
    margin-top: 50px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 15px;
    /* Removed the horizontal line here */
}

/* Status Badges */
.status-badge {
    padding: 3px 14px;
    border-radius: 13px;
    font-size: 13px;
    color: #fff;
    font-weight: 500;
}
.status-badge.processing { background: #17a2b8; }
.status-badge.declined { background: #6c757d; }
.status-badge.completed { background: #28a745; }
.status-badge.cancelled { background: #dc3545; }
.status-badge.pending { background: #ffc107; }

/* Table */
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 13px;
}

th, td {
    padding: 12px 10px;
    border: none;
    border-bottom: 1px solid #eee;
    text-align: left;
    word-wrap: break-word;
}

th {
    background: #e9ecef;
    color: #495057;
    font-weight: 600;
    font-size: 0.9rem;
}

td {
    color: #212529;
    font-size: 0.9rem;
}

/* Buttons */
.order-actions {
    margin-top: 15px;
    text-align: right;
}

.action-btn {
    background-color: #4e73df;
    color: white;
    padding: 9px 22px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    margin-left: 10px;
    font-size: 15px;
    font-weight: 500;
    transition: background 0.2s;
}

.action-btn.decline {
    background-color: #dc3545;
}

.action-btn:hover {
    background-color: #2e59d9;
}
.action-btn.decline:hover {
    background-color: #c82333;
}
/* --- General scrollbar style --- */
.main-container::-webkit-scrollbar {
    width: 8px;
}

.main-container::-webkit-scrollbar-thumb {
    background: #bbb;
    border-radius: 10px;
}

.main-container::-webkit-scrollbar-thumb:hover {
    background: #888;
}


@media (max-width: 576px) {
    .main-container {
        padding: 0.8rem;
    }

    .status-filter a {
        font-size: 12px;
        padding: 10px 20px;
        padding: 5px 10px;
    }
}
/* Order Card */
.order-card {
    width: 100%; /* ← full width */
    max-width: 1100px;
    margin: 0 auto 25px auto; /* center horizontally */
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    padding: 1.8rem;
    border: 1px solid #eee;
}
/* Responsive Styles */
@media (max-width: 768px) {
    h1 {
        font-size: 1.8rem;
        margin-top: 10px;
    }

    .status-filter {
        justify-content: flex-start;
        padding: 8px 12px;
        padding: 10px 20px;
        border-radius: 0;
        width: 100%;
        box-shadow: none;
     
        border-bottom: 2px solid #eaeaea;
    }

    .status-filter a {
        padding: 8px 14px;
        font-size: 13px;
    }
    .order-card {
        padding: 1rem;
        border-radius: 10px;
        width: 100%;
        box-shadow:#fff;
    }

    .order-header {
        font-size: 1rem;
    }

    table, thead, tbody, th, td, tr {
        display: block;
    }

    thead tr {
        position: absolute;
        top: -9999px;
        left: -9999px;
    }

    tr {
        border: 1px solid #ccc;
        margin-bottom: 10px;
        display: flex;
        flex-direction: column;
    }

    td {
        border: none;
        border-bottom: 1px solid #eee;
        position: relative;
        padding: 8px 10px 8px 50%;
        text-align: right;
        width: 100%;
    }

    td:before {
        position: absolute;
        top: 6px;
        left: 6px;
        width: 45%;
        white-space: nowrap;
        content: attr(data-label);
        font-weight: bold;
        text-align: left;
        color: #555;
    }

    .order-actions {
        text-align: center;
    }

    .action-btn {
        width: 100%;
        margin: 5px 0;
    }
}
/* Order Card */
.order-card {
    width: 100%; /* ← full width */
    max-width: 1100px;
    margin: 0 auto 25px auto; /* center horizontally */
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    padding: 1.8rem;
    border: 1px solid #eee;
}
@media (max-width: 991px) {
    .main-container {
        margin-left: 0; /* remove sidebar space */
        width: 100%;
        height: auto;
        min-height: 100vh;
        overflow-y: auto;
        overflow-x: hidden; /* prevent sideways scroll */
        padding: 1.5rem 1rem 3rem 1rem;
    }
    h1 {
        margin-top: 10px;
    }

    .status-filter a{
        padding: 10px 20px;
        margin-top: 15px;
        justify-content: flex-start;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch; /* smooth scroll on phones */
        scrollbar-width: thin;
    }

    /* Make scrollbar visible but slim on small screens */
    .main-container::-webkit-scrollbar {
        width: 5px;
    }

    .main-container::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 8px;
    }
}
/* Order Card */
.order-card {
    width: 100%; /* ← full width */
    max-width: 1100px;
    margin: 0 auto 25px auto; /* center horizontally */
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    padding: 1.8rem;
    border: 1px solid #eee;
}
</style>

</head>
<body>

    <div class="main-container">
        <h1>Orders</h1>
        <div class="status-filter">
            <?php foreach ($allowed_statuses as $status): ?>
                <a href="?status=<?= htmlspecialchars($status) ?>" class="<?= ($status === $status_filter) ? 'active' : '' ?>">
                    <?= ucfirst($status) ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <?php if (!empty($orders)): ?>
            <?php foreach ($orders as $order_id => $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        Order #<?= htmlspecialchars($order_id) ?> (Date: <?= htmlspecialchars($order['order_date']) ?>)
                        <span class="status-badge <?= htmlspecialchars($order['status']) ?>">
                            <?= ucfirst($order['status']) ?>
                        </span>
                    </div>
                    <div>
    <strong>Customer:</strong> <?= htmlspecialchars($order['fullname'] ?? '') ?><br>
    <strong>Store Name:</strong> <?= htmlspecialchars($order['store_name'] ?? '') ?><br>
    <strong>Address:</strong> <?= htmlspecialchars($order['address'] ?? '') ?><br>
    <?php if (!empty($order['promo_title'])): ?>
        <div><strong>Applied Promotion:</strong> 
    <?= !empty($order['promo_title']) ? htmlspecialchars($order['promo_title']) : 'None' ?>
</div>
<?php endif; ?>
</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Variation</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grand_total = 0; // Initialize grand total
                            foreach ($order['items'] as $item): 
                                $row_total = $item['quantity'] * $item['unit_price'];
                                $grand_total += $row_total;
                            ?>
                                <tr>
                                    <td data-label="Product Name"><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td data-label="Variation"><?= htmlspecialchars($item['variation']) ?></td>
                                    <td data-label="Quantity"><?= htmlspecialchars($item['quantity']) ?></td>
                                    <td data-label="Unit Price">₱<?= number_format($item['unit_price'], 2) ?></td>
                                    <td data-label="Total Price">₱<?= number_format($row_total, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($order['status'] === 'pending' && $user_role === 'Assistant Brand Manager'): ?>
                        <form method="post" class="order-actions">
                            <input type="hidden" name="order_id" value="<?= htmlspecialchars($order_id) ?>" />
                            <button type="submit" name="approve" class="action-btn">Approve</button>
                            <button type="submit" name="decline" class="action-btn decline">Decline</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align:center;">No orders to manage.</p>
        <?php endif; ?>
    </div>
</body>
</html>
