<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// 1. Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You must be logged in as a teacher.'];
    header('Location: index.php');
    exit;
}
$teacher_id = $_SESSION['user_id'];

// 2. Check if form data is sent via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard_teacher_main.php');
    exit;
}

// 3. Get common form data
$exam_id = $_POST['exam_id'] ?? null;
$assignment_id = $_POST['assignment_id'] ?? null;
$submission_type = $_POST['submission_type'] ?? null;

// 4. Basic Validation
if (empty($exam_id) || empty($assignment_id) || empty($submission_type)) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Please select an exam, assignment, and submission type.'];
    header('Location: dashboard_teacher_main.php');
    exit;
}

try {
    
    // --- BRANCH 1: Handle File Upload ---
    if ($submission_type === 'file') {
        
        if (!isset($_FILES['paper_file']) || $_FILES['paper_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Please select a file to upload.'];
            header('Location: dashboard_teacher_main.php');
            exit;
        }

        $file = $_FILES['paper_file'];
        $original_filename = $file['name'];
        $file_tmp_name = $file['tmp_name'];
        $file_size = $file['size'];
        $file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $allowed_extensions = ['pdf', 'doc', 'docx'];
        $max_file_size = 10 * 1024 * 1024; // 10MB

        // --- File Validation Checks ---
        if (!in_array($file_ext, $allowed_extensions)) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid file type. Only PDF, DOC, and DOCX are allowed.'];
            header('Location: dashboard_teacher_main.php');
            exit;
        }

        if ($file_size > $max_file_size) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'File is too large. Maximum size is 10MB.'];
            header('Location: dashboard_teacher_main.php');
            exit;
        }

        // --- Create a unique filename ---
        $safe_filename = preg_replace("/[^a-zA-Z0-9-_\.]/", "", basename($original_filename));
        $new_filename = uniqid() . '_' . $safe_filename;
        $target_file = $upload_dir . $new_filename;

        // --- Move file and insert into DB ---
        if (move_uploaded_file($file_tmp_name, $target_file)) {
            $sql = "INSERT INTO papers (teacher_id, exam_id, assignment_id, file_path, stored_filename, status, submission_type, paper_content) 
                    VALUES (?, ?, ?, ?, ?, 'pending_review', 'file', NULL)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$teacher_id, $exam_id, $assignment_id, $target_file, $new_filename]);

        } else {
            throw new RuntimeException('Failed to move uploaded file. Check server permissions.');
        }

    // --- BRANCH 2: Handle Text Submission ---
    } elseif ($submission_type === 'text') {
        
        $paper_content = $_POST['paper_content'] ?? null;

        if (empty(trim($paper_content)) || $paper_content === '<p></p>') {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Paper content cannot be empty. Please type your paper.'];
            header('Location: dashboard_teacher_main.php');
            exit;
        }

        // --- Insert into DB ---
        $sql = "INSERT INTO papers (teacher_id, exam_id, assignment_id, file_path, stored_filename, status, submission_type, paper_content) 
                VALUES (?, ?, ?, NULL, NULL, 'pending_review', 'text', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$teacher_id, $exam_id, $assignment_id, $paper_content]);

    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid submission type.'];
        header('Location: dashboard_teacher_main.php');
        exit;
    }

    // --- Success ---
    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Paper submitted successfully for review!'];
    header('Location: dashboard_teacher_main.php');
    exit;

} catch (PDOException $e) {
    // Database error
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    header('Location: dashboard_teacher_main.php');
    exit;
} catch (RuntimeException $e) {
    // File system error
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Server error: ' . $e->getMessage()];
    header('Location: dashboard_teacher_main.php');
    exit;
}
?>