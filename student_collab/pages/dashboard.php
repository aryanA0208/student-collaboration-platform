<?php
include("../includes/session.php");
include("../includes/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$sid = urlencode(session_id());

// COUNTS (scoped to current user)
$pStmt = $conn->prepare("
SELECT COUNT(DISTINCT p.id) as total
FROM projects p
JOIN project_members pm ON pm.project_id = p.id
WHERE pm.user_id = ?
");
$pStmt->bind_param("i", $user_id);
$pStmt->execute();
$projects = (int)($pStmt->get_result()->fetch_assoc()['total'] ?? 0);

// Pending tasks assigned to me; accepts/deletes/completions instantly affect this count.
$tStmt = $conn->prepare("
SELECT COUNT(*) as total
FROM tasks t
JOIN project_members pm ON pm.project_id = t.project_id AND pm.user_id = ?
WHERE t.assigned_to = ?
  AND t.status IN ('todo', 'pending')
");
$tStmt->bind_param("ii", $user_id, $user_id);
$tStmt->execute();
$tasks = (int)($tStmt->get_result()->fetch_assoc()['total'] ?? 0);

$m = $conn->query("
SELECT COUNT(*) as total 
FROM messages 
WHERE receiver_id='$user_id'
AND seen='0'
");

$messages = $m->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Dashboard</title>

<style>


/* RESET */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* BODY */
body {
    font-family: 'Poppins', sans-serif;
    display: flex;
    min-height: 100vh;
    background: linear-gradient(135deg, #1e1e2f, #2c2f48);
    color: white;
}

/* SIDEBAR */
.sidebar {
    width: 240px;
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(15px);
    padding: 20px;
}

.sidebar h2 {
    margin-bottom: 20px;
}

.sidebar a {
    display: block;
    color: #bbb;
    padding: 12px;
    margin: 10px 0;
    text-decoration: none;
    border-radius: 10px;
    transition: 0.3s;
}

.sidebar a:hover,
.sidebar a.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

/* MAIN */
.main {
    flex: 1;
    padding: 30px;
    overflow-y: auto;
}

/* HEADER */
.header {
    background: rgba(255,255,255,0.08);
    padding: 20px;
    border-radius: 15px;
    backdrop-filter: blur(15px);
    margin-bottom: 20px;
}

/* CARDS */
.cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.card {
    background: rgba(255,255,255,0.08);
    padding: 25px;
    border-radius: 15px;
    backdrop-filter: blur(15px);
    text-align: center;
    transition: 0.3s;
    cursor: pointer;
}

.card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
}

.card h3 {
    color: #ccc;
}

.card p {
    font-size: 30px;
    font-weight: bold;
}

</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>Collab Projects</h2>

    <a href="dashboard.php" class="active">🏠 Dashboard</a>
    <a href="project.php?sid=<?php echo $sid; ?>">📁 Projects</a>
    <a href="task.php?sid=<?php echo $sid; ?>">📋 Tasks</a>
    <a href="chat.php?sid=<?php echo $sid; ?>">💬 Chat</a>
    <a href="files.php?sid=<?php echo $sid; ?>">📂 Files</a>
    <a href="students.php?sid=<?php echo $sid; ?>">🧑‍🎓 Students</a>
    <a href="profile.php?sid=<?php echo $sid; ?>">👤 Profile</a>

    <a href="../logout.php?sid=<?php echo $sid; ?>">🚪 Logout</a>
</div>
 
<!-- MAIN -->
<div class="main">

<div class="header">
    <h2>Welcome, <?php echo $_SESSION['name']; ?> </h2>
</div>

<div class="cards">

    <div class="card" onclick="goTo('project.php')">
    <h3>Projects</h3>
    <p><?php echo $projects; ?></p>
</div>

<div class="card" onclick="goTo('task.php')">
    <h3>Pending Tasks</h3>
    <p><?php echo $tasks; ?></p>
</div>

<div class="card" onclick="goTo('chat.php')">
    <h3>Messages</h3>
    <p><?php echo $messages; ?></p>
</div>

</div>

</div>

<script>
function goTo(page) {
    window.location.href = page;
}
</script>

</body>
</html>