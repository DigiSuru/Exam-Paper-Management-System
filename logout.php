<?php
// Start session to access session variables
require_once 'config.php';

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to the login page
header("location: index.php");
exit;
?>
