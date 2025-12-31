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
$teacher_name = $_SESSION['name']; // Get teacher's name for notification

// 2. Check for POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_exam_papers.php');
    exit;
}

// 3. Get form data
$paper_id = $_POST['paper_id'] ?? null;
$answers = $_POST['answers'] ?? [];

// 4. Validate
if (empty($paper_id) || empty($answers)) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid submission.'];
    header('Location: my_exam_papers.php');
    exit;
}

try {
    // 5. --- Security Check ---
    // Get paper details first
    $stmt_paper = $pdo->prepare("
        SELECT p.class_id, p.subject_id, s.name as subject_name, c.name as class_name
        FROM admin_papers p
        JOIN subjects s ON p.subject_id = s.subject_id
        JOIN classes c ON p.class_id = c.class_id
        WHERE p.paper_id = ?
    ");
    $stmt_paper->execute([$paper_id]);
    $paper_info = $stmt_paper->fetch();

    if (!$paper_info) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Paper not found.'];
        header('Location: my_exam_papers.php');
        exit;
    }

    // Now check if this teacher is assigned to this paper's class/subject
    $stmt_check = $pdo->prepare("
        SELECT 1 FROM assignments 
        WHERE teacher_id = ? AND class_id = ? AND subject_id = ?
    ");
    $stmt_check->execute([$teacher_id, $paper_info['class_id'], $paper_info['subject_id']]);
    
    if ($stmt_check->fetchColumn() === false) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You are not assigned to this paper\'s subject.'];
        header('Location: my_exam_papers.php');
        exit;
    }

    // 6. Encode answers and insert/update the database
    $answers_json = json_encode($answers);

    // Use INSERT ... ON DUPLICATE KEY UPDATE to handle resubmissions
    $sql = "
        INSERT INTO answer_keys (paper_id, teacher_id, answers)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            answers = VALUES(answers),
            submitted_at = NOW()
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$paper_id, $teacher_id, $answers_json]);

    // --- NEW: Create Notification for ALL Admins ---
    $notif_message = $teacher_name . " submitted an answer key for " . $paper_info['subject_name'] . " (" . $paper_info['class_name'] . ").";
    $notif_link = "view_key_admin.php?paper_id=" . $paper_id;

    // Find all admin user IDs
    $stmt_admins = $pdo->query("SELECT user_id FROM users WHERE role = 'admin'");
    while ($admin = $stmt_admins->fetch()) {
        $admin_id = $admin['user_id'];
        $sql_notify = "INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)";
        $pdo->prepare($sql_notify)->execute([$admin_id, $notif_message, $notif_link]);
    }
    // --- END: Create Notification ---

    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Answer key submitted successfully!'];
    header('Location: my_exam_papers.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    header('Location: my_exam_papers.php');
    exit;
}
?>

