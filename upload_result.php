<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$exam_id = $_GET['exam_id'] ?? null;

if (!$exam_id) die("Invalid Request");

// Fetch Exam Info
$stmt = $pdo->prepare("SELECT * FROM mcq_exams WHERE mcq_exam_id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) die("Exam not found");

require_once 'header.php';
?>

<div class="flex flex-col lg:flex-row h-[calc(100vh-64px)] bg-gray-50">
    
    <!-- LEFT: Student Details & File Upload -->
    <div class="w-full lg:w-1/3 bg-white border-r border-gray-200 overflow-y-auto p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-1">Upload Student OMR</h2>
        <p class="text-sm text-gray-500 mb-6"><?php echo htmlspecialchars($exam['exam_name']); ?></p>

        <form action="grade_result_action.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="mcq_exam_id" value="<?php echo $exam_id; ?>">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Student Name</label>
                    <input type="text" name="student_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Roll Number</label>
                    <input type="text" name="roll_no" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Upload OMR Scan (Image/PDF)</label>
                    <input type="file" name="omr_file" accept="image/*,.pdf" id="omrInput" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                </div>
                
                <!-- Image Preview Area -->
                <div id="previewContainer" class="hidden mt-4 border-2 border-dashed border-gray-300 rounded-lg p-2">
                    <img id="omrPreview" class="w-full h-auto rounded" />
                </div>
            </div>

            <hr class="my-6">

            <h3 class="text-lg font-medium text-gray-900 mb-3">Transcribe Answers</h3>
            <p class="text-xs text-gray-500 mb-4">Select the option marked by the student. Leave blank if unattempted.</p>

            <div class="grid grid-cols-5 gap-2">
                <?php for ($i = 1; $i <= $exam['total_questions']; $i++): ?>
                    <div class="flex flex-col items-center p-2 border rounded hover:bg-gray-50">
                        <span class="text-xs font-bold text-gray-500 mb-1">Q<?php echo $i; ?></span>
                        <select name="responses[<?php echo $i; ?>]" class="block w-full p-1 text-sm border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">-</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="mt-6 sticky bottom-0 bg-white pt-4 pb-2 border-t">
                <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Grade & Save Result
                </button>
            </div>
        </form>
    </div>

    <!-- RIGHT: Results / Instructions (Placeholder for Visual Balance) -->
    <div class="hidden lg:block lg:w-2/3 bg-gray-100 flex items-center justify-center">
        <div class="text-center p-10">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">OMR Sheet Preview</h3>
            <p class="mt-1 text-sm text-gray-500">Upload an image to see it here while you fill the answers.</p>
        </div>
        <!-- The image preview will actually be moved/cloned here via JS for better UX if needed, 
             but for simplicity, the preview is kept in the form column or handled by JS below -->
    </div>

</div>

<script>
    // Simple Image Preview Script
    const omrInput = document.getElementById('omrInput');
    const previewContainer = document.getElementById('previewContainer');
    const omrPreview = document.getElementById('omrPreview');
    // For Desktop, we might want to show the preview in the right pane
    const rightPane = document.querySelector('.lg\\:w-2\\/3'); 

    omrInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // Mobile / Default Preview
                omrPreview.src = e.target.result;
                previewContainer.classList.remove('hidden');
                
                // Desktop: Show large preview in right pane
                if(window.innerWidth >= 1024) {
                    rightPane.innerHTML = `<div class="w-full h-full p-4 overflow-auto"><img src="${e.target.result}" class="max-w-full h-auto shadow-lg rounded" /></div>`;
                }
            }
            reader.readAsDataURL(file);
        }
    });
</script>