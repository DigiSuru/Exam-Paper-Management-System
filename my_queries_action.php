<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

// 1. Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You must be a teacher.'];
    header('Location: index.php');
    exit;
}
$teacher_id = $_SESSION['user_id'];

// 2. Check for POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_queries.php');
    exit;
}

// 3. Get form data
$paper_id = $_POST['paper_id'] ?? null;
$subject = $_POST['subject'] ?? null;
$message = $_POST['message'] ?? null;

// 4. Validate data
if (empty($subject) || empty($message)) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Subject and Message are required.'];
    header('Location: my_queries.php');
    exit;
}

// Handle empty string paper_id
if (empty($paper_id)) {
    $paper_id = null;
}

try {
    // 5. Insert into database
    $sql = "INSERT INTO teacher_queries (teacher_id, paper_id, subject, message, status) 
            VALUES (?, ?, ?, ?, 'open')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$teacher_id, $paper_id, $subject, $message]);

    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Query submitted successfully!'];
    header('Location: my_queries.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    header('Location: my_queries.php');
    exit;
}
?>
