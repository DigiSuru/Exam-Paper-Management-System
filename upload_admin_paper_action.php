<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// 1. Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You must be an admin.'];
    header('Location: index.php');
    exit;
}
$admin_id = $_SESSION['user_id'];

// 2. Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_papers.php');
    exit;
}

// 3. Get form data
$exam_id = $_POST['exam_id'] ?? null;
$class_id = $_POST['class_id'] ?? null;
$subject_id = $_POST['subject_id'] ?? null;
$num_questions = $_POST['num_questions'] ?? null;

// 4. Basic Validation
if (empty($exam_id) || empty($class_id) || empty($subject_id) || empty($num_questions)) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Please fill in all fields.'];
    header('Location: manage_papers.php');
    exit;
}
if (!is_numeric($num_questions) || $num_questions < 1 || $num_questions > 500) {
     $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid number of questions (must be 1-500).'];
    header('Location: manage_papers.php');
    exit;
}

// 5. File Upload Handling
if (isset($_FILES['paper_file']) && $_FILES['paper_file']['error'] === UPLOAD_ERR_OK) {
    
    $file = $_FILES['paper_file'];
    $original_filename = $file['name'];
    $file_tmp_name = $file['tmp_name'];
    $file_size = $file['size'];
    $file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    
    $upload_dir = 'uploads/admin_papers/'; // Use a dedicated directory
    $allowed_extensions = ['pdf', 'doc', 'docx'];
    $max_file_size = 10 * 1024 * 1024; // 10MB

    // Ensure upload directory exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // --- Validation Checks ---
    if (!in_array($file_ext, $allowed_extensions)) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid file type. Only PDF, DOC, and DOCX.'];
        header('Location: manage_papers.php');
        exit;
    }

    if ($file_size > $max_file_size) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'File is too large (Max 10MB).'];
        header('Location: manage_papers.php');
        exit;
    }

    // --- Create a unique, sanitized filename ---
    $safe_filename = preg_replace("/[^a-zA-Z0-9-_\.]/", "", basename($original_filename));
    $new_filename = uniqid() . '_' . $safe_filename;
    $target_file = $upload_dir . $new_filename;

    // --- Move file and insert into DB ---
    try {
        if (move_uploaded_file($file_tmp_name, $target_file)) {
            // File moved successfully, now insert into database
            $sql = "INSERT INTO admin_papers (exam_id, class_id, subject_id, admin_id, file_path, stored_filename, num_questions) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$exam_id, $class_id, $subject_id, $admin_id, $target_file, $new_filename, $num_questions]);

            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Paper uploaded successfully!'];
            header('Location: manage_papers.php');
            exit;

        } else {
            throw new RuntimeException('Failed to move uploaded file. Check server permissions.');
        }

    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        header('Location: manage_papers.php');
        exit;
    } catch (RuntimeException $e) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Server error: ' . $e->getMessage()];
        header('Location: manage_papers.php');
        exit;
    }

} else {
    // Handle upload errors
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'File upload error or no file selected.'];
    header('Location: manage_papers.php');
    exit;
}
?>
