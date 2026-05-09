<?php
session_start();
include("includes/db.php");

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? "");
    $email = trim($_POST['email'] ?? "");
    $password = $_POST['password'] ?? "";

    if ($name === "" || $email === "" || $password === "") {
        $message = "All fields are required.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $existing = $check->get_result();

        if ($existing && $existing->num_rows > 0) {
            $message = "Email already exists!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $email, $hashed_password);

            if ($stmt->execute()) {
                header("Location: login.php?success=1");
                exit();
            } else {
                $message = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>

    <style>
        body {
            margin: 0;
            font-family: Arial;
            background: linear-gradient(135deg, #43cea2, #185a9d);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .box {
            background: rgba(255,255,255,0.1);
            padding: 40px;
            border-radius: 15px;
            backdrop-filter: blur(12px);
            width: 350px;
            color: white;
        }

        input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border-radius: 8px;
            border: none;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #00c6ff;
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
        }

        .msg {
            text-align: center;
        }

        a {
            color: white;
            display: block;
            text-align: center;
            margin-top: 10px;
            text-decoration: none;
        }
    </style>
</head>

<body>

<div class="box">
    <h2>Register</h2>

    <?php if($message != "") echo "<p class='msg'>$message</p>"; ?>

    <form method="POST">
        <input type="text" name="name" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Enter Email" required>
        <input type="password" name="password" placeholder="Enter Password" required>
        <button type="submit">Register</button>
    </form>

    <a href="login.php">Already have an account? Login</a>
</div>

</body>
</html>