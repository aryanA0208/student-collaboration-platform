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

$active_project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

// Auto-fix older DBs: add taken_by / taken_at if missing (prevents fatal errors)
$has_taken_cols = false;
$c1 = $conn->query("SHOW COLUMNS FROM tasks LIKE 'taken_by'");
$c2 = $conn->query("SHOW COLUMNS FROM tasks LIKE 'taken_at'");
$has_taken_cols = ($c1 && $c1->num_rows > 0) && ($c2 && $c2->num_rows > 0);

if (!$has_taken_cols) {
    // add columns if missing
    $conn->query("ALTER TABLE tasks ADD COLUMN taken_by INT UNSIGNED NULL DEFAULT NULL AFTER status");
    $conn->query("ALTER TABLE tasks ADD COLUMN taken_at TIMESTAMP NULL DEFAULT NULL AFTER taken_by");
    $has_taken_cols = true;
}

// TAKE / ACCEPT TASK (only assignee)
if (isset($_POST['take_task'])) {
    $task_id = (int)($_POST['task_id'] ?? 0);
    $project_id = (int)($_POST['project_id'] ?? 0);

    if ($task_id > 0 && $project_id > 0) {
        // must be a member of project + must be assigned_to
        $stmt = $conn->prepare("
            SELECT t.id
            FROM tasks t
            JOIN project_members pm ON pm.project_id = t.project_id AND pm.user_id = ?
            WHERE t.id = ? AND t.project_id = ? AND t.assigned_to = ?
            LIMIT 1
        ");
        $stmt->bind_param("iiii", $user_id, $task_id, $project_id, $user_id);
        $stmt->execute();
        $ok = $stmt->get_result();

        if ($ok && $ok->num_rows > 0) {
            // Mark as taken (idempotent)
            if ($has_taken_cols) {
                $up = $conn->prepare("
                    UPDATE tasks
                    SET taken_by = ?, taken_at = IFNULL(taken_at, NOW()),
                        status = IF(status='todo', 'in-progress', status)
                    WHERE id = ? AND project_id = ? AND assigned_to = ?
                ");
                $up->bind_param("iiii", $user_id, $task_id, $project_id, $user_id);
                $up->execute();
            }
        }
    }

    header("Location: task.php?sid=" . urlencode(session_id()) . "&project_id=" . $project_id);
    exit();
}

// MARK TASK DONE + NOTIFY OWNER (only user who accepted the task)
if (isset($_POST['mark_done'])) {
    $task_id = (int)($_POST['task_id'] ?? 0);
    $project_id = (int)($_POST['project_id'] ?? 0);

    if ($task_id > 0 && $project_id > 0) {
        $stmt = $conn->prepare("
            SELECT t.id, t.title, t.status, p.created_by AS owner_id
            FROM tasks t
            JOIN projects p ON p.id = t.project_id
            JOIN project_members pm ON pm.project_id = t.project_id AND pm.user_id = ?
            WHERE t.id = ? AND t.project_id = ? AND t.taken_by = ?
            LIMIT 1
        ");
        $stmt->bind_param("iiii", $user_id, $task_id, $project_id, $user_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows > 0) {
            $task = $res->fetch_assoc();
            $status = strtolower((string)($task['status'] ?? ''));

            if ($status !== 'done' && $status !== 'completed') {
                $up = $conn->prepare("UPDATE tasks SET status = 'done' WHERE id = ? AND project_id = ? AND taken_by = ?");
                $up->bind_param("iii", $task_id, $project_id, $user_id);
                $up->execute();
            }

            $owner_id = (int)($task['owner_id'] ?? 0);
            if ($owner_id > 0 && $owner_id !== $user_id) {
                $note = "Task completed: " . ($task['title'] ?? '') . " (Project #" . $project_id . ")";
                $msg = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
                $msg->bind_param("iis", $user_id, $owner_id, $note);
                $msg->execute();
            }
        }
    }

    header("Location: task.php?sid=" . urlencode(session_id()) . "&project_id=" . $project_id);
    exit();
}

// DELETE TASK (creator or project owner)
if (isset($_POST['delete_task'])) {
    $task_id = (int)($_POST['task_id'] ?? 0);
    $project_id = (int)($_POST['project_id'] ?? 0);

    if ($task_id > 0 && $project_id > 0) {
        $del = $conn->prepare("
            DELETE t
            FROM tasks t
            JOIN projects p ON p.id = t.project_id
            JOIN project_members pm ON pm.project_id = t.project_id AND pm.user_id = ?
            WHERE t.id = ? AND t.project_id = ?
              AND (t.created_by = ? OR p.created_by = ?)
        ");
        $del->bind_param("iiiii", $user_id, $task_id, $project_id, $user_id, $user_id);
        $del->execute();
    }

    header("Location: task.php?sid=" . urlencode(session_id()) . "&project_id=" . $project_id);
    exit();
}

// ADD TASK
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $project_id = (int)($_POST['project_id'] ?? 0);
    $title = trim($_POST['title'] ?? "");
    $desc = trim($_POST['description'] ?? "");
    $assigned_to = (int)($_POST['assigned_to'] ?? 0);
    $status = $_POST['status'] ?? "todo";

    if ($project_id > 0 && $title !== "") {
        // Only allow creating tasks in projects you're a member of
        $chk = $conn->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1");
        $chk->bind_param("ii", $project_id, $user_id);
        $chk->execute();
        $chkRes = $chk->get_result();

        if ($chkRes && $chkRes->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO tasks (project_id, title, description, assigned_to, status, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issisi", $project_id, $title, $desc, $assigned_to, $status, $user_id);
            $stmt->execute();
        }
    }

    $redir = "task.php?sid=" . urlencode(session_id());
    if ($project_id > 0) $redir .= "&project_id=" . $project_id;
    header("Location: " . $redir);
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Tasks</title>

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
    background: linear-gradient(135deg, #141e30, #243b55);
    color: white;
}

h2 {
    margin-bottom: 15px;
    font-weight: 600;
}

/* SIDEBAR */
.sidebar {
    width: 240px;
    background: rgba(255,255,255,0.04);
    backdrop-filter: blur(20px);
    padding: 20px;
    border-right: 1px solid rgba(255,255,255,0.1);
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

/* FORM BOX */
.form-box {
    background: rgba(255,255,255,0.08);
    padding: 25px;
    border-radius: 15px;
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.1);
    box-shadow: 0 8px 30px rgba(0,0,0,0.3);
    margin-bottom: 25px;
}

/* INPUTS */
input, textarea, select {
    width: 100%;
    padding: 12px;
    margin: 10px 0;
    border-radius: 10px;
    border: none;
    outline: none;
    background: rgba(255,255,255,0.1);
    color: white;
}

/* BUTTON */
button {
    padding: 12px;
    background: linear-gradient(135deg, #00f2fe, #4facfe);
    border: none;
    border-radius: 10px;
    color: white;
    cursor: pointer;
    font-weight: bold;
    transition: 0.3s;
}

button:hover {
    transform: scale(1.05);
    box-shadow: 0 0 15px #4facfe;
}

/* TASK GRID */
.tasks {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 20px;
}

/* CARD */
.card {
    background: rgba(255,255,255,0.07);
    padding: 20px;
    border-radius: 18px;
    backdrop-filter: blur(20px);
    transition: 0.3s;
    border: 1px solid rgba(255,255,255,0.1);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}

.card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.5);
}

.card h3 {
    margin-bottom: 5px;
}

.card p {
    font-size: 14px;
    color: #ccc;
}

/* STATUS BADGES */
.status {
    margin-top: 10px;
    font-size: 12px;
    padding: 6px 10px;
    border-radius: 8px;
    display: inline-block;
}

/* STATUS COLORS */
.todo {
    background: linear-gradient(135deg, orange, #ff8c00);
}

.progress {
    background: linear-gradient(135deg, #007bff, #00c6ff);
}

.done {
    background: linear-gradient(135deg, #00c853, #69f0ae);
    color: black;
}

/* SELECT BOX */
select {
    background: rgba(255,255,255,0.1);
    color: white;              /* TEXT WHITE */
    border-radius: 10px;
}

/* DROPDOWN OPTIONS */
select option {
    background: #1e1e2f;       /* DARK BACKGROUND */
    color: white;              /* WHITE TEXT */
}
    </style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>🚀 Collab</h2>

    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="project.php?sid=<?php echo $sid; ?>">📁 Projects</a>
    <a href="task.php?sid=<?php echo $sid; ?>" class="active">📋 Tasks</a>
    <a href="chat.php?sid=<?php echo $sid; ?>">💬 Chat</a>
    <a href="files.php?sid=<?php echo $sid; ?>">📂 Files</a>
    <a href="students.php?sid=<?php echo $sid; ?>">🧑‍🎓 Students</a>
    <a href="profile.php?sid=<?php echo $sid; ?>">👤 Profile</a>

    <a href="../logout.php?sid=<?php echo $sid; ?>">🚪 Logout</a>
</div>

<!-- MAIN -->
<div class="main">

    <h2>📋 Tasks</h2>

    <!-- CREATE TASK -->
    <div class="form-box">
        <form method="POST">
            <select name="project_id" required>
                <option value="">Select Project</option>
                <?php
                $projStmt = $conn->prepare("
                    SELECT p.id, p.title
                    FROM projects p
                    JOIN project_members pm ON pm.project_id = p.id
                    WHERE pm.user_id = ?
                    ORDER BY p.id DESC
                ");
                $projStmt->bind_param("i", $user_id);
                $projStmt->execute();
                $projRes = $projStmt->get_result();
                while ($p = $projRes->fetch_assoc()) {
                    $sel = ($active_project_id === (int)$p['id']) ? "selected" : "";
                    echo "<option value='".(int)$p['id']."' $sel>".htmlspecialchars($p['title'])."</option>";
                }
                ?>
            </select>
            <input type="text" name="title" placeholder="Task Title" required>
            <textarea name="description" placeholder="Task Description"></textarea>

            <!-- USER SELECT -->
            <select name="assigned_to" required>
                <option value="">Assign User</option>
                <?php
                $users = $conn->query("SELECT id, name FROM users");
                while ($u = $users->fetch_assoc()) {
                    echo "<option value='".$u['id']."'>".$u['name']."</option>";
                }
                ?>
            </select>

            <select name="status">
                <option value="todo">To Do</option>
                <option value="in-progress">In Progress</option>
                <option value="done">Done</option>
            </select>

            <button type="submit">Add Task</button>
        </form>
    </div>

    <!-- TASK LIST -->
    <div class="tasks">
        <?php
        if ($active_project_id > 0) {
            if ($has_taken_cols) {
                $stmt = $conn->prepare("
                    SELECT t.*, u.name, p.title AS project_title, tb.name AS taken_by_name, p.created_by AS project_owner_id
                    FROM tasks t
                    LEFT JOIN users u ON t.assigned_to = u.id
                    LEFT JOIN users tb ON t.taken_by = tb.id
                    LEFT JOIN projects p ON p.id = t.project_id
                    JOIN project_members pm ON pm.project_id = t.project_id AND pm.user_id = ?
                    WHERE t.project_id = ?
                    ORDER BY t.id DESC
                ");
            } else {
                $stmt = $conn->prepare("
                    SELECT t.*, u.name, p.title AS project_title, p.created_by AS project_owner_id
                    FROM tasks t
                    LEFT JOIN users u ON t.assigned_to = u.id
                    LEFT JOIN projects p ON p.id = t.project_id
                    JOIN project_members pm ON pm.project_id = t.project_id AND pm.user_id = ?
                    WHERE t.project_id = ?
                    ORDER BY t.id DESC
                ");
            }
            $stmt->bind_param("ii", $user_id, $active_project_id);
        } else {
            if ($has_taken_cols) {
                $stmt = $conn->prepare("
                    SELECT t.*, u.name, p.title AS project_title, tb.name AS taken_by_name, p.created_by AS project_owner_id
                    FROM tasks t
                    LEFT JOIN users u ON t.assigned_to = u.id
                    LEFT JOIN users tb ON t.taken_by = tb.id
                    LEFT JOIN projects p ON p.id = t.project_id
                    JOIN project_members pm ON pm.project_id = t.project_id AND pm.user_id = ?
                    ORDER BY t.id DESC
                ");
            } else {
                $stmt = $conn->prepare("
                    SELECT t.*, u.name, p.title AS project_title, p.created_by AS project_owner_id
                    FROM tasks t
                    LEFT JOIN users u ON t.assigned_to = u.id
                    LEFT JOIN projects p ON p.id = t.project_id
                    JOIN project_members pm ON pm.project_id = t.project_id AND pm.user_id = ?
                    ORDER BY t.id DESC
                ");
            }
            $stmt->bind_param("i", $user_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            echo "<div class='card'>";
            echo "<h3>".htmlspecialchars($row['title'])."</h3>";
            echo "<p style='color:#ccc;font-size:13px;'><b>Project:</b> ".htmlspecialchars($row['project_title'] ?? "")."</p>";
            echo "<p>".nl2br(htmlspecialchars($row['description'] ?? ""))."</p>";
            echo "<p><b>Assigned to:</b> ".htmlspecialchars($row['name'] ?? "Unassigned")."</p>";

            if ($has_taken_cols && !empty($row['taken_at'])) {
                $takenBy = $row['taken_by_name'] ?? "";
                $takenBy = $takenBy !== "" ? $takenBy : "Someone";
                echo "<p style='color:#cbd5e1;font-size:13px;'><b>Accepted:</b> ".htmlspecialchars($takenBy)." (".htmlspecialchars($row['taken_at']).")</p>";

                if ((int)($row['taken_by'] ?? 0) === $user_id) {
                    $taskStatusNow = strtolower((string)($row['status'] ?? ''));
                    if ($taskStatusNow !== "done" && $taskStatusNow !== "completed") {
                        echo "<form method='POST' style='margin-top:10px;'>";
                        echo "<input type='hidden' name='task_id' value='".(int)$row['id']."'>";
                        echo "<input type='hidden' name='project_id' value='".(int)$row['project_id']."'>";
                        echo "<button type='submit' name='mark_done' style='width:100%;background:linear-gradient(135deg,#00c853,#69f0ae);color:#111;'>Mark Done & Notify Owner</button>";
                        echo "</form>";
                    }
                }
            } elseif ($has_taken_cols) {
                // If I am assignee, show Take button
                if ((int)($row['assigned_to'] ?? 0) === $user_id) {
                    echo "<form method='POST' style='margin-top:10px;'>";
                    echo "<input type='hidden' name='task_id' value='".(int)$row['id']."'>";
                    echo "<input type='hidden' name='project_id' value='".(int)$row['project_id']."'>";
                    echo "<button type='submit' name='take_task' style='width:100%;'>Take / Accept</button>";
                    echo "</form>";
                } else {
                    echo "<p style='color:#94a3b8;font-size:13px;'><b>Accepted:</b> Pending</p>";
                }
            }

            if ((int)($row['created_by'] ?? 0) === $user_id || (int)($row['project_owner_id'] ?? 0) === $user_id) {
                echo "<form method='POST' style='margin-top:10px;'>";
                echo "<input type='hidden' name='task_id' value='".(int)$row['id']."'>";
                echo "<input type='hidden' name='project_id' value='".(int)$row['project_id']."'>";
                echo "<button type='submit' name='delete_task' style='width:100%;background:#ff4d4d;'>Delete Task</button>";
                echo "</form>";
            }

            $status = strtolower((string)($row['status'] ?? ''));
            $statusClass = $status;
            if ($statusClass === "in-progress") {
                $statusClass = "progress";
            } elseif ($statusClass === "pending") {
                $statusClass = "todo";
            } elseif ($statusClass === "completed") {
                $statusClass = "done";
            }

            $statusLabel = "Pending";
            if ($status === "in-progress") {
                $statusLabel = "In Progress";
            } elseif ($status === "done" || $status === "completed") {
                $statusLabel = "Completed";
            }

            echo "<span class='status $statusClass'>".$statusLabel."</span>";
            echo "</div>";
        }
        ?>
    </div>

</div>

</body>
</html>