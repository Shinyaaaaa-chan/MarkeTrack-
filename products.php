<?php
ini_set('session.gc_maxlifetime', 86400); 
session_set_cookie_params(86400);
session_name('admin_session');

session_start();

include 'db_connection.php';
include 'sidebar.php';

$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

$search = isset($_GET['search']) ? $_GET['search'] : ''; 
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

$query = "SELECT * FROM products WHERE name LIKE ?";
if (!empty($category_filter)) { 
 $query .= " AND categories LIKE ?"; 
}
$query .= " ORDER BY created_at ASC";

$product_query = $conn->prepare($query); 
$search_term = "%$search%"; 
if (!empty($category_filter)) { 
 $category_term = "%$category_filter%"; 
 $product_query->bind_param("ss", $search_term, $category_term); 
} else {
 $product_query->bind_param("s", $search_term); 
}

$product_query->execute();
$result = $product_query->get_result(); 
$products = $result->fetch_all(MYSQLI_ASSOC);

// Fetch distinct categories
$category_query = $conn->prepare("SELECT DISTINCT categories
FROM products");
 $category_query->execute();
 $category_result = $category_query->get_result(); 
 $categories = $category_result->fetch_all(MYSQLI_ASSOC);

 ?>
 <!DOCTYPE html> 
 <html lang="en"> 
  <head>
  <meta charset="UTF-8"> 
  <meta name="viewport" content="width=device-width, initial-scale=1, 
  shrink-to-fit=no"> 
  <title>Products | MarkeTrack</title>

  <link href="css/style.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"> 
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

  <style>
/* ===== BASE STYLES ===== */
/* Main layout: sidebar + content */
.wrapper {
display: flex;
width: 100%;
}

.sidebar {
width: 200px; /* fixed width */
flex-shrink: 0; /* wag lumiit */
}

/* Content area */
.content {
flex-grow: 1;
/* ⛔️ TINANGGAL 'overflow-x: auto !important;' DITO para di buong page nag-scroll ⛔️ */
padding: 0px;
}

/* Para sa table */
.table-responsive {
width: 100%;
overflow-x: auto !important; /* Hayaan lang 'to as safety net */
}

/* ... (ibang styles, pareho lang) ... */

html, body {
height: auto !important;
min-height: 100vh;
background-color: #f8f9fc;
overflow-x: hidden; /* iwas page scrollbar -- TAMA ITO, WAG GALAWIN */
}

.container-fluid {
padding-right: 0 !important;
padding-left: 0 !important;
}

.card { 
margin-bottom: 1.5rem;
border-radius: 0.75rem;
overflow: hidden; 
margin-right: 15px;
margin-left: 15px;
}

.card-header {
background: #fff; 
border-bottom: 3px solid #dc3545;
}

.card-body {
max-height: 500px;
overflow-y: auto;
}

/* Table Responsive Container */
.table-responsive {
width: 100%;
overflow-x: auto !important; /* Hayaan lang 'to para sure, pero di dapat lilitaw */
-webkit-overflow-scrolling: touch;
padding-bottom: 15px;
margin-bottom: 1rem;
display: block;
}

/* ... (scrollbar styles, pareho lang) ... */
.table-responsive::-webkit-scrollbar { height: 10px; } 
.table-responsive::-webkit-scrollbar-thumb { background: #ccc; border-radius: 5px; } 
.table-responsive::-webkit-scrollbar-thumb:hover { background: #aaa; }

.table { 
border-collapse: collapse; 
width: 100%;
/* ⛔️ BINAGO: TINANGGAL 'min-width: 1050px;' ⛔️ */
}

.table th { 
background-color: #fff; 
color: #dc3545; 
font-weight: bold; 
}

.table-responsive > .table > thead > tr > th,
.table-responsive > .table > tbody > tr > td {
display: table-cell !important; 
}

.table th, .table td {
border: 1px solid #dee2e6 !important;
vertical-align: middle; 
text-align: center;
/* ⛔️ BINAGO: 'nowrap' to 'normal' para mag-wrap ang text ⛔️ */
white-space: normal;
/* 💡 DINAGDAG: para 'lumiit' ang laman 💡 */
font-size: 14px;
padding: 8px;
}

/* ⛔️ BINAGO: Ginawang porsyento (%) ang widths para mag-adjust ⛔️ */
.table thead th:nth-child(1), .table tbody td:nth-child(1) { width: 5%; }  /* ID */
.table thead th:nth-child(2), .table tbody td:nth-child(2) { width: 10%; } /* Image */
.table thead th:nth-child(3), .table tbody td:nth-child(3) { width: 25%; } /* Product Name */
.table thead th:nth-child(4), .table tbody td:nth-child(4) { width: 20%; } /* Categories */
.table thead th:nth-child(5), .table tbody td:nth-child(5) { width: 15%; } /* Actions */
.table thead th:nth-child(6), .table tbody td:nth-child(6) { width: 25%; } /* Variations */

.table img {
border-radius: 5px;
object-fit: cover;
}

/* ... (lahat ng natitirang style ay pareho lang) ... */

.table tr:last-child td {
border-bottom: 2px solid #dee2e6 !important;
}
.variation-row { 
display: none;
background: #f8f9fc;
} 
.show-variation { 
cursor: pointer; 
color: #007bff; 
text-decoration: underline;
}
ul.variation-list { 
list-style: none; 
padding: 0;
margin: 0; 
}
ul.variation-list li { 
padding: 6px 0; 
border-bottom: 1px solid #ddd;
} 
ul.variation-list li:last-child { 
border-bottom: none; 
}
.btn-warning, .btn-danger { 
border-radius: 6px; 
} 
.btn-warning i, .btn-danger i { 
font-size: 14px; 
}

/* ===== RESPONSIVE BREAKPOINTS ===== */
/* Pwede mo na i-adjust 'to o tanggalin kung sapat na yung base style */

@media (max-width: 991px) {
.table th, .table td { 
 font-size: 14px; 
 padding: 8px;
}
.container-fluid {
 padding: 0 8px; 
}
.card-header .form-inline {
 width: 100%;
 margin-bottom: 8px;
 display: flex;
 flex-direction: column; 
}
.card-header .form-inline .form-control-sm {
 width: 100%;
 margin-right: 0 !important;
 margin-bottom: 8px; 
}
.card-header .d-flex.flex-wrap.align-items-center {
 width: 100%;
 justify-content: center !important;
 margin-top: 10px;
}
.card-header a.btn {
 margin-left: 0 !important;
 margin-top: 5px;
}
.table-responsive {
 overflow-x: auto !important;
}
}
@media (max-width: 768px) {
.table th, .table td {
 font-size: 13px !important;
 padding: 6px 8px !important;
 /* ⛔️ BINAGO: 'nowrap' to 'normal' para mag-wrap ang text ⛔️ */
 white-space: normal;
}
.table-responsive {
 overflow-x: auto !important;
}
.card-header {
 flex-direction: column;
_align-items: flex-start;
}
.card-header .form-inline {
 width: 100%;
 flex-direction: column;
 align-items: stretch;
}
.card-header .form-inline .form-control-sm {
 width: 100%;
}
.card-header a.btn {
 width: 100%;
 text-align: center;
}
}
@media (max-width: 567px) {
.table th, .table td {
 font-size: 12px !important;
 padding: 5px 6px !important;
}
.table-responsive {
 overflow-x: auto !important;
}
.card-header .form-inline {
 flex-direction: column;
 gap: 6px;
}
.card-header .form-inline .form-control-sm {
 width: 100%;
}
.card-header a.btn {
 width: 100%; 
 text-align: center;
}
}
</style>
  </head>

  <body> 
  <div class="wrapper">
  <div class="content">
   <div class="container-fluid mt-4"> 
   <div class="card mb-4"> <!-- REMOVED 'shadow' class here -->
   <!-- Header adjusted to better handle wrapping on small screens -->
   <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center"> 
            <h6 class="m-0 font-weight-bold text-danger">Products List</h6> 
            <div class="d-flex flex-wrap align-items-center">
   <form action="" method="get" class="form-inline"> 
   <input type="text" name="search" class="form-control form-control-sm mr-2" placeholder="Search Products" value="<?= htmlspecialchars($search) ?>">
   <select name="category" class="form-control form-control-sm mr-2" onchange="this.form.submit()"> 
    <option value="">All Categories</option>
    <?php foreach ($categories as $category): ?> 
     <option value="<?= htmlspecialchars($category['categories']) ?>" <?= $category['categories'] === $category_filter ? 'selected' : '' 
     ?>>

<?= htmlspecialchars($category['categories']) ?>
</option>
<?php endforeach; ?> 
</select> 
</form> 
  <?php if ($role === 'Brand Manager'): ?>
  <a href="add_product.php" class="btn btn-danger btn-sm ml-2">
   <i class="fas fa-plus"></i> Add Product </a>
   <?php endif; ?> 
  </div>
 </div>

 <!-- Tinanggal ang style attribute dito at inilipat sa CSS ang control -->
 <div class="card-body"> 
 <?php if (count($products) > 0): ?>
  <!-- table-responsive is the key to maintaining horizontal scroll on all devices -->
  <div class="table-responsive">
  <table class="table table-bordered">
  <thead> 
    <tr>
     <th>ID</th>
      <th>Image</th>
      <th>Product Name</th>
      <th>Categories</th>
      <?php if ($role === 'Brand Manager'): ?><th>Actions</th><?php endif; ?>
       <th>Variations</th>
      </tr> 
      </thead> 
      <tbody>
      <?php foreach ($products as $row): ?>
       <tr> 
        <td><?= htmlspecialchars($row['id']) ?></td> 
        <td>
        <?php
        $image_folder = 'img/';
        $image_name = $row['image'];
        $image_path = $image_folder . $image_name;
        if (!empty($image_name) && file_exists($image_path)) {
          echo "<img src='$image_path' alt='" . htmlspecialchars($row['name']) . "' width='70' height='50'>";
        } else { echo "<div style='width:70px;
          height:50px;
          background:#ddd;
          display:flex;
          align-items:center;
          justify-content:center;
          color:#888;'>No Image</div>";
        } ?>
        </td>
        <td><?= htmlspecialchars($row['name']) ?></td> 
        <td><?= htmlspecialchars($row['categories']) ?></td>
        <?php if ($role === 'Brand Manager'): ?>
          <td>
          <a href="edit_product.php?id=<?= $row['id'] ?>" 
          class="btn btn-warning btn-sm mr-1"><i 
          class="fas fa-edit"></i></a> 
          <a href="delete_product.php?id=<?= $row['id'] ?>"
          onclick="return confirm('Are you sure?')"
           class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a>
           </td>
           <?php endif; ?>

           <td><span class="show-variation" data-id="<?= $row['id'] ?>">Show Variations</span></td>
           </tr>
           <tr class="variation-row" id="variation-row-<?= $row['id'] ?>">
           <td colspan="<?= $role === 'Brand Manager' ? 6 : 5 ?>">
           <?php
           $vid = (int)$row['id'];
           $variation_query = $conn->prepare("SELECT * FROM product_variations WHERE product_id = ?");
           $variation_query->bind_param("i", $vid); 
           $variation_query->execute(); 
           $variation_result = $variation_query->get_result();

           if ($variation_result && $variation_result->num_rows > 0) 
           {
             echo "<ul class='variation-list'>";
             while ($v = $variation_result->fetch_assoc()) {
              echo "<li><b>Flavor:</b> " . htmlspecialchars($v['flavor']) . " | 
              <b>Size:</b> " . htmlspecialchars($v['pack_size']) . " |
              <b>Price/Unit:</b> ₱" . number_format($v['price_unit'], 2) . "

              <b>Price/Case:</b> ₱" . number_format($v['price_case'], 2) . "</li>";
           } 
           echo "</ul>"; 
         } else {
           echo "<span style='color:gray;'>No variations found.</span>";
         } 
         ?> 
         </td>
         </tr>
         <?php endforeach; 
         ?> 
         </tbody> 
         </table>
       </div> 
       <?php else: ?>
         <p class="text-center text-muted">No products found.</p> 
         <?php endif; ?> 
         </div>
       </div> 
      </div> 

       <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> 
       <script> 
       $(document).ready(function(){
        $('.show-variation').on('click', function(){ 
          var id = $(this).data('id'); 
          var row = $('#variation-row-' + id); 
          if (row.is(':visible')) { 
          row.slideUp(200); 

          
          $(this).text('Show Variations');
          } else { 
           row.slideDown(200);
           $(this).text('Hide Variations');
           } 
           }); 
           }); 
           </script> 
           </body> 
           </html>
