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
$admin_id = $_SESSION['user_id'];
$error_message = '';
$flash_message = get_flash_message();
$exams = [];
$classes = [];
$subjects = [];
$papers = [];

// --- NEW: Get filter values from URL ---
$filter_exam_id = $_GET['filter_exam_id'] ?? null;
$filter_class_id = $_GET['filter_class_id'] ?? null;
$filter_subject_id = $_GET['filter_subject_id'] ?? null;
$filter_key_status = $_GET['filter_key_status'] ?? null;

try {
    // 2. Fetch data for dropdowns (used for both form and filters)
    $exams = $pdo->query("SELECT exam_id, name FROM exams ORDER BY name")->fetchAll();
    $classes = $pdo->query("SELECT class_id, name FROM classes ORDER BY name")->fetchAll();
    $subjects = $pdo->query("SELECT subject_id, name FROM subjects ORDER BY name")->fetchAll();

    // --- NEW: Build Dynamic Query ---
    $sql_params = [];
    $sql_where = [];

    // 3. Fetch all uploaded papers with details
    $sql_base = "
        SELECT 
            p.*, 
            e.name as exam_name, 
            c.name as class_name, 
            s.name as subject_name,
            u.name as admin_name,
            ak.answer_key_id,
            ut.name as teacher_name
        FROM admin_papers p
        JOIN exams e ON p.exam_id = e.exam_id
        JOIN classes c ON p.class_id = c.class_id
        JOIN subjects s ON p.subject_id = s.subject_id
        JOIN users u ON p.admin_id = u.user_id
        LEFT JOIN answer_keys ak ON p.paper_id = ak.paper_id
        LEFT JOIN users ut ON ak.teacher_id = ut.user_id
    ";

    if (!empty($filter_exam_id)) {
        $sql_where[] = "p.exam_id = ?";
        $sql_params[] = $filter_exam_id;
    }
    if (!empty($filter_class_id)) {
        $sql_where[] = "p.class_id = ?";
        $sql_params[] = $filter_class_id;
    }
    if (!empty($filter_subject_id)) {
        $sql_where[] = "p.subject_id = ?";
        $sql_params[] = $filter_subject_id;
    }
    if ($filter_key_status === 'submitted') {
        $sql_where[] = "ak.answer_key_id IS NOT NULL";
    }
    if ($filter_key_status === 'awaiting') {
        $sql_where[] = "ak.answer_key_id IS NULL";
    }

    if (!empty($sql_where)) {
        $sql_base .= " WHERE " . implode(" AND ", $sql_where);
    }

    $sql_base .= " ORDER BY p.uploaded_at DESC";

    $stmt = $pdo->prepare($sql_base);
    $stmt->execute($sql_params);
    $papers = $stmt->fetchAll();


} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

include 'header.php';
?>

<!-- Main Content -->
<main class="flex-1 p-6 sm:p-10">
    <h1 class="text-3xl font-semibold text-gray-800 mb-6">MCQs Answer Key Management</h1>

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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Column 1: Upload New Paper -->
        <div class="lg:col-span-1 bg-white p-6 rounded-lg shadow-lg h-fit"> <!-- Added h-fit -->
            <h2 class="text-xl font-semibold mb-4">Upload New Paper</h2>
            
            <form action="upload_admin_paper_action.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                
                <div>
                    <label for="exam_id" class="block text-sm font-medium text-gray-700">Exam</label>
                    <select id="exam_id" name="exam_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                        <option value="">-- Select Exam --</option>
                        <?php foreach ($exams as $exam): ?>
                            <option value="<?php echo $exam['exam_id']; ?>"><?php echo htmlspecialchars($exam['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="class_id" class="block text-sm font-medium text-gray-700">Class</label>
                    <select id="class_id" name="class_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                        <option value="">-- Select Class --</option>
                         <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="subject_id" class="block text-sm font-medium text-gray-700">Subject</label>
                    <select id="subject_id" name="subject_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                        <option value="">-- Select Subject --</option>
                         <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['subject_id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                 <div>
                    <label for="num_questions" class="block text-sm font-medium text-gray-700">Number of Questions</label>
                    <input type="number" id="num_questions" name="num_questions" min="1" max="200" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm"
                           placeholder="e.g., 20">
                </div>

                <div>
                    <label for="paper_file" class="block text-sm font-medium text-gray-700">Upload Paper File</label>
                    <input id="paper_file" name="paper_file" type="file" required
                           class="mt-1 block w-full text-sm text-gray-500
                                  file:mr-4 file:py-2 file:px-4
                                  file:rounded-md file:border-0
                                  file:text-sm file:font-semibold
                                  file:bg-sky-50 file:text-sky-700
                                  hover:file:bg-sky-100">
                    <p class="mt-1 text-xs text-gray-500">Allowed: PDF, DOC, DOCX. Max: 10MB.</p>
                </div>

                <div>
                    <button type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                        Upload Paper
                    </button>
                </div>
            </form>
        </div>

        <!-- Column 2: Uploaded Papers -->
        <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-semibold mb-4">Uploaded Papers</h2>

            <!-- NEW: Filter Form -->
            <form action="manage_papers.php" method="GET" class="mb-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="filter_exam_id" class="block text-sm font-medium text-gray-700">Exam</label>
                        <select id="filter_exam_id" name="filter_exam_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                            <option value="">All Exams</option>
                            <?php foreach ($exams as $item): ?>
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
                            <?php foreach ($classes as $item): ?>
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
                            <?php foreach ($subjects as $item): ?>
                                <option value="<?php echo $item['subject_id']; ?>" <?php echo ($filter_subject_id == $item['subject_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="filter_key_status" class="block text-sm font-medium text-gray-700">Key Status</label>
                        <select id="filter_key_status" name="filter_key_status" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                            <option value="">All Statuses</option>
                            <option value="submitted" <?php echo ($filter_key_status == 'submitted') ? 'selected' : ''; ?>>Submitted</option>
                            <option value="awaiting" <?php echo ($filter_key_status == 'awaiting') ? 'selected' : ''; ?>>Awaiting Key</option>
                        </select>
                    </div>
                    <div class="col-span-1 md:col-span-2 flex items-end space-x-2">
                         <button type="submit"
                                class="flex-1 w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                            Filter
                        </button>
                        <a href="manage_papers.php"
                           class="flex-1 w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                            Clear
                        </a>
                    </div>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Answer Key</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($papers)): ?>
                            <tr>
                                <td colspan="2" class="px-4 py-4 text-sm text-gray-500 text-center">No papers match your filter criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($papers as $paper): ?>
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($paper['exam_name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($paper['class_name'] . ' - ' . $paper['subject_name']); ?> (<?php echo $paper['num_questions']; ?> Qs)</div>
                                        <div class="text-xs text-gray-400">By: <?php echo htmlspecialchars($paper['admin_name']); ?> on <?php echo htmlspecialchars(date('M d, Y', strtotime($paper['uploaded_at']))); ?></div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm">
                                        <?php if ($paper['answer_key_id']): ?>
                                            <a href="view_key_admin.php?paper_id=<?php echo $paper['paper_id']; ?>" target="_blank"
                                               class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                                                View Key
                                            </a>
                                            <div class="text-xs text-gray-500 mt-1">By: <?php echo htmlspecialchars($paper['teacher_name']); ?></div>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                Awaiting Key
                                            </span>
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

