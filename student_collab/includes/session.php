<?php
// Per-tab sessions: allow multiple logins in same browser by passing ?sid=... (stored in sessionStorage).
// Usage: include this file BEFORE any output.

session_name("SCSESSID");

$sid = "";
if (isset($_GET["sid"])) $sid = (string)$_GET["sid"];
elseif (isset($_POST["sid"])) $sid = (string)$_POST["sid"];

if ($sid !== "") {
    // Allow only safe session id chars
    if (preg_match('/^[a-f0-9]{16,128}$/i', $sid)) {
        session_id($sid);
    }
}

session_start();

