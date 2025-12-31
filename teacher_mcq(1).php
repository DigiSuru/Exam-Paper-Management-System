<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.php');
    exit;
}
$teacher_id = $_SESSION['user_id'];

$exams = $pdo->query("
    SELECT m.*, c.name as class_name 
    FROM mcq_exams m 
    JOIN classes c ON m.class_id = c.class_id 
    ORDER BY m.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

require_once 'header.php';
?>
<main class="flex-1 p-6 lg:p-10 bg-gray-50 min-h-screen">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900">MCQ Exams & Grading</h1>
            <a href="dashboard_teacher.php" class="text-indigo-600 hover:text-indigo-800">&larr; Back to Dashboard</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($exams as $exam): ?>
                <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6 hover:shadow-lg transition-shadow flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($exam['exam_name']); ?></h3>
                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 mt-2">
                                <?php echo htmlspecialchars($exam['class_name']); ?>
                            </span>
                        </div>
                        <div class="text-right text-sm text-gray-500">
                            <?php echo $exam['total_questions']; ?> Qs
                        </div>
                    </div>
                    
                    <div class="mt-auto space-y-3">
                        <a href="fill_answer_key.php?id=<?php echo $exam['mcq_exam_id']; ?>" class="flex items-center justify-center w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Fill Answer Key
                        </a>

                        <div class="flex gap-2">
                            <a href="upload_result.php?exam_id=<?php echo $exam['mcq_exam_id']; ?>" class="flex-1 flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Grade Student
                            </a>
                            <a href="exam_results.php?id=<?php echo $exam['mcq_exam_id']; ?>" class="flex-1 flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                View Results
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>
<?php include 'footer.php'; ?>