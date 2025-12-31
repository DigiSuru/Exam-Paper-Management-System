<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'You must be logged in to access this page.'
    ];
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$flash_message = get_flash_message();
$user = null;

try {
    // 2. Fetch current user data
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        // This should realistically not happen if they are logged in
        throw new Exception("User not found.");
    }

} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}

include 'header.php';
?>

<!-- Main Content -->
<main class="flex-1 p-6 sm:p-10">
    <h1 class="text-3xl font-semibold text-gray-800 mb-6">My Profile</h1>

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

    <?php if ($user): ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Update Profile Details -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-semibold mb-4">Update Profile Details</h2>
            <form action="profile_action.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_details">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                </div>
                <div>
                    <button type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition duration-150 ease-in-out">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- Change Password -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-semibold mb-4">Change Password</h2>
            <form action="profile_action.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_password">
                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                </div>
                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                    <input type="password" id="new_password" name="new_password" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                </div>
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                </div>
                <div>
                    <button type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-700 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition duration-150 ease-in-out">
                        Change Password
                    </button>
                </div>
            </form>
        </div>

    </div>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>

