<?php
require_once 'config.php';

// --- 1. Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You are not authorized.'];
    header('Location: index.php');
    exit;
}

// --- 2. Input Validation & Action Routing ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $teacher_id = $_POST['teacher_id'] ?? null;
    $new_password = $_POST['new_password'] ?? null;

    if ($action === 'reset_password' && !empty($teacher_id) && !empty($new_password)) {
        try {
            // Validate new password (basic length check)
            if (strlen($new_password) < 8) {
                throw new RuntimeException('Password must be at least 8 characters long.');
            }
            
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update the user's password in the database
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ? AND role = 'teacher'");
            $stmt->execute([$hashed_password, $teacher_id]);
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Teacher password updated successfully.'];
            
        } catch (RuntimeException $e) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => $e->getMessage()];
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid form submission.'];
    }
    
    // Redirect back to the manage teachers page
    header('Location: manage_teachers.php');
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? null;
    $teacher_id = $_GET['id'] ?? null;

    if ($action === 'delete_teacher' && !empty($teacher_id)) {
        try {
            // Attempt to delete the teacher
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND role = 'teacher'");
            $stmt->execute([$teacher_id]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Teacher deleted successfully.'];
            } else {
                $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Teacher not found or already deleted.'];
            }
            
        } catch (PDOException $e) {
            // Check for foreign key constraint violation (Error code 23000)
            if ($e->getCode() == '23000') {
                $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Cannot delete teacher: They are associated with active assignments or papers.'];
            } else {
                $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
            }
        }
    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid action or missing ID.'];
    }

    // Redirect back to the manage teachers page
    header('Location: manage_teachers.php');
    exit;
}
?>
