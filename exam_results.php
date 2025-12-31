<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$assignment_id = $_GET['id'] ?? null;
if (!$assignment_id) die("Invalid Request");

// Fetch Exam & Class Info
$stmt = $pdo->prepare("
    SELECT m.exam_name, m.total_questions, c.name as class_name 
    FROM mcq_exam_assignments a
    JOIN mcq_exams m ON a.mcq_exam_id = m.mcq_exam_id
    JOIN classes c ON a.class_id = c.class_id
    WHERE a.assignment_id = ?
");
$stmt->execute([$assignment_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$exam) die("Exam not found.");

// Fetch Results for this Assignment
$stmt_res = $pdo->prepare("SELECT * FROM student_results WHERE assignment_id = ? ORDER BY total_score DESC");
$stmt_res->execute([$assignment_id]);
$results = $stmt_res->fetchAll(PDO::FETCH_ASSOC);

require_once 'header.php';
?>

<main class="flex-1 p-6 lg:p-10 bg-gray-50 min-h-screen">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Exam Results</h1>
                <p class="text-gray-500">
                    <?php echo htmlspecialchars($exam['exam_name']); ?> 
                    <span class="text-sm bg-gray-200 px-2 py-0.5 rounded ml-2"><?php echo htmlspecialchars($exam['class_name']); ?></span>
                </p>
            </div>
            <!-- Teachers can use this button, Admins usually just view -->
            <?php if($_SESSION['role'] === 'teacher'): ?>
            <a href="upload_result.php?exam_id=<?php echo $assignment_id; // Note: upload_result.php needs update too if not already done ?>" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                + Grade New Student
            </a>
            <?php endif; ?>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rank</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Score</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Accuracy</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">OMR Sheet</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if(empty($results)): ?>
                        <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No students graded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($results as $index => $res): 
                            $total_attempts = $res['correct_count'] + $res['wrong_count'];
                            $accuracy = ($total_attempts > 0) ? ($res['correct_count'] / $total_attempts) * 100 : 0;
                        ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#<?php echo $index + 1; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($res['student_name']); ?></div>
                                <div class="text-sm text-gray-500">Roll: <?php echo htmlspecialchars($res['roll_no']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-lg font-bold text-indigo-600"><?php echo $res['total_score']; ?></span>
                                <span class="text-xs text-gray-400">/ <?php echo $exam['total_questions']; ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div class="flex items-center">
                                    <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                        <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $accuracy; ?>%"></div>
                                    </div>
                                    <span><?php echo round($accuracy); ?>%</span>
                                </div>
                                <div class="text-xs mt-1 text-gray-400">
                                    C:<?php echo $res['correct_count']; ?> W:<?php echo $res['wrong_count']; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <?php if($res['omr_file_path']): ?>
                                    <a href="<?php echo htmlspecialchars($res['omr_file_path']); ?>" target="_blank" class="text-indigo-600 hover:text-indigo-900">View OMR</a>
                                <?php else: ?>
                                    <span class="text-gray-300">No File</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php include 'footer.php'; ?>