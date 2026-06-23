<?php
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);
session_name('admin_session');

session_start();

include 'db_connection.php';
include 'sidebar.php';
require_once 'functions.php';

// --- Security Check: Tanging MMT lang ang pwedeng mag-access ---
$user_role = $_SESSION['role'] ?? '';
if ($user_role !== 'Merchandising Marketing Team') {
    $_SESSION['error_message'] = "You are not authorized to access this page.";
    header("Location: inventoryoverview.php");
    exit;
}

$variation_id = $_POST['variation_id'] ?? null;
$stock_quantity = $_POST['stock_quantity'] ?? null;
$expiration_date = $_POST['expiration_date'] ?? null;
$batch_number = $_POST['batch_number'] ?? ''; // Opsyonal

// --- PANG-PROSESO NG FORM (KAPAG NAG-SUBMIT) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty($variation_id) || empty($stock_quantity) || empty($expiration_date)) {
        $_SESSION['error_message'] = "Please fill in all required fields (Product, Quantity, Expiration Date).";
    } elseif (!is_numeric($stock_quantity) || $stock_quantity <= 0) {
        $_SESSION['error_message'] = "Stock quantity must be a positive number.";
    } else {
        // Lahat ay OK, i-insert sa database
        // Gagamit tayo ng prepared statement para iwas SQL injection
        $sql = "INSERT INTO stock_batches (variation_id, stock, original_stock, expiration_date, batch_number) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        // Itatakda natin ang 'stock' at 'original_stock' sa parehong value
        $stmt->bind_param("iiiss", $variation_id, $stock_quantity, $stock_quantity, $expiration_date, $batch_number);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Stock added successfully!";
        } else {
            $_SESSION['error_message'] = "Error adding stock: " . $stmt->error;
        }
        $stmt->close();
        
        // Bumalik sa inventory overview pagkatapos
        header("Location: inventoryoverview.php");
        exit;
    }
}

// --- PANG-DISPLAY NG FORM (KAPAG BINUKSAN ANG PAGE) ---

// Kunin ang variation ID mula sa URL (kung meron) para i-pre-select
$selected_variation_id = $_GET['variation_id'] ?? '';

// Kunin lahat ng product variations para sa dropdown
$products_sql = "SELECT pv.id, p.name, pv.flavor, pv.pack_size 
                 FROM product_variations pv
                 JOIN products p ON p.id = pv.product_id
                 ORDER BY p.name, pv.flavor, pv.pack_size";
$products_result = $conn->query($products_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Stock - MarkeTrack</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body id="page-top">
<div id="content-wrapper" class="d-flex flex-column">
    <div id="content" class="container">
        
        <h1 class="h3 mb-4 text-gray-800">Add New Stock Batch</h1>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form action="add_stock.php" method="POST">
                    
                    <div class="mb-3">
                        <label for="variation_id" class="form-label">Product Variation <span class="text-danger">*</span></label>
                        <select class="form-select" id="variation_id" name="variation_id" required>
                            <option value="">Select a product...</option>
                            <?php if ($products_result->num_rows > 0): ?>
                                <?php while($row = $products_result->fetch_assoc()): ?>
                                    <?php 
                                        $product_name = htmlspecialchars($row['name'] . ' (' . $row['flavor'] . ' / ' . $row['pack_size'] . ')');
                                        // Titingnan kung itong option ba ang dapat i-select
                                        $is_selected = ($row['id'] == $selected_variation_id) ? 'selected' : '';
                                    ?>
                                    <option value="<?= $row['id'] ?>" <?= $is_selected ?>>
                                        <?= $product_name ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="stock_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" min="1" required>
                    </div>

                    <div class="mb-3">
                        <label for="expiration_date" class="form-label">Expiration Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="expiration_date" name="expiration_date" min="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="batch_number" class="form-label">Batch Number (Optional)</label>
                        <input type="text" class="form-control" id="batch_number" name="batch_number" placeholder="e.g., BATCH-001">
                    </div>
                    
                    <hr>

                    <div class="d-flex justify-content-end">
                        <a href="inventoryoverview.php" class="btn btn-secondary me-2">Cancel</a>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-plus-circle me-1"></i> Add Stock
                        </button>
                    </div>

                </form>
            </div>
        </div>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>