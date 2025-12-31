<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// 1. Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'You must be logged in as a teacher to submit papers.'
    ];
    header('Location: index.php');
    exit;
}
$teacher_id = $_SESSION['user_id'];

// 2. Check if form data is sent via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Not a POST request
    header('Location: dashboard_teacher.php');
    exit;
}

// 3. Get form data
$exam_id = $_POST['exam_id'] ?? null;
$assignment_id = $_POST['assignment_id'] ?? null;

// 4. Basic Validation
if (empty($exam_id) || empty($assignment_id)) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Please select both an exam and an assignment.'];
    header('Location: dashboard_teacher.php');
    exit;
}

// --- NEW: SERVER-SIDE CLASS RESTRICTION CHECK ---
try {
    // Check timezone
    $timezone = new DateTimeZone('Asia/Kolkata');
    $currentTime = new DateTime('now', $timezone);
    $cutoffTime = new DateTime('2025-11-26 21:00:00', $timezone);

    // EXEMPTION LOGIC
    // Normalize name to lowercase and trim
    $loggedInName = strtolower(trim($_SESSION['name'] ?? ''));
    $isExempt = ($loggedInName === 'nidhi pareek');

    // Proceed with check only if deadline passed AND user is NOT exempt
    if ($currentTime > $cutoffTime && !$isExempt) {
        // Fetch class info for the selected assignment
        $stmt_check = $pdo->prepare("
            SELECT c.name as class_name
            FROM assignments a
            JOIN classes c ON a.class_id = c.class_id
            WHERE a.assignment_id = ?
        ");
        $stmt_check->execute([$assignment_id]);
        $class_info = $stmt_check->fetch();

        if ($class_info) {
            $class_name = $class_info['class_name'];
            $grade = 0;
            // Parse grade
            if (preg_match('/(\d+)/', $class_name, $matches)) {
                $grade = intval($matches[1]);
            }
            
            // If class > 5th, reject upload
            if ($grade > 5) {
                 $_SESSION['flash_message'] = [
                    'type' => 'error', 
                    'message' => 'Upload Rejected: Paper upload is disabled for classes above 5th after Nov 26, 9:00 PM.'
                ];
                header('Location: dashboard_teacher.php');
                exit;
            }
        }
    }
} catch (PDOException $e) {
    // Log error but allow to proceed to not break flow if DB fails here (or block safer)
    // We'll block to be safe
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Validation error: ' . $e->getMessage()];
    header('Location: dashboard_teacher.php');
    exit;
}
// ------------------------------------------------


// 5. File Upload Handling
if (isset($_FILES['paper_file']) && $_FILES['paper_file']['error'] === UPLOAD_ERR_OK) {
    
    $file = $_FILES['paper_file'];
    $original_filename = $file['name'];
    $file_tmp_name = $file['tmp_name'];
    $file_size = $file['size'];
    $file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    
    $upload_dir = 'uploads/';
    $allowed_extensions = ['doc', 'docx'];
    $max_file_size = 10 * 1024 * 1024; // 10MB

    // --- Validation Checks ---
    if (!in_array($file_ext, $allowed_extensions)) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid file type. Only DOC, and DOCX are allowed.'];
        header('Location: dashboard_teacher.php');
        exit;
    }

    if ($file_size > $max_file_size) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'File is too large. Maximum size is 10MB.'];
        header('Location: dashboard_teacher.php');
        exit;
    }

    // --- Create a unique, sanitized filename ---
    // Sanitize the original filename to remove special characters
    $safe_filename = preg_replace("/[^a-zA-Z0-9-_\.]/", "", basename($original_filename));
    // Create the new unique filename
    $new_filename = uniqid() . '_' . $safe_filename;
    $target_file = $upload_dir . $new_filename;

    // --- Move file and insert into DB ---
    try {
        if (move_uploaded_file($file_tmp_name, $target_file)) {
            // File moved successfully, now insert into database
            
            // We include `stored_filename` in the query
            $sql = "INSERT INTO papers (teacher_id, exam_id, assignment_id, file_path, stored_filename, status) 
                    VALUES (?, ?, ?, ?, ?, 'pending_review')";
            $stmt = $pdo->prepare($sql);
            
            // Pass $new_filename to the execute array
            $stmt->execute([$teacher_id, $exam_id, $assignment_id, $target_file, $new_filename]);

            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Paper submitted successfully for review!'];
            header('Location: dashboard_teacher.php');
            exit;

        } else {
            throw new RuntimeException('Failed to move uploaded file. Check server permissions.');
        }

    } catch (PDOException $e) {
        // Database error
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        header('Location: dashboard_teacher.php');
        exit;
    } catch (RuntimeException $e) {
        // File system error
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Server error: ' . $e->getMessage()];
        header('Location: dashboard_teacher.php');
        exit;
    }

} else {
    // Handle other upload errors
    $error_message = 'File upload error. ';
    switch ($_FILES['paper_file']['error']) {
        case UPLOAD_ERR_INI_SIZE:
            $error_message .= 'File exceeds server upload limit.';
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $error_message .= 'File exceeds form upload limit.';
            break;
        case UPLOAD_ERR_PARTIAL:
            $error_message .= 'File was only partially uploaded.';
            break;
        case UPLOAD_ERR_NO_FILE:
            $error_message .= 'No file was selected for upload.';
            break;
        default:
            $error_message .= 'An unknown upload error occurred.';
            break;
    }
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => $error_message];
    header('Location: dashboard_teacher.php');
    exit;
}
?>