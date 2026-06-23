<?php
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);
session_name('admin_session');
session_start();

require_once 'functions.php';
include 'db_connection.php';
require_once 'load_sales_data.php'; // Dapat nandito 'yung code mo na may "UNION ALL"
require_once 'holt_winters_forecast.php'; 
require_once 'other_forecasts.php'; // Para sa MAE
include 'sidebar.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch user data
$user_id = $_SESSION['user_id'];
$query = "SELECT fullname, role, profile_image FROM users WHERE id = ?"; 
$stmt_user = $conn->prepare($query);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();
$stmt_user->close();

// --- START OF FIX 1 ---
// Product dropdown logic (NEW)
// Kukunin natin ang pangalan ng products na may data galing sa $salesData array
$productNamesFromData = array_keys($salesData);
sort($productNamesFromData); // Para alphabetical

// Kunin ang unang product name galing sa $salesData array
$firstProductName = array_key_first($salesData) ?? '';

// Ang $currentProduct natin ay 'yung PANGALAN na mismo
$currentProduct = $_GET['product'] ?? $firstProductName; 
// --- END OF FIX 1 ---


// --- START OF FIX 2 (Logic) ---
// --- START OF HOLT-WINTERS LOGIC ---

// $currentProduct galing sa Step 1 ay 'yung PANGALAN na (e.g., "Beng Beng")
$productName = $currentProduct; 

// Check lang kung may data, kung wala, fallback
if (!isset($salesData[$productName])) {
    $productName = array_key_first($salesData) ?? 'No Data';
    if ($productName === 'No Data') 
        die("No historical sales data available.");
}

// --- START OF FIX 3 (Data/Label Sync) ---
// (Pinalitan na nito 'yung luma mong $flatSales = flattenSalesData(...) at 'yung luma mong label loop)
$allHistoricalMonths = [];
$productHistoricalData = $salesData[$productName];

// Hanapin ang min/max year galing sa data
$years = array_keys($productHistoricalData);
$minYear = PHP_INT_MAX;
$maxYear = 0;
foreach ($years as $y) {
    if ($y > 0) {
        $minYear = min($minYear, $y);
        $maxYear = max($maxYear, $y);
    }
}

// Fallback kung walang valid year
if ($maxYear == 0) {
    // Check kung may laman pa, baka 0 index
    if (isset($productHistoricalData[0])) {
         $minYear = date('Y') - 1; // Default
         $maxYear = date('Y');
    } else {
        $minYear = date('Y');
        $maxYear = date('Y');
    }
}

// Loop mula min hanggang max year, at 1 hanggang 12 na buwan
$tempFlatData = [];
for ($y = $minYear; $y <= $maxYear; $y++) {
    for ($m = 1; $m <= 12; $m++) {
        // Ito 'yung logic ng flattenSalesData
        $value = $salesData[$productName][$y][$m] ?? 0;
        $tempFlatData[] = $value;
        
        // Ito 'yung bagong label generation
        $key = sprintf("%04d-%02d", $y, $m);
        $allHistoricalMonths[] = $key;
    }
}

// Ngayon, kailangan nating i-sync 'yung $actualSalesFiltered at $allHistoricalMonths
// Hanapin natin 'yung 'last sale index' galing sa $tempFlatData
$lastActualSaleIndex = -1;
foreach (array_reverse($tempFlatData, true) as $index => $value) {
    if ($value > 0) {
        $lastActualSaleIndex = $index;
        break;
    }
}

// Slice BOTH arrays gamit ang parehong index
$actualSalesFiltered = array_slice($tempFlatData, 0, $lastActualSaleIndex + 1);
$allHistoricalMonths = array_slice($allHistoricalMonths, 0, $lastActualSaleIndex + 1);
// --- END OF FIX 3 ---


// --- VALIDATION AND FINAL FORECAST ---
$seasonPeriod = 12;
$forecastPeriods = 3;
$trainingData = array_slice($actualSalesFiltered, 0, -$forecastPeriods);
$validationData = array_slice($actualSalesFiltered, -$forecastPeriods);

$hwValidationResult = holtWintersForecast($trainingData, $seasonPeriod, $forecastPeriods);
$hw_forecast_validation = $hwValidationResult['forecast'];
$hw_mae = calculateMAE($validationData, $hw_forecast_validation);

$holtInput_final = $actualSalesFiltered;
$holtResult_final = holtWintersForecast($holtInput_final, $seasonPeriod, $forecastPeriods);
$forecastData = array_map(function($value) {
    return max(0, round($value, 0));
}, $holtResult_final['forecast']);

