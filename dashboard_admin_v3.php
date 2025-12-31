<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('Asia/Kolkata'); 

require_once 'config.php';

// --- 1. Auth Check (ADMIN) ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Access Denied. Admin only.'];
    header('Location: index.php');
    exit;
}

// --- 2. Initialize Variables (PREVENT CRASHES) ---
$error_message = '';
$flash_message = null;

// Data containers
$all_corrections = [];
$teacher_uploads = [];
$classes = [];
$subjects = [];
$teachers = []; 

// Stats
$total_pending = 0;
$total_completed = 0;
$total_in_progress = 0;

// Handle Flash Messages
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Get Filter Inputs
$filter_class = $_GET['class_id'] ?? '';
$filter_subject = $_GET['subject_id'] ?? '';
$filter_teacher = $_GET['teacher_id'] ?? ''; 
$filter_status = $_GET['status'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// --- 3. Fetch Data ---
try {
    // A. Fetch Dropdown Data
    $classes = $pdo->query("SELECT class_id, name FROM classes ORDER BY name")->fetchAll();
    $subjects = $pdo->query("SELECT subject_id, name FROM subjects ORDER BY name")->fetchAll();
    $teachers = $pdo->query("SELECT user_id, name FROM users WHERE role = 'teacher' ORDER BY name")->fetchAll();

    // =========================================================
    // QUERY 1: CORRECTION PAPERS (Student Submissions)
    // =========================================================
    $query_corrections = "
        SELECT 
            cp.correction_paper_id,
            cp.original_file_name,
            cp.uploaded_at,
            cp.status,
            c.name as class_name,
            s.name as subject_name,
            u.name as teacher_name  
        FROM correction_papers cp
        JOIN classes c ON cp.class_id = c.class_id
        JOIN subjects s ON cp.subject_id = s.subject_id
        LEFT JOIN assignments a ON cp.class_id = a.class_id AND cp.subject_id = a.subject_id
        LEFT JOIN users u ON a.teacher_id = u.user_id
        WHERE 1=1
    ";

    $params_corr = [];

    // Apply Filters to Corrections
    if (!empty($filter_class)) {
        $query_corrections .= " AND cp.class_id = ?";
        $params_corr[] = $filter_class;
    }
    if (!empty($filter_subject)) {
        $query_corrections .= " AND cp.subject_id = ?";
        $params_corr[] = $filter_subject;
    }
    if (!empty($filter_teacher)) {
        $query_corrections .= " AND a.teacher_id = ?";
        $params_corr[] = $filter_teacher;
    }
    if (!empty($filter_status)) {
        $query_corrections .= " AND cp.status = ?";
        $params_corr[] = $filter_status;
    }
    if (!empty($filter_date_from)) {
        $query_corrections .= " AND DATE(cp.uploaded_at) >= ?";
        $params_corr[] = $filter_date_from;
    }
    if (!empty($filter_date_to)) {
        $query_corrections .= " AND DATE(cp.uploaded_at) <= ?";
        $params_corr[] = $filter_date_to;
    }

    $query_corrections .= " ORDER BY cp.uploaded_at DESC";

    $stmt = $pdo->prepare($query_corrections);
    $stmt->execute($params_corr);
    $all_corrections = $stmt->fetchAll();


    // =========================================================
    // QUERY 2: TEACHER UPLOADS (Filtered)
    // =========================================================
    $query_uploads = "
        SELECT 
            p.paper_id,
            p.file_path,
            p.submitted_at,
            p.status,
            p.submission_type,
            e.name as exam_name,
            c.name as class_name,
            s.name as subject_name,
            u.name as teacher_name
        FROM papers p
        JOIN exams e ON p.exam_id = e.exam_id
        JOIN assignments a ON p.assignment_id = a.assignment_id
        JOIN classes c ON a.class_id = c.class_id
        JOIN subjects s ON a.subject_id = s.subject_id
        JOIN users u ON p.teacher_id = u.user_id
        WHERE 1=1
    ";

    $params_up = [];

    // Apply Filters to Teacher Uploads
    if (!empty($filter_class)) {
        $query_uploads .= " AND a.class_id = ?";
        $params_up[] = $filter_class;
    }
    if (!empty($filter_subject)) {
        $query_uploads .= " AND a.subject_id = ?";
        $params_up[] = $filter_subject;
    }
    if (!empty($filter_teacher)) {
        $query_uploads .= " AND p.teacher_id = ?";
        $params_up[] = $filter_teacher;
    }
    if (!empty($filter_date_from)) {
        $query_uploads .= " AND DATE(p.submitted_at) >= ?";
        $params_up[] = $filter_date_from;
    }
    if (!empty($filter_date_to)) {
        $query_uploads .= " AND DATE(p.submitted_at) <= ?";
        $params_up[] = $filter_date_to;
    }

    $query_uploads .= " ORDER BY p.submitted_at DESC LIMIT 100"; 

    $stmt_up = $pdo->prepare($query_uploads);
    $stmt_up->execute($params_up);
    $teacher_uploads = $stmt_up->fetchAll();


    // C. Fetch Stats
    $total_pending = $pdo->query("SELECT COUNT(*) FROM correction_papers WHERE status = 'pending_review'")->fetchColumn();
    $total_completed = $pdo->query("SELECT COUNT(*) FROM correction_papers WHERE status = 'completed'")->fetchColumn();
    $total_in_progress = $pdo->query("SELECT COUNT(*) FROM correction_papers WHERE status = 'in_progress'")->fetchColumn();

} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

include 'header.php';
?>

<main class="flex-1 bg-gray-50 min-h-screen p-6 lg:p-10">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <!-- Header & Stats -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Admin Dashboard</h1>
                <p class="mt-1 text-sm text-gray-500">Monitor corrections, teacher uploads, and assign new work.</p>
            </div>
            
            <div class="flex flex-wrap gap-4">
                <div class="bg-white px-4 py-3 rounded-lg shadow-sm border border-gray-200 min-w-[120px]">
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Pending</span>
                    <span class="block text-2xl font-bold text-orange-600"><?php echo $total_pending; ?></span>
                </div>
                <div class="bg-white px-4 py-3 rounded-lg shadow-sm border border-gray-200 min-w-[120px]">
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">In Progress</span>
                    <span class="block text-2xl font-bold text-blue-600"><?php echo $total_in_progress; ?></span>
                </div>
                <div class="bg-white px-4 py-3 rounded-lg shadow-sm border border-gray-200 min-w-[120px]">
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Completed</span>
                    <span class="block text-2xl font-bold text-green-600"><?php echo $total_completed; ?></span>
                </div>
            </div>
        </div>

        <?php if ($flash_message): ?>
            <div class="rounded-md p-4 border-l-4 shadow-sm <?php echo $flash_message['type'] === 'success' ? 'bg-green-50 border-green-400 text-green-800' : 'bg-red-50 border-red-400 text-red-800'; ?>">
                <?php echo htmlspecialchars($flash_message['message']); ?>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="rounded-md p-4 border-l-4 bg-red-50 border-red-400 text-red-800 shadow-sm">
                <strong>System Error:</strong> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- NEW: Upload Paper for Correction (Admin Override) -->
        <div class="bg-white rounded-xl shadow-lg border border-indigo-100 overflow-hidden">
            <div class="px-6 py-4 bg-indigo-50 border-b border-indigo-100 flex items-center justify-between">
                <h2 class="text-lg font-bold text-indigo-900 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Upload Paper for Correction
                </h2>
                <span class="text-xs text-indigo-600 bg-white px-2 py-1 rounded-full font-medium">Assign to Teacher</span>
            </div>
            <div class="p-6">
                <!-- FIX: Updated action to point to the correct file and name to match backend -->
                <form action="upload_correction_paper_action_v3.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Class</label>
                        <select name="class_id" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Select Class</option>
                            <?php foreach($classes as $c): ?>
                                <option value="<?php echo $c['class_id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Subject</label>
                        <select name="subject_id" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Select Subject</option>
                            <?php foreach($subjects as $s): ?>
                                <option value="<?php echo $s['subject_id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Student Paper (PDF/IMG)</label>
                        <!-- FIX: Name changed to correction_paper_file -->
                        <input type="file" name="correction_paper_file" required class="w-full text-sm text-gray-500 file:mr-2 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>
                    <div>
                        <button type="submit" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Upload & Assign
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Filters Section (Applies to BOTH tables) -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                Global Filters
            </h2>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                
                <!-- Class -->
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Class</label>
                    <select name="class_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">All Classes</option>
                        <?php foreach($classes as $c): ?>
                            <option value="<?php echo $c['class_id']; ?>" <?php echo $filter_class == $c['class_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Subject -->
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Subject</label>
                    <select name="subject_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">All Subjects</option>
                        <?php foreach($subjects as $s): ?>
                            <option value="<?php echo $s['subject_id']; ?>" <?php echo $filter_subject == $s['subject_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Teacher (NEW) -->
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Teacher</label>
                    <select name="teacher_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">All Teachers</option>
                        <?php foreach($teachers as $t): ?>
                            <option value="<?php echo $t['user_id']; ?>" <?php echo $filter_teacher == $t['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">All Statuses</option>
                        <option value="pending_review" <?php echo $filter_status == 'pending_review' ? 'selected' : ''; ?>>Pending Review</option>
                        <option value="in_progress" <?php echo $filter_status == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>

                <!-- Dates -->
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">From</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-700 mb-1">To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <button type="submit" class="mb-[2px] px-3 py-2 bg-indigo-600 text-white rounded-md shadow-sm text-sm font-medium hover:bg-indigo-700">Filter</button>
                    <a href="dashboard_admin_test.php" class="mb-[2px] px-3 py-2 border border-gray-300 bg-white text-gray-700 rounded-md shadow-sm text-sm font-medium hover:bg-gray-50">Reset</a>
                </div>
            </form>
        </div>

        <!-- TABLE 1: CORRECTION QUEUE -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900">Correction Log (Assigned to Teachers)</h2>
                <div class="text-xs text-gray-500">Showing <?php echo count($all_corrections); ?> records</div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">File & Teacher</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class Info</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Upload Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($all_corrections)): ?>
                            <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500 italic">No corrections found matching your filters.</td></tr>
                        <?php else: ?>
                            <?php foreach($all_corrections as $cp): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 mb-0.5"><?php echo htmlspecialchars($cp['original_file_name']); ?></div>
                                        <div class="text-xs text-gray-500 flex items-center gap-1">
                                            <span class="text-gray-400">Assigned:</span>
                                            <span class="font-medium text-indigo-700"><?php echo htmlspecialchars($cp['teacher_name'] ?? 'Unassigned'); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            <?php echo htmlspecialchars($cp['class_name']); ?>
                                        </span>
                                        <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($cp['subject_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div><?php echo date('M d, Y', strtotime($cp['uploaded_at'])); ?></div>
                                        <div class="text-xs text-gray-400"><?php echo date('h:i A', strtotime($cp['uploaded_at'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                            $st = $cp['status'];
                                            $badge_class = 'bg-gray-100 text-gray-800';
                                            if($st == 'pending_review') $badge_class = 'bg-orange-100 text-orange-800';
                                            if($st == 'in_progress') $badge_class = 'bg-blue-100 text-blue-800';
                                            if($st == 'completed') $badge_class = 'bg-green-100 text-green-800';
                                        ?>
                                        <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $badge_class; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $st)); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="view_correction_paper.php?id=<?php echo $cp['correction_paper_id']; ?>" class="text-indigo-600 hover:text-indigo-900">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TABLE 2: TEACHER UPLOADS (Filtered) -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mt-8">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900">Teacher Uploads (Question Papers & Notes)</h2>
                <div class="text-xs text-gray-500">Showing <?php echo count($teacher_uploads); ?> records (Filtered)</div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Exam & Teacher</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Assignment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">File</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($teacher_uploads)): ?>
                            <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500 italic">No teacher uploads found matching your filters.</td></tr>
                        <?php else: ?>
                            <?php foreach($teacher_uploads as $up): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($up['exam_name']); ?></div>
                                        <div class="text-xs text-gray-500">By: <?php echo htmlspecialchars($up['teacher_name'] ?? 'Unknown'); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($up['class_name']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($up['subject_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                         <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $up['submission_type'] === 'text' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo $up['submission_type'] === 'text' ? 'Typed' : 'File'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y', strtotime($up['submitted_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            <?php echo ucwords(str_replace('_', ' ', $up['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <?php if($up['submission_type'] === 'file'): ?>
                                            <a href="<?php echo htmlspecialchars($up['file_path']); ?>" target="_blank" class="text-indigo-600 hover:text-indigo-900">Download</a>
                                        <?php else: ?>
                                            <a href="view_paper_content.php?id=<?php echo $up['paper_id']; ?>" target="_blank" class="text-indigo-600 hover:text-indigo-900">View Text</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>
<?php include 'footer.php'; ?>