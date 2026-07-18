<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error_message = null;

try {
    $exams = $pdo->query("SELECT * FROM exams ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $classes = $pdo->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Failed to load filters: " . $e->getMessage();
}

$selected_exam = $_GET['exam_id'] ?? '';
$selected_class = $_GET['class_id'] ?? '';

$students = [];
$subjects = [];
$marks_matrix = []; // Matrix: student_id => subject_id => mark_data

if ($selected_exam && $selected_class) {
    try {
        // 1. Fetch Students
        $stmt_students = $pdo->prepare("SELECT * FROM students WHERE class_id = ? ORDER BY roll_no ASC");
        $stmt_students->execute([$selected_class]);
        $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fetch Subjects that actually have marks for this class & exam
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

        foreach ($all_marks as $m) {
            $marks_matrix[$m['student_id']][$m['subject_id']] = $m;
        }

    } catch (PDOException $e) {
        $error_message = "Error fetching results: " . $e->getMessage();
    }
}
?>
<?php require_once 'header.php'; ?>

<main class="flex-1 bg-gray-50 min-h-screen p-6 lg:p-10">
    <div class="w-full space-y-6">
        
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Report Cards</h1>
                <p class="mt-1 text-sm text-gray-500">View aggregate student results for a specific exam and class.</p>
            </div>
            <div class="mt-4 sm:mt-0 space-x-2">
                <button onclick="window.print()" class="bg-white text-gray-700 rounded-md px-4 py-2 text-sm font-semibold border border-gray-300 shadow-sm hover:bg-gray-50">
                    Print / Export PDF
                </button>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="rounded-md bg-red-50 p-4 border-l-4 border-red-400 text-red-800">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Filters (Hidden during print) -->
        <div class="bg-white shadow-sm border border-gray-200 rounded-xl p-6 print:hidden">
            <form action="view_results.php" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Exam</label>
                    <select name="exam_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">Select Exam</option>
                        <?php foreach ($exams as $e): ?>
                            <option value="<?php echo $e['exam_id']; ?>" <?php echo $selected_exam == $e['exam_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($e['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Class</label>
                    <select name="class_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['class_id']; ?>" <?php echo $selected_class == $c['class_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="w-full bg-indigo-600 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-indigo-500">Generate Report</button>
                </div>
            </form>
        </div>

        <?php if ($selected_exam && $selected_class): ?>
            <div class="bg-white shadow-sm border border-gray-200 rounded-xl overflow-hidden print:shadow-none print:border-none">
                <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 print:bg-white text-center hidden print:block">
                    <h2 class="text-2xl font-bold">Official Exam Report</h2>
                    <p class="text-gray-600 mt-1">Class: <?php 
                        foreach($classes as $c) if($c['class_id']==$selected_class) echo $c['name']; 
                    ?> | Exam: <?php 
                        foreach($exams as $e) if($e['exam_id']==$selected_exam) echo $e['name']; 
                    ?></p>
                </div>

                <div class="overflow-x-auto p-4">
                    <table class="min-w-full divide-y divide-gray-200 border border-gray-200">
                        <thead class="bg-gray-100 print:bg-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase border-r border-gray-200">Roll No</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase border-r border-gray-200">Name</th>
                                <?php foreach ($subjects as $subj): ?>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase border-r border-gray-200">
                                        <?php echo htmlspecialchars($subj['name']); ?>
                                    </th>
                                <?php endforeach; ?>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase border-r border-gray-200 bg-indigo-50 text-indigo-800">Total</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase bg-indigo-50 text-indigo-800">Percentage</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($students)): ?>
                                <tr><td colspan="<?php echo count($subjects) + 4; ?>" class="px-6 py-8 text-center text-gray-500">No students found.</td></tr>
                            <?php elseif (empty($subjects)): ?>
                                <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">No marks have been entered for this class and exam yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): 
                                    $sid = $student['student_id'];
                                    $total_obtained = 0;
                                    $total_max = 0;
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 text-sm font-medium text-gray-900 border-r border-gray-200"><?php echo htmlspecialchars($student['roll_no']); ?></td>
                                        <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200 whitespace-nowrap"><?php echo htmlspecialchars($student['name']); ?></td>
                                        
                                        <?php foreach ($subjects as $subj): 
                                            $sub_id = $subj['subject_id'];
                                            if (isset($marks_matrix[$sid][$sub_id])) {
                                                $mark_data = $marks_matrix[$sid][$sub_id];
                                                $obt = floatval($mark_data['marks_obtained']);
                                                $max = floatval($mark_data['max_marks']);
                                                $total_obtained += $obt;
                                                $total_max += $max;
                                                
                                                // Color code failing marks (less than 33%)
                                                $is_fail = ($max > 0 && ($obt / $max) < 0.33);
                                                $color_class = $is_fail ? 'text-red-600 font-bold' : 'text-gray-900';
                                                
                                                echo "<td class='px-4 py-2 text-center text-sm border-r border-gray-200 $color_class'>{$obt}/{$max}</td>";
                                            } else {
                                                echo "<td class='px-4 py-2 text-center text-sm text-gray-400 border-r border-gray-200'>-</td>";
                                            }
                                        endforeach; ?>
                                        
                                        <?php 
                                        $percentage = 0;
                                        if ($total_max > 0) {
                                            $percentage = round(($total_obtained / $total_max) * 100, 2);
                                        }
                                        $perc_color = $percentage < 33 ? 'text-red-600 font-bold' : ($percentage >= 75 ? 'text-green-600 font-bold' : 'text-gray-900 font-medium');
                                        ?>
                                        
                                        <td class="px-4 py-2 text-center text-sm font-bold text-gray-900 border-r border-gray-200 bg-indigo-50/30">
                                            <?php echo $total_obtained; ?> / <?php echo $total_max; ?>
                                        </td>
                                        <td class="px-4 py-2 text-center text-sm <?php echo $perc_color; ?> bg-indigo-50/30">
                                            <?php echo $total_max > 0 ? $percentage . '%' : '-'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>
