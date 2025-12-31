<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// 1. Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'You must be logged in as a teacher to access this page.'
    ];
    header('Location: index.php');
    exit;
}

$teacher_id = $_SESSION['user_id'];
$error_message = '';
$all_papers = [];

try {
    // 2. Fetch ALL of the teacher's submissions
    // FIX: Corrected all column names (e.name, c.name, s.name, p.submitted_at)
    $stmt = $pdo->prepare("
        SELECT 
            p.paper_id,
            p.file_path, 
            p.status, 
            p.submitted_at, 
            e.name as exam_name, 
            c.name as class_name, 
            s.name as subject_name
        FROM papers p
        JOIN exams e ON p.exam_id = e.exam_id
        JOIN assignments a ON p.assignment_id = a.assignment_id
        JOIN classes c ON a.class_id = c.class_id
        JOIN subjects s ON a.subject_id = s.subject_id
        WHERE p.teacher_id = ?
        ORDER BY p.submitted_at DESC
    ");
    $stmt->execute([$teacher_id]);
    $all_papers = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = "Database error: Failed to load your submissions. " . $e->getMessage();
}

include 'header.php';
?>

<!-- Main Content -->
<main class="flex-1 p-6 sm:p-10">
    <h1 class="text-3xl font-semibold text-gray-800 mb-6">My Uploads</h1>

    <!-- Error Message Display -->
    <?php if ($error_message): ?>
        <div class="mb-6 rounded-md bg-red-100 border-red-400 text-red-700 border p-4">
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php endif; ?>

    <!-- All Submissions Table -->
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-semibold mb-4">All My Submissions</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uploaded On</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Download</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($all_papers)): ?>
                        <tr>
                            <td colspan="4" class="px-4 py-4 text-sm text-gray-500 text-center">You have not submitted any papers yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($all_papers as $paper): ?>
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($paper['exam_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($paper['class_name'] . ' - ' . $paper['subject_name']); ?></div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                     <div class="text-sm text-gray-900"><?php echo htmlspecialchars(date('M d, Y', strtotime($paper['submitted_at']))); ?></div>
                                     <div class="text-xs text-gray-500"><?php echo htmlspecialchars(date('h:i A', strtotime($paper['submitted_at']))); ?></div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <?php
                                        $status_color = 'bg-gray-100 text-gray-800'; // Default
                                        if ($paper['status'] === 'approved') {
                                            $status_color = 'bg-green-100 text-green-800';
                                        } elseif ($paper['status'] === 'rejected') {
                                            $status_color = 'bg-red-100 text-red-800';
                                        } elseif ($paper['status'] === 'pending_review') {
                                            $status_color = 'bg-yellow-100 text-yellow-800';
                                        }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_color; ?>">
                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $paper['status']))); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                    <!-- UPDATED: Link points to secure download.php script -->
                                    <a href="download.php?paper_id=<?php echo $paper['paper_id']; ?>" 
                                       class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                                        <svg class="-ml-0.5 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                          <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                        Download
                                    </a>
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

