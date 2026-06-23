<?php

ini_set('session.gc_maxlifetime', 86400); // 24 hours
session_set_cookie_params(86400);
session_name('admin_session');

session_start();

require_once 'functions.php';

include 'db_connection.php';
require_once 'load_sales_data.php';
require_once 'holt_winters_forecast.php'; // Load forecasting functions including flattenSalesData()
require_once 'other_forecasts.php'; // <--- IDAGDAG MO ITONG LINYA
include 'sidebar.php';




if (!isset($_SESSION['user_id'])) {
header("Location: login.php");
exit();
}

// Fetch logged-in user data (Staff)
$user_id = $_SESSION['user_id'];
$query = "SELECT fullname, role, profile_image FROM users WHERE id = ?"; 
$stmt_user = $conn->prepare($query);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
if (!$result_user) die("Database query failed: " . $conn->error);
$user = $result_user->fetch_assoc();
$stmt_user->close();



$productQuery = "
    SELECT 
        p.id AS product_id,
        p.name AS product_name,
        v.id AS variation_id,
        v.flavor,
        v.pack_size
    FROM products p
    JOIN product_variations v ON p.id = v.product_id
    ORDER BY p.name, v.flavor, v.pack_size
";

$productResult = mysqli_query($conn, $productQuery);
if (!$productResult) {
    die("Error fetching product variations: " . mysqli_error($conn));
}

mysqli_data_seek($productResult, 0);

// Get the first product (to use as default if none selected)
$firstProduct = mysqli_fetch_assoc($productResult);
mysqli_data_seek($productResult, 0); // reset pointer again for loop

$firstProductValue = $firstProduct
    ? $firstProduct['product_id'] . '_' . $firstProduct['variation_id']
    : '';

$currentProduct = $_GET['product'] ?? $firstProductValue; // if none selected, use first product


$totalSales = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT SUM(total_price) AS total_sales FROM orders
 WHERE MONTH(order_date) = MONTH(CURDATE())
 AND YEAR(order_date) = YEAR(CURDATE())"
))['total_sales'] ?? 0;


$demandRow = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT p.name, SUM(oi.quantity) AS total_sold
FROM order_items oi
JOIN products p ON oi.product_id = p.id
GROUP BY oi.product_id
ORDER BY total_sold DESC
LIMIT 1"
)); 

$topProduct = $demandRow['name'] ?? 'No data';
$topSold = $demandRow['total_sold'] ?? 0;



$lowStockCount = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS low_stock_count FROM stock_batches WHERE stock <= 10"
))['low_stock_count'] ?? 0;



$totalOrders = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total_orders FROM orders"
))['total_orders'] ?? 0;


// --- START OF FORECAST AND CHART LOGIC ---

// Default product is the one selected in dropdown, otherwise first product
$productName = $_GET['product'] ?? array_key_first($salesData);

// Check if product exists
if (!isset($salesData[$productName])) {
    $productName = array_key_first($salesData) ?? 'No Data';
    if ($productName === 'No Data') 
        die("No historical sales data available.Please check the 'historical_data' table.");
}

$flatSales = flattenSalesData($salesData[$productName]);

$lastActualSaleIndex = -1;
foreach (array_reverse($flatSales, true) as $index => $value) {
    if ($value > 0) { // Ito ang nagko-cut off ng zeros
        $lastActualSaleIndex = $index;
        break;
    }
}

// 2. Filter Actual Sales: Only include data up to the last actual sale index
$actualSalesFiltered = array_slice($flatSales, 0, $lastActualSaleIndex + 1);


// === BAGONG LOGIC PARA SA VALIDATION ===

// 3. Define Parameters
$seasonPeriod = 12; // 12 months in a year
$forecastPeriods = 3; // Mag-forecast (at validate) ng 3 months

// 4. Hatiin ang Data for Validation
// Itatago natin ang huling 3 data points para i-validate
$trainingData = array_slice($actualSalesFiltered, 0, -$forecastPeriods);
$validationData = array_slice($actualSalesFiltered, -$forecastPeriods);

// 5. Calculate Validation Forecast & Errors
$hwValidationResult = holtWintersForecast($trainingData, $seasonPeriod, $forecastPeriods);
$hw_forecast_validation = $hwValidationResult['forecast'];
$hw_mae = calculateMAE($validationData, $hw_forecast_validation);
$hw_rmse = calculateRMSE($validationData, $hw_forecast_validation);

// === END NG BAGONG LOGIC ===


// 6. Generate FINAL Forecast (para sa chart)
// Ngayon gagamitin natin ang BUONG $actualSalesFiltered data
$holtInput_final = $actualSalesFiltered;
$holtResult_final = holtWintersForecast($holtInput_final, $seasonPeriod, $forecastPeriods);

$forecastData = array_map(function($value) {
    // FINAL FIX for Negative Forecast: It must be >= 0
    $roundedValue = round($value, 0);
    return max(0, $roundedValue);
}, $holtResult_final['forecast']); // Gamitin ang $holtResult_final


// 7. Generahin ang forecast labels (Ito yung existing code mo, okay na 'to)
$allHistoricalMonths = [];
$productHistoricalData = $salesData[$productName];
ksort($productHistoricalData);

$maxMonths = count($actualSalesFiltered);
$count = 0;

foreach ($productHistoricalData as $year => $months) {
    if ($year <= 0) continue; // Skip invalid year
    ksort($months);
    for ($m = 1; $m <= 12; $m++) {
        if (!isset($months[$m])) continue; // skip walang sales sa month na ito
        $key = sprintf("%04d-%02d", $year, $m);
        $allHistoricalMonths[] = $key;
        $count++;
        if ($count >= $maxMonths) {
            break 2;
        }
    }
}

$lastDateKey = end($allHistoricalMonths);
if ($lastDateKey === false) { $lastDateKey = date('Y-m', strtotime('-1 month')); }
$lastDate = new DateTime($lastDateKey . '-01');

