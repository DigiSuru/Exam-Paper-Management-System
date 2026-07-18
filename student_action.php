<?php
require_once 'config.php';

// Allow admins and teachers
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

if ($action === 'add_student') {
    $name = $_POST['student_name'] ?? '';
    $roll = $_POST['roll_no'] ?? '';
    $cid = $_POST['class_id'] ?? '';
    
    // New fields
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $father_name = $_POST['father_name'] ?? null;
    $contact = $_POST['contact_number'] ?? null;
    $email = $_POST['email'] ?? null;
    $address = $_POST['address'] ?? null;

    if ($name && $roll && $cid) {
        try {
            $stmt = $pdo->prepare("INSERT INTO students (name, roll_no, class_id, dob, father_name, contact_number, email, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $roll, $cid, $dob, $father_name, $contact, $email, $address]);
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Student added successfully!'];
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to add student. Roll number might already exist.'];
        }
    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Please fill all required fields.'];
    }
    header('Location: manage_students.php');
    exit;
}

if ($action === 'edit_student') {
    $id = $_POST['student_id'] ?? '';
    $name = $_POST['student_name'] ?? '';
    $roll = $_POST['roll_no'] ?? '';
    $cid = $_POST['class_id'] ?? '';
    
    // New fields
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $father_name = $_POST['father_name'] ?? null;
    $contact = $_POST['contact_number'] ?? null;
    $email = $_POST['email'] ?? null;
    $address = $_POST['address'] ?? null;

    if ($id && $name && $roll && $cid) {
        try {
            $stmt = $pdo->prepare("UPDATE students SET name = ?, roll_no = ?, class_id = ?, dob = ?, father_name = ?, contact_number = ?, email = ?, address = ? WHERE student_id = ?");
            $stmt->execute([$name, $roll, $cid, $dob, $father_name, $contact, $email, $address, $id]);
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Student updated successfully!'];
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to update student.'];
        }
    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Please fill all required fields.'];
    }
    header('Location: manage_students.php');
    exit;
}

if ($action === 'delete_student') {
    $student_id = $_GET['id'] ?? '';
    if (!empty($student_id)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
            $stmt->execute([$student_id]);
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Student deleted successfully!'];
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    header('Location: manage_students.php');
    exit;
}

if ($action === 'import_students') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file_tmp, "r");
        
        if ($handle !== FALSE) {
            $success_count = 0;
            $skip_count = 0;
            
            // Skip the first row (headers)
            fgetcsv($handle, 1000, ",");
            
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO students (name, roll_no, class_id, dob, father_name, contact_number, email, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), dob=VALUES(dob), father_name=VALUES(father_name), contact_number=VALUES(contact_number), email=VALUES(email), address=VALUES(address)");
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Ensure row has at least 3 required columns (Name, Roll, ClassID)
                if (count($data) >= 3) {
                    $name = trim($data[0]);
                    $roll = trim($data[1]);
                    $cid = trim($data[2]);
                    
                    // Optional fields (if they exist in the CSV)
                    $dob = isset($data[3]) && trim($data[3]) !== '' ? trim($data[3]) : null;
                    $father = isset($data[4]) ? trim($data[4]) : null;
                    $contact = isset($data[5]) ? trim($data[5]) : null;
                    $email = isset($data[6]) ? trim($data[6]) : null;
                    $address = isset($data[7]) ? trim($data[7]) : null;
                    
                    if (!empty($name) && !empty($roll) && !empty($cid)) {
                        try {
                            $stmt->execute([$name, $roll, $cid, $dob, $father, $contact, $email, $address]);
                            $success_count++;
                        } catch (PDOException $e) {
                            $skip_count++;
                        }
                    } else {
                        $skip_count++;
                    }
                }
            }
            $pdo->commit();
            fclose($handle);
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => "Import complete! $success_count students added/updated. $skip_count rows skipped."];
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to read the CSV file.'];
        }
    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'No file uploaded or upload error occurred.'];
    }
    header('Location: manage_students.php');
    exit;
}

header('Location: manage_students.php');
exit;
