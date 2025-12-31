<?php
require_once 'config.php';

// --- 1. Authentication & Authorization ---
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You do not have permission to perform this action.'];
    header('Location: index.php');
    exit;
}

// --- 2. Determine Action ---
// We use a POST variable for add/edit and GET for delete
$action = $_POST['action'] ?? $_GET['action'] ?? null;

try {
    switch ($action) {
        // --- ADD CLASS ---
        case 'add_class':
            $class_name = $_POST['class_name'] ?? null;
            if (empty($class_name)) {
                throw new RuntimeException('Class name cannot be empty.');
            }
            
            $sql = "INSERT INTO classes (name) VALUES (:name)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':name' => $class_name]);
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Class added successfully.'];
            break;

        // --- EDIT CLASS ---
        case 'edit_class':
            $class_id = $_POST['class_id'] ?? null;
            $class_name = $_POST['class_name'] ?? null;
            
            if (empty($class_name) || empty($class_id)) {
                throw new RuntimeException('Invalid data for updating class.');
            }
            
            $sql = "UPDATE classes SET name = :name WHERE class_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':name' => $class_name, ':id' => $class_id]);
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Class updated successfully.'];
            break;

        // --- DELETE CLASS ---
        case 'delete_class':
            $class_id = $_GET['id'] ?? null;
            if (empty($class_id)) {
                throw new RuntimeException('Invalid class ID.');
            }
            
            // Note: We should add checks here to see if this class is used in 'assignments'
            // For simplicity, we'll allow deletion. In production, you'd check dependencies.
            
            $sql = "DELETE FROM classes WHERE class_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $class_id]);
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Class deleted successfully.'];
            break;

        default:
            throw new RuntimeException('Invalid action specified.');
    }

} catch (PDOException $e) {
    // Handle SQL errors (like trying to delete a class that is in use)
    $message = 'Database error: ' . $e->getMessage();
    if ($e->getCode() == '23000') { // Integrity constraint violation
        $message = 'This class cannot be deleted because it is currently being used in an assignment.';
    }
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => $message];
    
} catch (RuntimeException $e) {
    // Handle other errors
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => $e->getMessage()];
}

// Redirect back to the manage classes page
header('Location: manage_classes.php');
exit;
?>
