<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// 1. Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'You must be logged in as an admin to access this page.'
    ];
    header('Location: index.php');
    exit;
}

$error_message = '';
$flash_message = get_flash_message();
$stats = [
    'teachers' => 0,
    'classes' => 0,
    'subjects' => 0,
    'pending_papers' => 0
];
$papers = [];
$class_chart_data = [];
$detailed_chart_data = [];
$assignments_chart_data = [];
$modal_details = [];

// --- Data for filters ---
$filter_teachers = [];
$filter_classes = [];
$filter_subjects = [];
$filter_exams = [];
$statuses = ['pending_review', 'approved', 'rejected'];

// --- Get filter values from URL ---
$filter_teacher_id = $_GET['filter_teacher_id'] ?? null;
$filter_class_id = $_GET['filter_class_id'] ?? null;
$filter_subject_id = $_GET['filter_subject_id'] ?? null;
$filter_exam_id = $_GET['filter_exam_id'] ?? null;
$filter_status = $_GET['filter_status'] ?? null;

// --- NEW: Helper to get exam name for chart title ---
$current_exam_name = 'All Exams';

try {
    // 2. Fetch statistics
    $stats['teachers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
    $stats['classes'] = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    $stats['subjects'] = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
    $stats['pending_papers'] = $pdo->query("SELECT COUNT(*) FROM papers WHERE status = 'pending_review'")->fetchColumn();

    // --- Fetch data for filter dropdowns ---
    $filter_teachers = $pdo->query("SELECT user_id, name FROM users WHERE role = 'teacher' ORDER BY name")->fetchAll();
    $filter_classes = $pdo->query("SELECT class_id, name FROM classes ORDER BY name")->fetchAll();
    $filter_subjects = $pdo->query("SELECT subject_id, name FROM subjects ORDER BY name")->fetchAll();
    $filter_exams = $pdo->query("SELECT exam_id, name FROM exams ORDER BY name")->fetchAll();

    // Get current exam name if filtered
    if (!empty($filter_exam_id)) {
        $stmt_exam_name = $pdo->prepare("SELECT name FROM exams WHERE exam_id = ?");
        $stmt_exam_name->execute([$filter_exam_id]);
        $current_exam_name = $stmt_exam_name->fetchColumn() ?: 'All Exams';
    }

    // --- Build Shared Filter Query Parts ---
    $sql_where = [];
    $sql_params = [];

    if (!empty($filter_teacher_id)) {
        $sql_where[] = "p.teacher_id = ?";
        $sql_params[] = $filter_teacher_id;
    }
    if (!empty($filter_class_id)) {
        $sql_where[] = "a.class_id = ?";
        $sql_params[] = $filter_class_id;
    }
    if (!empty($filter_subject_id)) {
        $sql_where[] = "a.subject_id = ?";
        $sql_params[] = $filter_subject_id;
    }
    if (!empty($filter_exam_id)) {
        $sql_where[] = "p.exam_id = ?";
        $sql_params[] = $filter_exam_id;
    }
    if (!empty($filter_status)) {
        $sql_where[] = "p.status = ?";
        $sql_params[] = $filter_status;
    }

    $sql_where_clause = !empty($sql_where) ? " WHERE " . implode(" AND ", $sql_where) : "";

    // --- CHART 3: Assignments Overview by Class (Filtered by Exam) ---
    // 1. Get total assigned subjects per class (Always the same regardless of filters)
    $sql_total_assigned = "
        SELECT c.name as class_name, COUNT(a.subject_id) as total_assigned
        FROM classes c
        LEFT JOIN assignments a ON c.class_id = a.class_id
        GROUP BY c.class_id, c.name
        ORDER BY c.name
    ";
    $total_assigned = $pdo->query($sql_total_assigned)->fetchAll(PDO::FETCH_KEY_PAIR);

    // 2. Get number of subjects with at least one submission per class (Filtered by EXAM only for this chart)
    // We use a specific WHERE clause just for this chart to allow filtering by Exam without other filters interfering
    $submitted_where = [];
    $submitted_params = [];
    if (!empty($filter_exam_id)) {
        $submitted_where[] = "p.exam_id = ?";
        $submitted_params[] = $filter_exam_id;
    }
    $submitted_where_clause_chart = !empty($submitted_where) ? " WHERE " . implode(" AND ", $submitted_where) : "";

    $sql_submitted_count = "
        SELECT c.name as class_name, COUNT(DISTINCT a.subject_id) as submitted_count
        FROM classes c
        JOIN assignments a ON c.class_id = a.class_id
        JOIN papers p ON a.assignment_id = p.assignment_id
        $submitted_where_clause_chart
        GROUP BY c.name
    ";
    $stmt_submitted = $pdo->prepare($sql_submitted_count);
    $stmt_submitted->execute($submitted_params);
    $submitted_counts = $stmt_submitted->fetchAll(PDO::FETCH_KEY_PAIR);

    // --- Fetch Detailed Subject Lists for Modal ---
    // Get ALL assigned subjects for each class
    $sql_all_subjects = "
        SELECT c.name as class_name, s.name as subject_name, u.name as teacher_name
        FROM classes c
        JOIN assignments a ON c.class_id = a.class_id
        JOIN subjects s ON a.subject_id = s.subject_id
        LEFT JOIN users u ON a.teacher_id = u.user_id
        ORDER BY c.name, s.name
    ";
    $all_subjects_raw = $pdo->query($sql_all_subjects)->fetchAll(PDO::FETCH_ASSOC);

    // Get submitted subjects for each class (Filtered by EXAM)
    $sql_submitted_subjects = "
        SELECT DISTINCT c.name as class_name, s.name as subject_name
        FROM classes c
        JOIN assignments a ON c.class_id = a.class_id
        JOIN subjects s ON a.subject_id = s.subject_id
        JOIN papers p ON a.assignment_id = p.assignment_id
        $submitted_where_clause_chart
    ";
    $stmt_submitted_subs = $pdo->prepare($sql_submitted_subjects);
    $stmt_submitted_subs->execute($submitted_params);
    $submitted_subjects_raw = $stmt_submitted_subs->fetchAll(PDO::FETCH_ASSOC);

    // Organize data for easy lookup in JS
    $submitted_lookup = [];
    foreach ($submitted_subjects_raw as $row) {
        $submitted_lookup[$row['class_name']][] = $row['subject_name'];
    }

    foreach ($all_subjects_raw as $row) {
        $className = $row['class_name'];
        $subjectName = $row['subject_name'];
        $teacherName = $row['teacher_name'] ?? 'N/A';
        
        if (!isset($modal_details[$className])) {
            $modal_details[$className] = [
                'uploaded' => [],
                'not_uploaded' => []
            ];
        }

        $isSubmitted = isset($submitted_lookup[$className]) && in_array($subjectName, $submitted_lookup[$className]);
        $details = ['subject' => $subjectName, 'teacher' => $teacherName];

        if ($isSubmitted) {
            $modal_details[$className]['uploaded'][] = $details;
        } else {
            $modal_details[$className]['not_uploaded'][] = $details;
        }
    }


    // 3. Combine data for the chart
    $classes_for_chart = array_keys($total_assigned);
    $assignments_chart_data['labels'] = $classes_for_chart;
    foreach ($classes_for_chart as $class_name) {
         $assignments_chart_data['total'][] = (int)($total_assigned[$class_name] ?? 0);
         $assignments_chart_data['submitted'][] = (int)($submitted_counts[$class_name] ?? 0);
    }


    // --- CHART 1: Aggregated by CLASS only ---
    $sql_class_chart = "
        SELECT
            c.name as class_name,
            p.status,
            COUNT(p.paper_id) as count
        FROM papers p
        JOIN assignments a ON p.assignment_id = a.assignment_id
        JOIN classes c ON a.class_id = c.class_id
        $sql_where_clause
        GROUP BY c.name, p.status
        ORDER BY c.name
    ";
    $stmt_class = $pdo->prepare($sql_class_chart);
    $stmt_class->execute($sql_params);
    $raw_class_data = $stmt_class->fetchAll(PDO::FETCH_ASSOC);

    $class_data_processed = [];
    foreach ($raw_class_data as $row) {
        $key = $row['class_name'];
        if (!isset($class_data_processed[$key])) {
            $class_data_processed[$key] = ['Approved' => 0, 'Rejected' => 0, 'Pending Review' => 0];
        }
        $class_data_processed[$key][ucwords(str_replace('_', ' ', $row['status']))] = (int)$row['count'];
    }
    $class_chart_data['labels'] = array_keys($class_data_processed);
    $class_chart_data['approved'] = array_column($class_data_processed, 'Approved');
    $class_chart_data['rejected'] = array_column($class_data_processed, 'Rejected');
    $class_chart_data['pending'] = array_column($class_data_processed, 'Pending Review');

    // --- CHART 2: Detailed Aggregation (Class AND Subject) ---
    $sql_detailed_chart = "
        SELECT
            c.name as class_name,
            s.name as subject_name,
            p.status,
            COUNT(p.paper_id) as count
        FROM papers p
        JOIN assignments a ON p.assignment_id = a.assignment_id
        JOIN classes c ON a.class_id = c.class_id
        JOIN subjects s ON a.subject_id = s.subject_id
        $sql_where_clause
        GROUP BY c.name, s.name, p.status
        ORDER BY c.name, s.name
    ";
    $stmt_detailed = $pdo->prepare($sql_detailed_chart);
    $stmt_detailed->execute($sql_params);
    $raw_detailed_data = $stmt_detailed->fetchAll(PDO::FETCH_ASSOC);

    $detailed_data_processed = [];
    foreach ($raw_detailed_data as $row) {
        $key = $row['class_name'] . ' - ' . $row['subject_name'];
        if (!isset($detailed_data_processed[$key])) {
            $detailed_data_processed[$key] = ['Approved' => 0, 'Rejected' => 0, 'Pending Review' => 0];
        }
        $detailed_data_processed[$key][ucwords(str_replace('_', ' ', $row['status']))] = (int)$row['count'];
    }
    $detailed_chart_data['labels'] = array_keys($detailed_data_processed);
    $detailed_chart_data['approved'] = array_column($detailed_data_processed, 'Approved');
    $detailed_chart_data['rejected'] = array_column($detailed_data_processed, 'Rejected');
    $detailed_chart_data['pending'] = array_column($detailed_data_processed, 'Pending Review');

    // --- TABLE DATA ---
    $sql_base = "
        SELECT 
            p.paper_id,
            p.file_path,
            p.status,
            p.submitted_at as upload_timestamp,
            u.name as teacher_name,
            c.name as class_name,
            s.name as subject_name,
            e.name as exam_name
        FROM papers p
        JOIN users u ON p.teacher_id = u.user_id
        JOIN assignments a ON p.assignment_id = a.assignment_id
        JOIN classes c ON a.class_id = c.class_id
        JOIN subjects s ON a.subject_id = s.subject_id
        JOIN exams e ON p.exam_id = e.exam_id
        $sql_where_clause
        ORDER BY p.submitted_at DESC
    ";
    $stmt = $pdo->prepare($sql_base);
    $stmt->execute($sql_params);
    $papers = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = "Database error: Could not load dashboard data. " . $e->getMessage();
}

// Prepare JSON for JS
$assignments_chart_json = json_encode($assignments_chart_data);
$modal_details_json = json_encode($modal_details);
$class_chart_json = json_encode($class_chart_data);
$detailed_chart_json = json_encode($detailed_chart_data);
$current_exam_json = json_encode($current_exam_name);

include 'header.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<!-- Main Content -->
<main class="flex-1 p-6 sm:p-10 relative">
    <h1 class="text-3xl font-semibold text-gray-800 mb-6">Admin Dashboard</h1>

    <!-- Flash / Error Messages -->
    <?php if ($flash_message): ?>
        <div class="mb-6 rounded-md <?php echo $flash_message['type'] === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?> border p-4">
            <p><?php echo htmlspecialchars($flash_message['message']); ?></p>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="mb-6 rounded-md bg-red-100 border-red-400 text-red-700 border p-4">
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-white p-5 rounded-lg shadow-md border-b-4 border-sky-500">
            <div class="text-sm font-medium text-gray-500">Total Teachers</div>
            <div class="mt-1 text-3xl font-semibold text-gray-900"><?php echo htmlspecialchars($stats['teachers']); ?></div>
        </div>
        <div class="bg-white p-5 rounded-lg shadow-md border-b-4 border-sky-500">
            <div class="text-sm font-medium text-gray-500">Total Classes</div>
            <div class="mt-1 text-3xl font-semibold text-gray-900"><?php echo htmlspecialchars($stats['classes']); ?></div>
        </div>
        <div class="bg-white p-5 rounded-lg shadow-md border-b-4 border-sky-500">
            <div class="text-sm font-medium text-gray-500">Total Subjects</div>
            <div class="mt-1 text-3xl font-semibold text-gray-900"><?php echo htmlspecialchars($stats['subjects']); ?></div>
        </div>
        <div class="bg-white p-5 rounded-lg shadow-md border-b-4 border-yellow-500">
            <div class="text-sm font-medium text-gray-500">Pending Papers</div>
            <div class="mt-1 text-3xl font-semibold text-yellow-600"><?php echo htmlspecialchars($stats['pending_papers']); ?></div>
        </div>
    </div>
    
    <!-- Filter Form -->
    <div id="filter-form" class="bg-white p-6 rounded-lg shadow-lg mb-6">
        <h2 class="text-xl font-semibold mb-4">Filter Submissions</h2>
        <form action="dashboard_admin.php" method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div>
                <label for="filter_exam_id" class="block text-sm font-medium text-gray-700">Exam</label>
                <select id="filter_exam_id" name="filter_exam_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                    <option value="">All Exams</option>
                    <?php foreach ($filter_exams as $item): ?>
                        <option value="<?php echo $item['exam_id']; ?>" <?php echo ($filter_exam_id == $item['exam_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($item['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_class_id" class="block text-sm font-medium text-gray-700">Class</label>
                <select id="filter_class_id" name="filter_class_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                    <option value="">All Classes</option>
                     <?php foreach ($filter_classes as $item): ?>
                        <option value="<?php echo $item['class_id']; ?>" <?php echo ($filter_class_id == $item['class_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($item['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_subject_id" class="block text-sm font-medium text-gray-700">Subject</label>
                <select id="filter_subject_id" name="filter_subject_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                    <option value="">All Subjects</option>
                     <?php foreach ($filter_subjects as $item): ?>
                        <option value="<?php echo $item['subject_id']; ?>" <?php echo ($filter_subject_id == $item['subject_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($item['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_teacher_id" class="block text-sm font-medium text-gray-700">Teacher</label>
                <select id="filter_teacher_id" name="filter_teacher_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                    <option value="">All Teachers</option>
                     <?php foreach ($filter_teachers as $item): ?>
                        <option value="<?php echo $item['user_id']; ?>" <?php echo ($filter_teacher_id == $item['user_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($item['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_status" class="block text-sm font-medium text-gray-700">Status</label>
                <select id="filter_status" name="filter_status" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                    <option value="">All Statuses</option>
                     <?php foreach ($statuses as $status): ?>
                        <option value="<?php echo $status; ?>" <?php echo ($filter_status == $status) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $status))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end space-x-2">
                <button type="submit" class="flex-1 w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">Filter</button>
                <a href="dashboard_admin.php" class="flex-1 w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">Clear</a>
            </div>
        </form>
    </div>
    
    <!-- CHART MODULES -->
    
    <!-- CHART 3: Assignments Overview (Filtered by Exam) -->
    <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
        <h2 class="text-xl font-semibold mb-4 text-gray-800" id="assignmentsChartTitle">Assignments Overview by Class</h2>
        <p class="text-sm text-gray-500 mb-4">Click on a bar to see details for <strong id="currentExamName">All Exams</strong>.</p>
        <div class="relative h-96">
            <?php if (!empty($assignments_chart_data['labels'])): ?>
                <canvas id="assignmentsOverviewChart"></canvas>
            <?php else: ?>
                <div class="flex h-full items-center justify-center text-gray-500">No data available for this view.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- CHART 1: By Class -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Paper Status by Class</h2>
            <div class="relative h-80">
                <?php if (!empty($class_chart_data['labels'])): ?>
                    <canvas id="classStatusChart"></canvas>
                <?php else: ?>
                    <div class="flex h-full items-center justify-center text-gray-500">No data available for this view.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CHART 2: Detailed -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Detailed Status (Class - Subject)</h2>
            <div class="relative h-80">
                <?php if (!empty($detailed_chart_data['labels'])): ?>
                    <canvas id="detailedStatusChart"></canvas>
                <?php else: ?>
                    <div class="flex h-full items-center justify-center text-gray-500">No data available for this view.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Paper Submissions Table -->
    <div id="papers-table" class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-semibold mb-4">All Paper Submissions</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File / Download</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($papers)): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-sm text-gray-500 text-center">No papers match your filter criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($papers as $paper): ?>
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($paper['exam_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($paper['class_name'] . ' - ' . $paper['subject_name']); ?></div>
                                    <div class="text-xs text-gray-400">Uploaded: <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($paper['upload_timestamp']))); ?></div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($paper['teacher_name']); ?></div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <?php
                                        $status_color = 'bg-gray-100 text-gray-800';
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
                                    <a href="download.php?paper_id=<?php echo $paper['paper_id']; ?>" 
                                       class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                                        <svg class="-ml-0.5 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                          <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                        Download
                                    </a>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <?php if ($paper['status'] === 'pending_review'): ?>
                                        <form action="paper_action.php" method="POST" class="inline">
                                            <input type="hidden" name="paper_id" value="<?php echo $paper['paper_id']; ?>">
                                            <button type="submit" name="action" value="approve" class="text-green-600 hover:text-green-900">Approve</button>
                                        </form>
                                        <form action="paper_action.php" method="POST" class="inline">
                                            <input type="hidden" name="paper_id" value="<?php echo $paper['paper_id']; ?>">
                                            <button type="submit" name="action" value="reject" class="text-red-600 hover:text-red-900">Reject</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">Actioned</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- NEW: Detail Modal -->
    <div id="detailModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full m-4">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            <!-- Modal Title will be set by JS -->
                        </h3>
                        <div class="mt-4 max-h-64 overflow-y-auto" id="modal-content">
                            <!-- Modal Content will be injected by JS -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="closeModalBtn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Close
                </button>
            </div>
        </div>
    </div>

</main>

<?php include 'footer.php'; ?>

<!-- Chart Initialization -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Modal Handling ---
        const modal = document.getElementById('detailModal');
        const modalTitle = document.getElementById('modal-title');
        const modalContent = document.getElementById('modal-content');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const modalDetails = <?php echo $modal_details_json; ?>;
        const currentExamName = <?php echo $current_exam_json; ?>;

        // Update Exam Name in Chart Section
        document.getElementById('currentExamName').textContent = currentExamName;
        document.getElementById('assignmentsChartTitle').textContent = `Assignments Overview for ${currentExamName}`;

        function showModal(title, items, isUploaded) {
            modalTitle.textContent = title;
            let html = '<ul class="divide-y divide-gray-200">';
            if (items.length === 0) {
                html += '<li class="py-3 text-sm text-gray-500">No subjects found for this category.</li>';
            } else {
                items.forEach(item => {
                    html += `<li class="py-3 flex justify-between">
                                <span class="text-sm font-medium text-gray-900">${item.subject}</span>
                                <span class="text-sm text-gray-500">${item.teacher}</span>
                             </li>`;
                });
            }
            html += '</ul>';
            modalContent.innerHTML = html;
            modal.classList.remove('hidden');
        }

        function hideModal() {
            modal.classList.add('hidden');
        }

        closeModalBtn.addEventListener('click', hideModal);
        // Optional: Close on click outside
        modal.addEventListener('click', function(e) {
             if (e.target === modal) {
                 hideModal();
             }
        });


        // Helper function for common chart options
        function getChartOptions() {
             return {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, ticks: { autoSkip: false, maxRotation: 90 } },
                    y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } }
                },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                }
            };
        }

        // --- CHART 3: Assignments Overview ---
        const assignmentsData = <?php echo $assignments_chart_json; ?>;
        if (assignmentsData && assignmentsData.labels && assignmentsData.labels.length > 0) {
            const ctxAssignments = document.getElementById('assignmentsOverviewChart').getContext('2d');
            new Chart(ctxAssignments, {
                type: 'bar',
                data: {
                    labels: assignmentsData.labels,
                    datasets: [
                        {
                            label: 'Total Assigned Subjects',
                            data: assignmentsData.total,
                            backgroundColor: 'rgba(59, 130, 246, 0.6)', // Blue-500
                            borderColor: 'rgb(59, 130, 246)',
                            borderWidth: 1
                        },
                        {
                            label: 'Subjects with ≥1 Submission',
                            data: assignmentsData.submitted,
                            backgroundColor: 'rgba(16, 185, 129, 0.8)', // Green-500
                            borderColor: 'rgb(16, 185, 129)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { ticks: { autoSkip: false, maxRotation: 90 } },
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    },
                    plugins: { 
                        legend: { position: 'top' }
                    },
                    // --- CLICK HANDLER ---
                    onClick: (e, elements, chart) => {
                        if (elements.length > 0) {
                            const index = elements[0].index;
                            const datasetIndex = elements[0].datasetIndex;
                            const className = chart.data.labels[index];
                            const datasetLabel = chart.data.datasets[datasetIndex].label;

                            if (modalDetails[className]) {
                                if (datasetLabel === 'Subjects with ≥1 Submission') {
                                    showModal(`${className} - Uploaded Papers (${currentExamName})`, modalDetails[className].uploaded, true);
                                } else if (datasetLabel === 'Total Assigned Subjects') {
                                    showModal(`${className} - Pending Uploads (${currentExamName})`, modalDetails[className].not_uploaded, false);
                                }
                            }
                        }
                    }
                }
            });
        }

        // --- CHART 1: Class Overview ---
        const classData = <?php echo $class_chart_json; ?>;
        if (classData && classData.labels && classData.labels.length > 0) {
            const ctxClass = document.getElementById('classStatusChart').getContext('2d');
            new Chart(ctxClass, {
                type: 'bar',
                data: {
                    labels: classData.labels,
                    datasets: [
                        { label: 'Approved', data: classData.approved, backgroundColor: 'rgba(34, 197, 94, 0.7)' },
                        { label: 'Pending Review', data: classData.pending, backgroundColor: 'rgba(234, 179, 8, 0.7)' },
                        { label: 'Rejected', data: classData.rejected, backgroundColor: 'rgba(239, 68, 68, 0.7)' }
                    ]
                },
                options: getChartOptions()
            });
        }

        // --- CHART 2: Detailed Overview ---
        const detailedData = <?php echo $detailed_chart_json; ?>;
        if (detailedData && detailedData.labels && detailedData.labels.length > 0) {
            const ctxDetailed = document.getElementById('detailedStatusChart').getContext('2d');
            new Chart(ctxDetailed, {
                type: 'bar',
                data: {
                    labels: detailedData.labels,
                    datasets: [
                        { label: 'Approved', data: detailedData.approved, backgroundColor: 'rgba(34, 197, 94, 0.7)' },
                        { label: 'Pending Review', data: detailedData.pending, backgroundColor: 'rgba(234, 179, 8, 0.7)' },
                        { label: 'Rejected', data: detailedData.rejected, backgroundColor: 'rgba(239, 68, 68, 0.7)' }
                    ]
                },
                options: getChartOptions()
            });
        }
    });
</script>