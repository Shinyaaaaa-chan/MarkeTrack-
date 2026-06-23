<?php
ini_set('session.gc_maxlifetime', 86400); // 24 hours in seconds
session_set_cookie_params(86400);         // 24 hours in seconds
session_name('admin_session');

session_start();

include 'db_connection.php';
include 'sidebar.php';

$result = $conn->query("SELECT * FROM customers");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customers - MarkeTrack</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap & Icons -->
    <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        body {
            background-color: #f8f9fc;
            overflow: hidden; /* Prevent page scroll — container handles it */
           
        }
        .sidebar {
width: 200px; /* fixed width */
flex-shrink: 0; /* wag lumiit */
}

        /* Scrollable main container */
        .container-fluid {
            max-height: 100vh; /* full viewport height */
            overflow-y: auto;  /* vertical scroll */
            overflow-x: hidden;
            padding: 1.5rem;
        }

        /* Custom scrollbar styling */
        .container-fluid::-webkit-scrollbar {
            width: 10px;
        }
        .container-fluid::-webkit-scrollbar-thumb {
            background: rgba(0,0,0,0.3);
            border-radius: 10px;
        }
        .container-fluid::-webkit-scrollbar-thumb:hover {
            background: rgba(0,0,0,0.5);
        }

        .card {
            border-radius: 10px;
            padding: 1.5rem;
        }

        .btn-primary {
            background-color: #e63946;
            border-color: #e63946;
        }

        .btn-primary:hover {
            background-color: #d62828;
            border-color: #d62828;
        }

        .table th, .table td {
            vertical-align: middle;
            text-align: center;
        }

        h3 {
            font-weight: 700;
            color: #e63946;
        }

        /* Responsive adjustments */
        @media (max-width: 991px) {
            .card {
                padding: 1rem;
            }

            h3 {
                font-size: 1.5rem;
                text-align: center;
            }

            .btn {
                width: 100%;
                margin-bottom: 10px;
            }

            .table th, .table td {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 768px) {
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .table th, .table td {
                font-size: 0.9rem;
                white-space: nowrap;
            }

            .container-fluid {
                padding: 1rem;
                overflow-y: auto;
            }
        }

        @media (max-width: 576px) {
            .card {
                padding: 1rem;
            }

            h3 {
                font-size: 1.3rem;
            }

            .table th, .table td {
                font-size: 0.85rem;
            }

            .table-responsive {
                overflow-x: auto;
                display: block;
                width: 100%;
            }

            .container-fluid {
                overflow-y: scroll;
            }
        }

        /* Smooth scrollbar for table too */
        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }
        .table-responsive::-webkit-scrollbar-thumb {
            background-color: rgba(0,0,0,0.2);
            border-radius: 10px;
        }
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background-color: rgba(0,0,0,0.4);
        }
    </style>
</head>
<body>

<div class="container-fluid mt-4">
    <div class="card shadow">
        <h3 class="mb-4">Registered Customers</h3>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Assistant Brand Manager'): ?>
        <div class="mb-3">
            <a href="add_customer.php" class="btn btn-primary shadow-sm">
                <i class="fas fa-user-plus me-2"></i> Add Customer
            </a>
        </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Store Name</th>
                        <th>Address</th>
                        <th>Contact Number</th>
                        <th>Email Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['fullname']) ?></td>
                        <td><?= htmlspecialchars($row['store_name']) ?></td>
                        <td><?= htmlspecialchars($row['address']) ?></td>
                        <td><?= htmlspecialchars($row['contact_number']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div> 
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>
</html>
