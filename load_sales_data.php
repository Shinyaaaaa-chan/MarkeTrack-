<?php
include 'db_connection.php';

$salesData = [];

// Combine historical data + completed order data
$query = "
    SELECT 
        hd.product_name AS product,
        CAST(SUBSTRING(hd.month, 1, 4) AS UNSIGNED) AS sales_year,
        CAST(SUBSTRING(hd.month, 6, 2) AS UNSIGNED) AS sales_month,
        SUM(hd.actual_sales) AS total_quantity
    FROM historical_data hd
    WHERE hd.product_name IS NOT NULL AND hd.actual_sales IS NOT NULL
    GROUP BY hd.product_name, sales_year, sales_month

    UNION ALL

    SELECT 
        p.name AS product,
        YEAR(o.order_date) AS sales_year,
        MONTH(o.order_date) AS sales_month,
        SUM(oi.quantity) AS total_quantity
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE o.status = 'completed'
    GROUP BY p.name, sales_year, sales_month

    ORDER BY sales_year ASC, sales_month ASC;
";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query Failed: " . mysqli_error($conn));
}

while ($row = mysqli_fetch_assoc($result)) {
    $product  = trim($row['product']);
    $year     = (int)$row['sales_year'];
    $month    = (int)$row['sales_month'];
    $quantity = (int)$row['total_quantity'];

    // Skip invalid or empty data
    if ($product === "" || $year === 0 || $month === 0) {
        continue;
    }

    if (!isset($salesData[$product])) {
        $salesData[$product] = [];
    }
    if (!isset($salesData[$product][$year])) {
        $salesData[$product][$year] = [];
    }

    // Store sales per product, per year, per month
    $salesData[$product][$year][$month] = $quantity;
}

// Optional: uncomment to test
// echo "<pre>"; print_r($salesData); echo "</pre>";
?>
