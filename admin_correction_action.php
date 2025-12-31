<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// 1. Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You must be logged in as an admin.'];
    header('Location: index.php');
    exit;
}

// 2. Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['action']) || $_POST['action'] !== 'mark_completed') {
    header('Location: dashboard_admin.php');
    exit;
}

// 3. Validate inputs
$correction_paper_id = $_POST['correction_paper_id'] ?? null;
if (empty($correction_paper_id)) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid request.'];
    header('Location: dashboard_admin.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // 4. Update the paper status to 'completed'
    $stmt_update = $pdo->prepare("UPDATE correction_papers SET status = 'completed' WHERE correction_paper_id = ? AND status = 'corrected'");
    $stmt_update->execute([$correction_paper_id]);

    if ($stmt_update->rowCount() == 0) {
        throw new Exception("Paper was not in the correct state or not found.");
    }

    // 5. Find the teacher who submitted the correction to notify them
    $stmt_info = $pdo->prepare("
        SELECT 
            pcor.teacher_id, 
            cp.original_file_name,
            c.name as class_name
        FROM paper_corrections pcor
        JOIN correction_papers cp ON pcor.correction_paper_id = cp.correction_paper_id
        JOIN classes c ON cp.class_id = c.class_id
        WHERE pcor.correction_paper_id = ?
        ORDER BY pcor.submitted_at DESC
        LIMIT 1
    ");
    $stmt_info->execute([$correction_paper_id]);
    $info = $stmt_info->fetch();

    if ($info && $info['teacher_id']) {
        // 6. Send notification to the teacher
        $notification_message = "Your correction for '$info[original_file_name]' ($info[class_name]) has been applied and completed by the admin.";
        $stmt_notify = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt_notify->execute([$info['teacher_id'], $notification_message]);
    }

    $pdo->commit();
    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Paper marked as completed and teacher notified.'];

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
}

header('Location: dashboard_admin.php');
exit;