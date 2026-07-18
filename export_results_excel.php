<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$selected_exam = $_GET['exam_id'] ?? '';
$selected_class = $_GET['class_id'] ?? '';

if (!$selected_exam || !$selected_class) {
    die("Exam and Class must be selected.");
}

try {
    // Fetch Class and Exam Names
    $stmt_class = $pdo->prepare("SELECT name FROM classes WHERE class_id = ?");
    $stmt_class->execute([$selected_class]);
    $class_name = $stmt_class->fetchColumn();

    $stmt_exam = $pdo->prepare("SELECT name FROM exams WHERE exam_id = ?");
    $stmt_exam->execute([$selected_exam]);
    $exam_name = $stmt_exam->fetchColumn();

    // 1. Fetch Students
    $stmt_students = $pdo->prepare("SELECT * FROM students WHERE class_id = ? ORDER BY roll_no ASC");
    $stmt_students->execute([$selected_class]);
    $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Subjects
    $stmt_subj = $pdo->prepare("
        SELECT DISTINCT s.subject_id, s.name 
        FROM exam_marks em
        JOIN students st ON em.student_id = st.student_id
        JOIN subjects s ON em.subject_id = s.subject_id
        WHERE st.class_id = ? AND em.exam_id = ?
        ORDER BY s.name ASC
    ");
    $stmt_subj->execute([$selected_class, $selected_exam]);
    $subjects = $stmt_subj->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Marks
    $stmt_marks = $pdo->prepare("
        SELECT em.* 
        FROM exam_marks em
        JOIN students st ON em.student_id = st.student_id
        WHERE st.class_id = ? AND em.exam_id = ?
    ");
    $stmt_marks->execute([$selected_class, $selected_exam]);
    $all_marks = $stmt_marks->fetchAll(PDO::FETCH_ASSOC);

    $marks_matrix = [];
    foreach ($all_marks as $m) {
        $marks_matrix[$m['student_id']][$m['subject_id']] = $m;
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Generate CSV File
$filename = "Exam_Results_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $class_name) . "_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $exam_name) . "_" . date('Ymd') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Title Rows
fputcsv($output, ['Official Exam Report']);
fputcsv($output, ['Class:', $class_name]);
fputcsv($output, ['Exam:', $exam_name]);
fputcsv($output, ['Print Date:', date('Y-m-d H:i:s')]);
fputcsv($output, []); // Empty row

// Header Row
$headers = ['Roll No', 'Name'];
foreach ($subjects as $subj) {
    $headers[] = $subj['name'];
}
$headers[] = 'Total';
$headers[] = 'Percentage';
fputcsv($output, $headers);

// Data Rows
if (empty($students)) {
    fputcsv($output, ['No students found.']);
} elseif (empty($subjects)) {
    fputcsv($output, ['No marks entered for this class and exam.']);
} else {
    foreach ($students as $student) {
        $sid = $student['student_id'];
        $total_obtained = 0;
        $total_max = 0;
        
        $row = [];
        $row[] = $student['roll_no'];
        $row[] = $student['name'];
        
        foreach ($subjects as $subj) {
            $sub_id = $subj['subject_id'];
            if (isset($marks_matrix[$sid][$sub_id])) {
                $m = $marks_matrix[$sid][$sub_id];
                $row[] = $m['marks_obtained'] . " / " . $m['max_marks'];
                $total_obtained += $m['marks_obtained'];
                $total_max += $m['max_marks'];
            } else {
                $row[] = 'N/A';
            }
        }
        
        $row[] = $total_obtained . " / " . $total_max;
        if ($total_max > 0) {
            $percentage = ($total_obtained / $total_max) * 100;
            $row[] = number_format($percentage, 2) . '%';
        } else {
            $row[] = 'N/A';
        }
        
        fputcsv($output, $row);
    }
}

fclose($output);
exit;
