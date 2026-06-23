<?php 
ini_set('session.gc_maxlifetime', 86400); // 24 hours in seconds
session_set_cookie_params(86400);         // 24 hours in seconds
session_name('admin_session');

session_start();

include 'db_connection.php';
include 'sidebar.php';

$query = "
    SELECT 
  r.id AS rating_id, 
  r.order_id, 
  r.order_item_id, 
  r.user_id, 
  r.product_quality, 
  r.delivery_service, 
  r.overall_satisfaction, 
  r.created_at,
  r.comment,
  r.photo,
  o.order_date, 
  u.username AS customer_name
FROM order_ratings r
JOIN orders o ON r.order_id = o.id
JOIN users u ON r.user_id = u.id
ORDER BY r.created_at DESC
";

$result = $conn->query($query);

// Debugging: Check if results are being fetched
if (!$result) {
    die("Query failed: " . $conn->error);
}

$ratings = $result->fetch_all(MYSQLI_ASSOC);


?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Ratings | MarkeTrack</title>
    <link href="css/style.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
      /* 🌟 MarkeTrack | Ratings Page Styles */

/* ===== Global ===== */
body {
  background: #f8f9fc;
  font-family: 'Segoe UI', 'Roboto', Arial, sans-serif;
  color: #222;
}

/* ===== Ratings Container ===== */
.ratings-container {
  flex: 1;
  margin-left: 10px;
  padding: 2rem;
  max-width: 1200px;
  overflow-y: auto;
}

.ratings-container h1 {
  font-weight: 700;
  font-size: 2rem;
  margin-bottom: 2rem;
  color: #dc3545;
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

/* ===== Rating Card ===== */
.rating-card {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 3px 15px rgba(0,0,0,0.05);
  margin-bottom: 1.5rem;
  padding: 1.5rem;
  transition: all 0.2s ease;
}

.rating-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(0,0,0,0.08);
}

/* ===== Header Section ===== */
.rating-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.rating-header h3 {
  font-size: 1.1rem;
  font-weight: 600;
  margin: 0;
  color: #343a40;
}

.rating-header small {
  color: #777;
  font-size: 0.9rem;
}

/* ===== Stars ===== */
.rating-stars {
  text-align: right;
}

.rating-stars i {
  color: #f1c40f;
  margin: 0 1px;
}

.rating-stars small {
  display: block;
  font-size: 0.85rem;
  color: #555;
  margin-top: 0.2rem;
}

/* ===== Rating Metrics ===== */
.rating-metrics {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  margin-top: 0.8rem;
  margin-bottom: 0.5rem; /* spacing before comment/photo */
}

.metric-item {
  flex: 1 1 30%;
  min-width: 150px;
}

.metric-item:hover {
  background: #fff5f5;
  border-color: #f5c2c7;
}

.metric-label {
  font-size: 0.85rem;
  color: #666;
  margin-bottom: 0.3rem;
}

.metric-value {
  font-weight: 600;
  font-size: 1.1rem;
  color: #dc3545;
}

/* ===== Comment + Photo Section ===== */
.rating-feedback {
  display: flex;
  align-items: flex-start;
  gap: 1.2rem;
  margin-top: 1.2rem;
  flex-wrap: wrap;
}

/* Comment Box */
.rating-comment {
  flex: 1;
  background: #fff9f9;
  border-left: 4px solid #dc3545;
  padding: 0.9rem 1rem;
  border-radius: 8px;
  min-width: 230px;
}

.rating-comment strong {
  color: #b20000;
  font-size: 0.95rem;
}

.rating-comment p {
  margin: 0.4rem 0 0 0;
  font-size: 0.95rem;
  color: #333;
  line-height: 1.4;
}

/* Photo Section */
.rating-photo {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.photo-label {
  font-weight: 600;
  color: #b20000;
  font-size: 0.9rem;
  margin-bottom: 0.4rem;
}

.rating-photo img {
  max-width: 200px;
  border-radius: 10px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.08);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  border: 1px solid #eee;
}

.rating-photo img:hover {
  transform: scale(1.05);
  box-shadow: 0 6px 15px rgba(0,0,0,0.15);
}

/* ===== No Ratings ===== */
.no-ratings {
  background: #fff;
  border-radius: 12px;
  padding: 2rem;
  text-align: center;
  color: #777;
  font-size: 1.05rem;
  border: 1px dashed #ccc;
}

/* ===== Responsive Adjustments ===== */
@media (max-width: 992px) {
  .ratings-container {
    margin-left: 0;
    padding: 1rem;
  }
}

@media (max-width: 576px) {
  .rating-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }

  .rating-stars {
    text-align: left;
  }

  .rating-feedback {
    flex-direction: column;
  }

  .rating-photo img {
    width: 100%;
    max-width: none;
  }
}

    </style>
</head>
<body>
    <div class="ratings-container">
        <h1><i class="fas fa-star-half-alt"></i> Customer Ratings</h1>

        <?php if (empty($ratings)): ?>
            <div class="no-ratings">No ratings available yet.</div>
        <?php else: ?>
            <?php foreach ($ratings as $rating): ?>
                <div class="rating-card">
                    <div class="rating-header">
                        <div>
                            <h3>Order #<?= htmlspecialchars($rating['order_id']) ?></h3>
                            <small>
                                <?= 
                                    ($rating['created_at'] && $rating['created_at'] !== '0000-00-00 00:00:00') 
                                        ? date('M d, Y h:i A', strtotime($rating['created_at'])) 
                                        : 'Date not available'; 
                                ?>
                            </small>
                        </div>
                        <?php
    $avg_rating = round((
        (int)$rating['product_quality'] +
        (int)$rating['delivery_service'] +
        (int)$rating['overall_satisfaction']
    ) / 3);
?>
<div class="rating-stars">
    <?= str_repeat('<i class="fas fa-star"></i>', $avg_rating) ?>
    <?= str_repeat('<i class="far fa-star"></i>', 5 - $avg_rating) ?>
    <small style="font-size: 0.9rem; color: #555;">(<?= $avg_rating ?>/5)</small>
</div>

                    </div>

                    <div class="rating-metrics">
                        <div class="metric-item">
                            <div class="metric-label">Product Quality</div>
                            <div class="metric-value">
                                <?= htmlspecialchars($rating['product_quality']) ?>/5
                            </div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-label">Delivery Service</div>
                            <div class="metric-value">
                                <?= htmlspecialchars($rating['delivery_service']) ?>/5
                            </div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-label">Overall Satisfaction</div>
                            <div class="metric-value">
                                <?= htmlspecialchars($rating['overall_satisfaction']) ?>/5
                            </div>
                        </div>


                        <?php if (!empty($rating['comment']) || !empty($rating['photo'])): ?>
  <div class="rating-metrics">
      <?php if (!empty($rating['comment'])): ?>
          <div class="metric-item">
              <div class="metric-label">Customer Comment</div>
              <div class="metric-value" style="color:#333; font-weight:400;">
                  <?= nl2br(htmlspecialchars($rating['comment'])) ?>
              </div>
          </div>
      <?php endif; ?>

      
  </div>
<?php endif; ?>


                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
