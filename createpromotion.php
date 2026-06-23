<?php
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);
session_name('admin_session');

session_start();

include 'db_connection.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Trade and Marketing Team') {
  header("Location: login.php");
  exit;
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  // Ang form processing mo ay tama na, walang binago dito...
  $promotion_type = $_POST['promotion_type'];
  if ($promotion_type == 'other' && !empty($_POST['custom_promotion_type'])) {
    $promotion_type = $_POST['custom_promotion_type'];
}
  $promo_title = $_POST['promo_title'];
  $promo_description = $_POST['promo_description'];
  $start_date = $_POST['start_date'];
  $end_date = $_POST['end_date'];
  $min_purchase = !empty($_POST['min_purchase']) ? $_POST['min_purchase'] : null;  $product_variations = $_POST['product_variations'] ?? [];
  $discount_percentage = $_POST['discount_percentage'] ?? null;

  // Ang INSERT statement mo ay tama na rin.
  $insert = $conn->prepare("INSERT INTO promotions (
    promotion_type, promo_title, promo_description, discount_percentage, 
    start_date, end_date, min_purchase, created_at, status, created_by
  ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'pending', ?)");
  
  $insert->bind_param("sssdssdi", 
    $promotion_type, 
    $promo_title, 
    $promo_description, 
    $discount_percentage, 
    $start_date, 
    $end_date, 
    $min_purchase, 
    $_SESSION['user_id']
  );

  if ($insert->execute()) {
    $promotion_id = $insert->insert_id;

    // Ang pag-save ng products ay tama na rin.
    // TANDAAN: Ang $product_id dito ay galing na sa 'product_variations[]' 
    // na ang value ay 'variation_id' na (dahil sa ayos sa HTML sa baba)
    foreach ($product_variations as $product_id) {
      $stmt = $conn->prepare("INSERT INTO promotion_products (promotion_id, product_id) VALUES (?, ?)");
      $stmt->bind_param("ii", $promotion_id, $product_id);
      $stmt->execute();
    }

    // --- START: INAYOS NA NOTIFICATION LOGIC ---
    // Ito lang ang parteng binago
    
    // Find all Brand Managers
    $bm_users = get_user_ids_by_role($conn, 'Brand Manager');

    // Create a notification for each Brand Manager
    foreach ($bm_users as $user_id) {
      createNotification(
        $conn,
        $user_id,
        'admin',                           // 1. Idinagdag ang user_type
        'New Promotion Request',                   // 2. Ito na ang tamang title
        "A new promotion '{$promo_title}' is awaiting your approval.", // 3. Ito na ang message
        "promotionmanagement.php");
    }
    // --- END: INAYOS NA NOTIFICATION LOGIC ---

    header("Location: promotionmanagement.php?success=1");
    exit;
  } else {
    $error_message = "Error submitting promotion. Please try again.";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create Promotion</title>
  <link href="css/style.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body style="background-color: #f8f9fa;">
<div class="container py-4 bg-white rounded shadow" style="max-height: 95vh; overflow-y: auto;">
  <h2 class="mb-4 text-danger fw-bold">Create New Promotion</h2>

  <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger"><?= $error_message ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="mb-3">
      <label class="form-label">Promotion Type</label>
      <select name="promotion_type" id="promotion_type" class="form-select" required>
        <option value="" disabled selected>Select Promotion Type</option>
        <option value="percentage_discount">Percentage Discount</option>
        <option value="buy1take1">Buy 1 Take 1</option>
        <option value="fixed_discount">Fixed Amount Discount</option>
 _       <option value="bundle">Bundle (e.g. 3 for ₱100)</option>
        <option value="other">Other</option>
      </select>
    </div>

    <div id="custom_promo_type_group" class="mb-3" style="display: none;">
      <label class="form-label">Specify Promotion Type</label>
      <input type="text" name="custom_promotion_type" class="form-control" placeholder="Enter promotion type">
    </div>

    <div class="mb-3">
      <label class="form-label">Promotion Title</label>
      <input type="text" name="promo_title" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Promotion Description</label>
      <textarea name="promo_description" class="form-control" rows="3" required></textarea>
    </div>

    <div id="percentage_discount_section" class="mb-3" style="display:none;">
      <label class="form-label">Discount Percentage</label>
      <input type="number" name="discount_percentage" class="form-control" min="1" max="100">
    </div>

    <div class="mb-3">
    <label class="form-label">Applicable Products</label>
    <div class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
        <?php
        // 1. Inayos na query para kunin ang variation_id
        $query = "SELECT pv.id AS variation_id, p.name AS product_name, pv.flavor, pv.pack_size 
                  FROM product_variations pv 
                  JOIN products p ON p.id = pv.product_id";
        
        $result = $conn->query($query);

        // 2. Nagdagdag ng check kung may resulta bago mag-loop
        if ($result && $result->num_rows > 0) {

            // 3. Tiyaking TAMA ang itsura ng 'while' loop mo (ito ang pinaka-importante)
            while ($row = $result->fetch_assoc()):
                $label = htmlspecialchars($row['product_name'] . " - " . $row['flavor'] . " (" . $row['pack_size'] . ")");
                
                // 4. Siguraduhing 'variation_id' ang kinukuha
                $id = $row['variation_id'];
        ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="product_variations[]" value="<?= $id ?>" id="pv<?= $id ?>">
                <label class="form-check-label" for="pv<?= $id ?>"><?= $label ?></label>
            </div>
        <?php
            endwhile; // Ito ang dulo ng loop

        } else {
            // 5. Maglagay ng message kung walang nahanap na produkto
            echo "<p class='text-muted'>No products found in the database.</p>";
        }
        ?>
    </div>
</div>

    <div class="row mb-3">
      <div class="col-md-6 mb-2">
        <label class="form-label">Start Date</label>
        <input type="date" class="form-control" name="start_date" required>
      </div>
      <div class="col-md-6 mb-2">
        <label class="form-label">End Date</label>
        <input type="date" class="form-control" name="end_date" required>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Minimum Purchase Amount</label>
      <input type="number" class="form-control" name="min_purchase" placeholder="Leave blank if no minimum">    </div>

    <div class="text-end">
      <button type="submit" class="btn btn-danger">Submit Promotion</button>
      <a href="promotionmanagement.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<script>
  const promoType = document.getElementById("promotion_type");
  const customTypeGroup = document.getElementById("custom_promo_type_group");
  const discountSection = document.getElementById("percentage_discount_section");

  promoType.addEventListener("change", function () {
    const selected = this.value;
    customTypeGroup.style.display = (selected === "other") ? "block" : "none";
    discountSection.style.display = (selected === "percentage_discount") ? "block" : "none";
  });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>