$forecastLabels = [];
foreach ($forecastData as $f) {
    $lastDate->modify("+1 month");
    $forecastLabels[] = $lastDate->format("Y-m");
}

// 5. Chart Data Alignment
$actualSales = $actualSalesFiltered;
$allLabels = array_merge($allHistoricalMonths, $forecastLabels);
$forecastChartData = array_merge(array_fill(0, count($actualSales), null), $forecastData);

// --- END OF FORECAST AND CHART LOGIC ---



// 4. Generahin ang forecast labels

$allHistoricalMonths = [];

$productHistoricalData = $salesData[$productName];

ksort($productHistoricalData);



$maxMonths = count($actualSalesFiltered);

$count = 0;



foreach ($productHistoricalData as $year => $months) {

if ($year <= 0) continue; // Skip invalid year

ksort($months);

for ($m = 1; $m <= 12; $m++) {

if (!isset($months[$m])) continue; // skip walang sales sa month na ito

$key = sprintf("%04d-%02d", $year, $m);

$allHistoricalMonths[] = $key;

$count++;

if ($count >= $maxMonths) {

break 2;

}

}
}





$lastDateKey = end($allHistoricalMonths);

if ($lastDateKey === false) { $lastDateKey = date('Y-m', strtotime('-1 month')); }

$lastDate = new DateTime($lastDateKey . '-01');



$forecastLabels = [];

foreach ($forecastData as $f) {

$lastDate->modify("+1 month");

$forecastLabels[] = $lastDate->format("Y-m");

}





// 5. Chart Data Alignment

$actualSales = $actualSalesFiltered;

$allLabels = array_merge($allHistoricalMonths, $forecastLabels);

$forecastChartData = array_merge(array_fill(0, count($actualSales), null), $forecastData);



// --- END OF FORECAST AND CHART LOGIC ---





?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MarkeTrack</title>
<link href="css/style.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="vendor/chart.js/Chart.min.js"></script>

<style>

#adminChatModal {
    display: none; 
    position: fixed; 
    top: 0; 
    left: 0; 
    width: 100%; 
    height: 100%; 
    background: rgba(0,0,0,0.5); 
    z-index: 1040;
    justify-content: center;
    align-items: center;
}

#adminChatContainer {
    position: relative; 
    width: 80%; 
    max-width: 900px; 
    height: 70vh; 
    min-height: 500px;
    background: #fff; 
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    display: flex;
    overflow: hidden;
}
#closeAdminChat {
    position: absolute; 
    top: 10px; 
    right: 15px; 
    font-size: 24px; 
    cursor: pointer;
    color: #888;
    z-index: 10;
}
#closeAdminChat:hover { color: #000; }

/* Kaliwa (Convo List) */
#convoList {
    width: 30%; 
    height: 100%; 
    border-right: 1px solid #ddd; 
    background: #f9f9f9;
    display: flex;
    flex-direction: column;
}
#convoListHeader {
    padding: 15px; 
    font-weight: 600; 
    border-bottom: 1px solid #ddd;
    background: #f1f1f1;
    color: #333;
}

#convoListHeader h5 { margin: 0; font-size: 1rem; }

#newStaffChatBtn { background: none; border: none; font-size: 1.2em; cursor: pointer; color: #007bff; padding: 0 5px; display: none; /* Hidden by default */ }


#conversationListContainer {
    overflow-y: auto;
    flex: 1;
}

