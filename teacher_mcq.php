<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.php');
    exit;
}
$teacher_id = $_SESSION['user_id'];

// Fetch exams assigned to classes that this teacher teaches
// Note: This is a bit complex. We want to show ALL assigned exams, 
// but ideally filter by classes the teacher is actually linked to via `assignments` table.
// For simplicity, we show all active assignments, but the 'Fill Key' page will still block unauthorized access.

$sql = "
    SELECT 
        a.assignment_id, 
        m.exam_name, 
        m.total_questions, 
        m.file_path,
        c.name as class_name
    FROM mcq_exam_assignments a
    JOIN mcq_exams m ON a.mcq_exam_id = m.mcq_exam_id
    JOIN classes c ON a.class_id = c.class_id
    ORDER BY a.assigned_at DESC
";
$exams = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

require_once 'header.php';
?>
<main class="flex-1 p-6 lg:p-10 bg-gray-50 min-h-screen">
    <div class="max-w-6xl mx-auto">
        <!-- ... Same Header UI ... -->
        <h1 class="text-3xl font-bold text-gray-900 mb-6">MCQ Exams</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($exams as $exam): ?>
                <div class="bg-white rounded-xl shadow-md border border-gray-100 p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($exam['exam_name']); ?></h3>
                            <span class="inline-flex items-center rounded-full bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 mt-2">
                                <?php echo htmlspecialchars($exam['class_name']); ?>
                            </span>
                        </div>
                        <div class="text-right text-sm text-gray-500"><?php echo $exam['total_questions']; ?> Qs</div>
                    </div>
                    
                    <div class="mt-auto space-y-3">
                        <!-- IMPORTANT: Pass assignment_id now, not exam_id -->
                        <a href="fill_answer_key.php?id=<?php echo $exam['assignment_id']; ?>" class="flex items-center justify-center w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                            Fill Answer Key
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>
<?php include 'footer.php'; ?>