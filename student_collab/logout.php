<?php
include("includes/session.php");
session_destroy();
header("Location: login.php");
exit();
?>