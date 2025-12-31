<?php
require_once 'config.php';

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die('You must be logged in to download files.');
}
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// 2. Get file ID from URL and validate it
if (!isset($_GET['paper_id']) || !is_numeric($_GET['paper_id'])) {
    die('Invalid file ID.');
}
$paper_id = intval($_GET['paper_id']);

try {
    // 3. Get file details from new `admin_papers` table
    $stmt = $pdo->prepare("SELECT * FROM admin_papers WHERE paper_id = ?");
    $stmt->execute([$paper_id]);
    $paper = $stmt->fetch();

    if (!$paper) {
        die('File not found in database.');
    }

    // 4. Check permissions
    if ($role !== 'admin') {
        // If not admin, must be a teacher. Check if teacher is assigned to this paper.
        $stmt = $pdo->prepare("
            SELECT a.assignment_id 
            FROM assignments a
            WHERE a.teacher_id = ? AND a.class_id = ? AND a.subject_id = ?
        ");
        $stmt->execute([$user_id, $paper['class_id'], $paper['subject_id']]);
        if (!$stmt->fetch()) {
            die('You do not have permission to access this file.');
        }
    }
    
    // 5. Check if file exists on server
    $file_path = $paper['file_path']; // e.g., 'uploads/admin_papers/6537a1b2c3d4e_Physics-10.pdf'
    
    if (!file_exists($file_path)) {
        die('File not found on server. It may have been deleted.');
    }

    // 6. Force download
    $original_filename = $paper['stored_filename'];
    if (strpos($original_filename, '_') !== false) {
        $original_filename = substr($original_filename, strpos($original_filename, '_') + 1);
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($original_filename) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    flush(); 
    readfile($file_path);
    exit;

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
