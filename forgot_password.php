<?php
session_start();
date_default_timezone_set('Asia/Manila'); // fix timezone

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include 'db_connection.php';

// --- INA-ASSUME KO NA MAY SUCCESS MESSAGE KA RIN ---
// $success = "Test success message"; 
// $error = "Test error message";

if (isset($_POST['submit'])) {
    $email = $_POST['email'];

    // Check if email exists
    $stmt = $conn->prepare("SELECT * FROM customers WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $code = rand(100000, 999999);
        $expires_at = date("Y-m-d H:i:s", strtotime("+1 hour"));
        $created_at = date("Y-m-d H:i:s");

        // Save reset code
        $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $email, $code, $expires_at, $created_at);
        $stmt->execute();

        // Send email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'viaumali24@gmail.com'; 
            $mail->Password = 'axms xrwe ssib ojmb';  
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('viaumali24@gmail.com', 'MarkeTrack Support');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Your Password Reset Code';
            $mail->Body = "
                <p>Hello,</p>
                <p>You requested a password reset. Use this code to reset your password:</p>
                <h2>$code</h2>
                <p>This code will expire in 1 hour.</p>
            ";

            $mail->send();

            $_SESSION['reset_email'] = $email;
            header("Location: code_verification.php");
            exit();
        } catch (Exception $e) {
            $error = "❌ Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    } else {
        $error = "❌ Email not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
    /* * ================================
     * PINAGANDANG RESPONSIVE DESIGN (CLEAN BG)
     * ================================
     */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Poppins', sans-serif;
        /* --- TINANGGAL ANG GRADIENT --- */
        /* Pinalitan ng simple, light gray background */
        background-color: #f4f7f6; 
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        padding: 20px;
    }

    .container {
        width: 100%;
        max-width: 480px; 
        padding: 40px 30px; 
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08); /* Mas subtle na shadow */
        background: #ffffff; 
        color: #333; 
        text-align: center;
    }

    .lock-icon {
        font-size: 40px;
        color: #dc3545; /* Red icon */
        margin-bottom: 20px;
    }

    .container h2 {
        margin-bottom: 15px;
        font-size: 26px; 
        color: #333;
        font-weight: 600;
    }
    
    .container p {
        font-size: 15px;
        color: #666;
        margin-bottom: 25px;
    }

    form {
        display: flex;
        flex-direction: column;
    }

    input[type="email"] {
        margin-bottom: 15px;
        padding: 14px;
        font-size: 16px;
        border-radius: 8px;
        border: 1px solid #ccc;
        outline: none;
        font-family: 'Poppins', sans-serif;
        width: 100%;
        transition: border-color 0.3s, box-shadow 0.3s;
    }

    input[type="email"]:focus {
        border-color: #ff3c3c;
        box-shadow: 0 0 5px rgba(255, 60, 60, 0.25);
    }

    button[type="submit"] {
        padding: 14px;
        font-size: 16px;
        border-radius: 8px;
        border: none;
        outline: none;
        background: #dc3545; /* Solid red button */
        color: white;
        font-weight: 600;
        font-family: 'Poppins', sans-serif;
        cursor: pointer;
        transition: background-color 0.3s ease, transform 0.2s ease;
    }

    button[type="submit"]:hover {
        background-color: #c82333; /* Darker red on hover */
        transform: translateY(-2px);
    }

    /* --- STYLING PARA SA MESSAGES --- */
    .error-message, .success-message {
        font-size: 14px;
        margin-bottom: 15px;
        padding: 12px;
        border-radius: 8px;
        text-align: left;
    }

    .error-message {
        background-color: #ffebeB; /* Light red background */
        color: #c00; /* Dark red text */
        border: 1px solid #f5c6cb;
    }

    .success-message {
        background-color: #e6f7ec; /* Light green background */
        color: #006400; /* Dark green text */
        border: 1px solid #c3e6cb;
    }

</style>
</head>
<body>

<div class="container">
    <div class="lock-icon">
        <i class="fas fa-lock"></i> </div>
    <h2>Forgot Password</h2>
    <p>Enter your email and we'll send you a code to reset your password.</p>

    <?php if (isset($error)) echo "<div class='error-message'>$error</div>"; ?>
    <?php if (isset($success)) echo "<div class='success-message'>$success</div>"; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Enter your email" required>
        <button type="submit" name="submit">Send Verification Code</button>
    </form>
</div>

</body>
</html>