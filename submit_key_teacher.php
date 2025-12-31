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

// 2. Get paper_id from URL and validate
if (!isset($_GET['paper_id']) || !is_numeric($_GET['paper_id'])) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid paper ID.'];
    header('Location: my_exam_papers.php');
    exit;
}
$paper_id = intval($_GET['paper_id']);
$error_message = '';

try {
    // 3. Fetch paper details
    $stmt = $pdo->prepare("
        SELECT 
            p.paper_id, p.num_questions, e.name as exam_name, c.name as class_name, s.name as subject_name,
            p.class_id, p.subject_id
        FROM admin_papers p
        JOIN exams e ON p.exam_id = e.exam_id
        JOIN classes c ON p.class_id = c.class_id
        JOIN subjects s ON p.subject_id = s.subject_id
        WHERE p.paper_id = ?
    ");
    $stmt->execute([$paper_id]);
    $paper = $stmt->fetch();

    if (!$paper) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Paper not found.'];
        header('Location: my_exam_papers.php');
        exit;
    }

    // 4. Security Check: Ensure this teacher is assigned to this paper's class/subject
    $stmt = $pdo->prepare("
        SELECT assignment_id FROM assignments 
        WHERE teacher_id = ? AND class_id = ? AND subject_id = ?
    ");
    $stmt->execute([$teacher_id, $paper['class_id'], $paper['subject_id']]);
    if (!$stmt->fetch()) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You are not assigned to this paper\'s subject.'];
        header('Location: my_exam_papers.php');
        exit;
    }

    // 5. Check if a key already exists
    $stmt = $pdo->prepare("SELECT answer_key_id FROM answer_keys WHERE paper_id = ?");
    $stmt->execute([$paper_id]);
    if ($stmt->fetch()) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'An answer key has already been submitted for this paper.'];
        header('Location: my_exam_papers.php');
        exit;
    }

    $total_questions = $paper['num_questions'];

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

include 'header.php';
?>

<!-- Main Content -->
<main class="flex-1 p-6 sm:p-10">
    <h1 class="text-3xl font-semibold text-gray-800 mb-2">Submit Answer Key</h1>
    <p class="text-lg text-gray-600 mb-6">
        For: <strong><?php echo htmlspecialchars($paper['exam_name'] . ' - ' . $paper['class_name'] . ' - ' . $paper['subject_name']); ?></strong>
    </p>

    <!-- Error Message Display -->
    <?php if ($error_message): ?>
        <div class="mb-6 rounded-md bg-red-100 border-red-400 text-red-700 border p-4">
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-lg max-w-3xl mx-auto">
        
        <form action="submit_key_teacher_action.php" method="POST" id="answer-key-form">
            <input type="hidden" name="paper_id" value="<?php echo $paper_id; ?>">
            <h2 class="text-xl font-semibold mb-4">Select the Correct Option (<?php echo $total_questions; ?> Questions)</h2>
            
            <div id="question-list-container" class="space-y-5 max-h-[600px] overflow-y-auto pr-2">
                <!-- JavaScript will populate this area -->
            </div>

            <!-- Submit Button -->
            <div class="pt-6 border-t border-gray-200 mt-6">
                <button type="submit" id="submit-btn"
                        class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Submit Final Answer Key
                </button>
            </div>
        </form>
    </div>
</main>

<style>
    /* Custom styles for checked radio buttons */
    input[type="radio"]:checked + label {
        background-color: #0284c7; /* sky-600 */
        color: white;
        border-color: #0284c7; /* sky-600 */
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const total = <?php echo $total_questions; ?>;
    const container = document.getElementById('question-list-container');
    const form = document.getElementById('answer-key-form');
    
    if (total > 0) {
        // Generate question rows
        for (let i = 1; i <= total; i++) {
            const questionRow = `
                <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="font-semibold text-gray-800 mb-3">Question ${i}</p>
                    <div class="flex flex-wrap gap-3">
                        <input type="radio" id="q${i}-a" name="answers[${i}]" value="A" class="hidden" required>
                        <label for="q${i}-a" class="w-12 h-12 flex items-center justify-center rounded-full border-2 border-gray-300 cursor-pointer transition-colors font-medium text-gray-700 hover:bg-gray-100">A</label>
                        
                        <input type="radio" id="q${i}-b" name="answers[${i}]" value="B" class="hidden">
                        <label for="q${i}-b" class="w-12 h-12 flex items-center justify-center rounded-full border-2 border-gray-300 cursor-pointer transition-colors font-medium text-gray-700 hover:bg-gray-100">B</label>
                        
                        <input type="radio" id="q${i}-c" name="answers[${i}]" value="C" class="hidden">
                        <label for="q${i}-c" class="w-12 h-12 flex items-center justify-center rounded-full border-2 border-gray-300 cursor-pointer transition-colors font-medium text-gray-700 hover:bg-gray-100">C</label>
                        
                        <input type="radio" id="q${i}-d" name="answers[${i}]" value="D" class="hidden">
                        <label for="q${i}-d" class="w-12 h-12 flex items-center justify-center rounded-full border-2 border-gray-300 cursor-pointer transition-colors font-medium text-gray-700 hover:bg-gray-100">D</label>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', questionRow);
        }
    }

    // Add validation on submit
    form.addEventListener('submit', function(event) {
        let allAnswered = true;
        for(let i = 1; i <= total; i++) {
            if (!document.querySelector(`input[name="answers[${i}]"]:checked`)) {
                allAnswered = false;
                break;
            }
        }
        if (!allAnswered) {
            event.preventDefault();
            alert('Please select an answer for every question.');
        }
    });
});
</script>

<?php include 'footer.php'; ?>

