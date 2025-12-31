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
$teacher_name = $_SESSION['name'] ?? 'A teacher'; // Assuming name is stored in session

// 2. Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard_teacher.php');
    exit;
}

// 3. Validate inputs
$correction_paper_id = $_POST['correction_paper_id'] ?? null;
$action = $_POST['action'] ?? null;

if (empty($correction_paper_id) || empty($action)) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid request.'];
    header('Location: dashboard_teacher.php');
    exit;
}

// Fetch paper info for security check and notifications
try {
    $stmt = $pdo->prepare("
        SELECT cp.*, c.name as class_name, s.name as subject_name
        FROM correction_papers cp
        JOIN classes c ON cp.class_id = c.class_id
        JOIN subjects s ON cp.subject_id = s.subject_id
        WHERE cp.correction_paper_id = ?
    ");
    $stmt->execute([$correction_paper_id]);
    $paper = $stmt->fetch();

    if (!$paper) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Paper not found.'];
        header('Location: dashboard_teacher.php');
        exit;
    }

    // Security Check: Verify this teacher is assigned
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE teacher_id = ? AND class_id = ? AND subject_id = ?");
    $stmt_check->execute([$teacher_id, $paper['class_id'], $paper['subject_id']]);
    if ($stmt_check->fetchColumn() == 0) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You are not authorized for this paper.'];
        header('Location: dashboard_teacher.php');
        exit;
    }

    // Check if already reviewed
    if ($paper['status'] !== 'pending_review') {
         $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'This paper has already been reviewed.'];
        header('Location: view_correction_paper.php?id=' . $correction_paper_id);
        exit;
    }

    $pdo->beginTransaction();
    $notification_message = "";

    // --- Action 1: No Correction ---
    if ($action === 'no_correction') {
        $stmt_update = $pdo->prepare("UPDATE correction_papers SET status = 'no_correction' WHERE correction_paper_id = ?");
        $stmt_update->execute([$correction_paper_id]);
        
        // --- NEW: Log this action so the teacher can see it in their history ---
        $stmt_insert = $pdo->prepare("
            INSERT INTO paper_corrections (correction_paper_id, teacher_id, correction_notes)
            VALUES (?, ?, ?)
        ");
        $stmt_insert->execute([$correction_paper_id, $teacher_id, "Marked as 'No Correction Required'"]);
        // --- END NEW ---

        $notification_message = "$teacher_name marked '$paper[original_file_name]' ($paper[class_name]) as needing no correction.";
    
    // --- Action 2: Submit Correction ---
    } elseif ($action === 'submit_correction') {
        $correction_notes = $_POST['correction_notes'] ?? null;
        $file = $_FILES['correction_image'] ?? null;
        $image_path = null;

        if (empty(trim($correction_notes)) && (empty($file) || $file['error'] !== UPLOAD_ERR_OK)) {
             $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Please provide correction notes or upload a correction file.'];
             header('Location: view_correction_paper.php?id=' . $correction_paper_id);
             exit;
        }

        // Handle file upload
        if (!empty($file) && $file['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/correction_images/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name_safe = preg_replace("/[^a-zA-Z0-9\._-]/", "", basename($file['name']));
            $new_file_name = time() . '_' . $file_name_safe;
            $image_path = $upload_dir . $new_file_name;

            if (!move_uploaded_file($file['tmp_name'], $image_path)) {
                $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to move uploaded correction file.'];
                header('Location: view_correction_paper.php?id=' . $correction_paper_id);
                exit;
            }
        }

        // Insert into paper_corrections
        $stmt_insert = $pdo->prepare("
            INSERT INTO paper_corrections (correction_paper_id, teacher_id, correction_notes, correction_image_path)
            VALUES (?, ?, ?, ?)
        ");
        $stmt_insert->execute([$correction_paper_id, $teacher_id, $correction_notes, $image_path]);

        // Update correction_papers status
        $stmt_update = $pdo->prepare("UPDATE correction_papers SET status = 'corrected' WHERE correction_paper_id = ?");
        $stmt_update->execute([$correction_paper_id]);

        $notification_message = "$teacher_name submitted corrections for '$paper[original_file_name]' ($paper[class_name]).";
    
    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid action.'];
        header('Location: dashboard_teacher.php');
        exit;
    }

    // 6. Create notifications for ALL admins
    $stmt_admins = $pdo->prepare("SELECT user_id FROM users WHERE role = 'admin'");
    $stmt_admins->execute();
    $admin_ids = $stmt_admins->fetchAll(PDO::FETCH_COLUMN);

    $stmt_notify = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    foreach ($admin_ids as $admin_id) {
        $stmt_notify->execute([$admin_id, $notification_message]);
    }

    $pdo->commit();
    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Review submitted successfully. Admin has been notified.'];

} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
}

header('Location: dashboard_teacher.php');
exit;