<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// 1. Check if user is logged in and is an ADMIN
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You do not have permission.'];
    header('Location: index.php');
    exit;
}

// 2. Get paper_id from URL and validate
if (!isset($_GET['paper_id']) || !is_numeric($_GET['paper_id'])) {
    die('Invalid paper ID.');
}
$paper_id = intval($_GET['paper_id']);
$error_message = '';

try {
    // 3. Fetch paper details
    $stmt = $pdo->prepare("
        SELECT 
            e.name as exam_name, 
            c.name as class_name, 
            s.name as subject_name,
            p.num_questions
        FROM admin_papers p
        JOIN exams e ON p.exam_id = e.exam_id
        JOIN classes c ON p.class_id = c.class_id
        JOIN subjects s ON p.subject_id = s.subject_id
        WHERE p.paper_id = ?
    ");
    $stmt->execute([$paper_id]);
    $paper = $stmt->fetch();

    if (!$paper) {
        die('Paper not found.');
    }

    // 4. Fetch the answer key
    $stmt = $pdo->prepare("
        SELECT 
            ak.answers, 
            ak.submitted_at, 
            u.name as teacher_name
        FROM answer_keys ak
        JOIN users u ON ak.teacher_id = u.user_id
        WHERE ak.paper_id = ?
    ");
    $stmt->execute([$paper_id]);
    $key = $stmt->fetch();

    if (!$key) {
        die('Answer key not found for this paper.');
    }

    // 5. Decode the answers
    $answers = json_decode($key['answers'], true);
    ksort($answers, SORT_NUMERIC); // Sort by question number

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

include 'header.php';
?>

<!-- Main Content -->
<main class="flex-1 p-6 sm:p-10">
    <h1 class="text-3xl font-semibold text-gray-800 mb-2">Answer Key</h1>
    <p class="text-lg text-gray-600 mb-6">
        <strong>Exam:</strong> <?php echo htmlspecialchars($paper['exam_name']); ?><br>
        <strong>Class:</strong> <?php echo htmlspecialchars($paper['class_name']); ?><br>
        <strong>Subject:</strong> <?php echo htmlspecialchars($paper['subject_name']); ?> (<?php echo htmlspecialchars($paper['num_questions']); ?> Qs)<br>
        <strong>Submitted By:</strong> <?php echo htmlspecialchars($key['teacher_name']); ?> on <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($key['submitted_at']))); ?>
    </p>

    <!-- Error Message Display -->
    <?php if ($error_message): ?>
        <div class="mb-6 rounded-md bg-red-100 border-red-400 text-red-700 border p-4">
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
            <?php foreach ($answers as $question_num => $answer): ?>
                <div class="p-3 bg-gray-100 rounded-md text-center border">
                    <span class="block text-sm font-bold text-gray-800">Q<?php echo htmlspecialchars($question_num); ?></span>
                    <span class="block text-xl font-medium text-sky-600"><?php echo htmlspecialchars($answer); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
