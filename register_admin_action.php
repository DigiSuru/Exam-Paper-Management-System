<?php
require_once 'config.php';

// --- 1. Define Secret Code ---
// !! IMPORTANT: Change this to your own secret code !!
define('ADMIN_REGISTRATION_CODE', 'SuperSecr3tC0de!');

// --- 2. Initialize Variables ---
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$reg_code = $_POST['reg_code'] ?? '';


// --- 3. Validation ---
if (empty($name) || empty($email) || empty($password) || empty($reg_code)) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Please fill in all fields.'];
    header('Location: register_admin.php');
    exit;
}

// Check the secret code
if ($reg_code !== ADMIN_REGISTRATION_CODE) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid Admin Registration Code.'];
    header('Location: register_admin.php');
    exit;
}

if ($password !== $confirm_password) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Passwords do not match.'];
    header('Location: register_admin.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
     $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid email format.'];
    header('Location: register_admin.php');
    exit;
}

// --- 4. Check for existing user ---
try {
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'An account with this email already exists.'];
        header('Location: register_admin.php');
        exit;
    }

    // --- 5. Create New Admin User ---
    
    // Hash the password securely
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // *** FIX: Changed `password` to `password_hash` ***
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
    $stmt->execute([$name, $email, $password_hash]);

    // --- 6. Redirect to Login ---
    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Admin account created successfully! Please log in.'];
    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    header('Location: register_admin.php');
    exit;
}
?>