.chat-view-buttons { padding: 10px 15px; border-bottom: 1px solid #ddd; background: #f8f9fa; text-align: center; }
    .chat-view-buttons button { padding: 5px 15px; margin: 0 5px; border: 1px solid #ccc; background-color: #fff; cursor: pointer; border-radius: 15px; font-size: 0.9em; transition: background-color 0.2s, color 0.2s; }
    .chat-view-buttons button.active-view { background-color: #007bff; color: white; border-color: #007bff; font-weight: bold; }
    #conversationListContainer { overflow-y: auto; flex: 1; }
    .convo-item { padding: 10px 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: background-color 0.2s ease; position: relative; }
    .convo-item:hover { background-color: #e9e9e9; }
    .convo-item.active { background-color: #007bff; color: white; }
    .convo-item.active small { color: #eee !important; } /* Make small text lighter on active */
    .convo-item strong { display: block; font-size: 0.9em; padding-right: 25px; line-height: 1.3; }
    .convo-item small { color: #888; font-size: 0.75em; display: block; line-height: 1.2;}
    .convo-item .unread-badge { position: absolute; top: 50%; right: 10px; transform: translateY(-50%); background: #dc3545; color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 10px; font-weight: bold; }
    .convo-item.active .unread-badge { display: none; }

/* Kanan (Message View) */
#messageView { width: 65%; /* Adjusted width */ height: 100%; display: flex; flex-direction: column; }
    #adminChatHeader { padding: 15px; font-weight: 600; border-bottom: 1px solid #ddd; background: #f9f9f9; color: #555; min-height: 57px; display: flex; align-items: center; }
    #adminChatMessages { flex: 1; overflow-y: auto; padding: 15px; background: #fff; display: flex; flex-direction: column; gap: 8px; }
    #adminReplyArea { padding: 10px; border-top: 1px solid #ddd; display: flex; background: #f9f9f9; }
    #adminChatInput { flex: 1; border: 1px solid #ccc; padding: 8px 12px; border-radius: 20px; margin-right: 8px; }
    #adminSendButton { background: #007bff; color: white; border: none; padding: 8px 15px; border-radius: 20px; cursor: pointer; }
    #adminSendButton:hover { background: #0056b3; }

/* Message Bubbles */
.chat-message {
    margin-bottom: 5px;
    max-width: 80%;
    padding: 8px 12px;
    border-radius: 15px;
    word-wrap: break-word;
    font-size: 0.9rem;
    line-height: 1.4;
}
.message-sent { /* Galing kay Assistant */
    background: #007bff;
    color: white;
    margin-left: auto;
    border-bottom-right-radius: 5px;
}
.message-received { /* Galing kay Customer */
    background: #e9e9e9;
    color: #333;
    margin-right: auto;
    border-bottom-left-radius: 5px;
}
.message-timestamp {
    font-size: 0.7rem;
    color: #f0f0f0;
    display: block;
    margin-top: 3px;
    text-align: right;
}
.message-received .message-timestamp {
    color: #777;
    text-align: left;
}


/* =========================
       SELECT STAFF MODAL STYLES
    ========================= */
    #selectStaffModal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1050; justify-content: center; align-items: center; }
    #selectStaffContainer { background: #fff; padding: 20px; border-radius: 8px; width: 90%; max-width: 450px; max-height: 80vh; display: flex; flex-direction: column; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
    #selectStaffContainer h4 { margin: 0; color: #333;}
    .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
    #closeSelectStaffModal { background: none; border: none; font-size: 1.5em; cursor: pointer; color: #888; padding: 0; line-height: 1; }
    #staffSearchInput, #staffRoleFilter { padding: 8px 10px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 10px; width: calc(100% - 22px); /* Adjust width */ }
    #staffListContainer { overflow-y: auto; flex-grow: 1; border-top: 1px solid #eee; margin-top: 10px; padding-top: 10px; }
    .staff-select-item { padding: 8px 12px; border-bottom: 1px solid #eee; cursor: pointer; transition: background-color 0.2s ease; }
    .staff-select-item:last-child { border-bottom: none; }
    .staff-select-item:hover { background-color: #f0f0f0; }
    .staff-select-item strong { display: block; font-size: 0.95rem; }
    .staff-select-item small { color: #666; font-size: 0.8em; }
    .staff-select-item.hidden-by-search, .staff-select-item.hidden-by-filter { display: none; } /* Hide classes */
    .modal-footer { margin-top: 15px; text-align: right; }
    #cancelSelectStaff { padding: 8px 15px; background-color: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; }


    .navbar-nav li.nav-item.dropdown.no-arrow.mx-1 a#notificationDropdown {
        position: relative !important;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        padding: 0.5rem !important;
        line-height: 1;
    }
    .navbar-nav li.nav-item.dropdown.no-arrow.mx-1 a#notificationDropdown span#notifCount.badge-counter {
    position: absolute !important;
    top: 20px !important;    /* Hihilahin Pataas */
    right: 0px !important;  /* Hihilahin Pakanan (relative sa corner) */
    z-index: 10 !important;
    pointer-events: none !important;
    font-size: 0.900rem !important;
    padding: 1px 4px !important;
    background-color: #dc3545 !important;
    color: white !important;
    border-radius: 50% !important;
    line-height: 1 !important;
    min-width: 16px !important;
    height: 16px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

#notificationPanel .see-all-btn {
  display: block;
  text-align: center;
  padding: 8px;
  margin-top: 10px;
  background-color: #fc1500; /* Bright red for See All */
  color: white;
  border-radius: 5px;
  text-decoration: none;
  font-weight: 600;
  transition: background-color 0.3s ease;
}

#notificationPanel .see-all-btn:hover {
  background-color: #d4140a; /* Slightly darker red */
}

.notif-item {
  cursor: pointer;
  background-color: #f0f0f0; /* Light gray */
  padding: 8px 10px;
  border-bottom: 1px solid #ddd;
  text-decoration: none;
  color: #222;
  display: block;
  transition: background-color 0.2s ease;
}

.notif-item:hover {
  background-color: #e6e6e6; /* Even lighter on hover for effect */
}

.new-order {
  color: #e74c3c;
  font-weight: 700;
}


body {
font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
background-color: #f8f9fc;
margin: 0;
padding: 0;
color: #333;
}

#content-wrapper {
    margin-left: 10px;
}
/* Fix message icon visibility and alignment */
.nav-item.dropdown.no-arrow.mx-1 {
  z-index: 10; /* keep message icon above */
  position: relative;
}

.user-trigger img {
  z-index: 5;
  position: relative;
}

.navbar-nav {
  display: flex;
  align-items: center;
  /* adds space between message icon and profile */
}


.card {
border-radius: 12px;
border: none;
background: #fff;
box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}


.navbar {
padding: 0.5rem 1rem;
}


.navbar .text-gray-600 {
color: #5a5c69 !important;
}


.user-trigger i {
font-size: 1.5rem;
}


.dropdown-panel {
width: 100%;
max-width: 200px;
}


.chart-container {
position: relative;
height: 50vh;
width:100%;

}
.card h2 {
font-size: 1.5rem;
font-weight: 600;
margin-bottom: 10px;
}

.card p {
font-size: 1rem;
color: #666;
padding:0px;
}

.text-xs {
font-size: 0.75rem;
letter-spacing: .05em;
text-transform: uppercase;
}


/* =========================
    Responsive Adjustments
========================= */

/* Large tablets and small laptops (≤ 991px) */
@media (max-width: 991px) {
    #content-wrapper {
    margin-left: 10px !important;
    width: 100% !important;
  }
    .card {
        margin-bottom: 15px;
    }

    .navbar .text-gray-600.small {
        font-size: 0.9rem;
    }

    .row {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }

    .col-xl-3.col-md-6 {
        flex: 1 1 calc(50% - 15px); /* 2 cards per row */
        max-width: calc(50% - 15px);
    }
}

/* Tablets (≤ 768px) */
@media (max-width: 768px) {
    #content-wrapper {
    margin-left: 10px !important;
    width: 100% !important;
  }
    .card h2 {
        font-size: 1.2rem;
    }

    .card p {
        font-size: 0.9rem;
    }

    .navbar .text-gray-600.small {
        display: none; /* hide user fullname text */
    }

    .dropdown-panel {
        right: 10px;
        left: auto;
    }

    .col-xl-3.col-md-6 {
        flex: 1 1 100%; /* 1 card per row */
        max-width: 100%;
    }

    .chart-container {
        height: 40vh; /* adjust chart size */
    }
}

/* Mobile (≤ 568px) */
@media (max-width: 568px) {
    
    #content-wrapper {
    margin-left: 10px !important;
    width: 100% !important;
  }
    .card h2 {
        font-size: 1rem;
    }

    .card p,
    .card .h5,
    .card .h6 {
        font-size: 0.85rem;
    }

    .card-body {
        padding: 10px;
    }

    label, select {
        width: 100%; /* dropdown full width */
    }

    .dropdown-panel {
        max-width: 160px;
        font-size: 0.9rem;
    }

    nav.navbar {
        padding: 0.4rem 0.8rem;
    }
}

</style>

</head>

<body id="page-top">
<div id="content-wrapper" class="d-flex flex-column">
<div id="content">

<nav class="navbar navbar-expand navbar-light bg-light topbar mb-4 static-top shadow">
    <ul class="navbar-nav ml-auto">

        <li class="nav-item dropdown no-arrow mx-1" style="position: relative;">
            <a class="nav-link" href="#" id="messagesDropdown" role="button">
                <i class="fas fa-envelope fa-lg text-gray-600"></i>
                <span id="unreadCount" 
                      class="badge badge-danger badge-counter" 
                      style="position:absolute; top:20px; right:5px; font-size:0.7rem; display:none;">0</span>
            </a>
            
            </li>
        <li class="nav-item dropdown no-arrow mx-1" style="position: relative;">
            <a class="nav-link" href="#" id="notificationDropdown" role="button">
                <i class="fas fa-bell fa-lg text-gray-600"></i>
                <span id="notifCount" class="badge badge-counter" style="display:none;">0</span>
            </a>
            <div id="notificationPanel" class="dropdown-panel" style="display:none; position:absolute; top:40px; right:0; width:280px; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,0.15); border-radius:8px; z-index:1000; padding:10px;">
                <h6 style="font-weight:600; margin-bottom:8px;">Notifications</h6>
                <div id="notifList"></div>
                <a href="notifications.php" class="see-all-btn">See All</a>
            </div>
        </li>

        <li class="nav-item dropdown no-arrow ml-2" style="position: relative; display: flex; align-items: center;">
            <a href="#" class="user-trigger" style="cursor: pointer; display: flex; align-items: center; text-decoration: none;">
                <?php 
                if (!empty($user['profile_image']) && file_exists($user['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" 
                         alt="User Image" 
                         style="width:50px; height:50px; border-radius:50%; object-fit:cover; border:2px solid #ddd;">
                <?php else: ?>
                    <i class="fas fa-user-circle fa-lg text-gray-600"></i>
                <?php endif; ?>
            </a>

            <div id="userPanel" 
                 class="dropdown-panel" 
                 style="display:none; position:absolute; top:55px; right:0; width:200px; background:#fff; 
                        box-shadow:0 2px 8px rgba(0,0,0,0.15); border-radius:8px; z-index:1000; padding:10px;">
                
                <span class="mr-2 d-none d-lg-inline text-gray-600 small" style="display:block; padding: 0 8px 8px; border-bottom:1px solid #eee; margin-bottom: 8px;">
                    <?php echo htmlspecialchars($user['fullname']); ?>
                </span>

                <a href="edit_profile.php" 
                   style="display:block; padding:8px; text-decoration:none; color:#333;">
                   <i class="fas fa-user-edit mr-2"></i> Edit Profile
                </a>
                <a href="logout.php" 
                   style="display:block; padding:8px; text-decoration:none; color:#333;">
                   <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </div>
        </li>
    </ul>
</nav>


<div class="main-content">
<div class="card p-4 mb-4">
<h2>Hello, Welcome Back!</h2>
<p>Today's Date: <?php echo date('l, F j, Y'); ?></p>
</div>

<div class="row">
<div class="col-xl-3 col-md-6 mb-4">
<div class="card border-left-success shadow h-100 py-2">
<div class="card-body">
<div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Sales (This Month)</div>
<div class="h5 mb-0 font-weight-bold text-gray-800">₱<?php echo number_format($totalSales, 2); ?></div>
</div>
</div>
</div>

<div class="col-xl-3 col-md-6 mb-4">
<div class="card border-left-info shadow h-100 py-2">
<div class="card-body">
<div class="text-xs font-weight-bold text-info text-uppercase mb-1">Most In-Demand Product</div>
<div class="h6 mb-0 font-weight-bold text-gray-800"><?php echo $topProduct . " (" . $topSold . ")"; ?></div>
</div>
</div>
</div>

<div class="col-xl-3 col-md-6 mb-4">
<div class="card border-left-warning shadow h-100 py-2">
<div class="card-body">
<div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Low Stock Products</div>
<div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $lowStockCount; ?></div>
</div>
</div>
</div>

<div class="col-xl-3 col-md-6 mb-4">
<div class="card border-left-primary shadow h-100 py-2">
<div class="card-body">
<div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Orders</div>
<div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalOrders; ?></div>
</div>
</div>
</div>
</div>
<form method="GET" id="productForm">
    <label for="product">Select Product:</label>
    <select name="product" id="product" onchange="document.getElementById('productForm').submit()">
        <?php while ($row = mysqli_fetch_assoc($productResult)): 
            $optionValue = $row['product_id'] . '_' . $row['variation_id'];
            $optionLabel = htmlspecialchars($row['product_name'] . ' - ' . $row['flavor'] . ' (' . $row['pack_size'] . ')');
            $selected = ($currentProduct == $optionValue) ? 'selected' : '';
        ?>
            <option value="<?= $optionValue ?>" <?= $selected ?>><?= $optionLabel ?></option>
        <?php endwhile; ?>
    </select>
</form>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Model Accuracy (Holt-Winters)</h6>
    </div>
    <div class="card-body">
        <p>Error metrics based on validating the last 3 months of data. (Lower is better)</p>
        <div class="table-responsive">
            <table class="table table-bordered" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Error Metric</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>MAE (Mean Absolute Error)</strong></td>
                        <td><?php echo number_format($hw_mae ?? 0, 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>RMSE (Root Mean Squared Error)</strong></td>
                        <td><?php echo number_format($hw_rmse ?? 0, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="card shadow mb-4">
    <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary">Sales Forecast vs Actual</h6>
    </div>
    
        
<div class="card-body" style="height: 250px;">
<canvas id="salesChart"></canvas>
</div>
</div>
</div>
</div>
</div>
</div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {

    // --- User Profile Dropdown Logic ---
    const userTrigger = document.querySelector(".user-trigger");
    const userPanel = document.querySelector("#userPanel");
    // Add null checks for robustness
    if (userTrigger && userPanel) { 
        userTrigger.addEventListener("click", function(e) {
            e.preventDefault();
            userPanel.style.display = (userPanel.style.display === "block") ? "none" : "block";
        });
    }

    // --- Close Dropdowns when clicking outside ---
    document.addEventListener("click", function(e) {
        // Close user panel if click is outside
        if (userTrigger && userPanel && !userTrigger.contains(e.target) && !userPanel.contains(e.target)) {
            userPanel.style.display = "none";
        }
        // Add similar logic for notification panel if you include notifications later
    });
      // --- Sales Chart Initialization ---
      try {
        var labels = <?php echo json_encode($allLabels ?? []); ?>;
        var actualSales = <?php echo json_encode($actualSales ?? []); ?>;
        var forecastSales = <?php echo json_encode($forecastChartData ?? []); ?>;
        var ctx = document.getElementById("salesChart").getContext("2d");
        
        new Chart(ctx, {
            type: "line",
            data: {
                labels: labels,
                datasets: [{
                    label: "Actual Sales",
                    data: actualSales,
                    borderColor: "#007bff",
                    backgroundColor: "#007bff33",
                    fill: true,
                    tension: 0.2
                }, {
                    label: "Forecast Sales",
                    data: forecastSales,
                    borderColor: "#dc3545",
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0.2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: "top" },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y === null ? null : context.dataset.label + ": " + context.parsed.y + " units";
                            }
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true },
                    x: { ticks: { maxRotation: 45, minRotation: 0 } }
                }
            }
        });
    } catch (e) {
        console.error("Chart.js error:", e);
    }


    // --- 🔔 NOTIFICATIONS SCRIPT ---
    const notifTrigger = document.querySelector("#notificationDropdown");
    const notifPanel = document.querySelector("#notificationPanel");
    const notifCountBadge = document.querySelector("#notifCount");
    const notifList = document.querySelector("#notifList");

    if (notifTrigger) {
        function loadNotifications() {
            fetch('fetch_notifications.php')
                .then(res => res.json())
                .then(data => {
                    const notifications = data.notifications;
                    if (notifications.length === 0) {
                        notifList.innerHTML = "<p style='text-align:center; color:#888;'>No new notifications.</p>";
                        return;
                    }
                    notifList.innerHTML = notifications.map(n => `
                        <a href="${n.link || '#'}" class="notif-item" data-notif-id="${n.id}">
                            <strong class="${n.title.includes('New Order') ? 'new-order' : ''}">${n.title}</strong><br>
                            <span>${n.message}</span><br>
                            <small style="color:#999;">${n.formatted_date}</small>
                        </a>
                    `).join('');
                })
                .catch(() => {
                    notifList.innerHTML = "<p style='text-align:center; color:#888;'>Error loading notifications.</p>";
                });
        }

        function updateNotifCount() {
            fetch('fetch_notifications.php')
                .then(res => res.json())
                .then(data => {
                    const count = data.count;
                    if (count > 0) {
                        notifCountBadge.style.display = 'inline-block';
                        notifCountBadge.textContent = count;
                    } else {
                        notifCountBadge.style.display = 'none';
                    }
                });
        }

        notifTrigger.addEventListener("click", function(e) {
            e.preventDefault();
            const isPanelVisible = notifPanel.style.display === "block";
            notifPanel.style.display = isPanelVisible ? "none" : "block";
            if (!isPanelVisible) {
                loadNotifications();
            }
        });
        
        document.addEventListener("click", function(e) {
            if (notifTrigger && !notifTrigger.contains(e.target) && !notifPanel.contains(e.target)) {
                notifPanel.style.display = "none";
            }
        });

        updateNotifCount();
        setInterval(updateNotifCount, 30000); // Check every 30 seconds
    }



    // --- 💬 COMBINED CHAT SCRIPT ---
    var logged_in_user_id = <?php echo $_SESSION['user_id']; ?>; 
    var logged_in_user_role = "<?php echo isset($user['role']) ? addslashes($user['role']) : ''; ?>"; // Get user role
    var current_other_user_id = null; // ID of the person being chatted with
    var current_other_user_type = null; // 'customer' or 'admin'
    var chatInterval; // For periodic refreshing
    const adminChatModal = $("#adminChatModal");
    const adminChatMessages = $("#adminChatMessages");
    const convoListContainer = $("#conversationListContainer");
    const convoListTitle = $("#convoListTitle");
    const customerViewBtn = $("#customerViewBtn");
    const staffViewBtn = $("#staffViewBtn");
    const newStaffChatBtn = $("#newStaffChatBtn"); // New chat button reference

    // Select Staff Modal elements
    const selectStaffModal = $("#selectStaffModal");
    const staffListContainer = $("#staffListContainer");
    const staffSearchInput = $("#staffSearchInput");
    const staffRoleFilter = $("#staffRoleFilter");

    let current_view = (logged_in_user_role === 'Assistant Brand Manager') ? 'customer' : 'staff'; // Default view depends on role   
    let allStaffData = []; // Cache for staff list used in modal filtering

   // --- View Switching Logic (UPDATED) ---
   function switchView(viewType) {
        // --- Hide Customer button if logged-in user is NOT Assistant Brand Manager ---
        // Ensure logged_in_user_role variable is defined earlier in the script
        if (logged_in_user_role !== 'Assistant Brand Manager') {
            customerViewBtn.hide(); // Hide the Customer button
            // If the requested view was 'customer', force it to 'staff'
            if (viewType === 'customer') {
                console.log("Forcing staff view for non-ABM role.");
                viewType = 'staff'; // Override to staff view
            }
        } else {
             customerViewBtn.show(); // Ensure Customer button is visible for ABM
        }
        // --- End Role Check ---

        console.log("Switching view to:", viewType); // Debug log
        current_view = viewType; // Update the current view state

        // Reset current chat selection when switching views
        current_other_user_id = null;
        current_other_user_type = null;

        // Reset chat display area
        $("#adminChatHeader").text("Select a conversation");
        $("#adminChatMessages").html('<p style="text-align:center; color: #aaa; margin-top: 50px;">Please select a conversation from the left.</p>');
        $("#adminReplyArea").hide(); // Hide reply area until a chat is selected

        // Update button styles and list title based on the final viewType
        if (viewType === 'customer') {
            customerViewBtn.addClass('active-view');
            staffViewBtn.removeClass('active-view');
            convoListTitle.text("Customer Chats");
            newStaffChatBtn.hide(); // Hide (+) button in customer view
        } else { // staff view
            customerViewBtn.removeClass('active-view');
            staffViewBtn.addClass('active-view');
            convoListTitle.text("Staff Chats");
            newStaffChatBtn.show(); // Show (+) button in staff view
        }
        // Load the conversation list appropriate for the selected view
        loadConversationList();
    }


    
    // Attach click handlers to view switching buttons
    customerViewBtn.on('click', () => switchView('customer'));
    staffViewBtn.on('click', () => switchView('staff'));

    $("#messagesDropdown").click(function(e) {
    e.preventDefault();
    console.log("Messages dropdown clicked"); 
    adminChatModal.css('display', 'flex'); 
    // --- (PALITAN ITO) Default view based on role ---
    let initialView = (logged_in_user_role === 'Assistant Brand Manager') ? 'customer' : 'staff';
    switchView(initialView);
        // Start periodic refresh if not already running
        updateUnreadCount();
        if (!chatInterval) {
            console.log("Starting chat refresh interval"); // Debug log
            chatInterval = setInterval(refreshChat, 6000); // Check every 6 seconds
        }
    });

    $("#closeAdminChat").click(function() {
        console.log("Closing chat modal"); // Debug log
        adminChatModal.hide(); // Hide the modal
        // Reset current chat selection
        current_other_user_id = null; 
        current_other_user_type = null; 
        // Stop the periodic refresh
        if (chatInterval) { 
            console.log("Stopping chat refresh interval"); // Debug log
            clearInterval(chatInterval); 
            chatInterval = null; 
        }
    });

    // Handle click on a conversation item in the list (delegated event)
    convoListContainer.on('click', '.convo-item', function() { 
        // Get user ID, type, and name from data attributes
        current_other_user_id = $(this).data('user-id');    
        current_other_user_type = $(this).data('user-type'); 
        var user_name = $(this).data('user-name');          
        console.log("Selected conversation:", user_name, current_other_user_id, current_other_user_type); // Debug log

        // Update chat header and show reply area
        $("#adminChatHeader").text("Chat with " + user_name); 
        $("#adminReplyArea").show();
        // Show loading message while fetching messages
        adminChatMessages.html('<p style="text-align:center; color: #aaa; margin-top: 50px;">Loading messages...</p>');

        // Highlight the selected conversation item
        convoListContainer.find('.convo-item').removeClass('active'); // Use container context
        $(this).addClass('active');

        // Load the messages for the selected conversation
        loadAdminMessages(); 
    });

    // Handle send button click
    $("#adminSendButton").click(sendAdminMessage);

    // Handle Enter key press in the message input field
    $("#adminChatInput").keypress(function(e) { 
        if (e.which == 13 && !e.shiftKey) { // Check if Enter key (not Shift+Enter)
            e.preventDefault(); // Prevent default newline behavior
            sendAdminMessage(); // Send the message
        }
     });

    // --- AJAX Functions ---
    // Function to refresh conversation list and current chat view periodically
    function refreshChat() {
        console.log("Refreshing chat..."); // Debug log
        loadConversationList(); // Refresh the list on the left
        // Refresh messages only if a chat is currently selected
        if (current_other_user_id != null && current_other_user_type != null) { 
            loadAdminMessages(true); // Perform a silent refresh (no scroll jump)
        }
        updateUnreadCount(); // Update the unread count badge in the navbar
    }

    // Load conversation list based on the current view ('customer' or 'staff')
    function loadConversationList() {
        // Show loading message
        convoListContainer.html('<p style="padding: 15px; color: #888;">Loading conversations...</p>'); 
        $.ajax({
            url: 'admin_ajax.php', 
            type: 'POST',
            data: { 
                action: 'get_conversations',
                view_type: current_view // Tell backend which view to load
            }, 
            success: function(data) {
                // Populate the list container with the HTML returned by the server
                convoListContainer.html(data);
                // Re-apply 'active' class if the currently selected chat is in the list
                if(current_other_user_id && current_other_user_type) {
                    $('.convo-item[data-user-id="' + current_other_user_id + '"][data-user-type="' + current_other_user_type + '"]').addClass('active');
                }
            },
            error: function(xhr, status, error) { 
                 // Log error and show error message in the list container
                 console.error("Error loading conversation list:", error, xhr.responseText);
                 convoListContainer.html('<p style="padding: 15px; color: red;">Error loading conversations.</p>');
            }
        });
    }

    // Load messages for the selected conversation (right panel)
    function loadAdminMessages(isSilent = false) {
        // Ensure both ID and type are set before making the AJAX call
        if (current_other_user_id == null || current_other_user_type == null) {
            console.log("loadAdminMessages stopped: No recipient selected."); 
            return; 
        }
        console.log("Loading messages for:", current_other_user_id, current_other_user_type); // Debug log

        $.ajax({
            url: 'admin_ajax.php', 
            type: 'POST',
            data: {
                action: 'get_messages', 
                other_user_id: current_other_user_id,    
                other_user_type: current_other_user_type 
            },
            success: function(data) {
            console.log("Data received from get_messages:", data); // <-- IDAGDAG ITO

            // Check if the user is scrolled near the bottom before loading new messages
            var isAtBottom = adminChatMessages.scrollTop() + adminChatMessages.innerHeight() >= adminChatMessages[0].scrollHeight - 50;

            // Update the chat message display area with the received HTML
            adminChatMessages.html(data);
                // Scroll to bottom if not silent or if user was already near bottom
                if (!isSilent || isAtBottom) {
                    adminChatMessages.scrollTop(adminChatMessages[0].scrollHeight);
                }
                
                // Clear the unread badge after messages are loaded and marked read by backend
                if (!isSilent) {
                    var convoItem = $('.convo-item[data-user-id="' + current_other_user_id + '"][data-user-type="' + current_other_user_type + '"]');
                    convoItem.find('.unread-badge').hide(); 
                }
            },
             error: function(xhr, status, error) { 
                console.error("Error loading messages:", error, xhr.responseText);
                adminChatMessages.html('<p style="text-align:center; color: red;">Error loading messages. Check console.</p>');
             }
        });
    }

    // Send a message (to customer or staff)
    function sendAdminMessage() {
        var message = $("#adminChatInput").val().trim(); 
        
        // Validate message and recipient selection
        if (message == '' || current_other_user_id == null || current_other_user_type == null) {
            console.log("Send stopped: Empty message or no recipient selected."); 
            return; 
        }
        console.log("Sending message:", message, "to:", current_other_user_id, current_other_user_type); // Debug log

        // Optimistic update: Add message bubble immediately
        var sentTime = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }); 
        var optimisticHTML = `
            <div class="chat-message message-sent">
                ${message.replace(/</g, "&lt;").replace(/>/g, "&gt;")} 
                <span class="message-timestamp">${sentTime}</span>
            </div>`;
        adminChatMessages.append(optimisticHTML); 
        adminChatMessages.scrollTop(adminChatMessages[0].scrollHeight); 
        $("#adminChatInput").val(''); // Clear input

        // Send message data to the server
        $.ajax({
            url: 'admin_ajax.php',
            type: 'POST',
            data: {
                action: 'send_message',         
                receiver_id: current_other_user_id,    
                receiver_type: current_other_user_type, 
                message_content: message        
            },
            dataType: 'json', 
            success: function(response) {
                console.log("Send message response:", response); // Debug log
                if (!response.success) {
                    alert('Error sending message: ' + (response.error || 'Unknown error')); 
                    loadAdminMessages(); // Re-sync if sending failed
                }
                // Optionally: Trigger a conversation list refresh after sending to update order/time
                // loadConversationList(); 
            },
            error: function(xhr, status, error) { 
                console.error("Error sending message:", error, xhr.responseText);
                alert('Connection error. Message might not have sent.');
                loadAdminMessages(); // Re-sync on error
            }
        });
    }
    
    // Update unread count badge in the navbar
    function updateUnreadCount() {
        $.ajax({
            url: 'admin_ajax.php',
            type: 'POST',
            data: { action: 'get_unread_count' }, 
            dataType: 'json',
            success: function(data) {
                // Validate response data
                if (data && typeof data.count !== 'undefined') {
                    var count = parseInt(data.count);
                    var unreadBadge = $("#unreadCount"); // Make sure ID matches navbar badge
                    if (count > 0) {
                        unreadBadge.text(count).show(); 
                    } else {
                        unreadBadge.text('0').hide();   
                    }
                } else {
                     console.error("Invalid data received for unread count:", data);
                }
            },
            error: function(xhr, status, error) { 
                console.error("Failed to update unread count:", error, xhr.responseText);
            }
        });
    }
    
    // --- New Staff Chat Modal Logic ---
    
    // Fetch staff data and populate the modal
    function populateStaffModal() {
        staffListContainer.html('<p style="text-align: center; color: #888;">Loading staff list...</p>'); 
        staffRoleFilter.html('<option value="">All Roles</option>'); // Reset role filter

        $.ajax({
            url: 'admin_ajax.php',
            type: 'POST',
            data: { action: 'get_all_staff_with_roles' }, // Action to get staff list
            dataType: 'json', 
            success: function(response) {
                console.log("Get all staff response:", response); // Debug log
                if (response.success) {
                    allStaffData = response.staff || []; // Store staff data, ensure it's an array
                    let roles = response.roles || []; // Get roles from response
                    
                    // Populate role filter dropdown
                    roles.forEach(role => {
                         // Optional check to exclude specific roles if needed
                         if(role && role.toLowerCase() !== 'customer') { 
                           staffRoleFilter.append(`<option value="${escapeHtml(role)}">${escapeHtml(role)}</option>`);
                         }
                    });

                    renderStaffList(allStaffData); // Render the initial full list
                } else {
                     staffListContainer.html(`<p style="color: red; text-align: center;">${escapeHtml(response.error || 'Error loading staff.')}</p>`);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error fetching staff list:", error, xhr.responseText);
                staffListContainer.html('<p style="color: red; text-align: center;">Error loading staff list.</p>');
            }
        });
    }

    // Render the staff list based on filtered data array
    function renderStaffList(staffArray) {
        let staffHTML = '';
        if (staffArray && staffArray.length > 0) {
            staffArray.forEach(staff => {
                staffHTML += `
                    <div class="staff-select-item" 
                         data-user-id="${staff.id}" 
                         data-user-type="admin"  
                         data-user-name="${escapeHtml(staff.fullname)}"
                         data-user-role="${escapeHtml(staff.role)}"> <strong>${escapeHtml(staff.fullname)}</strong>
                        <small>${escapeHtml(staff.role || 'No Role')}</small> </div>`;
            });
        } else {
            staffHTML = '<p style="text-align: center; color: #888;">No staff found matching criteria.</p>';
        }
        staffListContainer.html(staffHTML);
    }
    
     // Utility to escape HTML characters
    function escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') {
            return ''; // Return empty string if input is not a string
        }
        return unsafe
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }

    // Apply filters based on search input and role dropdown
    function applyStaffFilters() {
        let searchTerm = staffSearchInput.val().toLowerCase();
        let selectedRole = staffRoleFilter.val();
        
        // Filter the cached staff data
        let filteredStaff = allStaffData.filter(staff => {
            // Check if name includes search term (case-insensitive)
            let nameMatch = staff.fullname.toLowerCase().includes(searchTerm);
            // Check if role matches selection (or if 'All Roles' is selected)
            let roleMatch = (selectedRole === "" || staff.role === selectedRole);
            // Include if both conditions are met
            return nameMatch && roleMatch;
        });

        // Re-render the list with filtered results
        renderStaffList(filteredStaff);
    }

    // --- Event handlers for the Staff Selection Modal ---
    // Open modal when '+' button is clicked
    newStaffChatBtn.on('click', function() {
        console.log("New staff chat button clicked"); // Debug log
        populateStaffModal(); // Fetch staff data and populate list/filters
        staffSearchInput.val(''); // Clear previous search term
        staffRoleFilter.val(''); // Reset role filter to 'All Roles'
        selectStaffModal.css('display', 'flex'); // Show the modal
    });

    // Apply filters when typing in search or changing role
    staffSearchInput.on('keyup', applyStaffFilters);
    staffRoleFilter.on('change', applyStaffFilters);

    // Handle click on a staff member within the modal
    staffListContainer.on('click', '.staff-select-item', function() {
        var selectedUserId = $(this).data('user-id');
        var selectedUserName = $(this).data('user-name');
        console.log("Selected staff from modal:", selectedUserName, selectedUserId); // Debug log
        
        selectStaffModal.hide(); // Close the modal

        // Check if a conversation item for this staff already exists in the main list
        var existingConvoItem = convoListContainer.find('.convo-item[data-user-id="' + selectedUserId + '"][data-user-type="admin"]');

        if (existingConvoItem.length > 0) {
            // If exists, simulate a click on it to load the existing conversation
            console.log("Existing conversation found, triggering click"); // Debug log
            existingConvoItem.trigger('click'); 
        } else {
            // If it's a new chat partner, set up the chat window
            console.log("Setting up new conversation window"); // Debug log
            current_other_user_id = selectedUserId;
            current_other_user_type = 'admin'; // Type is always 'admin' for staff

            $("#adminChatHeader").text("Chat with " + selectedUserName); 
            // Display a message indicating it's a new chat
            adminChatMessages.html('<p style="text-align:center; color: #aaa; margin-top: 50px;">Start chatting with ' + selectedUserName + '.</p>'); 
            $("#adminReplyArea").show(); // Show the reply input area
            
            // Deactivate any currently active item in the main list
            convoListContainer.find('.convo-item').removeClass('active'); 

            // Visually add the new contact to the top of the conversation list
            // This item will be updated properly on the next list refresh
            let tempNewItemHTML = `
                <div class="convo-item active" 
                     data-user-id="${selectedUserId}" 
                     data-user-type="admin" 
                     data-user-name="${escapeHtml(selectedUserName)}"
                     data-user-role="${escapeHtml($(this).data('user-role') || '')}"> <strong>${escapeHtml(selectedUserName)}</strong>
                    <small style="color: #007bff;">(Staff)</small> 
                </div>`;
            convoListContainer.prepend(tempNewItemHTML); // Add to the beginning of the list
            
            // Remove the "No conversations found" message if it exists
            convoListContainer.find('p:contains("No staff conversations found.")').remove();
        }
    });

    // Close modal using the 'X' button or Cancel button
    $("#closeSelectStaffModal, #cancelSelectStaff").on('click', function() {
        selectStaffModal.hide();
    });

    // --- Initial Load ---
    updateUnreadCount(); // Get initial unread count on page load
    // Set interval to periodically check for new unread messages
    setInterval(updateUnreadCount, 30000); // Check every 30 seconds

}); // End of document.addEventListener
</script>


</div> </div> <div id="adminChatModal">
    <div id="adminChatContainer">
        <span id="closeAdminChat">&times;</span>
        <div id="convoList">
            <div id="convoListHeader">
                <h5 id="convoListTitle">Customer Chats</h5> 
                <button id="newStaffChatBtn" title="Start New Staff Chat"><i class="fas fa-plus-circle"></i></button>
            </div>
            <div class="chat-view-buttons">
                <button id="customerViewBtn" class="active-view">Customers</button>
                <button id="staffViewBtn">Staff</button>
            </div>
            <div id="conversationListContainer">
                <p style="padding: 15px; color: #888;">Loading conversations...</p>
            </div>
        </div>
        <div id="messageView">
            <div id="adminChatHeader">Select a conversation</div>
            <div id="adminChatMessages">
                 <p style="text-align:center; color: #aaa; margin-top: 50px;">Please select a conversation from the left.</p>
            </div>
            <div id="adminReplyArea" style="display:none;">
                <input type="text" id="adminChatInput" placeholder="Type your reply...">
                <button id="adminSendButton"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
</div>

<div id="selectStaffModal">
    <div id="selectStaffContainer">
        <div class="modal-header">
            <h4>Select Staff to Chat With</h4>
            <button id="closeSelectStaffModal">&times;</button>
        </div>
        <select id="staffRoleFilter" style="margin-bottom: 10px;">
            <option value="">All Roles</option>
            </select>
        <input type="text" id="staffSearchInput" placeholder="Search staff name...">
        
        <div id="staffListContainer">
            <p style="text-align: center; color: #888;">Loading staff list...</p>
        </div>
        <div class="modal-footer">
             <button id="cancelSelectStaff" type="button">Cancel</button>
        </div>
    </div>
</div>
</body>
</html>
