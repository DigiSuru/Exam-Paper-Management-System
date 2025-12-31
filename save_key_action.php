<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$is_admin = ($_SESSION['role'] === 'admin');
$teacher_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CHANGE: We now use assignment_id
    $assignment_id = $_POST['assignment_id'];
    $subject_id = $_POST['subject_id'];
    $answers = $_POST['answers'] ?? []; 

    try {
        // --- Backend Authorization Check ---
        if (!$is_admin) {
            // 1. Get Class ID from assignment
            $stmt_assign = $pdo->prepare("SELECT class_id FROM mcq_exam_assignments WHERE assignment_id = ?");
            $stmt_assign->execute([$assignment_id]);
            $assign_data = $stmt_assign->fetch(PDO::FETCH_ASSOC);
            
            if (!$assign_data) throw new Exception("Invalid Assignment");

            // 2. Check Teacher Assignment (Core System)
            $stmt_auth = $pdo->prepare("
                SELECT COUNT(*) FROM assignments 
                WHERE class_id = ? AND subject_id = ? AND teacher_id = ?
            ");
            $stmt_auth->execute([$assign_data['class_id'], $subject_id, $teacher_id]);
            
            if ($stmt_auth->fetchColumn() == 0) {
                $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Security Alert: You are not assigned to this subject.'];
                header("Location: fill_answer_key.php?id=$assignment_id");
                exit;
            }
        }
        // ----------------------------------------

        $pdo->beginTransaction();

        // Check lock status using assignment_id
        $stmt_check = $pdo->prepare("SELECT is_locked FROM mcq_answer_keys WHERE assignment_id = ? AND question_number = ?");
        
        // Upsert Query using assignment_id
        $stmt_insert = $pdo->prepare("
            INSERT INTO mcq_answer_keys (assignment_id, subject_id, question_number, correct_option, submitted_by, is_locked)
            VALUES (?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE 
                correct_option = VALUES(correct_option), 
                submitted_by = VALUES(submitted_by)
        ");

        foreach ($answers as $q_num => $option) {
            // Check lock status (unless admin)
            if (!$is_admin) {
                $stmt_check->execute([$assignment_id, $q_num]);
                $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);
                
                if ($existing && $existing['is_locked'] == 1) {
                    continue; 
                }
            }

            $stmt_insert->execute([$assignment_id, $subject_id, $q_num, $option, $teacher_id]);
        }

        $pdo->commit();
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Answer keys saved successfully.'];

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error saving keys: ' . $e->getMessage()];
    }

    header("Location: fill_answer_key.php?id=$assignment_id"); 
    exit;
}
?>