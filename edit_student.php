<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: manage_students.php');
    exit;
}

$student_id = $_GET['id'];
$student = null;
$classes = [];

try {
    $classes = $pdo->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll();
    
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Student not found.'];
        header('Location: manage_students.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error.'];
    header('Location: manage_students.php');
    exit;
}

?>
<?php require_once 'header.php'; ?>

<main class="flex-1 bg-gray-50 min-h-screen p-6 lg:p-10">
    <div class="max-w-3xl mx-auto space-y-6">
        
        <div class="flex items-center gap-4">
            <a href="manage_students.php" class="text-indigo-600 hover:text-indigo-900 bg-white p-2 rounded-full shadow-sm">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Edit Student</h1>
        </div>

        <div class="bg-white shadow-sm rounded-xl overflow-hidden border border-gray-200 p-8">
            <form action="student_action.php" method="POST" class="space-y-6">
                <input type="hidden" name="action" value="edit_student">
                <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student['student_id']); ?>">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-900">Full Name *</label>
                        <input type="text" name="student_name" value="<?php echo htmlspecialchars($student['name'] ?? ''); ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900">Roll No *</label>
                        <input type="text" name="roll_no" value="<?php echo htmlspecialchars($student['roll_no'] ?? ''); ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-900">Class *</label>
                        <select name="class_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Select a Class</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['class_id']; ?>" <?php echo $student['class_id'] == $c['class_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900">Date of Birth</label>
                        <input type="date" name="dob" value="<?php echo htmlspecialchars($student['dob'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-900">Father's Name</label>
                        <input type="text" name="father_name" value="<?php echo htmlspecialchars($student['father_name'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900">Contact Number</label>
                        <input type="text" name="contact_number" value="<?php echo htmlspecialchars($student['contact_number'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-900">Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-900">Home Address</label>
                        <textarea name="address" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-100 flex justify-end">
                    <a href="manage_students.php" class="mr-4 bg-white border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 rounded-md shadow-sm hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="bg-indigo-600 px-6 py-2 text-sm font-semibold text-white rounded-md shadow-sm hover:bg-indigo-500">
                        Update Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>
