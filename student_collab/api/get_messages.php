<?php
include("../includes/session.php");
include("../includes/db.php");

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

$user_id = (int)$_SESSION['user_id'];

if (!isset($_GET['user_id'])) {
    echo "No user selected";
    exit();
}

$other_user = intval($_GET['user_id']);

// Ensure delete columns exist (older DB safe)
$c1 = $conn->query("SHOW COLUMNS FROM messages LIKE 'deleted_by_sender'");
$c2 = $conn->query("SHOW COLUMNS FROM messages LIKE 'deleted_by_receiver'");
if (!($c1 && $c1->num_rows > 0)) {
    $conn->query("ALTER TABLE messages ADD COLUMN deleted_by_sender TINYINT(1) NOT NULL DEFAULT 0 AFTER seen");
}
if (!($c2 && $c2->num_rows > 0)) {
    $conn->query("ALTER TABLE messages ADD COLUMN deleted_by_receiver TINYINT(1) NOT NULL DEFAULT 0 AFTER deleted_by_sender");
}

/* ✅ STEP 3: MARK RECEIVED MESSAGES AS READ */
$conn->query("
UPDATE messages 
SET seen='1'
WHERE sender_id='$other_user'
AND receiver_id='$user_id'
AND seen='0'
");

/* FETCH CHAT */
$sql = "SELECT * FROM messages 
        WHERE (
            sender_id='$user_id' AND receiver_id='$other_user' AND deleted_by_sender='0'
        ) OR (
            sender_id='$other_user' AND receiver_id='$user_id' AND deleted_by_receiver='0'
        )
        ORDER BY id ASC";

$result = $conn->query($sql);

/* SHOW MESSAGES */
while ($row = $result->fetch_assoc()) {

    if ($row['sender_id'] == $user_id) {
        echo "<div class='msg me' data-mid='".(int)$row['id']."'>";
        echo "<span>" . htmlspecialchars($row['message']) . "</span>";
        echo "<button class='del-btn' data-mid='".(int)$row['id']."' title='Delete' style='margin-left:10px;background:transparent;border:none;color:#0b1220;font-weight:800;cursor:pointer;'>×</button>";
        echo "</div>";
    } else {
        echo "<div class='msg other' data-mid='".(int)$row['id']."'>";
        echo "<span>" . htmlspecialchars($row['message']) . "</span>";
        echo "<button class='del-btn' data-mid='".(int)$row['id']."' title='Delete' style='margin-left:10px;background:transparent;border:none;color:#fff;font-weight:800;cursor:pointer;'>×</button>";
        echo "</div>";
    }

}
?>