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

$error_message = '';
$flash_message = get_flash_message();
$open_queries = [];
$closed_queries = [];

// NEW: Get filter from URL
$filter_status = $_GET['status'] ?? 'open'; // Default to 'open'

try {
    // 2. Fetch queries based on filter
    $sql = "
        SELECT 
            q.*, 
            u_teacher.name as teacher_name,
            u_admin.name as admin_name,
            p.paper_id, 
            e.name as exam_name, 
            c.name as class_name, 
            s.name as subject_name
        FROM teacher_queries q
        JOIN users u_teacher ON q.teacher_id = u_teacher.user_id
        LEFT JOIN users u_admin ON q.admin_id = u_admin.user_id
        LEFT JOIN admin_papers p ON q.paper_id = p.paper_id
        LEFT JOIN exams e ON p.exam_id = e.exam_id
        LEFT JOIN classes c ON p.class_id = c.class_id
        LEFT JOIN subjects s ON p.subject_id = s.subject_id
        WHERE q.status = ?
        ORDER BY q.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$filter_status]);
    $queries = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

include 'header.php';
?>

<!-- Main Content -->
<main class="flex-1 p-6 sm:p-10">
    <h1 class="text-3xl font-semibold text-gray-800 mb-6">Manage Teacher Queries</h1>

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

    <!-- Filter Tabs -->
    <div class="mb-4">
        <nav class="flex space-x-4" aria-label="Tabs">
            <a href="manage_queries.php?status=open" 
               class="<?php echo ($filter_status == 'open') ? 'bg-sky-100 text-sky-700' : 'text-gray-500 hover:text-gray-700'; ?> px-3 py-2 font-medium text-sm rounded-md">
                Open Queries
            </a>
            <a href="manage_queries.php?status=closed" 
               class="<?php echo ($filter_status == 'closed') ? 'bg-sky-100 text-sky-700' : 'text-gray-500 hover:text-gray-700'; ?> px-3 py-2 font-medium text-sm rounded-md">
                Closed Queries
            </a>
        </nav>
    </div>

    <!-- Queries Table -->
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-semibold mb-4"><?php echo ucfirst($filter_status); ?> Queries</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Query</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Related Paper</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reply</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($queries)): ?>
                        <tr>
                            <td colspan="4" class="px-4 py-4 text-sm text-gray-500 text-center">No <?php echo $filter_status; ?> queries found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($queries as $query): ?>
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($query['teacher_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($query['created_at']))); ?></div>
                                </td>
                                <td class="px-4 py-4 max-w-sm">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($query['subject']); ?></div>
                                    <p class="text-sm text-gray-500 truncate"><?php echo htmlspecialchars($query['message']); ?></p>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($query['paper_id']): ?>
                                        <?php echo htmlspecialchars($query['exam_name'] . ' - ' . $query['class_name']); ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm">
                                    <?php if ($query['status'] == 'open'): ?>
                                        <a href="reply_query.php?query_id=<?php echo $query['query_id']; ?>"
                                           class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-sky-600 hover:bg-sky-700">
                                            Reply
                                        </a>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-700 truncate max-w-xs"><?php echo htmlspecialchars($query['reply']); ?></p>
                                        <div class="text-xs text-gray-400 mt-1">
                                            By: <?php echo htmlspecialchars($query['admin_name']); ?> on <?php echo htmlspecialchars(date('M d, Y', strtotime($query['replied_at']))); ?>
                                        </div>
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
