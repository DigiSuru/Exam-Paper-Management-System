<?php
require_once 'config.php';

// 1. Auth Check (Admin Only)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied");
}

$assignment_id = $_GET['id'] ?? null;

if (!$assignment_id) {
    die("Invalid Assignment ID");
}

// 2. Fetch Exam Details via Assignment
$stmt = $pdo->prepare("
    SELECT m.exam_name, c.name as class_name 
    FROM mcq_exam_assignments a
    JOIN mcq_exams m ON a.mcq_exam_id = m.mcq_exam_id
    JOIN classes c ON a.class_id = c.class_id
    WHERE a.assignment_id = ?
");
$stmt->execute([$assignment_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    die("Exam Assignment not found");
}

// 3. Fetch Answer Keys with Subject Names
$stmt_keys = $pdo->prepare("
    SELECT 
        ak.question_number, 
        ak.correct_option, 
        s.name as subject_name,
        u.name as teacher_name,
        ak.updated_at
    FROM mcq_answer_keys ak
    JOIN subjects s ON ak.subject_id = s.subject_id
    LEFT JOIN users u ON ak.submitted_by = u.user_id
    WHERE ak.assignment_id = ?
    ORDER BY ak.question_number ASC
");
$stmt_keys->execute([$assignment_id]);
$keys = $stmt_keys->fetchAll(PDO::FETCH_ASSOC);

// 4. Set Headers for CSV Download
// Filename format: ExamName_ClassName_Date.csv
$filename = preg_replace('/[^a-zA-Z0-9]/', '_', $exam['exam_name'] . "_" . $exam['class_name']) . "_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// 5. Output CSV Data
$output = fopen('php://output', 'w');

// CSV Column Headers
fputcsv($output, ['Question No.', 'Subject', 'Correct Option', 'Submitted By', 'Last Updated']);

if (empty($keys)) {
    fputcsv($output, ['No answer keys found for this assignment.']);
} else {
    foreach ($keys as $row) {
        fputcsv($output, [
            $row['question_number'],
            $row['subject_name'],
            $row['correct_option'],
            $row['teacher_name'] ?? 'Unknown',
            $row['updated_at']
        ]);
    }
}

fclose($output);
exit;
?>