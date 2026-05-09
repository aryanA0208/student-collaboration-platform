<?php
include("../includes/session.php");
include("../includes/db.php");

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

$user_id = (int)$_SESSION['user_id'];

if (!isset($_POST['message']) || !isset($_POST['receiver_id'])) {
    exit();
}

$message = trim($_POST['message']);

if ($message == "") {
    exit();
}

$receiver_id = (int)($_POST['receiver_id'] ?? 0);
if ($receiver_id <= 0) {
    exit();
}

$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $user_id, $receiver_id, $message);
$stmt->execute();
?>