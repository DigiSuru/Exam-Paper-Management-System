<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// 1. Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You must be a teacher.'];
    header('Location: index.php');
    exit;
}
$teacher_id = $_SESSION['user_id'];
$error_message = '';
$flash_message = get_flash_message();
$assigned_papers = [];

try {
    // 2. Find all class/subject pairs assigned to this teacher
    $stmt = $pdo->prepare("SELECT class_id, subject_id FROM assignments WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $assignments = $stmt->fetchAll();

    if (empty($assignments)) {
        $error_message = "You are not currently assigned to any classes or subjects.";
    } else {
        // 3. Build a dynamic query to find papers matching the assignments
        $placeholders = [];
        $params = [];
        $where_clauses = [];

        foreach ($assignments as $assignment) {
            $where_clauses[] = "(p.class_id = ? AND p.subject_id = ?)";
            $params[] = $assignment['class_id'];
            $params[] = $assignment['subject_id'];
        }
        
        $where_sql = implode(" OR ", $where_clauses);

        // 4. Fetch all papers matching the assignments, and check if this teacher submitted a key
        $sql = "
            SELECT 
                p.paper_id,
                p.num_questions,
                e.name as exam_name,
                c.name as class_name,
                s.name as subject_name,
                ak.answer_key_id
            FROM admin_papers p
            JOIN exams e ON p.exam_id = e.exam_id
            JOIN classes c ON p.class_id = c.class_id
            JOIN subjects s ON p.subject_id = s.subject_id
            LEFT JOIN answer_keys ak ON p.paper_id = ak.paper_id 
            WHERE $where_sql
            ORDER BY e.name, c.name, s.name
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $assigned_papers = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

include 'header.php';
?>

<!-- Main Content -->
<main class="flex-1 p-6 sm:p-10">
    <h1 class="text-3xl font-semibold text-gray-800 mb-6">My Exam Papers</h1>

    <!-- Flash Message Display -->
    <?php if ($flash_message): ?>
        <div class="mb-6 rounded-md <?php echo $flash_message['type'] === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?> border p-4">
            <p><?php echo htmlspecialchars($flash_message['message']); ?></p>
        </div>
    <?php endif; ?>

    <!-- Error Message Display -->
    <?php if ($error_message): ?>
        <div class="mb-6 rounded-md bg-red-100 border-red-400 text-red-700 border p-4">
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-semibold mb-4">Papers Awaiting Your Answer Key</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam Details</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paper</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Answer Key</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($assigned_papers)): ?>
                        <tr>
                            <td colspan="3" class="px-4 py-4 text-sm text-gray-500 text-center">No exam papers found for your assigned subjects.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($assigned_papers as $paper): ?>
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($paper['exam_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($paper['class_name'] . ' - ' . $paper['subject_name']); ?></div>
                                    <div class="text-xs text-gray-400">(<?php echo htmlspecialchars($paper['num_questions']); ?> Questions)</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm">
                                    <a href="download_admin_paper.php?paper_id=<?php echo $paper['paper_id']; ?>" 
                                       class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-sky-600 hover:bg-sky-700">
                                        Download Paper
                                    </a>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                    <?php if ($paper['answer_key_id']): ?>
                                        <span class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-green-800 bg-green-100">
                                            Key Submitted
                                        </span>
                                    <?php else: ?>
                                        <a href="submit_key_teacher.php?paper_id=<?php echo $paper['paper_id']; ?>" 
                                           class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                                            Submit Key
                                        </a>
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
