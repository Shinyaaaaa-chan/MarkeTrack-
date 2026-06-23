<?php

ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);
session_name('admin_session');

session_start();

include 'db_connection.php';


if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

date_default_timezone_set('Asia/Manila');

$user_role = $_SESSION['role'];
$today = date('Y-m-d');
$promotions_result = $conn->query("SELECT * FROM promotions WHERE end_date < '$today' ORDER BY end_date DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Promotion History - MarkeTrack</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <!-- MarkeTrack Custom Style -->
    <link href="css/style.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fc;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            background-color: #fff;
            border-radius: 12px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
        }

        h2 {
            font-weight: 700;
            color: #dc3545;
        }

        .btn-outline-danger {
            border-radius: 8px;
            font-weight: 600;
        }

        .table th, .table td {
            vertical-align: middle;
            text-align: center;
            font-size: 0.95rem;
        }

        .badge {
            font-size: 0.85rem;
            padding: 6px 10px;
            border-radius: 8px;
        }

        /* Scrollable container */
        .table-responsive {
            max-height: 60vh;
            overflow-y: auto;
            overflow-x: auto;
            border-radius: 8px;
        }

        /* Responsive adjustments */
        @media (max-width: 991px) {
            h2 {
                font-size: 1.3rem;
                text-align: center;
            }
            .btn-outline-danger {
                width: 100%;
                margin-top: 0.5rem;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
                margin: 1rem;
            }

            .table th, .table td {
                font-size: 0.70rem;
            }
        }

        @media (max-width: 576px) {
            body {
                padding: 0;
            }
            .container {
                padding: 1rem;
                width: 100%;
                border-radius: 0;
                box-shadow: none;
            }
        }
    </style>
</head>

<body>

<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="mb-0">
            <i class="fas fa-clock-rotate-left me-2"></i> Promotion History
        </h2>
        <a href="promotionmanagement.php" class="btn btn-outline-danger mt-2 mt-md-0">
            <i class="fas fa-arrow-left me-1"></i> Back to Promotions
        </a>
    </div>

    <div class="card border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Promo Title</th>
                            <th>Validity Period</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($promotions_result->num_rows > 0): ?>
                            <?php while ($promotion = $promotions_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($promotion['promo_title']) ?></td>
                                    <td>
                                        <?= date('M d, Y', strtotime($promotion['start_date'])) ?> - 
                                        <?= date('M d, Y', strtotime($promotion['end_date'])) ?>
                                    </td>
                                    <td><span class="badge bg-secondary">Expired Promo</span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">No expired promotions found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
