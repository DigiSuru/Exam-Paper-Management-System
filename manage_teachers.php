<?php
require_once 'config.php';

// --- 1. Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// --- 2. Initialize Variables ---
$teachers = [];
$error_message = null;

// --- 3. Get Flash Message (for form actions) ---
$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Clear it after displaying
}

// --- 4. Fetch All Teachers ---
try {
    $stmt = $pdo->query("SELECT user_id, name, email, created_at FROM users WHERE role = 'teacher' ORDER BY name ASC");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Manage Teachers</h1>

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

        <!-- Note about adding teachers -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a1 1 0 110 2H9a1 1 0 110-2h.253a.25.25 0 01.244-.304l.459-2.066A1.75 1.75 0 008.253 9H8a1 1 0 01-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        New teachers can be added via the 
                        <a href="register.php" class="font-medium underline text-blue-700 hover:text-blue-600">Teacher Registration Page</a>.
                    </p>
                </div>
            </div>
        </div>

        <!-- 2. List of Existing Teachers -->
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Existing Teachers</h2>
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined On</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reset Password</th>
                            <th scope="col" class="relative px-6 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($teachers) && !$error_message): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                    No teachers found.
                                </td>
                            </tr>
                        <?php elseif (!empty($teachers)): ?>
                            <?php foreach ($teachers as $teacher): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($teacher['name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($teacher['email']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M j, Y', strtotime($teacher['created_at'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <!-- Reset Password Form -->
                                        <form action="teacher_action.php" method="POST" class="flex items-center space-x-2">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="teacher_id" value="<?php echo $teacher['user_id']; ?>">
                                            <div>
                                                <label for="new_password_<?php echo $teacher['user_id']; ?>" class="sr-only">New Password</label>
                                                <input type="password" name="new_password" id="new_password_<?php echo $teacher['user_id']; ?>" required
                                                       class="block w-full rounded-md border-0 py-1 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6"
                                                       placeholder="New Password">
                                            </div>
                                            <button type="submit" class="rounded-md bg-yellow-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-yellow-500">
                                                Reset
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <!-- Delete Teacher Link -->
                                        <a href="teacher_action.php?action=delete_teacher&id=<?php echo $teacher['user_id']; ?>" 
                                           class="text-red-600 hover:text-red-900" 
                                           onclick="return confirm('Are you sure you want to delete this teacher? This will fail if they have associated papers or assignments.');">Delete</a>
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
