<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
$error_message = null;

// Fetch Exams, Classes, and Subjects for dropdowns
try {
    $exams = $pdo->query("SELECT * FROM exams ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $classes = $pdo->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $subjects = $pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Failed to load filters: " . $e->getMessage();
}

$selected_exam = $_GET['exam_id'] ?? '';
$selected_class = $_GET['class_id'] ?? '';
$selected_subject = $_GET['subject_id'] ?? '';

$students = [];
$existing_marks = [];

if ($selected_exam && $selected_class && $selected_subject) {
    try {
        // Fetch students for the class
        $stmt = $pdo->prepare("SELECT * FROM students WHERE class_id = ? ORDER BY roll_no ASC");
        $stmt->execute([$selected_class]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch any existing marks for this exam/class/subject combo
        $stmt_marks = $pdo->prepare("SELECT * FROM exam_marks WHERE exam_id = ? AND subject_id = ?");
        $stmt_marks->execute([$selected_exam, $selected_subject]);
        $marks_data = $stmt_marks->fetchAll(PDO::FETCH_ASSOC);

        // Map marks by student_id
        foreach ($marks_data as $m) {
            $existing_marks[$m['student_id']] = $m;
        }

    } catch (PDOException $e) {
        $error_message = "Error fetching students: " . $e->getMessage();
    }
}

?>
<?php require_once 'header.php'; ?>
<main class="flex-1 bg-gray-50 min-h-screen p-6 lg:p-10">
    <div class="w-full space-y-6">
        
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Enter Marks</h1>
                <p class="mt-1 text-sm text-gray-500">Bulk enter student marks for an exam.</p>
            </div>
        </div>

        <?php if ($flash_message): ?>
            <div class="rounded-md p-4 border-l-4 shadow-sm <?php echo $flash_message['type'] === 'success' ? 'bg-green-50 border-green-400 text-green-800' : 'bg-red-50 border-red-400 text-red-800'; ?>">
                <?php echo htmlspecialchars($flash_message['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="rounded-md bg-red-50 p-4 border-l-4 border-red-400 text-red-800">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white shadow-sm border border-gray-200 rounded-xl p-6">
            <form action="enter_marks.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
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
                    <label class="block text-sm font-medium text-gray-700">Subject</label>
                    <select name="subject_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?php echo $s['subject_id']; ?>" <?php echo $selected_subject == $s['subject_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="w-full bg-indigo-600 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-indigo-500">Load Students</button>
                </div>
            </form>
        </div>

        <?php if ($selected_exam && $selected_class && $selected_subject): ?>
            <div class="bg-white shadow-sm border border-gray-200 rounded-xl overflow-hidden">
                <form action="enter_marks_action.php" method="POST">
                    <input type="hidden" name="exam_id" value="<?php echo htmlspecialchars($selected_exam); ?>">
                    <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($selected_class); ?>">
                    <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($selected_subject); ?>">
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Roll No</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Marks Obtained</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Max Marks</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remarks</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($students)): ?>
                                    <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No students found in this class.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($students as $index => $student): 
                                        $sid = $student['student_id'];
                                        $m = $existing_marks[$sid]['marks_obtained'] ?? '';
                                        $max = $existing_marks[$sid]['max_marks'] ?? '100';
                                        $rem = $existing_marks[$sid]['remarks'] ?? '';
                                    ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-3 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['roll_no']); ?></td>
                                            <td class="px-6 py-3 text-sm text-gray-900">
                                                <?php echo htmlspecialchars($student['name']); ?>
                                                <input type="hidden" name="students[<?php echo $index; ?>][student_id]" value="<?php echo $sid; ?>">
                                            </td>
                                            <td class="px-6 py-3">
                                                <input type="number" step="0.1" name="students[<?php echo $index; ?>][marks_obtained]" value="<?php echo htmlspecialchars($m); ?>" class="w-24 border-gray-300 rounded-md shadow-sm sm:text-sm">
                                            </td>
                                            <td class="px-6 py-3">
                                                <input type="number" step="0.1" name="students[<?php echo $index; ?>][max_marks]" value="<?php echo htmlspecialchars($max); ?>" class="w-24 border-gray-300 rounded-md shadow-sm sm:text-sm">
                                            </td>
                                            <td class="px-6 py-3">
                                                <input type="text" name="students[<?php echo $index; ?>][remarks]" value="<?php echo htmlspecialchars($rem); ?>" class="w-full border-gray-300 rounded-md shadow-sm sm:text-sm" placeholder="Optional">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($students)): ?>
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 text-right">
                            <button type="submit" class="bg-indigo-600 text-white rounded-md px-6 py-2 text-sm font-semibold hover:bg-indigo-500 shadow-sm">Save All Marks</button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>
