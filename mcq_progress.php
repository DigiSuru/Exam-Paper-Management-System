<?php
require_once 'config.php';

// 1. Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// 2. Fetch Exams for Dropdown
$exams = $pdo->query("SELECT mcq_exam_id, exam_name FROM mcq_exams ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// 3. Get Selected Exam ID (Default to latest)
$selected_exam_id = $_GET['exam_id'] ?? ($exams[0]['mcq_exam_id'] ?? null);

$progress_data = [];
$exam_details = null;

if ($selected_exam_id) {
    // Fetch Exam Info
    $stmt = $pdo->prepare("SELECT e.*, c.name as class_name FROM mcq_exams e JOIN classes c ON e.class_id = c.class_id WHERE mcq_exam_id = ?");
    $stmt->execute([$selected_exam_id]);
    $exam_details = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch Subject Ranges & Progress
    // This query joins ranges with assignments (to find teachers) and answer_keys (to count submissions)
    $sql = "
        SELECT 
            r.range_id,
            r.subject_id,
            r.start_q,
            r.end_q,
            s.name as subject_name,
            (SELECT COUNT(*) FROM mcq_answer_keys k WHERE k.mcq_exam_id = r.mcq_exam_id AND k.question_number BETWEEN r.start_q AND r.end_q) as filled_count,
            (
                SELECT GROUP_CONCAT(u.name SEPARATOR ', ')
                FROM assignments a
                JOIN users u ON a.teacher_id = u.user_id
                WHERE a.class_id = :class_id AND a.subject_id = r.subject_id
            ) as assigned_teachers
        FROM mcq_subject_ranges r
        JOIN subjects s ON r.subject_id = s.subject_id
        WHERE r.mcq_exam_id = :exam_id
        ORDER BY r.start_q ASC
    ";
    
    $stmt_prog = $pdo->prepare($sql);
    $stmt_prog->execute(['exam_id' => $selected_exam_id, 'class_id' => $exam_details['class_id']]);
    $progress_data = $stmt_prog->fetchAll(PDO::FETCH_ASSOC);
}

require_once 'header.php';
?>

<main class="flex-1 bg-gray-50 min-h-screen p-6 lg:p-10">
    <div class="max-w-6xl mx-auto space-y-6">
        
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Key Submission Progress</h1>
                <p class="mt-1 text-sm text-gray-500">Track which subjects have submitted their answer keys.</p>
            </div>
            
            <!-- Exam Selector -->
            <form action="" method="GET" class="mt-4 sm:mt-0">
                <select name="exam_id" onchange="this.form.submit()" class="block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                    <?php foreach ($exams as $e): ?>
                        <option value="<?php echo $e['mcq_exam_id']; ?>" <?php echo $selected_exam_id == $e['mcq_exam_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['exam_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($selected_exam_id && $exam_details): ?>
            
            <!-- Exam Summary Card -->
            <div class="bg-white shadow rounded-lg p-6 border border-gray-100">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                    <div>
                        <p class="text-sm text-gray-500">Class</p>
                        <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($exam_details['class_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Questions</p>
                        <p class="text-lg font-semibold text-gray-900"><?php echo $exam_details['total_questions']; ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Subjects Defined</p>
                        <p class="text-lg font-semibold text-gray-900"><?php echo count($progress_data); ?></p>
                    </div>
                </div>
            </div>

            <!-- Progress Table -->
            <div class="bg-white shadow-md rounded-xl overflow-hidden border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject & Range</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Teacher(s)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($progress_data)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                        No subject ranges defined for this exam yet. 
                                        <a href="manage_mcq.php" class="text-indigo-600 hover:underline">Go to Manage MCQ</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($progress_data as $row): 
                                    $total_q = $row['end_q'] - $row['start_q'] + 1;
                                    $filled = $row['filled_count'];
                                    $percent = ($total_q > 0) ? round(($filled / $total_q) * 100) : 0;
                                    
                                    $status_color = 'bg-gray-100 text-gray-800';
                                    $status_text = 'Pending';
                                    
                                    if ($filled == $total_q) {
                                        $status_color = 'bg-green-100 text-green-800';
                                        $status_text = 'Completed';
                                    } elseif ($filled > 0) {
                                        $status_color = 'bg-yellow-100 text-yellow-800';
                                        $status_text = 'In Progress';
                                    }
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['subject_name']); ?></div>
                                        <div class="text-xs text-gray-500">Q<?php echo $row['start_q']; ?> - Q<?php echo $row['end_q']; ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($row['assigned_teachers']): ?>
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($row['assigned_teachers']); ?></div>
                                        <?php else: ?>
                                            <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="w-full max-w-xs">
                                            <div class="flex items-center justify-between text-xs mb-1">
                                                <span class="text-gray-600"><?php echo $filled; ?> / <?php echo $total_q; ?></span>
                                                <span class="font-medium text-gray-900"><?php echo $percent; ?>%</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                                <div class="bg-indigo-600 h-2.5 rounded-full transition-all duration-500" style="width: <?php echo $percent; ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?php echo $status_color; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
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

<?php include 'footer.php'; ?>