<?php

ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);
session_name('admin_session');

session_start();

require_once 'db_connection.php';
// We will include the sidebar inside the body
include 'sidebar.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Allow only Brand Manager
if ($_SESSION['role'] !== 'Brand Manager') {
    die("Access Denied! This page is only for Brand Manager.");
}

// Fetch product variations for the dropdown, calculating stock from stock_batches.
$sql = "
SELECT
    pv.id,
    p.name AS product_name,
    pv.flavor,
    pv.pack_size,
    pv.price_case,
    COALESCE(SUM(sb.stock), 0) AS total_stock
FROM product_variations pv
JOIN products p ON pv.product_id = p.id
LEFT JOIN stock_batches sb ON sb.variation_id = pv.id
GROUP BY pv.id
ORDER BY p.name, pv.flavor;
";
$result = $conn->query($sql);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['variation_id'])) {
    $variation_id = $_POST['variation_id'];

    // Fetch selected product details, calculating stock from 'stock_batches'.
    $stmt = $conn->prepare("
        SELECT
            p.name AS product_name,
            pv.flavor,
            pv.pack_size,
            pv.price_case,
            COALESCE(SUM(sb.stock), 0) AS total_stock
        FROM product_variations pv
        JOIN products p ON pv.product_id = p.id
        LEFT JOIN stock_batches sb ON sb.variation_id = pv.id
        WHERE pv.id = ?
        GROUP BY pv.id
    ");

    $stmt->bind_param("i", $variation_id);
    $stmt->execute();
    $product_result = $stmt->get_result();

    if ($product_result->num_rows > 0) {
        $product = $product_result->fetch_assoc();
        $product_name = trim($product['product_name']);
        $flavor = trim($product['flavor']);
        $pack_size = trim($product['pack_size']);
        $current_price = (float)$product['price_case'];
        $stock = (int)$product['total_stock'];
        $full_name = '';
        if (!empty($flavor) && stristr($flavor, $product_name) !== false) {
            $full_name = $flavor . ' ' . $pack_size;
        } else {
            $name_parts = array_filter([$product_name, $flavor, $pack_size]);
            $full_name = implode(' ', $name_parts);
        }
        $full_name = preg_replace('/\s+/', ' ', $full_name);
        $query_sales = $conn->prepare("SELECT month, actual_sales FROM historical_data WHERE UPPER(product_name) = UPPER(?) ORDER BY STR_TO_DATE(CONCAT(month, '-01'), '%Y-%m-%d') DESC LIMIT 2");
        $query_sales->bind_param("s", $full_name);
        $query_sales->execute();
        $sales_result = $query_sales->get_result();
        $sales = [];
        while ($row = $sales_result->fetch_assoc()) {
            $sales[] = (float)$row['actual_sales'];
        }
        $status = "⚠️ No sales data found for '$full_name' — cannot adjust price.";
        $debug = "📊 Debug → Not enough historical sales data to perform analysis.";
        if (count($sales) == 2) {
            $latest_sales = $sales[0];
            $prev_sales = $sales[1];
            if ($prev_sales > 0) {
                $growth_rate = (($latest_sales - $prev_sales) / $prev_sales) * 100;
                $new_price = $current_price;
                if ($growth_rate >= 20 && $stock <= 200) {
                    $new_price *= 1.15;
                    $status = "📈 Strong demand — price increased by 15%.";
                } elseif ($growth_rate >= 10 && $stock <= 300) {
                    $new_price *= 1.08;
                    $status = "📊 Moderate demand — price increased by 8%.";
                } elseif ($growth_rate <= -10 && $stock > 400) {
                    $new_price *= 0.92;
                    $status = "📉 Low demand — price decreased by 8%.";
                } else {
                    $status = "Stable demand — no price change applied.";
                }
                if ($new_price != $current_price) {
                    $reason_for_log = preg_replace('/[^\p{L}\p{N}\s.,;—%]/u', '', strip_tags($status));
                    $stmt_update = $conn->prepare("UPDATE product_variations SET price_case = ? WHERE id = ?");
                    $stmt_update->bind_param("di", $new_price, $variation_id);
                    $stmt_update->execute();
                    $stmt_log = $conn->prepare("INSERT INTO price_change_history (variation_id, old_price, new_price, reason, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt_log->bind_param("idds", $variation_id, $current_price, $new_price, $reason_for_log);
                    $stmt_log->execute();
                }
                $debug = "📊 Debug → Latest sales: {$latest_sales} | Previous sales: {$prev_sales} | Growth: " . round($growth_rate, 2) . "% | Stock: $stock";
            } else {
                $status = "⚠️ Previous month's sales were zero — cannot calculate growth rate.";
                $debug = "📊 Debug → Trend calculation aborted to prevent division by zero.";
            }
        }
        $success = "✅ $status <br><small>$debug</small>";
    } else {
        $error = "Error: Could not find the selected product variation.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dynamic Pricing - MarkeTrack</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="css/style.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  .content-center{
    margin:auto;
    
  }
  .sidebar {
width: 200px; /* fixed width */
flex-shrink: 0; /* wag lumiit */
}

/* You can keep these specific styles or move them to your main style.css */
.card-header-custom { 
    background: linear-gradient(90deg); 
    color: white; 
    font-weight: 600; 
    font-size: 1.2rem;
}
.btn-danger-custom { 
    background: #ff4d4d; 
    border: none; 
    border-radius: 10px; 
    font-weight: 600; 
}
.btn-danger-custom:hover { background: #ff6b6b; }
.form-select-custom { 
    border-radius: 10px; 
    font-size: 0.95rem; 
    box-shadow: 0 2px 6px rgba(0,0,0,0.05); 
}

/* --- Media Queries for 768px --- */
@media (max-width: 768px) {
    .card-body {
        padding: 1.5rem !important; /* Binawasan ang padding sa card body */
    }

    h1.h3 {
        font-size: 1.5rem; /* Pinaliit ang font size ng page heading */
    }
}
</style>
</head>

<body style="background-color: #f8f9fa;">
<div class="container py-4 bg-white rounded shadow" style="max-height: 100vh; overflow-y: auto;">




                <div class="card shadow mb-4">
                    <div class="card-header py-3 card-header-custom">
                        <h6 class="m-0 font-weight-bold text-black text-center">Price Adjustment</h6>
                    </div>
                    <div class="card-body p-4">

                        <div class="text-end mb-4">
                            <a href="price_history.php" class="btn btn-outline-secondary rounded-pill"><i class="fas fa-history me-1"></i> View Price History</a>
                        </div>
                        
                        <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="dynamicpricing.php">
                            <div class="mb-4">
                                <label for="variation_id" class="form-label fw-semibold">Select Product Variation</label>
                                <select name="variation_id" id="variation_id" class="form-select form-select-custom" required>
                                    <option value="" disabled selected>Choose a product...</option>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php while($row = $result->fetch_assoc()): ?>
                                            <option value="<?= htmlspecialchars($row['id']) ?>">
                                                <?= htmlspecialchars($row['product_name'] . ' — ' . $row['flavor'] . ' (' . $row['pack_size'] . ')') ?>
                                                | ₱<?= number_format($row['price_case'], 2) ?> | Stock: <?= htmlspecialchars($row['total_stock']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-danger-custom w-100 py-2"><i class="fas fa-sync-alt me-2"></i> Run Auto Price Adjustment</button>
                        </form>

                    </div>
                </div>

            </div> </div>
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>