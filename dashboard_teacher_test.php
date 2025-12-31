<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// --- 1. Check if user is logged in and is a teacher ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    // Save a flash message and redirect
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'You must be logged in as a teacher to access this page.'
    ];
    header('Location: index.php');
    exit;
}

// --- 2. Initialize Variables ---
$teacher_id = $_SESSION['user_id'];
$error_message = '';
$flash_message = get_flash_message();
$active_exams = [];
$teacher_assignments = [];
$recent_papers = [];

try {
    // --- 3. Fetch Active Exams for Dropdown ---
    $stmt = $pdo->prepare("SELECT exam_id, name FROM exams WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $active_exams = $stmt->fetchAll();

    // --- 4. Fetch Teacher's Assignments for Dropdown ---
    $stmt = $pdo->prepare("
        SELECT 
            a.assignment_id, 
            c.name as class_name, 
            s.name as subject_name 
        FROM assignments a
        JOIN classes c ON a.class_id = c.class_id
        JOIN subjects s ON a.subject_id = s.subject_id
        WHERE a.teacher_id = ?
        ORDER BY c.name, s.name
    ");
    $stmt->execute([$teacher_id]);
    $teacher_assignments = $stmt->fetchAll();

    // --- 5. Fetch Teacher's Recent Submissions ---
    $stmt = $pdo->prepare("
        SELECT 
            p.paper_id, p.file_path, p.submission_type,
            p.status, p.submitted_at, 
            e.name as exam_name, 
            c.name as class_name, 
            s.name as subject_name
        FROM papers p
        JOIN exams e ON p.exam_id = e.exam_id
        JOIN assignments a ON p.assignment_id = a.assignment_id
        JOIN classes c ON a.class_id = c.class_id
        JOIN subjects s ON a.subject_id = s.subject_id
        WHERE p.teacher_id = ?
        ORDER BY p.submitted_at DESC
        LIMIT 5
    ");
    $stmt->execute([$teacher_id]);
    $recent_papers = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = "Database error: Failed to load dashboard data. " . $e->getMessage();
}

// Include header
include 'header.php';
?>

<!-- NEW: Add TinyMCE Rich Text Editor -->
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        tinymce.init({
            selector: '#paper_content_editor',
            plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
            height: 500,
            content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }'
        });
    });
</script>

