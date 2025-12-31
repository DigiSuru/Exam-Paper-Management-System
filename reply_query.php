<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

// 1. Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You must be an admin.'];
    header('Location: index.php');
    exit;
}

// 2. Get Query ID from URL
$query_id = $_GET['query_id'] ?? null;
if (!$query_id) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid query ID.'];
    header('Location: manage_queries.php');
    exit;
}

$error_message = '';
$query = null;

try {
    // 3. Fetch the query details
    $sql = "
        SELECT 
            q.*, 
            u_teacher.name as teacher_name,
            p.paper_id, 
            e.name as exam_name, 
            c.name as class_name, 
            s.name as subject_name
        FROM teacher_queries q
        JOIN users u_teacher ON q.teacher_id = u_teacher.user_id
        LEFT JOIN admin_papers p ON q.paper_id = p.paper_id
        LEFT JOIN exams e ON p.exam_id = e.exam_id
        LEFT JOIN classes c ON p.class_id = c.class_id
        LEFT JOIN subjects s ON p.subject_id = s.subject_id
        WHERE q.query_id = ? AND q.status = 'open'
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$query_id]);
    $query = $stmt->fetch();

    if (!$query) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Query not found or already closed.'];
        header('Location: manage_queries.php');
        exit;
    }

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

include 'header.php';
?>

<!-- Main Content -->
<main class="flex-1 p-6 sm:p-10">
    <h1 class="text-3xl font-semibold text-gray-800 mb-6">Reply to Query</h1>

    <!-- Error Message Display -->
    <?php if ($error_message): ?>
        <div class="mb-6 rounded-md bg-red-100 border-red-400 text-red-700 border p-4">
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($query): ?>
    <div class="bg-white p-6 rounded-lg shadow-lg max-w-2xl mx-auto">
        
        <!-- Teacher's Query Info -->
        <div class="mb-6 border-b pb-6">
            <h2 class="text-xl font-semibold mb-4">Teacher's Query</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="font-medium text-gray-500">From:</span>
                    <span class="text-gray-900"><?php echo htmlspecialchars($query['teacher_name']); ?></span>
                </div>
                 <div>
                    <span class="font-medium text-gray-500">Date:</span>
                    <span class="text-gray-900"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($query['created_at']))); ?></span>
                </div>
                <?php if ($query['paper_id']): ?>
                <div class="col-span-1 md:col-span-2">
                    <span class="font-medium text-gray-500">Related Paper:</span>
                    <span class="text-gray-900"><?php echo htmlspecialchars($query['exam_name'] . ' - ' . $query['class_name'] . ' - ' . $query['subject_name']); ?></span>
                </div>
                <?php endif; ?>
                <div class="col-span-1 md:col-span-2">
                    <span class="font-medium text-gray-500">Subject:</span>
                    <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($query['subject']); ?></span>
                </div>
            </div>
            
            <div class="mt-4">
                <p class="text-sm font-medium text-gray-500 mb-1">Message:</p>
                <p class="text-gray-800 bg-gray-50 p-4 rounded-md border"><?php echo nl2br(htmlspecialchars($query['message'])); ?></p>
            </div>
        </div>

        <!-- Admin Reply Form -->
        <h2 class="text-xl font-semibold mb-4">Your Reply</h2>
        <form action="reply_query_action.php" method="POST" class="space-y-4">
            <input type="hidden" name="query_id" value="<?php echo $query['query_id']; ?>">
            
            <div>
                <label for="reply" class="block text-sm font-medium text-gray-700">Reply Message</label>
                <textarea id="reply" name="reply" rows="6" required
                          class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm"
                          placeholder="Type your answer to the teacher here."></textarea>
            </div>

            <div>
                <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                    Send Reply and Close Query
                </button>
            </div>
        </form>

    </div>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>
