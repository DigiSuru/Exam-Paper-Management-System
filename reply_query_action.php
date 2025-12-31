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
$admin_name = $_SESSION['name']; // Get admin name for the message

// 2. Check for POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_queries.php');
    exit;
}

// 3. Get form data
$query_id = $_POST['query_id'] ?? null;
$reply = $_POST['reply'] ?? null;

// 4. Validate data
if (empty($query_id) || empty($reply)) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Reply message is required.'];
    header('Location: reply_query.php?query_id=' . $query_id);
    exit;
}

try {
    // 5. Fetch teacher_id from the query before we update it
    $stmt_get_teacher = $pdo->prepare("SELECT teacher_id, subject FROM teacher_queries WHERE query_id = ?");
    $stmt_get_teacher->execute([$query_id]);
    $query_data = $stmt_get_teacher->fetch();
    
    if (!$query_data) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Query not found.'];
        header('Location: manage_queries.php');
        exit;
    }
    $teacher_id_to_notify = $query_data['teacher_id'];
    $query_subject = $query_data['subject'];


    // 6. Update database
    $sql = "UPDATE teacher_queries 
            SET 
                reply = ?, 
                admin_id = ?, 
                status = 'closed', 
                replied_at = NOW()
            WHERE query_id = ? AND status = 'open'";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$reply, $admin_id, $query_id]);

    if ($stmt->rowCount() > 0) {
        
        // --- NEW: Create Notification ---
        $message = "Admin (" . $admin_name . ") replied to your query: '" . substr($query_subject, 0, 50) . "...'";
        $link = "my_queries.php";
        $sql_notify = "INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)";
        $pdo->prepare($sql_notify)->execute([$teacher_id_to_notify, $message, $link]);
        // --- END: Create Notification ---

        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Reply sent successfully!'];
    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to send reply. The query might have been closed already.'];
    }
    
    header('Location: manage_queries.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    header('Location: manage_queries.php');
    exit;
}
?>

