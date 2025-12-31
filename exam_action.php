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
        $valid_statuses = ['pending', 'active', 'completed'];

        if ($action === 'add_exam') {
            // --- Add Logic ---
            $exam_name = trim($_POST['exam_name'] ?? '');
            $exam_status = $_POST['exam_status'] ?? 'pending';

            if (empty($exam_name)) {
                throw new RuntimeException('Exam name cannot be empty.');
            }
            if (!in_array($exam_status, $valid_statuses)) {
                throw new RuntimeException('Invalid status selected.');
            }
            
            $stmt = $pdo->prepare("INSERT INTO exams (name, status) VALUES (?, ?)");
            $stmt->execute([$exam_name, $exam_status]);
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Exam added successfully.'];

        } elseif ($action === 'edit_exam') {
            // --- Edit Logic ---
            $exam_id = $_POST['exam_id'] ?? null;
            $exam_name = trim($_POST['exam_name'] ?? '');
            $exam_status = $_POST['exam_status'] ?? null;

            if (empty($exam_name) || empty($exam_id) || empty($exam_status)) {
                throw new RuntimeException('Invalid data for editing exam.');
            }
            if (!in_array($exam_status, $valid_statuses)) {
                throw new RuntimeException('Invalid status selected.');
            }
            
            $stmt = $pdo->prepare("UPDATE exams SET name = ?, status = ? WHERE exam_id = ?");
            $stmt->execute([$exam_name, $exam_status, $exam_id]);
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Exam updated successfully.'];
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? null;
        $exam_id = $_GET['id'] ?? null;

        if ($action === 'delete_exam' && !empty($exam_id)) {
            // --- Delete Logic ---
            $stmt = $pdo->prepare("DELETE FROM exams WHERE exam_id = ?");
            $stmt->execute([$exam_id]);
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Exam deleted successfully.'];
        }
    }

} catch (RuntimeException $e) {
    // Catch validation errors
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => $e->getMessage()];
} catch (PDOException $e) {
    // Catch database errors
    if ($e->getCode() == '23000') { // Foreign key constraint violation
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'This exam cannot be deleted because it is currently being used in an assignment or paper.'];
    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// --- 3. Redirect back to the manage page ---
header('Location: manage_exams.php');
exit;
?>
