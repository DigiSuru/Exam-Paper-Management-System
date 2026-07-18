<?php
require_once 'config.php';
require_once 'tcpdf/tcpdf.php';

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

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Exam Paper Management System');
$pdf->SetTitle("Report Card - {$class_name} - {$exam_name}");

// Set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, 20, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);

// Add a page
$pdf->AddPage();

// Print Document Header
$html = '
<h2 style="text-align:center;">Official Exam Report</h2>
<p style="text-align:center;">
    <strong>Class:</strong> ' . htmlspecialchars($class_name) . ' | 
    <strong>Exam:</strong> ' . htmlspecialchars($exam_name) . ' | 
    <strong>Print Date:</strong> ' . date('Y-m-d H:i:s') . '
</p>
<hr>
<br>
';

// Build the table HTML
$html .= '<table border="1" cellpadding="5" cellspacing="0">';
$html .= '<tr style="background-color:#f3f4f6; font-weight:bold;">';
$html .= '<th width="10%">Roll No</th>';
$html .= '<th width="20%">Name</th>';

$col_width = (70 / (count($subjects) + 2)); // Remaining 70% width split across subjects + total + percentage

foreach ($subjects as $subj) {
    $html .= '<th width="' . $col_width . '%" align="center">' . htmlspecialchars($subj['name']) . '</th>';
}
$html .= '<th width="' . $col_width . '%" align="center">Total</th>';
$html .= '<th width="' . $col_width . '%" align="center">%</th>';
$html .= '</tr>';

if (empty($students)) {
    $html .= '<tr><td colspan="' . (count($subjects) + 4) . '" align="center">No students found.</td></tr>';
} elseif (empty($subjects)) {
    $html .= '<tr><td colspan="4" align="center">No marks entered for this class and exam.</td></tr>';
} else {
    foreach ($students as $student) {
        $sid = $student['student_id'];
        $total_obtained = 0;
        $total_max = 0;
        
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($student['roll_no']) . '</td>';
        $html .= '<td>' . htmlspecialchars($student['name']) . '</td>';
        
        foreach ($subjects as $subj) {
            $sub_id = $subj['subject_id'];
            if (isset($marks_matrix[$sid][$sub_id])) {
                $m = $marks_matrix[$sid][$sub_id];
                $html .= '<td align="center">' . $m['marks_obtained'] . '<br><small>/ ' . $m['max_marks'] . '</small></td>';
                $total_obtained += $m['marks_obtained'];
                $total_max += $m['max_marks'];
            } else {
                $html .= '<td align="center">N/A</td>';
            }
        }
        
        $html .= '<td align="center"><strong>' . $total_obtained . '</strong><br><small>/ ' . $total_max . '</small></td>';
        
        if ($total_max > 0) {
            $percentage = ($total_obtained / $total_max) * 100;
            $html .= '<td align="center"><strong>' . number_format($percentage, 2) . '%</strong></td>';
        } else {
            $html .= '<td align="center">N/A</td>';
        }
        
        $html .= '</tr>';
    }
}
$html .= '</table>';

// Print text using writeHTMLCell()
$pdf->writeHTML($html, true, false, true, false, '');

// Output PDF to browser
$filename = "Exam_Results_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $class_name) . "_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $exam_name) . ".pdf";
$pdf->Output($filename, 'D'); // 'D' forces download
