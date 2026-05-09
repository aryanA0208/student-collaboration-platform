<?php
include("../includes/session.php");
include("../includes/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_id = (int)$user_id;
$sid = urlencode(session_id());

$active_project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

// 🗑️ DELETE FILE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $stmt = $conn->prepare("SELECT id, user_id, project_id, file_path FROM files WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $file = $res ? $res->fetch_assoc() : null;

    if ($file) {
        $allowed = ((int)$file['user_id'] === $user_id);
        if (!$allowed && !empty($file['project_id'])) {
            $chk = $conn->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ? AND role='owner' LIMIT 1");
            $pid = (int)$file['project_id'];
            $chk->bind_param("ii", $pid, $user_id);
            $chk->execute();
            $chkRes = $chk->get_result();
            $allowed = $chkRes && $chkRes->num_rows > 0;
        }

        if ($allowed) {
            $full_path = "../" . $file['file_path'];
            if (file_exists($full_path)) {
                unlink($full_path);
            }
            $del = $conn->prepare("DELETE FROM files WHERE id = ?");
            $del->bind_param("i", $id);
            $del->execute();
        }
    }

    $redirect = "files.php?sid=" . urlencode(session_id());
    if ($active_project_id > 0) $redirect .= "&project_id=" . $active_project_id;
    header("Location: $redirect");
    exit();
}

