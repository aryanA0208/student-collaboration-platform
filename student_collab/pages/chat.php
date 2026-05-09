<?php
include("../includes/session.php");
include("../includes/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$sid = urlencode(session_id());
?>

<!DOCTYPE html>
<html>
<head>
<title>Chat</title>

<style>

/* GLOBAL */
body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    display: flex;
    height: 100vh;
    background: linear-gradient(135deg, #141e30, #243b55);
    color: white;
}

/* SIDEBAR */
.sidebar {
    width: 220px;
    background: rgba(0,0,0,0.4);   /* darker */
    backdrop-filter: blur(15px);
    padding: 20px;
    border-right: 1px solid rgba(255,255,255,0.1);
}

.sidebar a {
    display: block;
    color: #bbb;
    padding: 10px;
    margin: 10px 0;
    text-decoration: none;
    border-radius: 8px;
}

.sidebar a:hover,
.sidebar a.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
}

/* USER LIST */
.users {
    width: 260px;
    background: rgba(255,255,255,0.06);  /* lighter */
    backdrop-filter: blur(15px);
    padding: 15px;
    border-right: 1px solid rgba(255,255,255,0.08);
}

/* USER ITEM */
.user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    margin-bottom: 8px;
    border-radius: 10px;
    cursor: pointer;
    transition: 0.3s;
}

.user:hover {
    background: rgba(255,255,255,0.08);
}

/* SELECTED USER */
.user.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    box-shadow: 0 0 15px rgba(102,126,234,0.4);
}

/* PROFILE PIC */
.user img {
    width: 35px;
    height: 35px;
    border-radius: 50%;
}

/* CHAT AREA */
.chat-area {
    flex: 1;
    display: flex;
    flex-direction: column;
}

/* MESSAGE AREA */
.messages {
    flex: 1;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    overflow-y: auto;
}

/* COMMON MESSAGE */
.msg {
    max-width: 60%;
    padding: 10px 15px;
    border-radius: 15px;
    margin: 5px 0;
    word-wrap: break-word;
    display: inline-flex;
    align-items: center;
}

/* MY MESSAGE (RIGHT SIDE) */
.me {
    background: linear-gradient(135deg, #00f2fe, #4facfe);
    align-self: flex-end;   /* RIGHT */
    border-bottom-right-radius: 5px;
    margin-left: auto;      /* force right */
}

/* OTHER MESSAGE (LEFT SIDE) */
.other {
    background: rgba(255,255,255,0.1);
    align-self: flex-start; /* LEFT */
    border-bottom-left-radius: 5px;
    margin-right: auto;     /* force left */
}

/* INPUT */
.input-area {
    display: flex;
    padding: 10px;
    background: rgba(255,255,255,0.05);
}

.input-area input {
    flex: 1;
    padding: 10px;
    border-radius: 8px;
    border: none;
}

.input-area button {
    padding: 10px;
    background: #00c6ff;
    border: none;
    color: white;
    margin-left: 10px;
    border-radius: 8px;
}

</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>🚀 Collab</h2>

    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="project.php?sid=<?php echo $sid; ?>" >📁 Projects</a>
    <a href="task.php?sid=<?php echo $sid; ?>">📋 Tasks</a>
    <a href="chat.php?sid=<?php echo $sid; ?>" class="active">💬 Chat</a>
    <a href="files.php?sid=<?php echo $sid; ?>">📂 Files</a>
    <a href="students.php?sid=<?php echo $sid; ?>">🧑‍🎓 Students</a>
    <a href="profile.php?sid=<?php echo $sid; ?>">👤 Profile</a>

    <a href="../logout.php?sid=<?php echo $sid; ?>">🚪 Logout</a>
</div>

<!-- USER LIST -->
<div class="users">
<h3>Users</h3>

<?php
$sql = "SELECT id, name, profile_pic FROM users WHERE id != $user_id";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $img = $row['profile_pic'] ?: 'default.svg';

    echo "<div class='user' id='user".$row['id']."' onclick='selectUser(".$row['id'].")'>";
    echo "<img src='../uploads/profile/$img' alt='Profile'>";
    echo "<span>".$row['name']."</span>";
    echo "</div>";
}
?>
</div>

<!-- CHAT -->
<div class="chat-area">

<div class="messages" id="messages"></div>

<div class="input-area">
    <input type="text" id="message" placeholder="Type message...">
    <button onclick="sendMessage()">Send</button>
</div>

</div>

<script>
let selectedUser = null;

window.onload = function() {
    const params = new URLSearchParams(window.location.search);
    const sid = params.get("sid") || "";
    const preselect = parseInt(params.get("user_id") || "", 10);
    if (!Number.isNaN(preselect)) {
        const el = document.getElementById("user" + preselect);
        if (el) {
            el.click();
            return;
        }
    }

    let firstUser = document.querySelector('.user');
    if (firstUser) firstUser.click();
}

function selectUser(id) {
    selectedUser = id;

    // remove old active
    document.querySelectorAll('.user').forEach(user => {
        user.classList.remove('active');
    });

    // add active on clicked user
    document.getElementById("user" + id).classList.add("active");

    loadMessages();
}

function loadMessages() {
    if (!selectedUser) return;

    const params = new URLSearchParams(window.location.search);
    const sid = params.get("sid") || "";
    fetch("../api/get_messages.php?sid=" + encodeURIComponent(sid) + "&user_id=" + selectedUser)
    .then(res => res.text())
    .then(data => {
        document.getElementById("messages").innerHTML = data;
        scrollBottom();
    });
}

function sendMessage() {
    if (!selectedUser) return;
    let msg = document.getElementById("message").value.trim();
    if (!msg) return;
    const params = new URLSearchParams(window.location.search);
    const sid = params.get("sid") || "";

    fetch("../api/send_message.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "sid=" + encodeURIComponent(sid) + "&message=" + encodeURIComponent(msg) + "&receiver_id=" + encodeURIComponent(String(selectedUser))
    }).then(() => {
        document.getElementById("message").value = "";
        loadMessages();
    });
}


function scrollBottom() {
    let box = document.getElementById("messages");
    box.scrollTop = box.scrollHeight;
}

setInterval(loadMessages, 1000);
scrollBottom();

// Send on Enter
document.getElementById("message").addEventListener("keydown", function(e) {
    if (e.key === "Enter") {
        e.preventDefault();
        sendMessage();
    }
});

// Delete message (for me)
document.getElementById("messages").addEventListener("click", function(e) {
    const btn = e.target.closest(".del-btn");
    if (!btn) return;
    const mid = btn.getAttribute("data-mid");
    if (!mid) return;
    const params = new URLSearchParams(window.location.search);
    const sid = params.get("sid") || "";

    fetch("../api/delete_message.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "sid=" + encodeURIComponent(sid) + "&message_id=" + encodeURIComponent(mid)
    }).then(() => loadMessages());
});
</script>

</body>
</html>