<?php

session_name('admin_session');



session_start();

include 'db_connection.php';

include 'sidebar.php';



// Restrict access to Brand Manager only

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Brand Manager') {

    header("Location: unauthorized.php");

    exit;

}



$success = "";

$error = "";



if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $fullname = trim($_POST['fullname']);

    $email = trim($_POST['email']);

    $username = trim($_POST['username']);

    $password = $_POST['password'];

    $role = $_POST['role'];



    // Check if username already exists

    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");

    $check->bind_param("s", $username);

    $check->execute();

    $result = $check->get_result();



    if ($result->num_rows > 0) {

        $error = "Username already exists. Please choose another one.";

    } else {

        // Hash the password

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);



        $stmt = $conn->prepare("INSERT INTO users (fullname, email, username, password, role) VALUES (?, ?, ?, ?, ?)");

        $stmt->bind_param("sssss", $fullname, $email, $username, $hashed_password, $role);



        if ($stmt->execute()) {

            $success = "Account successfully created!";

        } else {

            $error = "Error occurred while creating account: " . $conn->error;

        }

    }

}

?>



<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Add New Account - MarkeTrack</title>

    <link href="css/sb-admin-2.min.css" rel="stylesheet">

    <link href="css/style.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">



    <style>
        .container-fluid{
margin-top: 0px;;
        }

        body {

            background-color: #f8f9fc;

        }
 
        .sidebar {

width: 200px; /* fixed width */

flex-shrink: 0; /* wag lumiit */

        }

        .form-container {

            max-width: 2000px;

            margin: 0px auto;

            background-color: #fff;

            padding: 40px;

            border-radius: 10px;

            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);

            max-height: 600px;

           
        }



        .form-container h3 {

            font-size: 1.5rem;
padding-left: 300px;
            font-weight: bold;

            color: #E74A3B;

            margin-top: 0px;

        }



        .form-group label {

            font-size: 1rem;

            font-weight: 600;

            margin-bottom: 10px;

            color: #333;

        }



        .form-control,

        .form-select {

            height: 45px;

            font-size: 1rem;

            border-radius: 5px;

            border: 1px solid #ced4da;

        }



        .password-wrapper {

            position: relative;

        }



        .password-wrapper i {

            position: absolute;

            right: 15px;

            top: 50%;

            transform: translateY(-50%);

            color: #888;

            cursor: pointer;

        }



        .submit-btn {

            background-color: #E74A3B;

            color: white;

            padding: 10px 30px;

            font-size: 1rem;

            border: none;

            border-radius: 5px;

            transition: background-color 0.3s ease;

        }



        .submit-btn:hover {

            background-color: #c0392b;

        }



        .text-center-button {

            display: flex;

            justify-content: center;

        }



        @media (max-width: 768px) {

            .form-container {

                margin: 30px 15px;

                padding: 30px 20px;

            }



            .submit-btn {

                width: 100%;

            }

        }

    </style>

</head>

<body>



<div class="container-fluid">

    <div class="form-container">

        <h3 class="text-left">Add New Account</h3>



        <?php if ($success): ?>

            <div class="alert alert-success"><?= $success ?></div>

        <?php elseif ($error): ?>

            <div class="alert alert-danger"><?= $error ?></div>

        <?php endif; ?>



        <form action="" method="POST">

            <div class="form-group mb-3">

                <label for="fullname">Full Name</label>

                <input type="text" name="fullname" id="fullname" class="form-control" required>

            </div>



            <div class="form-group mb-3">

                <label for="email">Email Address</label>

                <input type="text" name="email" id="email" class="form-control" required>

            </div>



            <div class="form-group mb-3">

                <label for="username">Username</label>

                <input type="text" name="username" id="username" class="form-control" required>

            </div>



            <div class="form-group mb-3">

                <label for="password">Password</label>

                <div class="password-wrapper">

                    <input type="password" name="password" id="password" class="form-control" required>

                    <i class="fa-solid fa-eye" id="togglePassword"></i>

                </div>

            </div>



            <div class="form-group mb-4">

                <label for="role">Role</label>

                <select name="role" id="role" class="form-select" required>

                    <option value="Brand Manager">Brand Manager</option>

                    <option value="Assistant Brand Manager">Assistant Brand Manager</option>

                    <option value="Merchandising Marketing Team">Merchandising Marketing Team</option>

                    <option value="Trade and Marketing Team">Trade and Marketing Team</option>

                    <option value="Logistics">Logistics</option>

                </select>

            </div>



            <div class="text-center-button">

                <button type="submit" class="submit-btn">Create Account</button>

            </div>

        </form>

    </div>

</div>



<!-- Scripts -->

<script src="vendor/jquery/jquery.min.js"></script>

<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<script src="vendor/jquery-easing/jquery.easing.min.js"></script>

<script src="js/sb-admin-2.min.js"></script>



<script>

    // Toggle password visibility

    const togglePassword = document.querySelector('#togglePassword');

    const password = document.querySelector('#password');



    togglePassword.addEventListener('click', function () {

        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';

        password.setAttribute('type', type);

        this.classList.toggle('fa-eye-slash');

    });

</script>



</body>

</html>