<?php
require_once 'config.php';

// --- 1. Authentication & Authorization ---
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// --- 2. Initialize Variables ---
$classes = [];
$error_message = null;
$search = $_GET['search'] ?? ''; // Get search term from URL

// --- 3. Get Flash Message (for form actions) ---
$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Clear it after displaying
}

// --- 4. Fetch Classes (with Search Logic) ---
try {
    $sql = "SELECT * FROM classes";
    $params = [];

    // Add filter if search term exists
    if (!empty($search)) {
        $sql .= " WHERE name LIKE :search";
        $params['search'] = "%$search%";
    }

    $sql .= " ORDER BY name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

?>

<!-- Include the shared header -->
<?php require_once 'header.php'; ?>

<!-- Page Content -->
<main class="flex-1 bg-gray-50 min-h-screen p-6 lg:p-10">
    <div class="max-w-5xl mx-auto space-y-6">
        
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Manage Classes</h1>
                <p class="mt-1 text-sm text-gray-500">Create and manage the classes/grades for your institution.</p>
            </div>
            <div class="mt-4 sm:mt-0">
                <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-sm font-medium text-indigo-700 ring-1 ring-inset ring-indigo-700/10">
                    Total Classes: <?php echo count($classes); ?>
                </span>
            </div>
        </div>

        <!-- Display Persistent Database Errors -->
        <?php if ($error_message): ?>
            <div class="rounded-md bg-red-50 p-4 border-l-4 border-red-400 shadow-sm">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">System Error</h3>
                        <div class="mt-2 text-sm text-red-700"><p><?php echo htmlspecialchars($error_message); ?></p></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Display Action Flash Messages -->
        <?php if ($flash_message): ?>
            <div class="rounded-md p-4 border-l-4 shadow-sm <?php echo $flash_message['type'] === 'success' ? 'bg-green-50 border-green-400' : 'bg-red-50 border-red-400'; ?>">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <?php if ($flash_message['type'] === 'success'): ?>
                            <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                            </svg>
                        <?php else: ?>
                            <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: Add New Class -->
            <div class="lg:col-span-1">
                <div class="bg-white shadow-md rounded-xl overflow-hidden border border-gray-100 sticky top-6">
                    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50">
                        <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 text-indigo-600">
                              <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                            </svg>
                            Add New Class
                        </h2>
                    </div>
                    <div class="p-6">
                        <form action="class_action.php" method="POST">
                            <input type="hidden" name="action" value="add_class">
                            
                            <div class="mb-4">
                                <label for="class_name" class="block text-sm font-medium leading-6 text-gray-900">Class Name</label>
                                <div class="relative mt-2">
                                    <input type="text" name="class_name" id="class_name" required
                                        class="block w-full rounded-md border-0 py-2.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 pl-3"
                                        placeholder="e.g., Grade 10 (Section A)">
                                </div>
                                <p class="mt-2 text-xs text-gray-500">Enter a unique name for the class or grade.</p>
                            </div>
                            
                            <button type="submit"
                                    class="w-full rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-colors">
                                Add Class
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column: Existing Classes & Search -->
            <div class="lg:col-span-2">
                <div class="bg-white shadow-md rounded-xl overflow-hidden border border-gray-100">
                    <!-- Toolbar: Title & Search -->
                    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h2 class="text-lg font-semibold text-gray-900">Existing Classes</h2>
                        
                        <!-- Search Form -->
                        <form action="manage_classes.php" method="GET" class="relative flex items-center max-w-sm w-full sm:w-auto">
                            <div class="relative w-full">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                       class="block w-full rounded-md border-0 py-1.5 pl-10 pr-3 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                       placeholder="Search classes...">
                            </div>
                            <?php if (!empty($search)): ?>
                                <a href="manage_classes.php" class="ml-2 text-sm text-gray-500 hover:text-red-600 underline">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-full">Class Name</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($classes) && !$error_message): ?>
                                    <tr>
                                        <td colspan="2" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center justify-center">
                                                <svg class="h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                </svg>
                                                <h3 class="mt-2 text-sm font-semibold text-gray-900">
                                                    <?php echo empty($search) ? 'No classes found' : 'No matching classes'; ?>
                                                </h3>
                                                <p class="mt-1 text-sm text-gray-500">
                                                    <?php echo empty($search) ? 'Get started by creating a new class.' : 'Try adjusting your search terms.'; ?>
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php elseif (!empty($classes)): ?>
                                    <?php foreach ($classes as $class): ?>
                                        <tr class="group hover:bg-gray-50 transition-colors">
                                            <!-- Each row is a form for editing -->
                                            <form action="class_action.php" method="POST">
                                                <input type="hidden" name="action" value="edit_class">
                                                <input type="hidden" name="class_id" value="<?php echo $class['class_id']; ?>">
                                                
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <input type="text" name="class_name" value="<?php echo htmlspecialchars($class['name']); ?>" required
                                                           class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-transparent group-hover:ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 bg-transparent group-hover:bg-white transition-all">
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <div class="flex items-center justify-end space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                        <!-- Update Button -->
                                                        <button type="submit" class="text-indigo-600 hover:text-indigo-900 p-1.5 hover:bg-indigo-50 rounded-md transition-colors" title="Save Changes">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                                              <path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.5a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z" />
                                                            </svg>
                                                        </button>
                                                        
                                                        <!-- Delete Button -->
                                                        <a href="class_action.php?action=delete_class&id=<?php echo $class['class_id']; ?>" 
                                                           class="text-red-600 hover:text-red-900 p-1.5 hover:bg-red-50 rounded-md transition-colors" 
                                                           title="Delete Class"
                                                           onclick="return confirm('Are you sure you want to delete this class? This action cannot be undone.');">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                                              <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" />
                                                            </svg>
                                                        </a>
                                                    </div>
                                                </td>
                                            </form>
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