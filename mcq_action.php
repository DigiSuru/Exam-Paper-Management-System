<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;

// 1. Create Master Exam (No class_id here anymore)
if ($action === 'create_master_exam' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_name = $_POST['exam_name'];
    $total_questions = $_POST['total_questions'];
    
    $upload_dir = 'uploads/mcq_papers/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    $file_name = time() . '_' . basename($_FILES['paper_file']['name']);
    $target_file = $upload_dir . $file_name;
    
    if (move_uploaded_file($_FILES['paper_file']['tmp_name'], $target_file)) {
        $stmt = $pdo->prepare("INSERT INTO mcq_exams (exam_name, file_path, total_questions) VALUES (?, ?, ?)");
        $stmt->execute([$exam_name, $target_file, $total_questions]);
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Master Exam created. Now assign it to classes.'];
    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'File upload failed.'];
    }
    header('Location: manage_mcq.php');
    exit;

// 2. Assign Master Exam to a Class
} elseif ($action === 'assign_class' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $mcq_exam_id = $_POST['mcq_exam_id'];
    $class_id = $_POST['class_id'];
    
    // Check if already assigned
    $check = $pdo->prepare("SELECT * FROM mcq_exam_assignments WHERE mcq_exam_id = ? AND class_id = ?");
    $check->execute([$mcq_exam_id, $class_id]);
    if ($check->rowCount() > 0) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Already assigned to this class.'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO mcq_exam_assignments (mcq_exam_id, class_id) VALUES (?, ?)");
        $stmt->execute([$mcq_exam_id, $class_id]);
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Class assigned successfully.'];
    }
    header('Location: manage_mcq.php');
    exit;

// 3. Add Range (Specific to an Assignment/Class)
} elseif ($action === 'add_range_v2' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignment_id = $_POST['assignment_id'];
    $subject_id = $_POST['subject_id'];
    $start = $_POST['start_q'];
    $end = $_POST['end_q'];

    if ($start > $end) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid range.'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO mcq_subject_ranges (assignment_id, subject_id, start_q, end_q) VALUES (?, ?, ?, ?)");
        $stmt->execute([$assignment_id, $subject_id, $start, $end]);
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Subject range defined for this class.'];
    }
    header('Location: manage_mcq.php');
    exit;

} elseif ($action === 'delete_assignment') {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM mcq_exam_assignments WHERE assignment_id = ?");
    $stmt->execute([$id]);
    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Assignment removed.'];
    header('Location: manage_mcq.php');
    exit;
}
?>