<!-- Main Content -->
<main class="flex-1 p-6 sm:p-10" x-data="{ submissionType: 'file' }">
    <h1 class="text-3xl font-semibold text-gray-800 mb-6">Teacher Dashboard</h1>

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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Column 1: Submit New Paper (NOW WITH TABS) -->
        <div class="lg:col-span-1 bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-semibold mb-4">Submit New Exam Paper</h2>
            
            <form action="upload_paper_action.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                
                <!-- Step 1 & 2: Exam and Assignment (Common) -->
                <div>
                    <label for="exam_id" class="block text-sm font-medium text-gray-700">1. Select Exam</label>
                    <select id="exam_id" name="exam_id" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                        <option value="">-- Select an active exam --</option>
                        <?php if (empty($active_exams)): ?>
                            <option value="" disabled>No active exams found</option>
                        <?php else: ?>
                            <?php foreach ($active_exams as $exam): ?>
                                <option value="<?php echo $exam['exam_id']; ?>"><?php echo htmlspecialchars($exam['name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div>
                    <label for="assignment_id" class="block text-sm font-medium text-gray-700">2. Select Your Assignment</label>
                    <select id="assignment_id" name="assignment_id" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                        <option value="">-- Select your class & subject --</option>
                        <?php if (empty($teacher_assignments)): ?>
                            <option value="" disabled>No assignments found for you</option>
                        <?php else: ?>
                            <?php foreach ($teacher_assignments as $assignment): ?>
                                <option value="<?php echo $assignment['assignment_id']; ?>">
                                    <?php echo htmlspecialchars($assignment['class_name'] . ' - ' . $assignment['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Step 3: Choose Submission Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">3. Submission Type</label>
                    <fieldset class="mt-2">
                        <legend class="sr-only">Submission type</legend>
                        <div class="flex items-center space-x-4">
                            <label for="type_file" class="flex items-center">
                                <input id="type_file" name="submission_type" type="radio" value="file" x-model="submissionType" class="h-4 w-4 text-sky-600 border-gray-300 focus:ring-sky-500">
                                <span class="ml-2 block text-sm text-gray-900">Upload File</span>
                            </label>
                            <label for="type_text" class="flex items-center">
                                <input id="type_text" name="submission_type" type="radio" value="text" x-model="submissionType" class="h-4 w-4 text-sky-600 border-gray-300 focus:ring-sky-500">
                                <span class="ml-2 block text-sm text-gray-900">Type Paper</span>
                            </label>
                        </div>
                    </fieldset>
                </div>

                <!-- Step 4: Conditional Inputs -->
                
                <!-- Option 1: File Upload -->
                <div x-show="submissionType === 'file'" x-transition>
                    <label for="paper_file" class="block text-sm font-medium text-gray-700">Upload Paper File</label>
                    <input id="paper_file" name="paper_file" type="file"
                           class="mt-1 block w-full text-sm text-gray-500
                                  file:mr-4 file:py-2 file:px-4
                                  file:rounded-md file:border-0
                                  file:text-sm file:font-semibold
                                  file:bg-sky-50 file:text-sky-700
                                  hover:file:bg-sky-100">
                    <p class="mt-1 text-xs text-gray-500">Allowed types: PDF, DOC, DOCX. Max size: 10MB.</p>
                </div>

                <!-- Option 2: Text Editor -->
                <div x-show="submissionType === 'text'" x-transition x-cloak>
                    <label for="paper_content_editor" class="block text-sm font-medium text-gray-700">Type Your Paper</label>
                    
                    <!-- Voice Typing Controls -->
                    <div class="mt-2 mb-2 flex items-center space-x-2" x-data="voiceTyping()">
                        <button @click.prevent="startSpeech" type="button" :class="isListening ? 'bg-red-500 hover:bg-red-600' : 'bg-sky-600 hover:bg-sky-700'"
                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                            <svg class="h-4 w-4 mr-1.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                              <path d="M7 4a3 3 0 016 0v6a3 3 0 11-6 0V4z" />
                              <path d="M5.5 9.5a.5.5 0 01.5-.5h.5a.5.5 0 01.5.5v1a.5.5 0 01-.5.5h-.5a.5.5 0 01-.5-.5v-1zM12.5 9a.5.5 0 00-.5.5v1a.5.5 0 00.5.5h.5a.5.5 0 00.5-.5v-1a.5.5 0 00-.5-.5h-.5z" />
                              <path d="M3 9.5a.5.5 0 01.5-.5h.5a.5.5 0 01.5.5v1a.5.5 0 01-.5.5h-.5a.5.5 0 01-.5-.5v-1zM15.5 9a.5.5 0 00-.5.5v1a.5.5 0 00.5.5h.5a.5.5 0 00.5-.5v-1a.5.5 0 00-.5-.5h-.5z" />
                              <path d="M10 13a3 3 0 01-3 3H7a3 3 0 01-3-3v-1.5a.5.5 0 01.5-.5h.5a.5.5 0 01.5.5V10a2 2 0 104 0v-.5a.5.5 0 01.5-.5h.5a.5.5 0 01.5.5V10a3 3 0 01-3 3zm-1 3.5a.5.5 0 01.5-.5h1a.5.5 0 01.5.5v1a.5.5 0 01-.5.5h-1a.5.5 0 01-.5-.5v-1z" />
                            </svg>
                            <span x-text="isListening ? 'Stop Listening...' : 'Start Voice Typing'"></span>
                        </button>
                        <select x-model="lang" class="block w-auto px-3 py-1.5 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm">
                            <option value="en-US">English (US)</option>
                            <option value="hi-IN">Hindi (India)</option>
                        </select>
                    </div>
                    
                    <textarea id="paper_content_editor" name="paper_content"></textarea>
                    <p class="mt-1 text-xs text-gray-500">Supports copy/paste and voice typing. </p>
                </div>

                <!-- Submit Button -->
                <div>
                    <button type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition duration-150 ease-in-out">
                        Submit Paper
                    </button>
                </div>
            </form>
        </div>

        <!-- Column 2: My Recent Submissions -->
        <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-semibold mb-4">My Recent Submissions</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">View / Download</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($recent_papers)): ?>
                            <tr>
                                <td colspan="3" class="px-4 py-4 text-sm text-gray-500 text-center">You have not submitted any papers yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_papers as $paper): ?>
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($paper['exam_name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($paper['class_name'] . ' - ' . $paper['subject_name']); ?></div>
                                        <div class="text-xs text-gray-400">Uploaded: <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($paper['submitted_at']))); ?></div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <?php
                                            $status_color = 'bg-gray-100 text-gray-800'; // Default
                                            if ($paper['status'] === 'approved') {
                                                $status_color = 'bg-green-100 text-green-800';
                                            } elseif ($paper['status'] === 'rejected') {
                                                $status_color = 'bg-red-100 text-red-800';
                                            } elseif ($paper['status'] === 'pending_review') {
                                                $status_color = 'bg-yellow-100 text-yellow-800';
                                            }
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_color; ?>">
                                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $paper['status']))); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php if ($paper['submission_type'] === 'text'): ?>
                                            <a href="view_paper_content.php?id=<?php echo $paper['paper_id']; ?>" target="_blank"
                                               class="text-sky-600 hover:text-sky-900">
                                                View Paper
                                            </a>
                                        <?php else: ?>
                                            <a href="download.php?paper_id=<?php echo $paper['paper_id']; ?>"
                                               class="text-sky-600 hover:text-sky-900">
                                                Download File
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                 <a href="my_uploads.php" class="inline-block mt-4 text-sm font-medium text-sky-600 hover:text-sky-800">
                    View All My Submissions &rarr;
                </a>
            </div>
        </div>
    </div>
