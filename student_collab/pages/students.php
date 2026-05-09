<?php
include("../includes/session.php");
include("../includes/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$sid = urlencode(session_id());

$q = trim($_GET['q'] ?? "");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Students</title>
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            display: flex;
            min-height: 100vh;
            background: linear-gradient(135deg, #141e30, #243b55);
            color: white;
        }

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

        .main {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .search {
            background: rgba(255,255,255,0.08);
            padding: 18px;
            border-radius: 14px;
            margin-bottom: 18px;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
        }

        input[type="text"]{
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: none;
            outline: none;
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .grid{
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 18px;
        }

        .card{
            background: rgba(255,255,255,0.08);
            padding: 18px;
            border-radius: 16px;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            display:flex;
            gap: 14px;
            align-items: center;
        }

        .card img{
            width: 54px;
            height: 54px;
            border-radius: 50%;
            border: 2px solid #00f2fe;
            object-fit: cover;
        }

        .meta{
            flex: 1;
        }

        .name{
            font-weight: 700;
            margin-bottom: 4px;
        }

        .email{
            font-size: 13px;
            color: #cbd5e1;
            word-break: break-all;
        }

        .btn{
            padding: 10px 12px;
            background: linear-gradient(135deg, #00f2fe, #4facfe);
            border: none;
            border-radius: 10px;
            color: #0b1220;
            font-weight: 700;
            cursor: pointer;
            text-decoration:none;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>🚀 Collab</h2>
        <a href="dashboard.php?sid=<?php echo $sid; ?>">🏠 Dashboard</a>
        <a href="project.php?sid=<?php echo $sid; ?>">📁 Projects</a>
        <a href="task.php?sid=<?php echo $sid; ?>">📋 Tasks</a>
        <a href="chat.php?sid=<?php echo $sid; ?>">💬 Chat</a>
        <a href="files.php?sid=<?php echo $sid; ?>">📂 Files</a>
        <a href="students.php?sid=<?php echo $sid; ?>" class="active">🧑‍🎓 Students</a>
        <a href="profile.php?sid=<?php echo $sid; ?>">👤 Profile</a>
        <a href="../logout.php?sid=<?php echo $sid; ?>">🚪 Logout</a>
    </div>

    <div class="main">
        <h2>🧑‍🎓 Students</h2>

        <div class="search">
            <form method="GET">
                <input type="hidden" name="sid" value="<?php echo $sid; ?>">
                <input type="text" name="q" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($q); ?>">
            </form>
        </div>

        <div class="grid">
            <?php
            if ($q !== "") {
                $like = "%" . $q . "%";
                $stmt = $conn->prepare("SELECT id, name, email, profile_pic FROM users WHERE id != ? AND (name LIKE ? OR email LIKE ?) ORDER BY id DESC");
                $stmt->bind_param("iss", $user_id, $like, $like);
            } else {
                $stmt = $conn->prepare("SELECT id, name, email, profile_pic FROM users WHERE id != ? ORDER BY id DESC");
                $stmt->bind_param("i", $user_id);
            }
            $stmt->execute();
            $res = $stmt->get_result();

            while ($u = $res->fetch_assoc()) {
                $img = $u['profile_pic'] ?: 'default.svg';
                ?>
                <div class="card">
                    <img src="../uploads/profile/<?php echo htmlspecialchars($img); ?>" alt="Profile">
                    <div class="meta">
                        <div class="name"><?php echo htmlspecialchars($u['name']); ?></div>
                        <div class="email"><?php echo htmlspecialchars($u['email']); ?></div>
                    </div>
                    <a class="btn" href="chat.php?sid=<?php echo $sid; ?>&user_id=<?php echo (int)$u['id']; ?>">Message</a>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
</body>
</html>

