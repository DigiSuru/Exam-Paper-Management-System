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
$open_queries = [];
$closed_queries = [];
$assigned_papers = [];

try {
    // 2. Fetch teacher's assigned papers for the dropdown
    $stmt = $pdo->prepare("
        SELECT p.paper_id, e.name as exam_name, c.name as class_name, s.name as subject_name
        FROM admin_papers p
        JOIN exams e ON p.exam_id = e.exam_id
        JOIN classes c ON p.class_id = c.class_id
        JOIN subjects s ON p.subject_id = s.subject_id
        JOIN assignments a ON a.class_id = p.class_id AND a.subject_id = p.subject_id
        WHERE a.teacher_id = ?
        ORDER BY e.name, c.name, s.name
    ");
    $stmt->execute([$teacher_id]);
    $assigned_papers = $stmt->fetchAll();

    // 3. Fetch teacher's open queries
    $stmt = $pdo->prepare("
        SELECT q.*, p.paper_id, e.name as exam_name, c.name as class_name, s.name as subject_name
        FROM teacher_queries q
        LEFT JOIN admin_papers p ON q.paper_id = p.paper_id
        LEFT JOIN exams e ON p.exam_id = e.exam_id
        LEFT JOIN classes c ON p.class_id = c.class_id
        LEFT JOIN subjects s ON p.subject_id = s.subject_id
        WHERE q.teacher_id = ? AND q.status = 'open'
        ORDER BY q.created_at DESC
    ");
    $stmt->execute([$teacher_id]);
    $open_queries = $stmt->fetchAll();

    // 4. Fetch teacher's closed queries
    $stmt = $pdo->prepare("
        SELECT q.*, p.paper_id, e.name as exam_name, c.name as class_name, s.name as subject_name
        FROM teacher_queries q
        LEFT JOIN admin_papers p ON q.paper_id = p.paper_id
        LEFT JOIN exams e ON p.exam_id = e.exam_id
        LEFT JOIN classes c ON p.class_id = c.class_id
        LEFT JOIN subjects s ON p.subject_id = s.subject_id
        WHERE q.teacher_id = ? AND q.status = 'closed'
        ORDER BY q.replied_at DESC
        LIMIT 10
    ");
    $stmt->execute([$teacher_id]);
    $closed_queries = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

include 'header.php';
?>

<!-- Main Content -->
<main class="flex-1 p-6 sm:p-10">
    <h1 class="text-3xl font-semibold text-gray-800 mb-6">My Queries</h1>

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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Column 1: Submit New Query -->
        <div class="lg:col-span-1 bg-white p-6 rounded-lg shadow-lg h-fit">
            <h2 class="text-xl font-semibold mb-4">Submit a New Query</h2>
            
            <form action="my_queries_action.php" method="POST" class="space-y-4">
                
                <div>
                    <label for="paper_id" class="block text-sm font-medium text-gray-700">Related Paper (Optional)</label>
                    <select id="paper_id" name="paper_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                        <option value="">-- None --</option>
                        <?php foreach ($assigned_papers as $paper): ?>
                            <option value="<?php echo $paper['paper_id']; ?>">
                                <?php echo htmlspecialchars($paper['exam_name'] . ' - ' . $paper['class_name'] . ' - ' . $paper['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="subject" class="block text-sm font-medium text-gray-700">Subject / Title</label>
                    <input type="text" id="subject" name="subject" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm"
                           placeholder="e.g., Question 5 Correction">
                </div>

                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700">Message</label>
                    <textarea id="message" name="message" rows="5" required
                              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm"
                              placeholder="Please describe the issue in detail."></textarea>
                </div>

                <div>
                    <button type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                        Submit Query
                    </button>
                </div>
            </form>
        </div>

        <!-- Column 2: Query History -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Open Queries -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h2 class="text-xl font-semibold mb-4">Open Queries</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($open_queries)): ?>
                                <tr>
                                    <td colspan="2" class="px-4 py-4 text-sm text-gray-500 text-center">You have no open queries.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($open_queries as $query): ?>
                                    <tr>
                                        <td class="px-4 py-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($query['subject']); ?></div>
                                            <p class="text-sm text-gray-500 truncate max-w-md"><?php echo htmlspecialchars($query['message']); ?></p>
                                            <?php if ($query['paper_id']): ?>
                                                <div class="text-xs text-gray-400 mt-1">
                                                    Related to: <?php echo htmlspecialchars($query['exam_name'] . ' - ' . $query['class_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                Open
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Closed Queries -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h2 class="text-xl font-semibold mb-4">Resolved Queries (Recent 10)</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Query</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reply</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($closed_queries)): ?>
                                <tr>
                                    <td colspan="2" class="px-4 py-4 text-sm text-gray-500 text-center">No resolved queries.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($closed_queries as $query): ?>
                                     <tr>
                                        <td class="px-4 py-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($query['subject']); ?></div>
                                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($query['message']); ?></p>
                                            <div class="text-xs text-gray-400 mt-1">Sent: <?php echo htmlspecialchars(date('M d, Y', strtotime($query['created_at']))); ?></div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($query['reply'])); ?></p>
                                            <div class="text-xs text-gray-400 mt-1">Replied: <?php echo htmlspecialchars(date('M d, Y', strtotime($query['replied_at']))); ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
