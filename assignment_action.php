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

        if ($action === 'add_assignment') {
            // --- Add Logic ---
            $teacher_id = $_POST['teacher_id'] ?? null;
            $class_id = $_POST['class_id'] ?? null;
            $subject_id = $_POST['subject_id'] ?? null;

            if (empty($teacher_id) || empty($class_id) || empty($subject_id)) {
                throw new RuntimeException('All fields (Teacher, Class, Subject) are required.');
            }
            
            // Check if this assignment already exists
            $stmt = $pdo->prepare("SELECT 1 FROM assignments WHERE teacher_id = ? AND class_id = ? AND subject_id = ?");
            $stmt->execute([$teacher_id, $class_id, $subject_id]);
            if ($stmt->fetch()) {
                throw new RuntimeException('This exact assignment (Teacher + Class + Subject) already exists.');
            }

            // Insert new assignment
            $stmt = $pdo->prepare("INSERT INTO assignments (teacher_id, class_id, subject_id) VALUES (?, ?, ?)");
            $stmt->execute([$teacher_id, $class_id, $subject_id]);
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Assignment created successfully.'];
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? null;
        $assignment_id = $_GET['id'] ?? null;

        if ($action === 'delete_assignment' && !empty($assignment_id)) {
            // --- Delete Logic ---
            $stmt = $pdo->prepare("DELETE FROM assignments WHERE assignment_id = ?");
            $stmt->execute([$assignment_id]);
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Assignment deleted successfully.'];
        }
    }

} catch (RuntimeException $e) {
    // Catch validation errors
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => $e->getMessage()];
} catch (PDOException $e) {
    // Catch database errors
    if ($e->getCode() == '23000') { // Foreign key constraint violation
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'This assignment cannot be deleted because it is currently being used in a paper submission.'];
    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// --- 3. Redirect back to the manage page ---
header('Location: manage_assignments.php');
exit;
?>
