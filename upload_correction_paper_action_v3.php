<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// 1. Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'You must be logged in as an admin.'
    ];
    header('Location: index.php');
    exit;
}

$admin_id = $_SESSION['user_id'];

// 2. Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect back to the correct dashboard file
    header('Location: dashboard_admin_test.php');
    exit;
}

// 3. Validate inputs
$class_id = $_POST['class_id'] ?? null;
$subject_id = $_POST['subject_id'] ?? null;
$file = $_FILES['correction_paper_file'] ?? null;

if (empty($class_id) || empty($subject_id) || empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid input. Please select class, subject, and a valid file.'];
    header('Location: dashboard_admin_test.php');
    exit;
}

// 4. File Upload Logic
$upload_dir = 'uploads/correction_papers/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']; // Added images just in case
if (!in_array(strtolower($file_extension), $allowed_extensions)) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid file type. Only PDF, DOC, DOCX, and Images are allowed.'];
    header('Location: dashboard_admin_test.php');
    exit;
}

$file_name_safe = preg_replace("/[^a-zA-Z0-9\._-]/", "", basename($file['name']));
$new_file_name = time() . '_' . $file_name_safe;
$file_path = $upload_dir . $new_file_name;

if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to move uploaded file.'];
    header('Location: dashboard_admin_test.php');
    exit;
}

// 5. Insert into database
try {
    $pdo->beginTransaction();

    // Insert the paper (Assuming 'admin_id' column exists in correction_papers, otherwise remove it from query)
    // If your table doesn't track WHO uploaded it (admin_id), remove 'admin_id' from the query below.
    $stmt = $pdo->prepare("
        INSERT INTO correction_papers (class_id, subject_id, original_file_name, original_file_path, status, uploaded_at)
        VALUES (?, ?, ?, ?, 'pending_review', NOW())
    ");
    // Note: I removed admin_id from INSERT if your schema doesn't support it. 
    // If you have an uploader_id column, add it back.
    $stmt->execute([$class_id, $subject_id, $file_name_safe, $file_path]);
    
    // Get class and subject names for notification
    $stmt_info = $pdo->prepare("SELECT name FROM classes WHERE class_id = ?");
    $stmt_info->execute([$class_id]);
    $class_name = $stmt_info->fetchColumn();

    $stmt_info = $pdo->prepare("SELECT name FROM subjects WHERE subject_id = ?");
    $stmt_info->execute([$subject_id]);
    $subject_name = $stmt_info->fetchColumn();

    // 6. Create notifications for teachers assigned to this class/subject
    $stmt_teachers = $pdo->prepare("
        SELECT DISTINCT teacher_id FROM assignments 
        WHERE class_id = ? AND subject_id = ?
    ");
    $stmt_teachers->execute([$class_id, $subject_id]);
    $teacher_ids = $stmt_teachers->fetchAll(PDO::FETCH_COLUMN);

    if ($teacher_ids) {
        $notification_message = "New paper uploaded by Admin for correction: $class_name - $subject_name";
        $stmt_notify = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");

        foreach ($teacher_ids as $teacher_id) {
            $stmt_notify->execute([$teacher_id, $notification_message]);
        }
    }

    $pdo->commit();
    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Paper uploaded successfully and teachers notified.'];

} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
}

header('Location: dashboard_admin_test.php');
exit;