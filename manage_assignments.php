<?php
require_once 'config.php';

// --- 1. Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// --- 2. Initialize Variables ---
$assignments = [];
$teachers = [];
$classes = [];
$subjects = [];
$error_message = null;

// --- Filters & Search Inputs ---
$search = $_GET['search'] ?? '';
$filter_teacher = $_GET['teacher'] ?? '';
$filter_class = $_GET['class'] ?? '';
$filter_subject = $_GET['subject'] ?? '';

// --- 3. Get Flash Message ---
$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// --- 4. Fetch Data ---
try {
    // Fetch data for dropdowns
    $stmt_teachers = $pdo->query("SELECT user_id, name FROM users WHERE role = 'teacher' ORDER BY name ASC");
    $teachers = $stmt_teachers->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_classes = $pdo->query("SELECT class_id, name FROM classes ORDER BY name ASC");
    $classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_subjects = $pdo->query("SELECT subject_id, name FROM subjects ORDER BY name ASC");
    $subjects = $stmt_subjects->fetchAll(PDO::FETCH_ASSOC);

    // Build Query for Assignments with Filters
    $sql = "
        SELECT 
            a.assignment_id,
            u.name AS teacher_name,
            c.name AS class_name,
            s.name AS subject_name
        FROM assignments a
        JOIN users u ON a.teacher_id = u.user_id
        JOIN classes c ON a.class_id = c.class_id
        JOIN subjects s ON a.subject_id = s.subject_id
    ";

    $where_clauses = [];
    $params = [];

    // Apply Text Search
    if (!empty($search)) {
        $where_clauses[] = "(u.name LIKE :search OR c.name LIKE :search OR s.name LIKE :search)";
        $params['search'] = "%$search%";
    }

    // Apply Dropdown Filters
    if (!empty($filter_teacher)) {
        $where_clauses[] = "a.teacher_id = :teacher_id";
        $params['teacher_id'] = $filter_teacher;
    }
    if (!empty($filter_class)) {
        $where_clauses[] = "a.class_id = :class_id";
        $params['class_id'] = $filter_class;
    }
    if (!empty($filter_subject)) {
        $where_clauses[] = "a.subject_id = :subject_id";
        $params['subject_id'] = $filter_subject;
    }

    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
    }

    $sql .= " ORDER BY u.name, c.name, s.name";

    $stmt_assignments = $pdo->prepare($sql);
    $stmt_assignments->execute($params);
    $assignments = $stmt_assignments->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

?>

<!-- Include the shared header -->
<?php require_once 'header.php'; ?>

<!-- Page Content -->
<main class="flex-1 bg-gray-50 min-h-screen p-6 lg:p-10">
    <div class="max-w-6xl mx-auto space-y-6">
        
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Manage Assignments</h1>
                <p class="mt-1 text-sm text-gray-500">Assign subjects to teachers for specific classes.</p>
            </div>
            <div class="mt-4 sm:mt-0">
                <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-sm font-medium text-indigo-700 ring-1 ring-inset ring-indigo-700/10">
                    Total Assignments: <?php echo count($assignments); ?>
                </span>
            </div>
        </div>

        <!-- Error & Flash Messages -->
        <?php if ($error_message): ?>
            <div class="rounded-md bg-red-50 p-4 border-l-4 border-red-400 shadow-sm">
                 <div class="flex">
                    <div class="flex-shrink-0">
                        <!-- Error Icon -->
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" /></svg>
                    </div>
                    <div class="ml-3">
                         <h3 class="text-sm font-medium text-red-800">Error Fetching Data</h3>
                        <p class="mt-1 text-sm text-red-700"><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($flash_message): ?>
            <div class="rounded-md p-4 border-l-4 shadow-sm <?php echo $flash_message['type'] === 'success' ? 'bg-green-50 border-green-400' : 'bg-red-50 border-red-400'; ?>">
                 <div class="flex">
                    <div class="flex-shrink-0">
                        <?php if ($flash_message['type'] === 'success'): ?>
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                        <?php else: ?>
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" /></svg>
                        <?php endif; ?>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium <?php echo $flash_message['type'] === 'success' ? 'text-green-800' : 'text-red-800'; ?>">
                            <?php echo htmlspecialchars($flash_message['message']); ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 1. Add New Assignment Form -->
        <div class="bg-white shadow-md rounded-xl overflow-hidden border border-gray-100">
            <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 text-indigo-600">
                      <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                    </svg>
                    Add New Assignment
                </h2>
            </div>
            <div class="p-6">
                <form action="assignment_action.php" method="POST" class="grid grid-cols-1 gap-y-6 sm:grid-cols-4 sm:gap-x-4 items-end">
                    <input type="hidden" name="action" value="add_assignment">
                    
                    <div>
                        <label for="teacher_id" class="block text-sm font-medium leading-6 text-gray-900">Teacher</label>
                        <div class="mt-2">
                             <select id="teacher_id" name="teacher_id" required
                                    class="block w-full rounded-md border-0 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                <option value="">Select a teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['user_id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label for="class_id" class="block text-sm font-medium leading-6 text-gray-900">Class</label>
                        <div class="mt-2">
                             <select id="class_id" name="class_id" required
                                    class="block w-full rounded-md border-0 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                <option value="">Select a class</option>
                                 <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="subject_id" class="block text-sm font-medium leading-6 text-gray-900">Subject</label>
                        <div class="mt-2">
                             <select id="subject_id" name="subject_id" required
                                    class="block w-full rounded-md border-0 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                <option value="">Select a subject</option>
                                 <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['subject_id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <button type="submit"
                                class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-colors">
                            Add Assignment
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 2. List of Existing Assignments -->
        <div class="bg-white shadow-md rounded-xl overflow-hidden border border-gray-100">
            
            <!-- Filter & Search Toolbar -->
            <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Assignments List</h2>
                
                <form action="manage_assignments.php" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    
                    <!-- Text Search -->
                    <div class="relative">
                         <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search..." class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                    </div>

                    <!-- Teacher Filter -->
                    <select name="teacher" class="block w-full rounded-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                        <option value="">All Teachers</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?php echo $t['user_id']; ?>" <?php echo $filter_teacher == $t['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Class Filter -->
                     <select name="class" class="block w-full rounded-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['class_id']; ?>" <?php echo $filter_class == $c['class_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                            Filter
                        </button>
                        <?php if (!empty($search) || !empty($filter_teacher) || !empty($filter_class) || !empty($filter_subject)): ?>
                            <a href="manage_assignments.php" class="flex items-center justify-center rounded-md bg-red-50 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-100 ring-1 ring-inset ring-red-600/10">
                                Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                            <th scope="col" class="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($assignments) && !$error_message): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-semibold text-gray-900">No assignments found</h3>
                                    <p class="mt-1 text-sm text-gray-500">Try adjusting your filters or create a new assignment.</p>
                                </td>
                            </tr>
                        <?php elseif (!empty($assignments)): ?>
                            <?php foreach ($assignments as $assignment): ?>
                                <tr class="group hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($assignment['teacher_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                            <?php echo htmlspecialchars($assignment['class_name']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($assignment['subject_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <!-- Delete button is a link with an icon -->
                                        <a href="assignment_action.php?action=delete_assignment&id=<?php echo $assignment['assignment_id']; ?>" 
                                           class="text-red-600 hover:text-red-900 p-1.5 hover:bg-red-50 rounded-md transition-colors inline-flex items-center" 
                                           title="Delete Assignment"
                                           onclick="return confirm('Are you sure you want to delete this assignment?');">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                              <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
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