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

// --- 3. Get Flash Message (for form actions) ---
$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Clear it after displaying
}

// --- 4. Fetch Data for Forms and List ---
try {
    // Fetch data for dropdowns
    $stmt_teachers = $pdo->query("SELECT user_id, name FROM users WHERE role = 'teacher' ORDER BY name ASC");
    $teachers = $stmt_teachers->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_classes = $pdo->query("SELECT class_id, name FROM classes ORDER BY name ASC");
    $classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_subjects = $pdo->query("SELECT subject_id, name FROM subjects ORDER BY name ASC");
    $subjects = $stmt_subjects->fetchAll(PDO::FETCH_ASSOC);

    // Fetch existing assignments with details
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
        ORDER BY u.name, c.name, s.name
    ";
    $stmt_assignments = $pdo->query($sql);
    $assignments = $stmt_assignments->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // If fetching fails, set a persistent error message
    $error_message = "Database Error: " . $e->getMessage();
}

?>

<!-- Include the shared header -->
<?php require_once 'header.php'; ?>

<!-- Page Content -->
<main class="flex-1 p-6 lg:p-10">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Manage Assignments</h1>

        <!-- Display Persistent Database Errors -->
        <?php if ($error_message): ?>
            <div class="my-4 rounded-md bg-red-50 p-4">
                 <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                         <h3 class="text-sm font-medium text-red-800">Error Fetching Data</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p><?php echo htmlspecialchars($error_message); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Display Action Flash Messages (Success/Error) -->
        <?php if ($flash_message): ?>
            <div class="my-4 rounded-md <?php echo $flash_message['type'] === 'success' ? 'bg-green-50' : 'bg-red-50'; ?> p-4">
                 <div class="flex">
                    <div class="flex-shrink-0">
                        <?php if ($flash_message['type'] === 'success'): ?>
                            <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                            </svg>
                        <?php else: ?>
                            <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                            </svg>
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
        <div class="bg-white shadow rounded-lg p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Add New Assignment</h2>
            <form action="assignment_action.php" method="POST" class="grid grid-cols-1 gap-y-6 sm:grid-cols-4 sm:gap-x-4">
                <input type="hidden" name="action" value="add_assignment">
                
                <div>
                    <label for="teacher_id" class="block text-sm font-medium leading-6 text-gray-900">Teacher</label>
                    <div class="mt-2">
                         <select id="teacher_id" name="teacher_id" required
                                class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
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
                                class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
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
                                class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
                            <option value="">Select a subject</option>
                             <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['subject_id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="sm:pt-7">
                    <button type="submit"
                            class="inline-flex w-full justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 sm:w-auto">
                        Add Assignment
                    </button>
                </div>
            </form>
        </div>

        <!-- 2. List of Existing Assignments -->
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Existing Assignments</h2>
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                            <th scope="col" class="relative px-6 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($assignments) && !$error_message): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                    No assignments found. Add one above.
                                </td>
                            </tr>
                        <?php elseif (!empty($assignments)): ?>
                            <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($assignment['teacher_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($assignment['class_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($assignment['subject_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <!-- Delete button is a link -->
                                        <a href="assignment_action.php?action=delete_assignment&id=<?php echo $assignment['assignment_id']; ?>" 
                                           class="text-red-600 hover:text-red-900" 
                                           onclick="return confirm('Are you sure you want to delete this assignment?');">Delete</a>
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
