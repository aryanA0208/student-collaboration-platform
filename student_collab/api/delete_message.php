<?php
include("../includes/session.php");
include("../includes/db.php");

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$message_id = (int)($_POST['message_id'] ?? 0);

if ($message_id <= 0) {
    http_response_code(400);
    exit();
}

// Ensure columns exist (older DB safe)
$c1 = $conn->query("SHOW COLUMNS FROM messages LIKE 'deleted_by_sender'");
$c2 = $conn->query("SHOW COLUMNS FROM messages LIKE 'deleted_by_receiver'");
if (!($c1 && $c1->num_rows > 0)) {
    $conn->query("ALTER TABLE messages ADD COLUMN deleted_by_sender TINYINT(1) NOT NULL DEFAULT 0 AFTER seen");
}
if (!($c2 && $c2->num_rows > 0)) {
    $conn->query("ALTER TABLE messages ADD COLUMN deleted_by_receiver TINYINT(1) NOT NULL DEFAULT 0 AFTER deleted_by_sender");
}

// Check ownership (sender or receiver)
$stmt = $conn->prepare("SELECT sender_id, receiver_id FROM messages WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $message_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;

if (!$row) {
    http_response_code(404);
    exit();
}

$sender_id = (int)$row['sender_id'];
$receiver_id = (int)$row['receiver_id'];

if ($user_id !== $sender_id && $user_id !== $receiver_id) {
    http_response_code(403);
    exit();
}

if ($user_id === $sender_id) {
    $up = $conn->prepare("UPDATE messages SET deleted_by_sender = 1 WHERE id = ?");
    $up->bind_param("i", $message_id);
    $up->execute();
} else {
    $up = $conn->prepare("UPDATE messages SET deleted_by_receiver = 1 WHERE id = ?");
    $up->bind_param("i", $message_id);
    $up->execute();
}

// Optional cleanup: if both deleted, hard delete
$conn->query("DELETE FROM messages WHERE id = $message_id AND deleted_by_sender = 1 AND deleted_by_receiver = 1");

echo "OK";

