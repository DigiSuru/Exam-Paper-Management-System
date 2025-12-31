<?php
require_once 'config.php';

// --- 1. Initialize Variables ---
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// --- 2. Basic Validation ---
if (empty($name) || empty($email) || empty($password)) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Please fill in all fields.'];
    header('Location: register.php');
    exit;
}

if ($password !== $confirm_password) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Passwords do not match.'];
    header('Location: register.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
     $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid email format.'];
    header('Location: register.php');
    exit;
}

// --- 3. Check for existing user ---
try {
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'An account with this email already exists.'];
        header('Location: register.php');
        exit;
    }

    // --- 4. Create New User ---
    
    // Hash the password securely
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // *** FIX: Changed `password` to `password_hash` ***
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'teacher')");
    $stmt->execute([$name, $email, $password_hash]);

    // --- 5. Redirect to Login ---
    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Registration successful! Please log in.'];
    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    header('Location: register.php');
    exit;
}
?>

