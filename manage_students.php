<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$search = $_GET['search'] ?? '';
$filter_class = $_GET['class_id'] ?? '';

$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
$error_message = null;

// Fetch classes for dropdowns
$classes = [];
try {
    $classes = $pdo->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching classes.";
}

// Fetch students
$students = [];
try {
    $sql = "SELECT s.*, c.name as class_name 
            FROM students s 
            JOIN classes c ON s.class_id = c.class_id 
            WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (s.name LIKE :search OR s.roll_no LIKE :search)";
        $params['search'] = "%$search%";
    }
    if (!empty($filter_class)) {
        $sql .= " AND s.class_id = :class_id";
        $params['class_id'] = $filter_class;
    }

    $sql .= " ORDER BY c.name ASC, s.roll_no ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

?>

<?php require_once 'header.php'; ?>

<main class="flex-1 bg-gray-50 min-h-screen p-6 lg:p-10">
    <div class="w-full space-y-6">
        
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Manage Students</h1>
                <p class="mt-1 text-sm text-gray-500">Add, edit, and view students in the system.</p>
            </div>
            <div class="mt-4 sm:mt-0">
                <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-sm font-medium text-indigo-700 ring-1 ring-inset ring-indigo-700/10">
                    Total Students: <?php echo count($students); ?>
                </span>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if ($flash_message): ?>
            <div class="rounded-md p-4 border-l-4 shadow-sm <?php echo $flash_message['type'] === 'success' ? 'bg-green-50 border-green-400 text-green-800' : 'bg-red-50 border-red-400 text-red-800'; ?>">
                <?php echo htmlspecialchars($flash_message['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="rounded-md bg-red-50 p-4 border-l-4 border-red-400 shadow-sm text-red-800">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Forms Section: Add Student & Bulk Import -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Add Student (Link) -->
            <div class="bg-white shadow-sm border border-gray-200 rounded-xl p-6 flex flex-col justify-center items-center text-center space-y-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Add New Student</h2>
                    <p class="text-sm text-gray-500 mt-1">Open the detailed form to register a new student with full profile information.</p>
                </div>
                <a href="add_student.php" class="bg-indigo-600 px-6 py-3 text-sm font-semibold text-white rounded-md shadow-sm hover:bg-indigo-500 inline-flex items-center">
                    <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Go to Add Student Form
                </a>
            </div>

            <!-- Bulk Import -->
            <div class="bg-white shadow-sm border border-gray-200 rounded-xl p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Bulk Import (CSV)</h2>
                <form action="student_action.php" method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-4 items-end">
                    <input type="hidden" name="action" value="import_students">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-900 mb-1">Upload CSV</label>
                        <input type="file" name="csv_file" accept=".csv" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="text-xs text-gray-500 mt-2">Required Columns: Name, Roll No, Class ID.</p>
                        <p class="text-xs text-gray-500">Optional: DOB (YYYY-MM-DD), Father's Name, Contact, Email, Address</p>
                    </div>
                    <button type="submit" class="bg-white border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 rounded-md shadow-sm hover:bg-gray-50">
                        Import CSV
                    </button>
                </form>
            </div>
        </div>

        <!-- Student List -->
        <div class="bg-white shadow-sm rounded-xl overflow-hidden border border-gray-200">
            <div class="px-6 py-5 border-b border-gray-200 bg-gray-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h2 class="text-lg font-semibold text-gray-900">Students Database</h2>
                
                <!-- Search & Filter Form -->
                <form action="manage_students.php" method="GET" class="flex gap-2 w-full sm:w-auto">
                    <select name="class_id" class="rounded-md border-gray-300 shadow-sm text-sm" onchange="this.form.submit()">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['class_id']; ?>" <?php echo $filter_class == $c['class_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name/roll..." class="rounded-md border-gray-300 shadow-sm text-sm">
                    <button type="submit" class="bg-white border border-gray-300 shadow-sm px-3 py-1 rounded text-sm hover:bg-gray-50 font-medium text-gray-700">Search</button>
                </form>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Roll No</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider w-1/3">Student Name</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Class</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($students)): ?>
                            <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">No students found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($student['roll_no']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($student['name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php 
                                            foreach($classes as $c) {
                                                if ($c['class_id'] == $student['class_id']) echo htmlspecialchars($c['name']);
                                            }
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="edit_student.php?id=<?php echo $student['student_id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-4 font-semibold bg-indigo-50 px-3 py-1.5 rounded-md hover:bg-indigo-100 transition-colors">View / Edit</a>
                                        <a href="student_action.php?action=delete_student&id=<?php echo $student['student_id']; ?>" 
                                           class="text-red-600 hover:text-red-900 font-semibold bg-red-50 px-3 py-1.5 rounded-md hover:bg-red-100 transition-colors"
                                           onclick="return confirm('Are you sure you want to delete this student?');">Delete</a>
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
