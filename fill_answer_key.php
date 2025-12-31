<?php
// --- DEBUGGING ON ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once 'config.php';

if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$is_admin = ($_SESSION['role'] === 'admin');
$teacher_id = $_SESSION['user_id'];

// We expect 'id' to be the ASSIGNMENT_ID
$assignment_id = $_GET['id'] ?? null;

if (!$assignment_id) die("<h3>Error:</h3> Invalid Request. Missing Assignment ID in URL.");

try {
    // 1. Fetch Exam Details via Assignment ID
    $stmt = $pdo->prepare("
        SELECT m.*, a.class_id, c.name as class_name
        FROM mcq_exam_assignments a
        JOIN mcq_exams m ON a.mcq_exam_id = m.mcq_exam_id
        JOIN classes c ON a.class_id = c.class_id
        WHERE a.assignment_id = ?
    ");
    $stmt->execute([$assignment_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        die("<h3>Error:</h3> Exam Assignment not found. Check if the 'mcq_exam_assignments' table has data for ID: $assignment_id");
    }

    // 2. Fetch Defined Ranges (Linked to Assignment ID)
    $stmt_ranges = $pdo->prepare("
        SELECT r.*, s.name as subject_name 
        FROM mcq_subject_ranges r 
        JOIN subjects s ON r.subject_id = s.subject_id 
        WHERE r.assignment_id = ?
        ORDER BY r.start_q ASC
    ");
    $stmt_ranges->execute([$assignment_id]);
    $ranges = $stmt_ranges->fetchAll(PDO::FETCH_ASSOC);

    // 3. Get Filtered Subject
    $selected_range_id = $_GET['range_id'] ?? ($ranges[0]['range_id'] ?? null);
    $current_range = null;

    foreach ($ranges as $r) {
        if ($r['range_id'] == $selected_range_id) {
            $current_range = $r;
            break;
        }
    }

    // --- Check Authorization ---
    $is_authorized_for_subject = false;

    if ($is_admin) {
        $is_authorized_for_subject = true; 
    } elseif ($current_range) {
        // Check assignments table
        $stmt_auth = $pdo->prepare("
            SELECT COUNT(*) FROM assignments 
            WHERE class_id = ? AND subject_id = ? AND teacher_id = ?
        ");
        $stmt_auth->execute([$exam['class_id'], $current_range['subject_id'], $teacher_id]);
        if ($stmt_auth->fetchColumn() > 0) {
            $is_authorized_for_subject = true;
        }
    }

    // 4. Fetch Existing Answers
    $existing_answers = [];
    if ($current_range) {
        $stmt_ans = $pdo->prepare("SELECT question_number, correct_option, is_locked FROM mcq_answer_keys WHERE assignment_id = ? AND question_number BETWEEN ? AND ?");
        $stmt_ans->execute([$assignment_id, $current_range['start_q'], $current_range['end_q']]);
        while ($row = $stmt_ans->fetch(PDO::FETCH_ASSOC)) {
            $existing_answers[$row['question_number']] = $row;
        }
    }

} catch (PDOException $e) {
    // Catch Database Errors
    die("<div style='background:#fdd; padding:20px; border:1px solid red;'>
            <h3>Database Error:</h3>
            <p>" . htmlspecialchars($e->getMessage()) . "</p>
            <p><strong>Hint:</strong> Did you run the updated 'schema_mcq_v2.sql'?</p>
         </div>");
} catch (Exception $e) {
    // Catch General Errors
    die("<h3>System Error:</h3> " . htmlspecialchars($e->getMessage()));
}

require_once 'header.php';
?>

<!-- Responsive Container -->
<div class="flex flex-col lg:flex-row h-[calc(100vh-64px)] overflow-hidden">
    
    <!-- Left: PDF Viewer (Desktop) -->
    <div class="hidden lg:block lg:w-1/2 lg:h-full border-r border-gray-200 bg-gray-800 relative">
        <?php if(!empty($exam['file_path'])): ?>
            <iframe src="<?php echo htmlspecialchars($exam['file_path']); ?>" class="w-full h-full" frameborder="0"></iframe>
        <?php else: ?>
            <div class="flex items-center justify-center h-full text-white">No PDF file uploaded.</div>
        <?php endif; ?>
    </div>

    <!-- Right: OMR Interface -->
    <div class="w-full lg:w-1/2 h-full overflow-y-auto bg-white p-4 lg:p-6">
        <div class="mb-4 lg:mb-6 border-b pb-4">
            <h2 class="text-xl lg:text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($exam['exam_name']); ?></h2>
            <span class="inline-flex items-center rounded-full bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 mt-1">
                <?php echo htmlspecialchars($exam['class_name']); ?>
            </span>

            <!-- Mobile PDF Alert -->
            <div class="lg:hidden mt-2 p-2 bg-indigo-50 border-l-4 border-indigo-400 text-indigo-700 text-xs">
                <p><strong>Note:</strong> The PDF viewer is hidden on mobile. Please use a separate device or download the paper.</p>
            </div>
            
            <!-- Authorization Alert -->
            <?php if (!$is_admin && !$is_authorized_for_subject && $current_range): ?>
                <div class="mt-2 p-3 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700 text-sm">
                    <p class="font-bold">View Only Mode</p>
                    <p>You are not assigned to teach <strong><?php echo htmlspecialchars($current_range['subject_name']); ?></strong>. You cannot submit answers.</p>
                </div>
            <?php endif; ?>
            
            <!-- Subject Selector -->
            <form action="" method="GET" class="mt-4">
                <input type="hidden" name="id" value="<?php echo $assignment_id; ?>">
                <?php if($is_admin): ?><input type="hidden" name="admin_mode" value="1"><?php endif; ?>
                <label class="block text-sm font-medium text-gray-700">Select Subject Section:</label>
                <select name="range_id" onchange="this.form.submit()" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                    <?php foreach ($ranges as $r): ?>
                        <option value="<?php echo $r['range_id']; ?>" <?php echo $r['range_id'] == $selected_range_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['subject_name']); ?> (Q<?php echo $r['start_q']; ?> - Q<?php echo $r['end_q']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($current_range): ?>
            <form action="save_key_action.php" method="POST">
                <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
                <input type="hidden" name="subject_id" value="<?php echo $current_range['subject_id']; ?>">
                
                <div class="grid grid-cols-1 gap-3 lg:gap-4">
                    <?php 
                    $locked_count = 0;
                    for ($q = $current_range['start_q']; $q <= $current_range['end_q']; $q++): 
                        $saved = $existing_answers[$q] ?? null;
                        
                        $is_db_locked = ($saved && $saved['is_locked'] && !$is_admin);
                        $is_auth_locked = (!$is_authorized_for_subject);
                        $disabled = $is_db_locked || $is_auth_locked;

                        if($is_db_locked) $locked_count++;
                    ?>
                        <div class="flex items-center p-2 lg:p-3 bg-gray-50 rounded-lg border <?php echo $disabled ? 'border-gray-200 opacity-60' : 'border-indigo-100'; ?>">
                            <span class="w-8 h-8 lg:w-10 lg:h-10 flex items-center justify-center bg-indigo-600 text-white font-bold rounded-full mr-3 lg:mr-4 text-sm lg:text-base">
                                <?php echo $q; ?>
                            </span>
                            <div class="flex-1 flex justify-between lg:justify-around">
                                <?php foreach (['A', 'B', 'C', 'D'] as $opt): ?>
                                    <label class="flex flex-col items-center cursor-pointer mx-1">
                                        <input type="radio" name="answers[<?php echo $q; ?>]" value="<?php echo $opt; ?>" 
                                            <?php echo ($saved && $saved['correct_option'] == $opt) ? 'checked' : ''; ?>
                                            <?php echo $disabled ? 'disabled' : ''; ?>
                                            class="h-4 w-4 lg:h-5 lg:w-5 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                        <span class="text-xs mt-1 font-medium text-gray-600"><?php echo $opt; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php if($is_db_locked): ?>
                                <span class="ml-2 text-[10px] lg:text-xs text-red-500 font-semibold px-2 py-1 bg-red-50 rounded">Locked</span>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="sticky bottom-0 bg-white pt-4 pb-6 border-t mt-6 z-10">
                    <?php if ($is_authorized_for_subject): ?>
                        <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                            <?php echo $is_admin ? 'Update Key' : 'Save Answer Key'; ?>
                        </button>
                        <?php if(!$is_admin && $locked_count > 0): ?>
                            <p class="text-xs text-red-500 text-center mt-2">Some answers are already submitted and locked.</p>
                        <?php endif; ?>
                    <?php else: ?>
                         <button type="button" disabled class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-400 cursor-not-allowed">
                            Not Authorized
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center h-64 text-gray-500">
                <svg class="w-12 h-12 mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p>No question ranges defined for this exam yet.</p>
                <p class="text-sm mt-1">Please ask Admin to define which subjects cover which questions.</p>
            </div>
        <?php endif; ?>
    </div>
</div>