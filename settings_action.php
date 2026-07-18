<?php
require_once 'config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'Unauthorized access.'
    ];
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cutoff_time = $_POST['teacher_login_cutoff'] ?? '';

    try {
        // Insert or update the setting
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('teacher_login_cutoff', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$cutoff_time, $cutoff_time]);

        if (empty($cutoff_time)) {
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Teacher login restriction removed. Logins allowed at all times.'];
        } else {
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Teacher login cutoff time updated successfully.'];
        }
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    }
}

header('Location: dashboard_admin.php');
exit;
?>
