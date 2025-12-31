<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You must be logged in.'];
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$error_message = '';
$flash_message = get_flash_message();
$all_notifications = [];

// 2. Handle "Mark All as Read" action
if (isset($_POST['action']) && $_POST['action'] == 'mark_all_read') {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'All notifications marked as read.'];
        header('Location: all_notifications.php');
        exit;
    } catch (PDOException $e) {
        $error_message = "Failed to mark all as read: " . $e->getMessage();
    }
}

try {
    // 3. Fetch all notifications (read and unread)
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
    $stmt->execute([$user_id]);
    $all_notifications = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

include 'header.php';
?>

<!-- Main Content -->
<main class="flex-1 p-6 sm:p-10">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">All Notifications</h1>
        <form action="all_notifications.php" method="POST">
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                Mark All as Read
            </button>
        </form>
    </div>

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

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <div class="flow-root">
            <ul role="list" class="-mb-8">
                <?php if (empty($all_notifications)): ?>
                    <li class="text-center text-gray-500 py-4">You have no notifications.</li>
                <?php else: ?>
                    <?php foreach ($all_notifications as $i => $notif): ?>
                    <li>
                        <div class="relative pb-8">
                            <?php if ($i != count($all_notifications) - 1): // Add line if not last item ?>
                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                            <?php endif; ?>
                            
                            <div class="relative flex space-x-3">
                                <div>
                                    <span class="h-8 w-8 rounded-full <?php echo $notif['is_read'] ? 'bg-gray-400' : 'bg-indigo-500'; ?> flex items-center justify-center ring-8 ring-white">
                                        <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                    <div>
                                        <p class="text-sm text-gray-700 <?php echo $notif['is_read'] ? 'text-gray-500' : 'font-medium text-gray-900'; ?>">
                                            <?php echo htmlspecialchars($notif['message']); ?>
                                        </p>
                                    </div>
                                    <div class="whitespace-nowrap text-right text-sm text-gray-500">
                                        <time datetime="<?php echo $notif['created_at']; ?>"><?php echo htmlspecialchars(date('M d', strtotime($notif['created_at']))); ?></time>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