// --- Generate Forecast Labels ---
$lastDateKey = end($allHistoricalMonths);
if ($lastDateKey === false) { $lastDateKey = date('Y-m', strtotime('-1 month')); }
$lastDate = new DateTime($lastDateKey . '-01');
$forecastLabels = [];
foreach ($forecastData as $f) {
    $lastDate->modify("+1 month");
    $forecastLabels[] = $lastDate->format("Y-m");
}
$actualSales = $actualSalesFiltered;
$allLabels = array_merge($allHistoricalMonths, $forecastLabels);
$forecastChartData = array_merge(array_fill(0, count($actualSales), null), $forecastData);
// --- END OF HOLT-WINTERS LOGIC ---
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HW Forecast - MarkeTrack</title>
<link href="css/style.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="vendor/chart.js/Chart.min.js"></script>
</head>

<body id="page-top">
<div id="content-wrapper" class="d-flex flex-column">
<div id="content">

<nav class="navbar navbar-expand navbar-light bg-light topbar mb-4 static-top shadow">
    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown no-arrow mx-1" style="position: relative;">
            <a class="nav-link" href="#" id="messagesDropdown" role="button">
                <i class="fas fa-envelope fa-lg text-gray-600"></i>
                <span id="unreadCount" class="badge badge-danger badge-counter" style="position:absolute; top:-2px; right:-5px; font-size:0.7rem; display:none;">0</span>
            </a>
        </li>
        <li class="nav-item dropdown no-arrow mx-1" style="position: relative;">
            <a class="nav-link" href="#" id="notificationDropdown" role="button">
                <i class="fas fa-bell fa-lg text-gray-600"></i>
                <span id="notifCount" class="badge badge-counter" style="display:none;">0</span>
            </a>
        </li>
        <li class="nav-item dropdown no-arrow ml-2" style="position: relative; display: flex; align-items: center;">
            <a href="#" class="user-trigger" style="cursor: pointer; display: flex; align-items: center; text-decoration: none;">
                <?php if (!empty($user['profile_image']) && file_exists($user['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="User Image" style="width:50px; height:50px; border-radius:50%; object-fit:cover; border:2px solid #ddd;">
                <?php else: ?>
                    <i class="fas fa-user-circle fa-lg text-gray-600"></i>
                <?php endif; ?>
            </a>
            <div id="userPanel" class="dropdown-panel" style="display:none; position:absolute; top:55px; right:0; width:200px; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,0.15); border-radius:8px; z-index:1000; padding:10px;">
                <span class="mr-2 d-none d-lg-inline text-gray-600 small" style="display:block; padding: 0 8px 8px; border-bottom:1px solid #eee; margin-bottom: 8px;">
                    <?php echo htmlspecialchars($user['fullname']); ?>
                </span>
                <a href="edit_profile.php" style="display:block; padding:8px; text-decoration:none; color:#333;"><i class="fas fa-user-edit mr-2"></i> Edit Profile</a>
                <a href="logout.php" style="display:block; padding:8px; text-decoration:none; color:#333;"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
            </div>
        </li>
    </ul>
</nav>

<div class="main-content">
    <div class="card p-4 mb-4">
        <h2>Forecasting: Holt-Winters</h2>
        <p>This page analyzes sales data using the Holt-Winters Triple Exponential Smoothing method.</p>
    </div>

    <form method="GET" id="productForm">
        <label for="product">Select Product:</label>
        <select name="product" id="product" onchange="document.getElementById('productForm').submit()">
            <?php foreach ($productNamesFromData as $productNameOption): 
                // Ang value ay 'Beng Beng', ang label ay 'Beng Beng'
                $optionValue = $productNameOption;
                $optionLabel = htmlspecialchars($productNameOption);
                $selected = ($currentProduct == $optionValue) ? 'selected' : '';
            ?>
                <option value="<?= $optionValue ?>" <?= $selected ?>><?= $optionLabel ?></option>
            <?php endforeach; ?>
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
                            <td><?php echo number_format($hw_mae, 2); ?></td>
                        </tr>
                        </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Holt-Winters Forecast vs Actual</h6>
        </div>
        <div class="card-body" style="height: 250px;">
            <canvas id="salesChart"></canvas>
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
    if (userTrigger && userPanel) { 
        userTrigger.addEventListener("click", function(e) {
            e.preventDefault();
            userPanel.style.display = (userPanel.style.display === "block") ? "none" : "block";
        });
    }
    document.addEventListener("click", function(e) {
        if (userTrigger && userPanel && !userTrigger.contains(e.target) && !userPanel.contains(e.target)) {
            userPanel.style.display = "none";
        }
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
                    label: "Holt-Winters Forecast",
                    data: forecastSales,
                    borderColor: "#dc3545", // Red
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
});
</script>

</body>
</html>