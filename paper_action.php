<?php
require_once 'config.php';

// --- 1. Check if user is logged in and is an ADMIN ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'You do not have permission to perform this action.'
    ];
    // Redirect non-admins or logged-out users
    header('Location: ' . (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher' ? 'dashboard_teacher.php' : 'index.php'));
    exit;
}

// --- 2. Get POST data ---
$action = $_POST['action'] ?? null;
$paper_id = $_POST['paper_id'] ?? null;

// --- 3. Validate data ---
if (!$action || !$paper_id || !in_array($action, ['approve', 'reject'])) {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'Invalid action or missing paper ID.'
    ];
    header('Location: dashboard_admin.php');
    exit;
}

// --- 4. Determine new status ---
$new_status = '';
if ($action === 'approve') {
    $new_status = 'approved';
} elseif ($action === 'reject') {
    $new_status = 'rejected';
}

try {
    // --- 5. Update paper status in the database ---
    $stmt = $pdo->prepare("UPDATE papers SET status = ? WHERE paper_id = ?");
    $stmt->execute([$new_status, $paper_id]);

    // --- 6. Set success message and redirect ---
    $_SESSION['flash_message'] = [
        'type' => 'success',
        'message' => "Paper has been successfully " . $new_status . "."
    ];
    header('Location: dashboard_admin.php');
    exit;

} catch (PDOException $e) {
    // --- 7. Handle database errors ---
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ];
    header('Location: dashboard_admin.php');
    exit;
}
?>
