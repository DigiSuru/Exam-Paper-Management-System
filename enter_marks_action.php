<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_id = $_POST['exam_id'] ?? '';
    $class_id = $_POST['class_id'] ?? '';
    $subject_id = $_POST['subject_id'] ?? '';
    $students = $_POST['students'] ?? [];

    if (empty($exam_id) || empty($class_id) || empty($subject_id)) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Missing exam, class, or subject.'];
        header('Location: enter_marks.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO exam_marks (student_id, exam_id, subject_id, marks_obtained, max_marks, remarks) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                marks_obtained = VALUES(marks_obtained), 
                max_marks = VALUES(max_marks), 
                remarks = VALUES(remarks)
        ");

        foreach ($students as $st) {
            $sid = $st['student_id'] ?? '';
            $marks = $st['marks_obtained'] ?? '';
            $max = $st['max_marks'] ?? '100';
            $remarks = $st['remarks'] ?? '';

            // Only insert/update if marks_obtained is provided
            if ($sid !== '' && $marks !== '') {
                $stmt->execute([$sid, $exam_id, $subject_id, $marks, $max, $remarks]);
            }
        }

        $pdo->commit();
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Marks saved successfully!'];

    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error saving marks: ' . $e->getMessage()];
    }

    // Redirect back with the same filters applied
    header("Location: enter_marks.php?exam_id=$exam_id&class_id=$class_id&subject_id=$subject_id");
    exit;
}

header('Location: enter_marks.php');
exit;