</main>

<!-- Alpine.js script for voice typing -->
<script>
    function voiceTyping() {
        return {
            isListening: false,
            lang: 'en-US',
            recognition: null,
            
            init() {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SpeechRecognition) {
                    console.error("Speech Recognition not supported in this browser.");
                    alert("Speech Recognition is not supported in this browser. Please use Google Chrome or Edge.");
                    return;
                }
                
                this.recognition = new SpeechRecognition();
                this.recognition.interimResults = true;
                this.recognition.continuous = true;

                this.recognition.onstart = () => {
                    this.isListening = true;
                };
                
                this.recognition.onend = () => {
                    this.isListening = false;
                };
                
                this.recognition.onerror = (event) => {
                    console.error("Speech recognition error", event.error);
                    this.isListening = false;
                };
                
                this.recognition.onresult = (event) => {
                    let final_transcript = '';
                    let interim_transcript = '';

                    for (let i = event.resultIndex; i < event.results.length; ++i) {
                        if (event.results[i].isFinal) {
                            final_transcript += event.results[i][0].transcript;
                        } else {
                            interim_transcript += event.results[i][0].transcript;
                        }
                    }

                    // Insert the final transcript into TinyMCE
                    if (final_transcript) {
                        tinymce.activeEditor.execCommand('mceInsertContent', false, final_transcript + ' ');
                    }
                };
            },
            
            startSpeech() {
                if (this.isListening) {
                    this.recognition.stop();
                } else {
                    if (!this.recognition) {
                        this.init();
                        if (!this.recognition) return; // Stop if init failed
                    }
                    this.recognition.lang = this.lang;
                    this.recognition.start();
                }
            }
        }
    }
</script>

<?php include 'footer.php'; ?>