<?php
mysqli_report(MYSQLI_REPORT_OFF);

$db_user = "root";
$db_pass = "";
$db_name = "student_collab_platform";
$db_port = 3306;

// Try TCP first to avoid localhost socket/pipe handshake issues on Windows.
$hosts = ["127.0.0.1", "localhost"];
$conn = null;
$last_error = "Unknown DB error";

foreach ($hosts as $host) {
    $mysqli = mysqli_init();
    if ($mysqli === false) {
        $last_error = "Failed to initialize mysqli.";
        continue;
    }

    // Fail fast instead of hanging for 120s.
    mysqli_options($mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, 5);

    if (@mysqli_real_connect($mysqli, $host, $db_user, $db_pass, $db_name, $db_port)) {
        $conn = $mysqli;
        break;
    }

    $last_error = mysqli_connect_error();
    mysqli_close($mysqli);
}

if (!$conn) {
    die("Database connection failed. Please start MySQL in XAMPP and verify port 3306. Error: " . $last_error);
}

$conn->set_charset("utf8mb4");
?>