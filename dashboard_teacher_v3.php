<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set default timezone for correct time display
date_default_timezone_set('Asia/Kolkata'); 

require_once 'config.php';

// --- 1. Check if user is logged in and is a teacher ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'You must be logged in as a teacher to access this page.'
    ];
    header('Location: index.php');
    exit;
}

// --- 2. Initialize Variables ---
$teacher_id = $_SESSION['user_id'];
$error_message = '';
$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

$active_exams = [];
$teacher_assignments = [];
$recent_papers = [];
$correction_papers = [];

try {
    // --- 3. Fetch Data ---

    // A. Active Exams
    $stmt = $pdo->prepare("SELECT exam_id, name FROM exams WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $active_exams = $stmt->fetchAll();

    // B. Teacher's Assignments
    $stmt = $pdo->prepare("
        SELECT a.assignment_id, c.name as class_name, s.name as subject_name 
        FROM assignments a
        JOIN classes c ON a.class_id = c.class_id
        JOIN subjects s ON a.subject_id = s.subject_id
        WHERE a.teacher_id = ?
        ORDER BY c.name, s.name
    ");
    $stmt->execute([$teacher_id]);
    $teacher_assignments = $stmt->fetchAll();

    // C. Recent Submissions (My Uploads)
    $stmt = $pdo->prepare("
        SELECT p.file_path, p.status, p.submitted_at, e.name as exam_name, c.name as class_name, s.name as subject_name
        FROM papers p
        JOIN exams e ON p.exam_id = e.exam_id
        JOIN assignments a ON p.assignment_id = a.assignment_id
        JOIN classes c ON a.class_id = c.class_id
        JOIN subjects s ON a.subject_id = s.subject_id
        WHERE p.teacher_id = ?
        ORDER BY p.submitted_at DESC LIMIT 5
    ");
    $stmt->execute([$teacher_id]);
    $recent_papers = $stmt->fetchAll();

    // D. Correction Papers (Complete List for this Teacher)
    // Fetches papers assigned to this teacher's class/subject for correction
    $stmt_cp = $pdo->prepare("
        SELECT 
            cp.correction_paper_id, 
            cp.original_file_name, 
            cp.uploaded_at,
            cp.status,
            c.name as class_name,
            s.name as subject_name
        FROM correction_papers cp
        JOIN classes c ON cp.class_id = c.class_id
        JOIN subjects s ON cp.subject_id = s.subject_id
        JOIN assignments a ON cp.class_id = a.class_id AND cp.subject_id = a.subject_id
        WHERE a.teacher_id = ? 
        ORDER BY FIELD(cp.status, 'pending_review', 'in_progress', 'completed'), cp.uploaded_at DESC
    ");
    $stmt_cp->execute([$teacher_id]);
    $correction_papers = $stmt_cp->fetchAll();

    // Count pending
    $pending_count = 0;
    foreach($correction_papers as $p) {
        if($p['status'] == 'pending_review') $pending_count++;
    }

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

include 'header.php';
?>

<main class="flex-1 p-6 sm:p-10 bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <h1 class="text-3xl font-bold text-gray-900">Teacher Dashboard</h1>
            
            <?php if($pending_count > 0): ?>
                <div class="bg-orange-100 border border-orange-200 text-orange-800 px-4 py-2 rounded-lg flex items-center gap-2 shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    <span class="font-medium">You have <?php echo $pending_count; ?> papers pending correction</span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($flash_message): ?>
            <div class="rounded-md p-4 border-l-4 shadow-sm <?php echo $flash_message['type'] === 'success' ? 'bg-green-50 border-green-400 text-green-800' : 'bg-red-50 border-red-400 text-red-800'; ?>">
                <?php echo htmlspecialchars($flash_message['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="rounded-md bg-red-50 border-red-400 text-red-700 border p-4">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- === 1. CORRECTION MODULE === -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-sky-50/50 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Correction Queue
                </h2>
                <a href="#" class="text-sm text-sky-600 font-medium hover:underline">View All &rarr;</a>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paper Details</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class Info</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Uploaded At</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($correction_papers)): ?>
                            <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No papers assigned for correction.</td></tr>
                        <?php else: ?>
                            <?php foreach ($correction_papers as $cp): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                            $st = $cp['status'];
                                            $color = 'bg-gray-100 text-gray-800';
                                            if($st == 'pending_review') $color = 'bg-orange-100 text-orange-800';
                                            if($st == 'in_progress') $color = 'bg-blue-100 text-blue-800';
                                            if($st == 'completed') $color = 'bg-green-100 text-green-800';
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $color; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $st)); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 truncate max-w-xs" title="<?php echo htmlspecialchars($cp['original_file_name']); ?>">
                                            <?php echo htmlspecialchars($cp['original_file_name']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($cp['class_name']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($cp['subject_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <!-- Fixed Time Display -->
                                        <?php echo date('M d, Y h:i A', strtotime($cp['uploaded_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="view_correction_paper.php?id=<?php echo $cp['correction_paper_id']; ?>" 
                                           class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-sky-600 hover:bg-sky-700 shadow-sm transition-colors">
                                           <?php echo ($cp['status'] === 'completed') ? 'View Result' : 'Start Correction'; ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- === 2. SUBMIT FORM === -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 sticky top-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-100 pb-2">Submit Exam Paper</h2>
                    
                    <form action="upload_paper_action.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Exam</label>
                            <select name="exam_id" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm">
                                <option value="">-- Choose Exam --</option>
                                <?php foreach ($active_exams as $exam): ?>
                                    <option value="<?php echo $exam['exam_id']; ?>"><?php echo htmlspecialchars($exam['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Assignment</label>
                            <select name="assignment_id" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm">
                                <option value="">-- Choose Class/Subject --</option>
                                <?php foreach ($teacher_assignments as $assignment): ?>
                                    <option value="<?php echo $assignment['assignment_id']; ?>">
                                        <?php echo htmlspecialchars($assignment['class_name'] . ' - ' . $assignment['subject_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Upload File (PDF/DOC)</label>
                            <input name="paper_file" type="file" required class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100">
                        </div>

                        <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition-colors">
                            Submit Paper
                        </button>
                    </form>
                </div>
            </div>

            <!-- === 3. MY RECENT UPLOADS === -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                        <h2 class="text-lg font-semibold text-gray-900">My Recent Submissions</h2>
                        <a href="my_uploads.php" class="text-sm text-sky-600 font-medium hover:underline">View History &rarr;</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Exam / Class</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($recent_papers)): ?>
                                    <tr><td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">No submissions found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_papers as $paper): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($paper['exam_name']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($paper['class_name'] . ' - ' . $paper['subject_name']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                 <?php echo date('M d, Y h:i A', strtotime($paper['submitted_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                    $status_color = 'bg-gray-100 text-gray-800';
                                                    if ($paper['status'] === 'approved') $status_color = 'bg-green-100 text-green-800';
                                                    if ($paper['status'] === 'rejected') $status_color = 'bg-red-100 text-red-800';
                                                    if ($paper['status'] === 'pending_review') $status_color = 'bg-yellow-100 text-yellow-800';
                                                ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_color; ?>">
                                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $paper['status']))); ?>
                                                </span>
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
    </div>
</main>
<?php include 'footer.php'; ?>