<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You must be logged in.'];
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// 2. Get Paper ID
$paper_id = $_GET['id'] ?? null;
if (empty($paper_id)) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid paper ID.'];
    header('Location: ' . ($role === 'admin' ? 'dashboard_admin.php' : 'dashboard_teacher_main.php'));
    exit;
}

$paper = null;
$error_message = '';

try {
    // 3. Fetch paper details
    $stmt = $pdo->prepare("
        SELECT 
            p.paper_content, p.teacher_id, p.submission_type,
            e.name as exam_name, 
            c.name as class_name, 
            s.name as subject_name
        FROM papers p
        JOIN exams e ON p.exam_id = e.exam_id
        JOIN assignments a ON p.assignment_id = a.assignment_id
        JOIN classes c ON a.class_id = c.class_id
        JOIN subjects s ON a.subject_id = s.subject_id
        WHERE p.paper_id = ?
    ");
    $stmt->execute([$paper_id]);
    $paper = $stmt->fetch();

    // 4. Validation and Security
    if (!$paper) {
        $error_message = "Paper not found.";
    } elseif ($paper['submission_type'] !== 'text') {
        $error_message = "This is not a text-based paper.";
    } elseif ($role === 'teacher' && $paper['teacher_id'] !== $user_id) {
        // Security: Teacher can only view their own paper
        $error_message = "You are not authorized to view this paper.";
    }

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

include 'header.php';
?>

<!-- Main Content -->
<main class="flex-1 p-6 sm:p-10">
    <h1 class="text-3xl font-semibold text-gray-800 mb-6">View Paper Content</h1>

    <?php if ($error_message): ?>
        <div class="mb-6 rounded-md bg-red-100 border-red-400 text-red-700 border p-4">
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php elseif ($paper): ?>
        <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
            <h2 class="text-xl font-semibold mb-2"><?php echo htmlspecialchars($paper['exam_name']); ?></h2>
            <p class="text-lg text-gray-700 mb-4"><?php echo htmlspecialchars($paper['class_name'] . ' - ' . $paper['subject_name']); ?></p>
            
            <a href="javascript:window.print()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 mb-4">
                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v6a2 2 0 002 2h1v-4a1 1 0 011-1h8a1 1 0 011 1v4h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd" />
                </svg>
                Print Paper
            </a>
        </div>
        
        <!-- Rendered Paper Content -->
        <div class="bg-white p-6 sm:p-10 rounded-lg shadow-lg">
            <div class="prose max-w-none">
                <?php 
                    // We output the raw HTML content stored from the rich text editor
                    echo $paper['paper_content']; 
                ?>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>