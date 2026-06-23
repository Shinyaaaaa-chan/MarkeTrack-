<?php
session_start();
date_default_timezone_set('Asia/Manila');
include 'db_connection.php';

if (!isset($_SESSION['reset_email']) || !isset($_SESSION['verified'])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION['reset_email'];

if (isset($_POST['submit'])) {
    $password_raw = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $errors = [];

    // Backend validation (para secure kahit disabled ang JS)
    if (strlen($password_raw) < 8) $errors[] = "Password must be at least 8 characters.";
    if (!preg_match('/[A-Z]/', $password_raw)) $errors[] = "Password must contain at least one uppercase letter.";
    if (!preg_match('/[a-z]/', $password_raw)) $errors[] = "Password must contain at least one lowercase letter.";
    if (!preg_match('/[0-9]/', $password_raw)) $errors[] = "Password must contain at least one number.";
    if (!preg_match('/[\W_]/', $password_raw)) $errors[] = "Password must contain at least one special character.";
    if ($password_raw !== $confirm_password) $errors[] = "Passwords do not match.";

    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    } else {
        $password = password_hash($password_raw, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password=? WHERE email=?");
        $stmt->bind_param("ss", $password, $email);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM password_resets WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();

        session_unset();
        session_destroy();

        echo "<script>
        alert('✅ Password has been successfully changed. Please login.');
        window.location.href = 'login.php?reset=success';
    </script>";
    exit();
    

    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
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

.requirements {
    list-style: none;
    padding: 0;
    margin: 15px 0;
    text-align: left;
    font-size: 14px;
}

.requirements li {
    margin: 5px 0;
    padding: 5px 10px;
    border-radius: 5px;
    background: rgba(255,255,255,0.1);
    color: #ffcccc; /* default red-ish */
    transition: 0.3s ease;
}

/* kapag valid */
.requirements li.valid {
    color: #b6ffb6;  /* green text */
    background: rgba(0, 128, 0, 0.2); /* light green bg */
    font-weight: bold;
}

</style>

</head>
<body>
    <?php if (isset($error)) echo "<div class='message error'>$error</div>"; ?>
    <?php if (isset($success)) echo "<div class='message success'>$success</div>"; ?>

   

    <div class="container">
    <h2>Reset Password</h2>
    <?php if (isset($error)) echo "<div class='message error'>$error</div>"; ?>
    <?php if (isset($success)) echo "<div class='message success'>$success</div>"; ?>

    <form method="POST">
        <input type="password" name="password" id="password" placeholder="Enter new password" required>
        <input type="password" name="confirm_password" placeholder="Confirm new password" required>

        <ul class="requirements" id="passwordRequirements">
            <li id="length">At least 8 characters</li>
            <li id="uppercase">At least one uppercase letter</li>
            <li id="lowercase">At least one lowercase letter</li>
            <li id="number">At least one number</li>
            <li id="special">At least one special character</li>
        </ul>

        <button type="submit" name="submit" id="resetBtn" disabled>Reset Password</button>
    </form>
</div>

<script>
    const passwordInput = document.getElementById("password");
    const resetBtn = document.getElementById("resetBtn");
    const requirements = {
        length: document.getElementById("length"),
        uppercase: document.getElementById("uppercase"),
        lowercase: document.getElementById("lowercase"),
        number: document.getElementById("number"),
        special: document.getElementById("special")
    };

    passwordInput.addEventListener("input", function() {
        const value = passwordInput.value;
        let validCount = 0;

        if (value.length >= 8) { requirements.length.classList.add("valid"); validCount++; } else { requirements.length.classList.remove("valid"); }
        if (/[A-Z]/.test(value)) { requirements.uppercase.classList.add("valid"); validCount++; } else { requirements.uppercase.classList.remove("valid"); }
        if (/[a-z]/.test(value)) { requirements.lowercase.classList.add("valid"); validCount++; } else { requirements.lowercase.classList.remove("valid"); }
        if (/[0-9]/.test(value)) { requirements.number.classList.add("valid"); validCount++; } else { requirements.number.classList.remove("valid"); }
        if (/[\W_]/.test(value)) { requirements.special.classList.add("valid"); validCount++; } else { requirements.special.classList.remove("valid"); }

        resetBtn.disabled = (validCount < 5);
    });
</script>

</body>
</html>
