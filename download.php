<?php
require_once 'config.php';

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die('You must be logged in to download files.');
}

// 2. Get file ID from URL and validate it
if (!isset($_GET['paper_id']) || !is_numeric($_GET['paper_id'])) {
    die('Invalid file ID.');
}
$paper_id = intval($_GET['paper_id']);
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    // 3. Get file details from database
    $stmt = $pdo->prepare("SELECT * FROM papers WHERE paper_id = ?");
    $stmt->execute([$paper_id]);
    $paper = $stmt->fetch();

    if (!$paper) {
        die('File not found in database.');
    }

    // 4. Check permissions
    // Allow download if:
    // a) The user is an admin
    // b) The user is the teacher who uploaded the file
    if ($role !== 'admin' && $paper['teacher_id'] !== $user_id) {
        die('You do not have permission to access this file.');
    }

    // 5. Check if file exists on server
    $file_path = $paper['file_path']; // e.g., 'uploads/6537a1b2c3d4e_Mid-Term-Math.docx'
    
    if (!file_exists($file_path)) {
        die('File not found on server. It may have been deleted.');
    }

    // 6. Force download
    // Get the original filename (the part after the unique ID and underscore)
    $original_filename = basename($file_path);
    if (strpos($original_filename, '_') !== false) {
        $original_filename = substr($original_filename, strpos($original_filename, '_') + 1);
    }

    // Set headers to force download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream'); // Generic binary file type
    header('Content-Disposition: attachment; filename="' . basename($original_filename) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    
    // Clear output buffer
    flush(); 
    
    // Read the file and send it to the output buffer
    readfile($file_path);
    exit;

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

