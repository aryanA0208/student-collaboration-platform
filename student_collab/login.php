<?php
include("includes/session.php");
include("includes/db.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? "");
    $password = $_POST['password'] ?? "";

    if ($email === "" || $password === "") {
        $error = "Email and password are required.";
    } else {
        $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                // Create a per-tab session id, so multiple accounts can stay logged in on same browser
                $sid = bin2hex(random_bytes(16));
                session_write_close();
                session_id($sid);
                session_start();

                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['name'] = $user['name'];

                header("Location: pages/dashboard.php?sid=" . urlencode($sid));
                exit();
            } else {
                $error = "Invalid Password!";
            }
        } else {
            $error = "User not found!";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>

    <style>
        body {
            margin: 0;
            font-family: Arial;
            background: linear-gradient(135deg, #667eea, #764ba2);
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

        .error {
            color: #ff4d4d;
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
    <h2>Login</h2>

    <?php if($error != "") echo "<p class='error'>$error</p>"; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Enter Email" required>
        <input type="password" name="password" placeholder="Enter Password" required>
        <button type="submit">Login</button>
    </form>

    <!-- 🔥 REGISTER LINK -->
    <a href="register.php">New user? Register Here</a>
</div>

</body>
</html>