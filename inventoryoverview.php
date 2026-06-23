<?php
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);
session_name('admin_session');

session_start();

include 'db_connection.php';
include 'sidebar.php';
require_once 'functions.php'; // Idinagdag para maging consistent

$sql = "SELECT 
            pv.id, p.name, pv.flavor, pv.pack_size,
            COALESCE(SUM(sb.stock), 0) AS total_stock,
            COALESCE(SUM(sb.original_stock), 0) AS total_original_stock,
            MIN(sb.expiration_date) AS nearest_expiration
        FROM product_variations pv
        JOIN products p ON p.id = pv.product_id
        LEFT JOIN stock_batches sb ON sb.variation_id = pv.id AND sb.stock > 0
        GROUP BY pv.id, p.name, pv.flavor, pv.pack_size
        ORDER BY p.name, pv.flavor, pv.pack_size";

$result = $conn->query($sql);
$products = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

$user_role = $_SESSION['role'] ?? '';
$isMerchandisingMarketingTeam = ($user_role === 'Merchandising Marketing Team');
$isABM = ($user_role === 'Assistant Brand Manager'); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Overview - MarkeTrack</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body id="page-top">
<div id="content-wrapper" class="d-flex flex-column">
    <div id="content" class="container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Flavor / Pack Size</th>
                                <th>Total Stock</th>
                                <th>Nearest Expiration</th>
                                <th>Status</th> 
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($products) > 0): ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($product['name']) ?></td>
                                        <td><?= htmlspecialchars($product['flavor']) ?> / <?= htmlspecialchars($product['pack_size']) ?></td>
                                        <td><?= (int)$product['total_stock'] ?></td>
                                        <td><?= $product['nearest_expiration'] ? date('Y-m-d', strtotime($product['nearest_expiration'])) : '—' ?></td>
                                        <td>
                                            <?php
                                                $stock = (int)$product['total_stock'];
                                                $original_stock = (int)$product['total_original_stock'];
                                                $low_stock_threshold = ($original_stock > 0) ? $original_stock / 4 : 0; 
                                                $is_low_or_out_of_stock = ($stock <= $low_stock_threshold);
                                            ?>
                                            <?php if ($stock <= 0): ?>
                                                <span class="badge bg-dark">Out of Stock</span>
                                            <?php elseif ($is_low_or_out_of_stock): ?>
                                                <span class="badge bg-danger">Low Stock (<?= $stock ?> left)</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">In Stock</span>
                                            <?php endif; ?>

                                            <?php if ($isABM && $is_low_or_out_of_stock): ?>
                                                <button type="button" class="btn btn-primary btn-sm d-block mx-auto mt-2" 
                                                        style="--bs-btn-padding-y: .2rem; --bs-btn-padding-x: .4rem; --bs-btn-font-size: .75rem;"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#restockModal"
                                                        data-variation-id="<?= $product['id'] ?>"
                                                        data-product-name="<?= htmlspecialchars($product['name'] . ' (' . $product['flavor'] . ' / ' . $product['pack_size'] . ')') ?>">
                                                    Request Restock
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($isMerchandisingMarketingTeam && $is_low_or_out_of_stock): ?>
                                                <a href="add_stock.php?variation_id=<?= $product['id'] ?>" 
                                                   class="btn btn-danger btn-sm d-block mx-auto mt-2"
                                                   style="--bs-btn-padding-y: .2rem; --bs-btn-padding-x: .4rem; --bs-btn-font-size: .75rem;">
                                                    <i class="fas fa-plus-circle me-1"></i> Add Stock
                                                </a>
                                            <?php endif; ?>
                                            </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">
                                        No products found.
                                    </td>
                                </tr>
                                <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="restockModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Request Restock</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="process_restock_request.php" method="POST">
        <div class="modal-body">
          <p>You are requesting a restock for: <strong id="modalProductName"></strong></p>
          <input type="hidden" name="variation_id" id="modalVariationId">
          <div class="mb-3">
            <label for="notes" class="form-label">Notes (Optional):</label>
            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="e.g., Priority restock, sales are high."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var restockModal = document.getElementById('restockModal');
    if(restockModal) {
        restockModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var variationId = button.getAttribute('data-variation-id');
            var productName = button.getAttribute('data-product-name');
            var modalProductName = restockModal.querySelector('#modalProductName');
            var modalVariationIdInput = restockModal.querySelector('#modalVariationId');
            modalProductName.textContent = productName;
            modalVariationIdInput.value = variationId;
        });
    }
});
</script>

</body>
</html>