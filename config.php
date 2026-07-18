<?php
// Start the session at the very beginning
session_start();

// --- DATABASE CONFIGURATION ---
define('DB_HOST', '127.0.0.1'); // Bypass IPv6 lookup delay on Windows
define('DB_NAME', 'epms_db');
define('DB_USER', 'root');
define('DB_PASS', 'root'); // Your MySQL password

// --- 2. PDO Database Connection ---
try {
    // Create PDO connection string
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    
    // PDO options
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepared statements
    ];

    // Create PDO instance
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    // Stop the script and show a friendly error
    // In a production environment, you'd log this error instead of showing it
    die("Error: Could not connect to the database. " . $e->getMessage());
}

// --- 3. Start Session ---
// This must be after any potential output, but here is fine.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// --- 4. *** NEW: Flash Message Function *** ---
/**
 * Gets and clears a flash message from the session.
 *
 * @return array|null The flash message [type, message] or null if none exists.
 */
function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']); // Clear it after getting
        return $message;
    }
    return null;
}

?>

