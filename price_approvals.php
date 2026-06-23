<?php
session_start();
include 'db_connection.php';

// Restrict access to Brand Manager only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Brand Manager') {
    die("❌ Access Denied! This page is only for the Brand Manager.");
}

// Fetch pending price suggestions
$sql = "SELECT ps.id, p.name AS product_name, pv.flavor, pv.pack_size, ps.old_price, ps.suggested_price, ps.created_at
        FROM price_suggestions ps
        JOIN product_variations pv ON ps.variation_id = pv.id
        JOIN products p ON pv.product_id = p.id
        WHERE ps.status = 'pending'";
$result = $conn->query($sql);

// Handle approval/rejection
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $suggestion_id = $_POST['suggestion_id'];
    $action = $_POST['action'];

    if ($action == "approve") {
        // Update product price
        $stmt = $conn->prepare("UPDATE product_variations pv
                                JOIN price_suggestions ps ON pv.id = ps.variation_id
                                SET pv.price_case = ps.suggested_price
                                WHERE ps.id = ?");
        $stmt->bind_param("i", $suggestion_id);
        $stmt->execute();

        // Mark suggestion as approved
        $stmt2 = $conn->prepare("UPDATE price_suggestions SET status = 'approved' WHERE id = ?");
        $stmt2->bind_param("i", $suggestion_id);
        $stmt2->execute();
    } elseif ($action == "reject") {
        $stmt = $conn->prepare("UPDATE price_suggestions SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $suggestion_id);
        $stmt->execute();
    }

    header("Location: price_approvals.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Price Approvals</title>
<link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
<link href="css/style.css" rel="stylesheet">
<link href="css/sb-admin-2.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body id="page-top">

<div id="content-wrapper" class="d-flex flex-column">
    <div id="content" class="container-fluid">

        <div class="row justify-content-center mt-5">
            <div class="col-md-10">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-success text-white text-center">
                        <h4 class="m-0"><i class="fas fa-check-circle me-2"></i>Price Approvals</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($result->num_rows > 0): ?>
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>Flavor</th>
                                        <th>Pack/Size</th>
                                        <th>Old Price</th>
                                        <th>Suggested Price</th>
                                        <th>Date Suggested</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= $row['product_name'] ?></td>
                                            <td><?= $row['flavor'] ?></td>
                                            <td><?= $row['pack_size'] ?></td>
                                            <td>₱<?= number_format($row['old_price'], 2) ?></td>
                                            <td>₱<?= number_format($row['suggested_price'], 2) ?></td>
                                            <td><?= $row['created_at'] ?></td>
                                            <td>
                                                <form method="POST" action="price_approvals.php" class="d-inline">
                                                    <input type="hidden" name="suggestion_id" value="<?= $row['id'] ?>">
                                                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                                                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">Reject</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="alert alert-info text-center">No pending price suggestions.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

</body>
</html>
