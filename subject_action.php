<?php
require_once 'config.php';

// --- 1. Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You are not authorized.'];
    header('Location: index.php');
    exit;
}

// --- 2. Input Validation & Action Routing ---
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? null;

        if ($action === 'add_subject') {
            // --- Add Logic ---
            $subject_name = trim($_POST['subject_name'] ?? '');
            
            if (empty($subject_name)) {
                throw new RuntimeException('Subject name cannot be empty.');
            }
            
            $stmt = $pdo->prepare("INSERT INTO subjects (name) VALUES (?)");
            $stmt->execute([$subject_name]);
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Subject added successfully.'];

        } elseif ($action === 'edit_subject') {
            // --- Edit Logic ---
            $subject_id = $_POST['subject_id'] ?? null;
            $subject_name = trim($_POST['subject_name'] ?? '');

            if (empty($subject_name) || empty($subject_id)) {
                throw new RuntimeException('Invalid data for editing subject.');
            }
            
            $stmt = $pdo->prepare("UPDATE subjects SET name = ? WHERE subject_id = ?");
            $stmt->execute([$subject_name, $subject_id]);
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Subject updated successfully.'];
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? null;
        $subject_id = $_GET['id'] ?? null;

        if ($action === 'delete_subject' && !empty($subject_id)) {
            // --- Delete Logic ---
            $stmt = $pdo->prepare("DELETE FROM subjects WHERE subject_id = ?");
            $stmt->execute([$subject_id]);
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Subject deleted successfully.'];
        }
    }

} catch (RuntimeException $e) {
    // Catch validation errors
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => $e->getMessage()];
} catch (PDOException $e) {
    // Catch database errors
    if ($e->getCode() == '23000') { // Foreign key constraint violation
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'This subject cannot be deleted because it is currently being used in an assignment.'];
    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// --- 3. Redirect back to the manage page ---
header('Location: manage_subjects.php');
exit;
?>
