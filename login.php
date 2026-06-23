<?php
include 'db_connection.php'; // Make sure db_connection.php is correct

if (isset($_POST['login'])) {
    session_name('admin_session');
    session_start();

    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    // Prepare query for security
    $query = "SELECT * FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        die("Database query failed: " . mysqli_error($conn));
    }

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        if (password_verify($password, $user['password'])) {
            // ✅ Login success → set sessions
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            header("Location: index.php");
            exit;
        } else {
            session_start();
            $_SESSION['error'] = "Invalid email or password.";
            header("Location: login.php");
            exit;
        }
    } else {
        session_start();
        $_SESSION['error'] = "Invalid email or password.";
        header("Location: login.php");
        exit;
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    * {
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
      margin: 0;
      padding: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      background: #f0f2f5;
    }

    .container {
      display: flex;
      width: 800px;
      height: 500px;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 0 20px rgba(0,0,0,0.1);
      background: white;
    }

    .left {
      width: 50%;
      background: linear-gradient(to right, #ff3c3c, #ff7b00);
      color: white;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 30px;
      border-top-left-radius: 20px;
      border-bottom-left-radius: 20px;
    }

    .left h2 {
      margin-bottom: 10px;
      font-size: 28px;
      text-align: center;
    }

    .left p {
      font-size: 16px;
      margin-bottom: 30px;
      text-align: center;
    }

    .left button {
      padding: 12px 30px;
      border: none;
      border-radius: 25px;
      background-color: white;
      color: #ff3c3c;
      font-weight: bold;
      cursor: pointer;
      transition: 0.3s ease;
    }

    .left button:hover {
      background-color: #f0f0f0;
    }

    .right {
      width: 50%;
      padding: 40px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .right h2 {
      margin-bottom: 20px;
      color: #333;
    }

    .right form {
      display: flex;
      flex-direction: column;
    }

    .right input {
      margin-bottom: 15px;
      padding: 12px;
      border: 1px solid #ccc;
      border-radius: 10px;
      font-size: 16px;
    }

    .right button {
      padding: 12px;
      background-color: #a60000;
      color: white;
      border: none;
      border-radius: 10px;
      font-size: 16px;
      cursor: pointer;
      transition: background 0.3s ease;
    }

    .right button:hover {
      background-color: #8b0000;
    }

    .error-message {
      color: red;
      margin-bottom: 10px;
      font-size: 14px;
    }

    .password-wrapper {
  position: relative;
  display: flex;
  align-items: stretch; /* Para pantay height */
  width: 100%;
}

.password-wrapper input {
  flex: 1;
  padding-right: 40px; /* space for the icon */
  margin-bottom: 15px;
  border: 1px solid #ccc;
  border-radius: 10px;
  font-size: 16px;
  height: 45px; /* same height as email input */
  box-sizing: border-box;
}

.password-wrapper i.toggle-password {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-90%); /* para pantay vertically */
  color: #999;
  cursor: pointer;
  font-size: 16px;
}


/* For mobile phones (max-width: 567px) */
@media (max-width: 567px) {
  .container {
    width: 100%;
    margin: 10px;
  }

  .right h2 {
    font-size: 20px;
  }

  .left h2 {
    font-size: 22px;
  }

  
  .password-wrapper input {
    font-size: 14px;
    height: 40px;
    width: 80px;
    
  }

  .right button {
    font-size: 14px;
    padding: 15px;
  }
}

  </style>
</head>
<body>
  <div class="container">
    <div class="left">
      <h2>Welcome Back to Marketrack!</h2>
      <p>Enter your personal details to use all of the features</p>
    </div>
    <div class="right">
      <h2>Login to Your Account</h2>

      <?php if (isset($_SESSION['error'])): ?>
        <p class="error-message"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
      <?php endif; ?>

      <form action="login.php" method="POST">
        <input type="text" name="email" placeholder="Email Address" required />
        <div class="password-wrapper">
        <input type="password" name="password" id="registerPassword" placeholder="Password" required />
        <i class="fas fa-eye-slash toggle-password" data-target="registerPassword"></i>
      </div>

        <button type="submit" name="login">LOG IN</button>
      </form>
      <p style="text-align:center;"><a href="forgot_password.php">Forgot Password?</a></p>
    </div>
  </div>
  <script>
    
  // Toggle password visibility
  document.querySelectorAll('.toggle-password').forEach(icon => {
    icon.addEventListener('click', function () {
      const input = document.getElementById(this.dataset.target);
      input.type = input.type === 'password' ? 'text' : 'password';
      this.classList.toggle('fa-eye');
      this.classList.toggle('fa-eye-slash');
    });
  });
  </script>
</body>
</html>
