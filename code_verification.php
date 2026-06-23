<?php
session_start();
date_default_timezone_set('Asia/Manila'); // fix timezone
include 'db_connection.php';

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION['reset_email'];

if (isset($_POST['submit'])) {
    $code = $_POST['verification_code'];

    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE email=? AND token=? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("ss", $email, $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    // DEBUG
    // var_dump($row); exit;

    if ($row && strtotime($row['expires_at']) >= time()) {
        $_SESSION['verified'] = true;
        header("Location: reset_password.php");
        exit();
    } else {
        $error = "❌ Invalid or expired code.";
    }
}
?>
<?php if (isset($error)) { echo "<p>$error</p>"; } ?>
<style>
   body {
    margin: 0; 
    padding: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    background: #fff;
    font-family: Arial, sans-serif;
}
.container {
    width: 400px;
    padding: 30px;
    border-radius: 20px;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
    background: linear-gradient(to right, #ff3c3c, #ff7b00);
    color: white;
    text-align: center;
}
.container h2 {
    margin-bottom: 20px;
    font-size: 22px;
}
form {
    display: flex;
    flex-direction: column;
}
input, button {
    margin-bottom: 12px;
    padding: 10px;
    font-size: 15px;
    border-radius: 8px;
    border: none;
    outline: none;
}
input {
    border: 1px solid #ccc;
    color: #333;
}
button {
    background-color: #a60000;
    color: white;
    font-weight: bold;
    cursor: pointer;
    transition: background-color 0.3s ease;
}
button:hover {
    background-color: #8b0000;
}
.error-message {
    color: yellow;
    font-size: 13px;
    margin-bottom: 10px;
}
.success-message {
    color: lightgreen;
    font-size: 13px;
    margin-bottom: 10px;
}

</style>
<div class="container">
    <h2>Code Verification</h2>
    <?php if (isset($error)) echo "<div class='message error'>$error</div>"; ?>
    <?php if (isset($success)) echo "<div class='message success'>$success</div>"; ?>

    <form method="POST">
        <input type="text" name="verification_code" placeholder="Enter code here" required>
        <button type="submit" name="submit">Confirm Code</button>
    </form>
</div>

