<?php
ini_set('session.gc_maxlifetime', 86400); // 24 hours
session_set_cookie_params(86400);
session_name('admin_session');

session_start();
include 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch current user data
$query = "SELECT * FROM users WHERE id='$user_id'";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : $user['password'];

    $targetDir = "uploads/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    $profile_image = $user['profile_image']; // keep old image by default

    if (!empty($_FILES['profile_image']['name'])) {
        $fileName = time() . "_" . basename($_FILES["profile_image"]["name"]);
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $targetFile)) {
            $profile_image = $targetFile;
        }
    }

    $updateQuery = "
        UPDATE users 
        SET fullname='$fullname', username='$username', email='$email', role='$role',
            password='$password', profile_image='$profile_image'
        WHERE id='$user_id'
    ";

    if (mysqli_query($conn, $updateQuery)) {
        echo "<script>alert('Profile updated successfully!'); window.location='index.php';</script>";
        exit();
    } else {
        echo "<script>alert('Error updating profile.');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Profile</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
body {
  font-family: 'Segoe UI', sans-serif;
  background-color: #f8f9fc;
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
}
.card {
  background: #fff;
  padding: 30px;
  border-radius: 15px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.1);
  width: 400px;
}
.card h2 {
  text-align: center;
  margin-bottom: 20px;
  color: #4e73df;
}
.card img {
  width: 130px;
  height: 130px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #ddd;
  display: block;
  margin: 0 auto 15px;
}
.form-group {
  margin-bottom: 15px;
}
label {
  display: block;
  font-weight: 600;
  color: #333;
  margin-bottom: 5px;
}
input[type="text"],
input[type="email"],
input[type="password"],
select {
  width: 100%;
  padding: 8px;
  border: 1px solid #ccc;
  border-radius: 6px;
  font-size: 14px;
}
input[type="file"] {
  margin-top: 8px;
}
button {
  margin-top: 15px;
  background: #4e73df;
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 8px;
  cursor: pointer;
  width: 100%;
  font-weight: bold;
}
button:hover {
  background: #2e59d9;
}
a {
  display: inline-block;
  text-decoration: none;
  color: #4e73df;
  text-align: center;
  width: 100%;
  margin-top: 10px;
}
</style>
</head>
<body>

<div class="card">
  <h2>Edit Profile</h2>
  <?php if (!empty($user['profile_image']) && file_exists($user['profile_image'])): ?>
    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile Picture">
  <?php else: ?>
    <img src="https://via.placeholder.com/130?text=No+Photo" alt="Default Image">
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
    </div>

    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
    </div>

    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
    </div>


    <div class="form-group">
      <label>Password <small>(Leave blank to keep current)</small></label>
      <input type="password" name="password" placeholder="Enter new password">
    </div>

    <div class="form-group">
      <label>Profile Image</label>
      <input type="file" name="profile_image" accept="image/*">
    </div>

    <button type="submit">Save Changes</button>
  </form>

  <a href="index.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

</body>
</html>
