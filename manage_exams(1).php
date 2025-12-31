<?php
require_once 'config.php';

// --- 1. Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// --- 2. Initialize Variables ---
$exams = [];
$error_message = null;

// --- 3. Get Flash Message (for form actions) ---
$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Clear it after displaying
}

// --- 4. Fetch All Exams ---
try {
    $stmt = $pdo->query("SELECT * FROM exams ORDER BY created_at DESC");
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If fetching fails, set a persistent error message
    $error_message = "Database Error: " . $e->getMessage();
}

?>

<!-- Include the shared header -->
<?php require_once 'header.php'; ?>

<!-- Page Content -->
<main class="flex-1 p-6 lg:p-10">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Manage Exams</h1>

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
                <!-- Flash message icon and text -->
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

        <!-- 1. Add New Exam Form -->
        <div class="bg-white shadow rounded-lg p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Add New Exam</h2>
            <form action="exam_action.php" method="POST" class="grid grid-cols-1 gap-y-6 sm:grid-cols-3 sm:gap-x-4">
                <input type="hidden" name="action" value="add_exam">
                
                <div class="sm:col-span-2">
                    <label for="exam_name" class="block text-sm font-medium leading-6 text-gray-900">Exam Name</label>
                    <div class="mt-2">
                        <input type="text" name="exam_name" id="exam_name" required
                               class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6"
                               placeholder="e.g., Mid-Term Exam 2024">
                    </div>
                </div>
                
                <div>
                    <label for="exam_status" class="block text-sm font-medium leading-6 text-gray-900">Status</label>
                    <div class="mt-2">
                         <select id="exam_status" name="exam_status" required
                                class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <button type="submit"
                            class="inline-flex justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                        Add Exam
                    </button>
                </div>
            </form>
        </div>

        <!-- 2. List of Existing Exams -->
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Existing Exams</h2>
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created On</th>
                            <th scope="col" class="relative px-6 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($exams) && !$error_message): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                    No exams found. Add one above.
                                </td>
                            </tr>
                        <?php elseif (!empty($exams)): ?>
                            <?php foreach ($exams as $exam): ?>
                                <!-- Each row is a small form for editing -->
                                <form action="exam_action.php" method="POST">
                                    <input type="hidden" name="action" value="edit_exam">
                                    <input type="hidden" name="exam_id" value="<?php echo $exam['exam_id']; ?>">
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input type="text" name="exam_name" value="<?php echo htmlspecialchars($exam['name']); ?>" required
                                                   class="block w-full rounded-md border-0 py-1 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <select name="exam_status" required
                                                    class="block w-full rounded-md border-0 py-1 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
                                                <option value="pending" <?php echo ($exam['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                <option value="active" <?php echo ($exam['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                                <option value="completed" <?php echo ($exam['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                            </select>
                                        </td>
                                         <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M j, Y', strtotime($exam['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                            <button type="submit" class="text-blue-600 hover:text-blue-900">Update</button>
                                            <!-- Delete button is a separate link -->
                                            <a href="exam_action.php?action=delete_exam&id=<?php echo $exam['exam_id']; ?>" 
                                               class="text-red-600 hover:text-red-900" 
                                               onclick="return confirm('Are you sure you want to delete this exam? This action cannot be undone.');">Delete</a>
                                        </td>
                                    </tr>
                                </form>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>
