<?php
/**
 * Creates a notification for all users with the 'admin' role.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param string $message The notification message.
 * @param string $link The link (relative path) for the notification.
 * @return void
 */
function notify_admins(PDO $pdo, string $message, string $link): void {
    try {
        // 1. Find all admin user IDs
        $stmt_admins = $pdo->query("SELECT user_id FROM users WHERE role = 'admin'");
        $admin_ids = $stmt_admins->fetchAll(PDO::FETCH_COLUMN);

        if (empty($admin_ids)) {
            // No admins found, nothing to do.
            return;
        }

        // 2. Prepare the notification insert statement
        $sql = "INSERT INTO notifications (user_id, message, link, is_read, created_at) 
                VALUES (?, ?, ?, 0, NOW())";
        $stmt_notify = $pdo->prepare($sql);

        // 3. Insert a notification for each admin
        foreach ($admin_ids as $admin_id) {
            // Use execute with array binding inside the loop
            $stmt_notify->execute([$admin_id, $message, $link]);
        }

    } catch (PDOException $e) {
        // Log the error. This function should not break the parent script.
        error_log("Failed to send admin notifications: " . $e->getMessage());
    }
}
?>