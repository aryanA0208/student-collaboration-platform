<?php
include("../includes/session.php");
include("../includes/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$sid = urlencode(session_id());

// UPDATE PROFILE
if (isset($_POST['update'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];

    // IMAGE UPLOAD
    if (!empty($_FILES['profile_pic']['name'])) {
        if (!file_exists("../uploads/profile")) {
            mkdir("../uploads/profile", 0777, true);
        }

        $img = time() . "_" . basename($_FILES['profile_pic']['name']);
        $path = "../uploads/profile/" . $img;

        move_uploaded_file($_FILES['profile_pic']['tmp_name'], $path);

        $conn->query("UPDATE users SET profile_pic='$img' WHERE id='$user_id'");
    }

    $conn->query("UPDATE users 
                  SET name='$name', email='$email', phone='$phone' 
                  WHERE id='$user_id'");
}

// FETCH USER
$res = $conn->query("SELECT * FROM users WHERE id='$user_id'");
$user = $res->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
<title>Profile</title>

<style>

/* GLOBAL */
body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    display: flex;
    min-height: 100vh;
    background: linear-gradient(135deg, #141e30, #243b55);
    color: white;
}

/* SIDEBAR */
.sidebar {
    width: 240px;
    background: rgba(255,255,255,0.04);
    backdrop-filter: blur(20px);
    padding: 20px;
}

.sidebar a {
    display: block;
    color: #bbb;
    padding: 12px;
    margin: 10px 0;
    text-decoration: none;
    border-radius: 10px;
}

.sidebar a:hover,
.sidebar a.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

/* MAIN */
.main {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* CARD */
.card {
    background: rgba(255,255,255,0.08);
    padding: 30px;
    border-radius: 20px;
    text-align: center;
    backdrop-filter: blur(20px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.4);
    width: 320px;
}

/* IMAGE */
.card img {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    border: 3px solid #00f2fe;
    margin-bottom: 10px;
}

/* INPUTS */
input {
    width: 100%;
    padding: 10px;
    margin: 8px 0;
    border-radius: 8px;
    border: none;
}

/* BUTTON */
button {
    padding: 10px;
    background: linear-gradient(135deg, #00f2fe, #4facfe);
    border: none;
    border-radius: 10px;
    color: white;
    cursor: pointer;
    margin-top: 10px;
}
/* HIDE DEFAULT INPUT */
.upload-btn input {
    display: none;
}

/* CUSTOM BUTTON */
.upload-btn {
    display: inline-block;
    padding: 10px 15px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-radius: 10px;
    cursor: pointer;
    font-size: 14px;
    transition: 0.3s;
    margin-bottom: 10px;
}

/* HOVER EFFECT */
.upload-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 0 10px rgba(102,126,234,0.7);
}

/* FILE NAME TEXT */
#fileName {
    font-size: 12px;
    color: #ccc;
    margin-top: 5px;
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
    <a href="chat.php?sid=<?php echo $sid; ?>">💬 Chat</a>
    <a href="files.php?sid=<?php echo $sid; ?>">📂 Files</a>
    <a href="students.php?sid=<?php echo $sid; ?>">🧑‍🎓 Students</a>
    <a href="profile.php?sid=<?php echo $sid; ?>" class="active">👤 Profile</a>

    <a href="../logout.php?sid=<?php echo $sid; ?>">🚪 Logout</a>
</div>

<!-- MAIN -->
<div class="main">

<div class="card">

    <!-- PROFILE IMAGE -->
    <img src="../uploads/profile/<?php echo $user['profile_pic'] ?: 'default.svg'; ?>" alt="Profile">

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="sid" value="<?php echo $sid; ?>">

        <!-- CHANGE IMAGE -->
        <label class="upload-btn">
            Choose Image
    <input type="file" name="profile_pic" onchange="showFileName(this)">
</label>

<p id="fileName"></p>

        <!-- EDIT FIELDS -->
        <input type="text" name="name" value="<?php echo $user['name']; ?>" placeholder="Name">
        <input type="email" name="email" value="<?php echo $user['email']; ?>" placeholder="Email">
        <input type="text" name="phone" value="<?php echo $user['phone']; ?>" placeholder="Phone Number">

        <button name="update">Update Profile</button>
    </form>

</div>

</div>

<script>
function showFileName(input) {
    const fileName = input.files[0]?.name;
    document.getElementById("fileName").innerText = fileName || "";
}
</script>

</body>
</html>