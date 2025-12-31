<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// --- 1. Check if user is logged in and is a teacher ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    // Save a flash message and redirect
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
$flash_message = get_flash_message();
$active_exams = [];
$teacher_assignments = [];
$recent_papers = [];

// --- NEW: Correction Papers ---
$correction_papers_to_review = [];

try {
    // --- 3. Fetch Active Exams for Dropdown ---
    $stmt = $pdo->prepare("SELECT exam_id, name FROM exams WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $active_exams = $stmt->fetchAll();

    // --- 4. Fetch Teacher's Assignments for Dropdown ---
    $stmt = $pdo->prepare("
        SELECT 
            a.assignment_id, 
            c.name as class_name, 
            s.name as subject_name 
        FROM assignments a
        JOIN classes c ON a.class_id = c.class_id
        JOIN subjects s ON a.subject_id = s.subject_id
        WHERE a.teacher_id = ?
        ORDER BY c.name, s.name
    ");
    $stmt->execute([$teacher_id]);
    $teacher_assignments = $stmt->fetchAll();

    // --- 5. Fetch Teacher's Recent Submissions ---
    $stmt = $pdo->prepare("
        SELECT 
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
        LIMIT 5
    ");
    $stmt->execute([$teacher_id]);
    $recent_papers = $stmt->fetchAll();

    // --- 6. NEW: Fetch Correction Papers to Review ---
    $stmt_cp = $pdo->prepare("
        SELECT DISTINCT 
            cp.correction_paper_id, 
            cp.original_file_name, 
            cp.uploaded_at,
            c.name as class_name,
            s.name as subject_name
        FROM correction_papers cp
        JOIN classes c ON cp.class_id = c.class_id
        JOIN subjects s ON cp.subject_id = s.subject_id
        JOIN assignments a ON cp.class_id = a.class_id AND cp.subject_id = a.subject_id
        WHERE a.teacher_id = ? AND cp.status = 'pending_review'
        ORDER BY cp.uploaded_at DESC
    ");
    $stmt_cp->execute([$teacher_id]);
    $correction_papers_to_review = $stmt_cp->fetchAll();


} catch (PDOException $e) {
    $error_message = "Database error: Failed to load dashboard data. " . $e->getMessage();
}

// Include header
include 'header.php';
?>

<!-- Main Content -->
<main class="flex-1 p-6 sm:p-10">
    <h1 class="text-3xl font-semibold text-gray-800 mb-6">Teacher Dashboard</h1>

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

    <!-- === NEW: PAPERS FOR CORRECTION MODULE === -->
    <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
        <h2 class="text-xl font-semibold mb-4">Papers for Correction</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paper</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class / Subject</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Uploaded</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($correction_papers_to_review)): ?>
                        <tr><td colspan="4" class="px-4 py-4 text-center text-gray-500">No papers are pending your review.</td></tr>
                    <?php else: ?>
                        <?php foreach ($correction_papers_to_review as $cp): ?>
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($cp['original_file_name']); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($cp['class_name'] . ' - ' . $cp['subject_name']); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(date('M d, Y', strtotime($cp['uploaded_at']))); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="view_correction_paper.php?id=<?php echo $cp['correction_paper_id']; ?>" 
                                       class="text-sky-600 hover:text-sky-900">
                                       Review Paper &rarr;
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- === END NEW MODULE === -->


    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Column 1: Submit New Paper -->
        <div class="lg:col-span-1 bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-semibold mb-4">Submit New Exam Paper</h2>
            
            <form action="upload_paper_action.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                
                <div>
                    <label for="exam_id" class="block text-sm font-medium text-gray-700">1. Select Exam</label>
                    <select id="exam_id" name="exam_id" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                        <option value="">-- Select an active exam --</option>
                        <?php if (empty($active_exams)): ?>
                            <option value="" disabled>No active exams found</option>
                        <?php else: ?>
                            <?php foreach ($active_exams as $exam): ?>
                                <option value="<?php echo $exam['exam_id']; ?>"><?php echo htmlspecialchars($exam['name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div>
                    <label for="assignment_id" class="block text-sm font-medium text-gray-700">2. Select Your Assignment</label>
                    <select id="assignment_id" name="assignment_id" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                        <option value="">-- Select your class & subject --</option>
                        <?php if (empty($teacher_assignments)): ?>
                            <option value="" disabled>No assignments found for you</option>
                        <?php else: ?>
                            <?php foreach ($teacher_assignments as $assignment): ?>
                                <option value="<?php echo $assignment['assignment_id']; ?>">
                                    <?php echo htmlspecialchars($assignment['class_name'] . ' - ' . $assignment['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div>
                    <label for="paper_file" class="block text-sm font-medium text-gray-700">3. Upload Paper File</label>
                    <input id="paper_file" name="paper_file" type="file" required
                           class="mt-1 block w-full text-sm text-gray-500
                                  file:mr-4 file:py-2 file:px-4
                                  file:rounded-md file:border-0
                                  file:text-sm file:font-semibold
                                  file:bg-sky-50 file:text-sky-700
                                  hover:file:bg-sky-100">
                    <p class="mt-1 text-xs text-gray-500">Allowed types: PDF, DOC, DOCX. Max size: 10MB.</p>
                </div>

                <div>
                    <button type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition duration-150 ease-in-out">
                        Submit Paper
                    </button>
                </div>
            </form>
        </div>

        <!-- Column 2: My Recent Submissions -->
        <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-semibold mb-4">My Recent Submissions</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($recent_papers)): ?>
                            <tr>
                                <td colspan="3" class="px-4 py-4 text-sm text-gray-500 text-center">You have not submitted any papers yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_papers as $paper): ?>
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($paper['exam_name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($paper['class_name'] . ' - ' . $paper['subject_name']); ?></div>
                                        <div class="text-xs text-gray-400">Uploaded: <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($paper['submitted_at']))); ?></div>
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
                                        <!-- Note: This link needs to be updated to the secure download.php -->
                                        <a href="<?php echo htmlspecialchars($paper['file_path']); ?>" target="_blank"
                                           class="text-sky-600 hover:text-sky-900">
                                            View File
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                 <a href="my_uploads.php" class="inline-block mt-4 text-sm font-medium text-sky-600 hover:text-sky-800">
                    View All My Submissions &rarr;
                </a>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>