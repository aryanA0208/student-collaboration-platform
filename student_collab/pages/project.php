<?php
include("../includes/session.php");
include("../includes/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$user_id = (int)$user_id;
$sid = urlencode(session_id());

function isOwner(mysqli $conn, int $project_id, int $user_id): bool {
    $stmt = $conn->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ? AND role = 'owner' LIMIT 1");
    $stmt->bind_param("ii", $project_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res && $res->num_rows > 0;
}

// ADD PROJECT (also add creator as owner member)
if (isset($_POST['add'])) {
    $title = trim($_POST['title'] ?? "");
    $desc = trim($_POST['description'] ?? "");

    if ($title !== "") {
        $stmt = $conn->prepare("INSERT INTO projects (title, description, created_by) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $title, $desc, $user_id);
        if ($stmt->execute()) {
            $project_id = (int)$conn->insert_id;
            $m = $conn->prepare("INSERT IGNORE INTO project_members (project_id, user_id, role) VALUES (?, ?, 'owner')");
            $m->bind_param("ii", $project_id, $user_id);
            $m->execute();

            // Optional file upload while creating project
            if (isset($_FILES['project_file']) && (int)($_FILES['project_file']['error'] ?? 4) === 0) {
                $file = $_FILES['project_file'];
                $filename = time() . "_" . basename((string)$file['name']);
                $server_path = "../uploads/" . $filename;
                $db_path = "uploads/" . $filename;

                if (!file_exists("../uploads")) {
                    mkdir("../uploads", 0777, true);
                }

                if (move_uploaded_file($file['tmp_name'], $server_path)) {
                    $fs = $conn->prepare("INSERT INTO files (project_id, user_id, file_name, file_path) VALUES (?, ?, ?, ?)");
                    $fs->bind_param("iiss", $project_id, $user_id, $filename, $db_path);
                    $fs->execute();
                }
            }
        }
    }
    header("Location: project.php");
    exit();
}

// REQUEST TO JOIN PROJECT
if (isset($_POST['request_join'])) {
    $project_id = (int)($_POST['project_id'] ?? 0);

    if ($project_id > 0) {
        $check = $conn->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1");
        $check->bind_param("ii", $project_id, $user_id);
        $check->execute();
        $res = $check->get_result();

        if (!$res || $res->num_rows === 0) {
            $req = $conn->prepare("INSERT IGNORE INTO project_requests (project_id, from_user_id, status) VALUES (?, ?, 'pending')");
            $req->bind_param("ii", $project_id, $user_id);
            $req->execute();
        }
    }

    header("Location: project.php");
    exit();
}

// OWNER: ACCEPT / REJECT JOIN REQUEST
if (isset($_POST['handle_request'])) {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $project_id = (int)($_POST['project_id'] ?? 0);
    $from_user_id = (int)($_POST['from_user_id'] ?? 0);
    $action = $_POST['action'] ?? "";

    if ($request_id > 0 && $project_id > 0 && $from_user_id > 0 && isOwner($conn, $project_id, $user_id)) {
        if ($action === "accept") {
            $up = $conn->prepare("UPDATE project_requests SET status='accepted' WHERE id = ? AND project_id = ?");
            $up->bind_param("ii", $request_id, $project_id);
            $up->execute();

            $add = $conn->prepare("INSERT IGNORE INTO project_members (project_id, user_id, role) VALUES (?, ?, 'member')");
            $add->bind_param("ii", $project_id, $from_user_id);
            $add->execute();
        } elseif ($action === "reject") {
            $up = $conn->prepare("UPDATE project_requests SET status='rejected' WHERE id = ? AND project_id = ?");
            $up->bind_param("ii", $request_id, $project_id);
            $up->execute();
        }
    }

    header("Location: project.php");
    exit();
}

// DELETE PROJECT (owner only)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0 && isOwner($conn, $id, $user_id)) {
        $stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: project.php");
    exit();
}

// UPDATE PROJECT (owner only)
if (isset($_POST['update'])) {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? "");
    $desc = trim($_POST['description'] ?? "");

    if ($id > 0 && $title !== "" && isOwner($conn, $id, $user_id)) {
        $stmt = $conn->prepare("UPDATE projects SET title = ?, description = ? WHERE id = ?");
        $stmt->bind_param("ssi", $title, $desc, $id);
        $stmt->execute();
    }
    header("Location: project.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Projects</title>

<style>

html, body {
    height: 100%;
}

body {

    margin: 0;
    font-family: 'Segoe UI';
    display: flex;
    min-height: 100vh;   /* FIX */
    background: linear-gradient(135deg, #1e1e2f, #2c2f48);
    color: white;
}

.sidebar {
    width: 230px;
    background: rgba(0,0,0,0.3);
    padding: 20px;
    min-height: 100vh;   /* FIX */
}

.sidebar a {
    display: block;
    color: #ddd;
    padding: 10px;
    margin: 10px 0;
    text-decoration: none;
    border-radius: 8px;
}

.sidebar a:hover,
.sidebar a.active {
    background: #6c63ff;
}

.main {
    flex: 1;
    padding: 25px;
    overflow-y: auto;   /* SCROLL ENABLE */
}

.form-box {
    background: rgba(255,255,255,0.08);
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
}

input, textarea {
    width: 100%;
    padding: 10px;
    margin: 10px 0;
    border-radius: 8px;
    border: none;
}

button {
    cursor: pointer;
    border: none;
}

.add-btn {
    background: #00c6ff;
    color: white;
    padding: 10px;
    border-radius: 8px;
}

.projects {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
}

.card {
    background: rgba(255,255,255,0.08);
    padding: 15px;
    border-radius: 12px;
}

.actions {
    margin-top: 10px;
}

/* EDIT BUTTON */
.edit-btn {
    background: orange;
    padding: 6px 10px;
    border-radius: 6px;
    margin-right: 8px;
}

/* DELETE ICON */
.delete-icon {
    color: red;
    font-size: 18px;
    text-decoration: none;
}

/* EDIT FORM */
.edit-form {
    margin-top: 10px;
}

.update-btn {
    background: #00c6ff;
    color: white;
    padding: 8px;
    border-radius: 6px;
}
</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>🚀 Collab</h2>

    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="project.php?sid=<?php echo $sid; ?>" class="active">📁 Projects</a>
    <a href="task.php?sid=<?php echo $sid; ?>">📋 Tasks</a>
    <a href="chat.php?sid=<?php echo $sid; ?>">💬 Chat</a>
    <a href="files.php?sid=<?php echo $sid; ?>">📂 Files</a>
    <a href="students.php?sid=<?php echo $sid; ?>">🧑‍🎓 Students</a>
    <a href="profile.php?sid=<?php echo $sid; ?>">👤 Profile</a>

    <a href="../logout.php?sid=<?php echo $sid; ?>">🚪 Logout</a>
</div>

<!-- MAIN -->
<div class="main">

<h2>📁 Projects</h2>

<!-- ADD PROJECT -->
<div class="form-box">
    <form method="POST" enctype="multipart/form-data">
        <input type="text" name="title" placeholder="Project Title" required>
        <textarea name="description" placeholder="Description"></textarea>
        <input type="file" name="project_file" style="background:rgba(255,255,255,0.08);color:white;">
        <button name="add" class="add-btn">Add Project</button>
    </form>
</div>

<!-- PROJECT LIST -->
<div class="projects">

<?php
$stmt = $conn->prepare("
    SELECT 
        p.*,
        u.name AS owner_name,
        pm.role AS my_role,
        pr.status AS my_request_status,
        COALESCE(ts.total_tasks, 0) AS total_tasks,
        COALESCE(ts.completed_tasks, 0) AS completed_tasks
    FROM projects p
    JOIN users u ON u.id = p.created_by
    LEFT JOIN project_members pm 
        ON pm.project_id = p.id AND pm.user_id = ?
    LEFT JOIN project_requests pr
        ON pr.project_id = p.id AND pr.from_user_id = ?
    LEFT JOIN (
        SELECT
            project_id,
            COUNT(*) AS total_tasks,
            SUM(CASE WHEN status IN ('done', 'completed') THEN 1 ELSE 0 END) AS completed_tasks
        FROM tasks
        GROUP BY project_id
    ) ts ON ts.project_id = p.id
    ORDER BY p.id DESC
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
?>

<div class="card">
    <h3><?php echo htmlspecialchars($row['title']); ?></h3>
    <p><?php echo nl2br(htmlspecialchars($row['description'] ?? "")); ?></p>
    <?php
    $totalTasks = (int)($row['total_tasks'] ?? 0);
    $completedTasks = (int)($row['completed_tasks'] ?? 0);
    if ($totalTasks > 0) {
        $taskState = ($completedTasks === $totalTasks) ? "Task is completed" : "Task is pending";
        echo "<p style='color:#9fe3ff;font-size:13px;margin-top:8px;'><b>Status:</b> " . htmlspecialchars($taskState) . " (" . $completedTasks . "/" . $totalTasks . ")</p>";
    }
    ?>

    <p style="color:#ccc;font-size:13px;margin-top:8px;">
        Owner: <b><?php echo htmlspecialchars($row['owner_name']); ?></b>
        <?php if (!empty($row['my_role'])) { ?>
            • You are: <b><?php echo htmlspecialchars($row['my_role']); ?></b>
        <?php } ?>
        <?php if (empty($row['my_role']) && !empty($row['my_request_status'])) { ?>
            • Request: <b><?php echo htmlspecialchars($row['my_request_status']); ?></b>
        <?php } ?>
    </p>

    <div class="actions">
        <?php if (!empty($row['my_role'])) { ?>
            <a href="task.php?sid=<?php echo $sid; ?>&project_id=<?php echo (int)$row['id']; ?>" style="color:#00c6ff;text-decoration:none;margin-right:10px;">Open Tasks</a>
            <a href="files.php?sid=<?php echo $sid; ?>&project_id=<?php echo (int)$row['id']; ?>" style="color:#00c6ff;text-decoration:none;">Open Files</a>
        <?php } ?>

        <?php if (($row['my_role'] ?? "") === "owner") { ?>
            <button onclick="toggleEdit(<?php echo (int)$row['id']; ?>)" class="edit-btn">✏️</button>
            <a href="?delete=<?php echo (int)$row['id']; ?>" 
               onclick="return confirm('Delete this project?')" 
               class="delete-icon">🗑</a>
        <?php } ?>
    </div>

    <?php if (empty($row['my_role']) && empty($row['my_request_status'])) { ?>
        <form method="POST" style="margin-top:12px;">
            <input type="hidden" name="project_id" value="<?php echo (int)$row['id']; ?>">
            <button name="request_join" class="add-btn" type="submit">Request to Join</button>
        </form>
    <?php } ?>

    <?php if (($row['my_role'] ?? "") === "owner") { ?>
        <div id="editForm<?php echo (int)$row['id']; ?>" class="edit-form" style="display:none;">
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                <input type="text" name="title" value="<?php echo htmlspecialchars($row['title']); ?>">
                <textarea name="description"><?php echo htmlspecialchars($row['description'] ?? ""); ?></textarea>
                <button name="update" class="update-btn">Update</button>
            </form>
        </div>

        <div style="margin-top:14px;">
            <div style="font-weight:700;margin-bottom:6px;">Join requests</div>
            <?php
            $reqStmt = $conn->prepare("
                SELECT pr.id, pr.from_user_id, u.name
                FROM project_requests pr
                JOIN users u ON u.id = pr.from_user_id
                WHERE pr.project_id = ? AND pr.status = 'pending'
                ORDER BY pr.id DESC
            ");
            $pid = (int)$row['id'];
            $reqStmt->bind_param("i", $pid);
            $reqStmt->execute();
            $reqs = $reqStmt->get_result();

            if ($reqs && $reqs->num_rows > 0) {
                while ($rq = $reqs->fetch_assoc()) {
                    ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.08);">
                        <div><?php echo htmlspecialchars($rq['name']); ?></div>
                        <form method="POST" style="display:flex;gap:8px;">
                            <input type="hidden" name="handle_request" value="1">
                            <input type="hidden" name="request_id" value="<?php echo (int)$rq['id']; ?>">
                            <input type="hidden" name="project_id" value="<?php echo (int)$row['id']; ?>">
                            <input type="hidden" name="from_user_id" value="<?php echo (int)$rq['from_user_id']; ?>">
                            <button name="action" value="accept" class="add-btn" type="submit" style="padding:6px 10px;">Accept</button>
                            <button name="action" value="reject" type="submit" style="background:#ff4d4d;padding:6px 10px;border-radius:8px;">Reject</button>
                        </form>
                    </div>
                    <?php
                }
            } else {
                echo "<div style='color:#ccc;font-size:13px;'>No pending requests.</div>";
            }
            ?>
        </div>
    <?php } ?>

</div>

<?php } ?>

</div>

</div>

<!-- JS -->
<script>
function toggleEdit(id) {
    let form = document.getElementById("editForm" + id);

    if (form.style.display === "none") {
        form.style.display = "block";
    } else {
        form.style.display = "none";
    }
}
</script>

</body>
</html>