// 📂 FILE UPLOAD
if (isset($_POST['upload'])) {
    $project_id = (int)($_POST['project_id'] ?? 0);

    if ($project_id <= 0) {
        $msg = "❌ Please select a project.";
    } else {
        $chk = $conn->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1");
        $chk->bind_param("ii", $project_id, $user_id);
        $chk->execute();
        $chkRes = $chk->get_result();

        if (!$chkRes || $chkRes->num_rows === 0) {
            $msg = "❌ You are not a member of this project.";
        } elseif (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {

            $file = $_FILES['file'];
            $filename = time() . "_" . basename($file['name']);

            $server_path = "../uploads/" . $filename;
            $db_path = "uploads/" . $filename;

            if (!file_exists("../uploads")) {
                mkdir("../uploads", 0777, true);
            }

            if (move_uploaded_file($file['tmp_name'], $server_path)) {

                $stmt = $conn->prepare("INSERT INTO files (project_id, user_id, file_name, file_path) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiss", $project_id, $user_id, $filename, $db_path);

                $msg = $stmt->execute() ? "✅ File uploaded!" : "❌ DB Error";

            } else {
                $msg = "❌ Upload failed!";
            }

        } else {
            $msg = "❌ No file selected!";
        }
    }

    header("Location: files.php?sid=" . urlencode(session_id()) . "&project_id=" . $project_id);
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Files</title>

    <style>
        /* BACKGROUND */
body {
    margin: 0;
    font-family: 'Segoe UI', sans-serif;
    display: flex;
    background: linear-gradient(135deg, #1e1e2f, #2c2f48);
    color: white;
}

/* SIDEBAR */
.sidebar {
    width: 230px;
    background: rgba(0,0,0,0.3);
    backdrop-filter: blur(10px);
    padding: 20px;
    height: 100vh;
}

.sidebar a {
    display: block;
    color: #ddd;
    padding: 10px;
    margin: 10px 0;
    text-decoration: none;
    border-radius: 8px;
    transition: 0.3s;
}

.sidebar a:hover,
.sidebar a.active {
    background: #6c63ff;
    color: white;
}

/* MAIN */
.main {
    flex: 1;
    padding: 25px;
}

/* UPLOAD BOX */
.upload-box {
    background: rgba(255,255,255,0.08);
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 20px;
    backdrop-filter: blur(12px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
}

/* FILE BUTTON */
input[type="file"] {
    display: none;
}

.custom-file {
    padding: 12px 20px;
    background: #6c63ff;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
}

.custom-file:hover {
    background: #574fd6;
}

/* FILE NAME */
.file-name {
    margin-left: 10px;
    color: #ccc;
}

/* UPLOAD BUTTON */
.upload-btn {
    padding: 12px 20px;
    background: linear-gradient(135deg, #00c6ff, #0072ff);
    border: none;
    border-radius: 8px;
    color: white;
    cursor: pointer;
    margin-left: 10px;
    font-weight: bold;
}

.upload-btn:hover {
    transform: scale(1.05);
}

/* FILE LIST */
.files {
    background: rgba(255,255,255,0.08);
    padding: 20px;
    border-radius: 15px;
    backdrop-filter: blur(12px);
}

/* FILE ITEM */
.file {
    display: flex;
    justify-content: space-between;
    padding: 12px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

/* ACTIONS */
.actions a {
    margin-left: 10px;
}

.download {
    color: #00c6ff;
}

.delete {
    color: #ff4d4d;
    display: inline-flex;
    align-items: center;
    transition: 0.3s;
}

.delete:hover {
    color: #ff0000;
    transform: scale(1.2);
}

/* MESSAGE */
.msg {
    margin-bottom: 10px;
    font-weight: bold;
}
    </style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>🚀 Collab</h2>

    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="project.php?sid=<?php echo $sid; ?>">📁 Projects</a>
    <a href="task.php?sid=<?php echo $sid; ?>">📋 Tasks</a>
    <a href="chat.php?sid=<?php echo $sid; ?>">💬 Chat</a>
    <a href="files.php?sid=<?php echo $sid; ?>" class="active">📂 Files</a>
    <a href="students.php?sid=<?php echo $sid; ?>">🧑‍🎓 Students</a>
    <a href="profile.php?sid=<?php echo $sid; ?>">👤 Profile</a>

    <a href="../logout.php?sid=<?php echo $sid; ?>">🚪 Logout</a>
</div>

<!-- MAIN -->
<div class="main">

    <h2>📂 File Upload</h2>

    <?php if (isset($msg)) echo "<p class='msg'>$msg</p>"; ?>

    <!-- UPLOAD -->
    <div class="upload-box">
        <form method="POST" enctype="multipart/form-data">
            <select name="project_id" required style="width:100%;padding:12px;margin-bottom:12px;border-radius:10px;border:none;">
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

            <label class="custom-file">
                Choose File
                <input type="file" name="file" id="fileInput" required>
            </label>

            <span class="file-name" id="fileName">No file chosen</span>

            <button type="submit" name="upload" class="upload-btn">Upload</button>

        </form>
    </div>

    <!-- FILE LIST -->
    <div class="files">
        <h3>Uploaded Files</h3>

        <?php
        if ($active_project_id > 0) {
            $stmt = $conn->prepare("
                SELECT f.*
                FROM files f
                JOIN project_members pm ON pm.project_id = f.project_id AND pm.user_id = ?
                WHERE f.project_id = ?
                ORDER BY f.id DESC
            ");
            $stmt->bind_param("ii", $user_id, $active_project_id);
        } else {
            $stmt = $conn->prepare("
                SELECT f.*
                FROM files f
                JOIN project_members pm ON pm.project_id = f.project_id AND pm.user_id = ?
                ORDER BY f.id DESC
            ");
            $stmt->bind_param("i", $user_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            echo "<div class='file'>";
            echo "<span>".htmlspecialchars($row['file_name'])."</span>";

            echo "<div class='actions'>";
            echo "<a class='download' href='../".$row['file_path']."' download>Download</a>";
            $delLink = "files.php?delete=".$row['id'];
            if ($active_project_id > 0) $delLink .= "&project_id=".$active_project_id;
            echo "<a class='delete' href='".$delLink."' onclick=\"return confirm('Delete this file?')\">
<svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' fill='currentColor' viewBox='0 0 16 16'>
  <path d='M5.5 5.5v6h1v-6h-1zm4 0v6h1v-6h-1z'/>
  <path fill-rule='evenodd' d='M14 3H2v1h1v9a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V4h1V3zM4 4h8v9a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4z'/>
  <path d='M9.5 1h-3l-.5 1H4v1h8V2h-2l-.5-1z'/>
</svg>
</a>";
            echo "</div>";

            echo "</div>";
        }
        ?>
    </div>

</div>

<!-- JS -->
<script>
document.getElementById("fileInput").addEventListener("change", function() {
    let fileName = this.files[0]?.name || "No file chosen";
    document.getElementById("fileName").innerText = fileName;
});
</script>

</body>
</html>