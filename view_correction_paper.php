<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// 1. Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You must be logged in as a teacher.'];
    header('Location: index.php');
    exit;
}
$teacher_id = $_SESSION['user_id'];
$flash_message = get_flash_message();
$error_message = '';
$paper = null;

// 2. Get paper ID from URL
$correction_paper_id = $_GET['id'] ?? null;
if (empty($correction_paper_id)) {
    header('Location: dashboard_teacher.php');
    exit;
}

try {
    // 3. Fetch paper details
    $stmt = $pdo->prepare("
        SELECT cp.*, c.name as class_name, s.name as subject_name
        FROM correction_papers cp
        JOIN classes c ON cp.class_id = c.class_id
        JOIN subjects s ON cp.subject_id = s.subject_id
        WHERE cp.correction_paper_id = ?
    ");
    $stmt->execute([$correction_paper_id]);
    $paper = $stmt->fetch();

    if (!$paper) {
        $error_message = "Paper not found.";
    } else {
        // 4. Security Check: Verify this teacher is assigned to this class/subject
        $stmt_check = $pdo->prepare("
            SELECT COUNT(*) FROM assignments
            WHERE teacher_id = ? AND class_id = ? AND subject_id = ?
        ");
        $stmt_check->execute([$teacher_id, $paper['class_id'], $paper['subject_id']]);
        if ($stmt_check->fetchColumn() == 0) {
            $error_message = "You are not authorized to review this paper.";
            $paper = null; // Prevent display
        }
    }

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

include 'header.php';
?>

<!-- Main Content -->
<main class="flex-1 p-6 sm:p-10">
    <a href="dashboard_teacher.php" class="text-sm text-sky-600 hover:text-sky-800 mb-4 inline-block">&larr; Back to Dashboard</a>
    <h1 class="text-3xl font-semibold text-gray-800 mb-6">Paper Correction</h1>

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
    <?php elseif ($paper): ?>
        
        <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
            <h2 class="text-xl font-semibold mb-4"><?php echo htmlspecialchars($paper['original_file_name']); ?></h2>
            <div class="mb-4">
                <p class="text-sm text-gray-600"><strong>Class:</strong> <?php echo htmlspecialchars($paper['class_name']); ?></p>
                <p class="text-sm text-gray-600"><strong>Subject:</strong> <?php echo htmlspecialchars($paper['subject_name']); ?></p>
                <p class="text-sm text-gray-600"><strong>Uploaded:</strong> <?php echo htmlspecialchars(date('M d, Y', strtotime($paper['uploaded_at']))); ?></p>
            </div>
            <a href="<?php echo htmlspecialchars($paper['original_file_path']); ?>" 
               target="_blank"
               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
                Download and Review Paper
            </a>
        </div>

        <?php if ($paper['status'] === 'pending_review'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Form 1: No Correction Required -->
                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h3 class="text-lg font-medium mb-4">Option 1: No Correction</h3>
                    <p class="text-sm text-gray-600 mb-4">If the paper is correct and requires no changes, mark it as complete.</p>
                    <form action="submit_correction_action.php" method="POST">
                        <input type="hidden" name="correction_paper_id" value="<?php echo $paper['correction_paper_id']; ?>">
                        <input type="hidden" name="action" value="no_correction">
                        <button type="submit"
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            No Correction Required
                        </button>
                    </form>
                </div>

                <!-- Form 2: Submit Correction -->
                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h3 class="text-lg font-medium mb-4">Option 2: Submit Correction</h3>
                    <p class="text-sm text-gray-600 mb-4">If the paper needs changes, please provide notes and/or upload a file with corrections.</p>
                    <form action="submit_correction_action.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="correction_paper_id" value="<?php echo $paper['correction_paper_id']; ?>">
                        <input type="hidden" name="action" value="submit_correction">
                        
                        <div>
                            <label for="correction_notes" class="block text-sm font-medium text-gray-700">Correction Notes (Optional)</label>
                            <textarea id="correction_notes" name="correction_notes" rows="4"
                                      class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm"></textarea>
                        </div>
                        
                        <div>
                            <label for="correction_image" class="block text-sm font-medium text-gray-700">Correction File (Optional)</label>
                            <input id="correction_image" name="correction_image" type="file"
                                   class="mt-1 block w-full text-sm text-gray-500
                                          file:mr-4 file:py-2 file:px-4
                                          file:rounded-md file:border-0
                                          file:text-sm file:font-semibold
                                          file:bg-sky-50 file:text-sky-700
                                          hover:file:bg-sky-100">
                            <p class="mt-1 text-xs text-gray-500">Upload an image (JPG, PNG) or document (PDF, DOCX) with your corrections.</p>
                        </div>

                        <div>
                            <button type="submit"
                                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                Submit Correction
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-medium mb-4">Review Complete</h3>
                <?php if ($paper['status'] === 'no_correction'): ?>
                    <p class="text-sm text-gray-700">You marked this paper as requiring no correction.</p>
                <?php elseif ($paper['status'] === 'corrected'): ?>
                    <p class="text-sm text-gray-700">You submitted corrections for this paper.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>