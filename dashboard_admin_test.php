<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// --- 1. Auth Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.php');
    exit;
}
$teacher_id = $_SESSION['user_id'];

// --- 2. Initialize Variables ---
$error_message = '';
$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// --- 3. Fetch Data ---
try {
    // A. Stats
    $pending_corrections_count = $pdo->query("
        SELECT COUNT(*) 
        FROM correction_papers cp
        JOIN assignments a ON cp.class_id = a.class_id AND cp.subject_id = a.subject_id
        WHERE a.teacher_id = $teacher_id AND cp.status = 'pending_review'
    ")->fetchColumn();

    $active_mcq_count = $pdo->query("
        SELECT COUNT(*) 
        FROM mcq_exam_assignments mea
        JOIN assignments a ON mea.class_id = a.class_id
        WHERE a.teacher_id = $teacher_id
    ")->fetchColumn();

    // B. Pending Corrections List
    $stmt_corrections = $pdo->prepare("
        SELECT cp.*, c.name as class_name, s.name as subject_name
        FROM correction_papers cp
        JOIN classes c ON cp.class_id = c.class_id
        JOIN subjects s ON cp.subject_id = s.subject_id
        JOIN assignments a ON cp.class_id = a.class_id AND cp.subject_id = a.subject_id
        WHERE a.teacher_id = ? AND cp.status = 'pending_review'
        ORDER BY cp.uploaded_at DESC
    ");
    $stmt_corrections->execute([$teacher_id]);
    $pending_corrections = $stmt_corrections->fetchAll();

    // C. Dropdown Data for Submission Form
    $active_exams = $pdo->query("SELECT exam_id, name FROM exams WHERE status = 'active' ORDER BY name")->fetchAll();
    
    $stmt_assignments = $pdo->prepare("
        SELECT a.assignment_id, c.name as class_name, s.name as subject_name 
        FROM assignments a
        JOIN classes c ON a.class_id = c.class_id
        JOIN subjects s ON a.subject_id = s.subject_id
        WHERE a.teacher_id = ?
        ORDER BY c.name, s.name
    ");
    $stmt_assignments->execute([$teacher_id]);
    $teacher_assignments = $stmt_assignments->fetchAll();

    // D. Recent Submissions
    $stmt_recent = $pdo->prepare("
        SELECT p.*, e.name as exam_name, c.name as class_name, s.name as subject_name
        FROM papers p
        JOIN exams e ON p.exam_id = e.exam_id
        JOIN assignments a ON p.assignment_id = a.assignment_id
        JOIN classes c ON a.class_id = c.class_id
        JOIN subjects s ON a.subject_id = s.subject_id
        WHERE p.teacher_id = ?
        ORDER BY p.submitted_at DESC LIMIT 5
    ");
    $stmt_recent->execute([$teacher_id]);
    $recent_papers = $stmt_recent->fetchAll();

} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

include 'header.php';
?>

<!-- TinyMCE Editor (No API Key) -->
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        tinymce.init({
            selector: '#paper_content_editor',
            plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
            height: 400,
            menubar: false,
            content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }'
        });
    });
</script>

<main class="flex-1 bg-gray-50 min-h-screen p-6 lg:p-10" x-data="{ submissionType: 'file' }">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <!-- 1. Welcome & Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-2">
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Teacher Dashboard</h1>
                <p class="mt-1 text-sm text-gray-500">Welcome back! Here is your daily overview.</p>
            </div>
            <!-- Quick Stats Cards -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Pending Corrections</p>
                    <p class="text-2xl font-bold text-orange-600"><?php echo $pending_corrections_count; ?></p>
                </div>
                <div class="p-3 bg-orange-50 rounded-full text-orange-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if ($flash_message): ?>
            <div class="rounded-md p-4 border-l-4 shadow-sm <?php echo $flash_message['type'] === 'success' ? 'bg-green-50 border-green-400' : 'bg-red-50 border-red-400'; ?>">
                <p class="text-sm font-medium <?php echo $flash_message['type'] === 'success' ? 'text-green-800' : 'text-red-800'; ?>">
                    <?php echo htmlspecialchars($flash_message['message']); ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- LEFT COLUMN: Tasks & Actions -->
            <div class="lg:col-span-2 space-y-8">
                
                <!-- 2. CORRECTION WORKFLOW SECTION -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-orange-50/50 flex justify-between items-center">
                        <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                            <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Papers to Correct
                        </h2>
                        <?php if($pending_corrections_count > 0): ?>
                            <span class="bg-orange-100 text-orange-700 text-xs font-bold px-2 py-1 rounded-full"><?php echo $pending_corrections_count; ?> New</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if(empty($pending_corrections)): ?>
                        <div class="p-8 text-center text-gray-500">
                            <p class="italic">No pending corrections. Good job!</p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-gray-100">
                            <?php foreach($pending_corrections as $cp): ?>
                                <div class="p-4 hover:bg-gray-50 transition-colors flex items-center justify-between">
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($cp['original_file_name']); ?></p>
                                        <div class="text-sm text-gray-500 mt-1 flex gap-2">
                                            <span class="bg-gray-100 px-2 py-0.5 rounded"><?php echo htmlspecialchars($cp['class_name']); ?></span>
                                            <span class="bg-gray-100 px-2 py-0.5 rounded"><?php echo htmlspecialchars($cp['subject_name']); ?></span>
                                        </div>
                                    </div>
                                    <a href="view_correction_paper.php?id=<?php echo $cp['correction_paper_id']; ?>" class="inline-flex items-center px-3 py-1.5 border border-orange-200 text-xs font-medium rounded text-orange-700 bg-orange-50 hover:bg-orange-100 transition-colors">
                                        Review &rarr;
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 3. MCQ MODULE CARD -->
                <div class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl shadow-lg p-6 text-white relative overflow-hidden group">
                    <div class="relative z-10 flex justify-between items-center">
                        <div>
                            <h2 class="text-xl font-bold">MCQ Answer Keys & Grading</h2>
                            <p class="text-indigo-100 text-sm mt-1">Manage answer keys for <?php echo $active_mcq_count; ?> assigned exams.</p>
                        </div>
                        <a href="teacher_mcq.php" class="bg-white text-indigo-600 px-4 py-2 rounded-lg text-sm font-bold shadow hover:bg-gray-50 transition-colors">
                            Open Module &rarr;
                        </a>
                    </div>
                    <!-- Decorative Icon -->
                    <svg class="absolute right-[-20px] bottom-[-20px] w-32 h-32 text-white opacity-10 transform group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                </div>

                <!-- 4. RECENT SUBMISSIONS LIST -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                        <h2 class="text-lg font-semibold text-gray-900">Recent Submissions</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">View</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if(empty($recent_papers)): ?>
                                    <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500 text-sm">No papers submitted yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach($recent_papers as $p): ?>
                                        <tr>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($p['exam_name']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($p['class_name'].' - '.$p['subject_name']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $p['submission_type'] === 'text' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                    <?php echo $p['submission_type'] === 'text' ? 'Typed' : 'File'; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                    $st_color = 'bg-gray-100 text-gray-800';
                                                    if($p['status']=='approved') $st_color='bg-green-100 text-green-800';
                                                    if($p['status']=='rejected') $st_color='bg-red-100 text-red-800';
                                                    if($p['status']=='pending_review') $st_color='bg-yellow-100 text-yellow-800';
                                                ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $st_color; ?>">
                                                    <?php echo ucwords(str_replace('_', ' ', $p['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-right text-sm font-medium">
                                                <?php if($p['submission_type'] === 'text'): ?>
                                                    <a href="view_paper_content.php?id=<?php echo $p['paper_id']; ?>" target="_blank" class="text-indigo-600 hover:text-indigo-900">View</a>
                                                <?php else: ?>
                                                    <a href="download.php?paper_id=<?php echo $p['paper_id']; ?>" class="text-indigo-600 hover:text-indigo-900">Download</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-gray-100 text-right">
                        <a href="my_uploads.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">View All Submissions &rarr;</a>
                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN: Submit New Paper Form -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg border border-indigo-100 sticky top-6">
                    <div class="px-6 py-4 border-b border-gray-100 bg-indigo-50/50">
                        <h2 class="text-lg font-semibold text-gray-900">Submit New Exam Paper</h2>
                    </div>
                    <div class="p-6">
                        <form action="upload_paper_action.php" method="POST" enctype="multipart/form-data" class="space-y-5">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Exam</label>
                                <select name="exam_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Exam</option>
                                    <?php foreach($active_exams as $ex): ?>
                                        <option value="<?php echo $ex['exam_id']; ?>"><?php echo htmlspecialchars($ex['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Assignment (Class - Subject)</label>
                                <select name="assignment_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Assignment</option>
                                    <?php foreach($teacher_assignments as $ta): ?>
                                        <option value="<?php echo $ta['assignment_id']; ?>">
                                            <?php echo htmlspecialchars($ta['class_name'] . ' - ' . $ta['subject_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Submission Type Toggle -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Submission Format</label>
                                <div class="flex items-center space-x-4 bg-gray-50 p-3 rounded-md border border-gray-200">
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="submission_type" value="file" x-model="submissionType" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Upload File</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="submission_type" value="text" x-model="submissionType" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Type Paper</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Option A: File Upload -->
                            <div x-show="submissionType === 'file'" x-transition>
                                <label class="block text-sm font-medium text-gray-700">Upload File (PDF/Doc)</label>
                                <input type="file" name="paper_file" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            </div>

                            <!-- Option B: Text Editor -->
                            <div x-show="submissionType === 'text'" x-transition x-cloak>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Type Content</label>
                                <textarea id="paper_content_editor" name="paper_content"></textarea>
                            </div>

                            <button type="submit" class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                                Submit Paper
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<?php include 'footer.php'; ?>