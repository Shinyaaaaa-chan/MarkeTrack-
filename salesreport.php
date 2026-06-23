<?php  
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);
session_name('admin_session');
session_start();

include 'db_connection.php';
include 'sidebar.php';

// Get report type and subtype
$report_type = $_GET['report_type'] ?? 'orders';
$sub_type = $_GET['sub_type'] ?? 'all';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Report - MarkeTrack</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSS -->
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

    <style>
        body {
            background-color: #f8f9fc;
            overflow-x: hidden;
        }
        .container-fluid {
            padding: 1.5rem;
        }
        .card {
            border-radius: 10px;
            padding: 1.5rem;
        }
        h1 {
            font-weight: 700;
            color: #e63946;
        }
        .table th, .table td {
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
        }
        .table thead {
            background-color: #f8f9fc;
            color: #e63946;
        }
        .badge-success { background-color: #28a745; }
        .badge-warning { background-color: #ffc107; color: #000; }
        .badge-danger { background-color: #dc3545; }

        @media (max-width: 768px) {
    #content-wrapper {
        margin-left: 0 !important;
        padding-left: 0 !important;
    }

    #content {
        margin-left: 10px !important;
        
        padding-left: 0 !important;
    }

    .container-fluid {
        margin-left: 0 !important;
        padding-left: 0 !important;
        width: 100% !important;
    }

    .card {
        width: 100% !important;
        border-radius: 10px;
    }
}

    </style>
</head>

<body id="page-top">
<div id="content-wrapper" class="d-flex flex-column">
<div id="content" class="container-fluid mt-4">
<div class="card shadow">

<!-- ✅ FILTERS -->
<form method="get" class="mb-3">
    <div class="form-row align-items-center">
        <div class="col-auto">
            <label class="font-weight-bold mr-2">Report Type:</label>
            <select name="report_type" class="form-control" onchange="this.form.submit()">
                <option value="orders" <?= $report_type == 'orders' ? 'selected' : '' ?>>Completed Orders</option>
                <option value="inventory" <?= $report_type == 'inventory' ? 'selected' : '' ?>>Inventory Report</option>
                <option value="demand" <?= $report_type == 'demand' ? 'selected' : '' ?>>Demand Report</option>
                <option value="promotions" <?= $report_type == 'promotions' ? 'selected' : '' ?>>Promotions Report</option>
            </select>
        </div>

        <?php if ($report_type == 'inventory'): ?>
        <div class="col-auto">
            <label class="font-weight-bold mr-2">Subtype:</label>
            <select name="sub_type" class="form-control" onchange="this.form.submit()">
                <option value="all" <?= $sub_type == 'all' ? 'selected' : '' ?>>All Inventory</option>
                <option value="instock" <?= $sub_type == 'instock' ? 'selected' : '' ?>>In Stock</option>
                <option value="lowstock" <?= $sub_type == 'lowstock' ? 'selected' : '' ?>>Low Stock</option>
                <option value="outofstock" <?= $sub_type == 'outofstock' ? 'selected' : '' ?>>Out of Stock</option>
            </select>
        </div>
        <?php endif; ?>

        <?php if ($report_type == 'demand'): ?>
        <div class="col-auto">
            <label class="font-weight-bold mr-2">Subtype:</label>
            <select name="sub_type" class="form-control" onchange="this.form.submit()">
                <option value="top" <?= $sub_type == 'top' ? 'selected' : '' ?>>Top Selling</option>
                <option value="least" <?= $sub_type == 'least' ? 'selected' : '' ?>>Least Selling</option>
            </select>
        </div>
        <?php endif; ?>
    </div>
</form>

<!-- ✅ TABLE -->
<div class="table-responsive">
<table class="table table-bordered table-hover" id="salesTable" width="100%" cellspacing="0">
<thead>
<tr>
<?php
if ($report_type == 'orders') {
    $columns = ["Order ID","Customer Name","Store Name","Address","Product Name","Flavor","Pack/Size","Quantity","Total Price","Order Date"];
} elseif ($report_type == 'inventory') {
    $columns = ["Product Name","Flavor","Pack/Size","Stock","Price/Unit","Price/Case"];
} elseif ($report_type == 'demand') {
    $columns = ["#","Product Name","Flavor","Pack/Size","Total Sold"];
} elseif ($report_type == 'promotions') {
    $columns = [
        "#","Promotion Title","Type","Description","Discount (%)","Cashback (₱)",
        "Start Date","End Date","Status","Created By","Included Products",
        "Total Orders","Total Quantity Sold","Total Sales (₱)"
    ];
}
foreach ($columns as $col) {
    echo "<th>{$col}</th>";
}
?>
</tr>
</thead>
<tbody>
<?php
// ✅ QUERY BUILDER
if ($report_type == 'orders') {
    $query = "
        SELECT o.id AS order_id, c.fullname AS customer_name, c.store_name, c.address,
               p.name AS product_name, pv.flavor, pv.pack_size, oi.quantity, o.total_price, o.order_date
        FROM orders o
        JOIN customers c ON o.user_id = c.id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN product_variations pv ON oi.variation_id = pv.id
        JOIN products p ON pv.product_id = p.id
        WHERE o.status = 'completed'
        ORDER BY o.order_date DESC";
} elseif ($report_type == 'inventory') {
    $query = "
        SELECT 
    p.name AS product_name, 
    pv.flavor, 
    pv.pack_size, 
    COALESCE(SUM(sb.stock), 0) AS stock,
    pv.price_unit, 
    pv.price_case
FROM product_variations pv
JOIN products p ON pv.product_id = p.id
LEFT JOIN stock_batches sb ON sb.variation_id = pv.id
GROUP BY pv.id, p.name, pv.flavor, pv.pack_size, pv.price_unit, pv.price_case

        
        
        ";

    if ($sub_type == 'instock') $query .= " AND sb.stock > 50";
    elseif ($sub_type == 'lowstock') $query .= " AND sb.stock > 0 AND sb.stock <= 50";
    elseif ($sub_type == 'outofstock') $query .= " AND sb.stock = 0";

    $query .= " ORDER BY sb.stock DESC";
} elseif ($report_type == 'demand') {
    $query = "
        SELECT p.name AS product_name, pv.flavor, pv.pack_size,
               COALESCE(SUM(oi.quantity), 0) AS total_sold
        FROM product_variations pv
        JOIN products p ON pv.product_id = p.id
        LEFT JOIN order_items oi ON pv.id = oi.variation_id
        GROUP BY pv.id, p.name, pv.flavor, pv.pack_size
        ORDER BY total_sold " . ($sub_type == 'least' ? "ASC" : "DESC");
} elseif ($report_type == 'promotions') {
    $query = "
        SELECT 
            p.id,
            p.promo_title,
            p.promotion_type,
            p.promo_description,
            p.discount_percentage,
            p.cashback_amount,
            p.start_date,
            p.end_date,
            p.status,
            p.created_by,
            GROUP_CONCAT(DISTINCT pr.name SEPARATOR ', ') AS included_products,
            COUNT(DISTINCT o.id) AS total_orders,
            SUM(oi.quantity) AS total_quantity,
            COALESCE(SUM(oi.quantity * oi.price), 0) AS total_sales
        FROM promotions p
        LEFT JOIN promotion_products pp ON p.id = pp.promotion_id
        LEFT JOIN products pr ON pp.product_id = pr.id
        LEFT JOIN order_items oi ON pr.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
        GROUP BY p.id
        ORDER BY total_sales DESC";
}

// ✅ RUN QUERY
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $rank = 1;
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        if ($report_type == 'orders') {
            echo "<td>{$row['order_id']}</td>
                  <td>{$row['customer_name']}</td>
                  <td>{$row['store_name']}</td>
                  <td>{$row['address']}</td>
                  <td>{$row['product_name']}</td>
                  <td>{$row['flavor']}</td>
                  <td>{$row['pack_size']}</td>
                  <td>{$row['quantity']}</td>
                  <td>₱" . number_format($row['total_price'], 2) . "</td>
                  <td>" . date('F j, Y', strtotime($row['order_date'])) . "</td>";
        } elseif ($report_type == 'inventory') {
            echo "<td>{$row['product_name']}</td>
                  <td>{$row['flavor']}</td>
                  <td>{$row['pack_size']}</td>
                  <td>{$row['stock']}</td>
                  <td>₱" . number_format($row['price_unit'], 2) . "</td>
                  <td>₱" . number_format($row['price_case'], 2) . "</td>";
        } elseif ($report_type == 'demand') {
            echo "<td>{$rank}</td>
                  <td>{$row['product_name']}</td>
                  <td>{$row['flavor']}</td>
                  <td>{$row['pack_size']}</td>
                  <td>{$row['total_sold']}</td>";
            $rank++;
        } elseif ($report_type == 'promotions') {
            echo "<td>{$rank}</td>
                  <td>{$row['promo_title']}</td>
                  <td>{$row['promotion_type']}</td>
                  <td>{$row['promo_description']}</td>
                  <td>" . number_format($row['discount_percentage'], 2) . "%</td>
                  <td>" . ($row['cashback_amount'] ? '₱' . number_format($row['cashback_amount'], 2) : '-') . "</td>
                  <td>" . date('M j, Y', strtotime($row['start_date'])) . "</td>
                  <td>" . date('M j, Y', strtotime($row['end_date'])) . "</td>
                  <td><span class='badge badge-" . 
                      ($row['status'] == 'approved' ? 'success' : ($row['status'] == 'pending' ? 'warning' : 'danger')) . 
                      "'>{$row['status']}</span></td>
                  <td>{$row['created_by']}</td>
                  <td>{$row['included_products']}</td>
                  <td>" . ($row['total_orders'] ?? 0) . "</td>
                  <td>" . ($row['total_quantity'] ?? 0) . "</td>
                  <td>₱" . number_format($row['total_sales'], 2) . "</td>";
            $rank++;
        }
        echo "</tr>";
    }
}
?>
</tbody>
</table>
</div>
</div>
</div>
</div>

<!-- ✅ JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script>
$(document).ready(function () {
    $('#salesTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'collection',
                text: 'Export',
                className: 'btn btn-danger',
                buttons: [
                    { extend: 'excelHtml5', title: 'Sales Report' },
                    { extend: 'csvHtml5', title: 'Sales Report' },
                    { 
                        extend: 'pdfHtml5', 
                        title: 'Sales Report', 
                        orientation: 'landscape', 
                        pageSize: 'A4',
                        customize: function (doc) {
                            doc.defaultStyle.alignment = 'center';
                            doc.styles.tableHeader.alignment = 'center';
                        }
                    },
                    { extend: 'print', title: 'Sales Report' }
                ]
            }
        ],
        responsive: true,
        pageLength: 10
    });
});
</script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
