<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$exams = [];
$classes = [];
$subjects = [];
$error_message = null;
$flash_message = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

// Filter
$search_text = $_GET['search'] ?? '';

try {
    $classes = $pdo->query("SELECT class_id, name FROM classes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $subjects = $pdo->query("SELECT subject_id, name FROM subjects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Master Exams
    $sql = "SELECT * FROM mcq_exams";
    if ($search_text) {
        $sql .= " WHERE exam_name LIKE :search";
    }
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    if ($search_text) $stmt->bindValue(':search', "%$search_text%");
    $stmt->execute();
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}
require_once 'header.php'; 
?>

<main class="flex-1 bg-gray-50 min-h-screen p-6 lg:p-10">
    <div class="max-w-6xl mx-auto space-y-6">
        
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Manage MCQ Exams</h1>
                <p class="text-sm text-gray-500 mt-1">Create master exams and assign them to classes.</p>
            </div>
            
            <!-- Search Filter -->
            <form action="" method="GET" class="relative">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search_text); ?>" placeholder="Search exams..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </form>
        </div>

        <!-- Flash Messages -->
        <?php if ($flash_message): ?>
            <div class="rounded-md p-4 border-l-4 shadow-sm <?php echo $flash_message['type'] === 'success' ? 'bg-green-50 border-green-400 text-green-700' : 'bg-red-50 border-red-400 text-red-700'; ?>">
                <?php echo htmlspecialchars($flash_message['message']); ?>
            </div>
        <?php endif; ?>

        <!-- 1. Create Master Exam -->
        <div class="bg-white shadow-md rounded-xl border border-gray-100 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">1. Create Master Exam</h2>
            <form action="mcq_action.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <input type="hidden" name="action" value="create_master_exam">
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Exam Name</label>
                    <input type="text" name="exam_name" required placeholder="e.g. Science Olympiad 2025" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>

                <div class="md:col-span-1">
                    <label class="block text-sm font-medium text-gray-700">Total Questions</label>
                    <input type="number" name="total_questions" required min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>

                <div class="md:col-span-1">
                    <label class="block text-sm font-medium text-gray-700">Paper File (PDF)</label>
                    <input type="file" name="paper_file" required accept=".pdf" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                </div>

                <div class="md:col-span-4 text-right">
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none">
                        Create Master Exam
                    </button>
                </div>
            </form>
        </div>

        <!-- 2. List of Master Exams -->
        <div class="space-y-6">
            <?php foreach ($exams as $exam): 
                // Fetch Assignments (Classes assigned to this exam)
                $stmt_a = $pdo->prepare("
                    SELECT a.*, c.name as class_name 
                    FROM mcq_exam_assignments a 
                    JOIN classes c ON a.class_id = c.class_id 
                    WHERE a.mcq_exam_id = ?
                    ORDER BY c.name
                ");
                $stmt_a->execute([$exam['mcq_exam_id']]);
                $assignments = $stmt_a->fetchAll();
            ?>
            
            <div class="bg-white shadow-md rounded-xl border border-gray-100 overflow-hidden">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($exam['exam_name']); ?></h3>
                        <a href="<?php echo htmlspecialchars($exam['file_path']); ?>" target="_blank" class="text-xs text-indigo-600 hover:underline">View Original Paper</a>
                    </div>
                    <div class="text-sm text-gray-500">Total Qs: <?php echo $exam['total_questions']; ?></div>
                </div>

                <!-- Assignments List -->
                <div class="p-6">
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Assigned Classes</h4>
                    
                    <?php if (empty($assignments)): ?>
                        <p class="text-sm text-gray-400 italic mb-4">Not assigned to any class yet.</p>
                    <?php else: ?>
                        <div class="grid gap-6 mb-6">
                            <?php foreach ($assignments as $assign): 
                                // Fetch ranges for this assignment
                                $stmt_r = $pdo->prepare("SELECT r.*, s.name as subj FROM mcq_subject_ranges r JOIN subjects s ON r.subject_id = s.subject_id WHERE r.assignment_id = ? ORDER BY r.start_q");
                                $stmt_r->execute([$assign['assignment_id']]);
                                $ranges = $stmt_r->fetchAll();
                            ?>
                            <div class="border rounded-lg p-4 bg-gray-50">
                                <div class="flex justify-between items-center mb-3 border-b border-gray-200 pb-2">
                                    <span class="font-semibold text-lg text-gray-800"><?php echo htmlspecialchars($assign['class_name']); ?></span>
                                    
                                    <!-- ADMIN ACTIONS FOR THIS ASSIGNMENT -->
                                    <div class="flex gap-2">
                                        <!-- View/Edit Key -->
                                        <a href="fill_answer_key.php?id=<?php echo $assign['assignment_id']; ?>&admin_mode=1" class="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded hover:bg-indigo-200" title="View Answer Key">
                                            Keys
                                        </a>
                                        <!-- Export CSV -->
                                        <a href="export_mcq_keys.php?id=<?php echo $assign['assignment_id']; ?>" class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200" title="Export to CSV">
                                            Export
                                        </a>
                                        <!-- View Results -->
                                        <a href="exam_results.php?id=<?php echo $assign['assignment_id']; ?>" class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded hover:bg-blue-200" title="Student Results">
                                            Results
                                        </a>
                                        <!-- Remove Assignment -->
                                        <a href="mcq_action.php?action=delete_assignment&id=<?php echo $assign['assignment_id']; ?>" onclick="return confirm('Remove this class assignment? This will delete keys and results!')" class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded hover:bg-red-200">
                                            Remove
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Ranges List -->
                                <div class="text-sm space-y-1 mb-2">
                                    <?php if(empty($ranges)): ?>
                                        <span class="text-xs text-orange-500">No question ranges defined for this class.</span>
                                    <?php else: ?>
                                        <div class="flex flex-wrap gap-2">
                                        <?php foreach($ranges as $r): ?>
                                            <div class="text-xs text-gray-600 bg-white px-2 py-1 rounded border shadow-sm">
                                                <span class="font-medium"><?php echo htmlspecialchars($r['subj']); ?>:</span>
                                                <span>Q<?php echo $r['start_q']; ?>-<?php echo $r['end_q']; ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Add Range Form -->
                                <form action="mcq_action.php" method="POST" class="flex gap-1 mt-3 items-center">
                                    <input type="hidden" name="action" value="add_range_v2">
                                    <input type="hidden" name="assignment_id" value="<?php echo $assign['assignment_id']; ?>">
                                    <span class="text-xs text-gray-500 mr-1">Add Subject:</span>
                                    <select name="subject_id" required class="text-xs border-gray-300 rounded w-32">
                                        <option value="">Select Subject</option>
                                        <?php foreach ($subjects as $s): ?><option value="<?php echo $s['subject_id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?>
                                    </select>
                                    <input type="number" name="start_q" placeholder="Start Q" class="text-xs border-gray-300 rounded w-16">
                                    <input type="number" name="end_q" placeholder="End Q" class="text-xs border-gray-300 rounded w-16">
                                    <button type="submit" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1 rounded text-xs font-bold">+</button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Assign to Class Form -->
                    <form action="mcq_action.php" method="POST" class="flex items-center gap-3 border-t pt-4">
                        <input type="hidden" name="action" value="assign_class">
                        <input type="hidden" name="mcq_exam_id" value="<?php echo $exam['mcq_exam_id']; ?>">
                        <span class="text-sm font-medium text-gray-700">Assign new class:</span>
                        <select name="class_id" required class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['class_id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="inline-flex justify-center py-1.5 px-3 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                            Assign
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</main>
<?php include 'footer.php'; ?>