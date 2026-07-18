<?php
// Make sure config is included for $pdo (notification logic)
// This is safer than relying on the parent page.
require_once 'config.php';

// We don't call session_start() here because config.php already did.
$current_page = basename($_SERVER['PHP_SELF']);
$user_name = $_SESSION['name'] ?? 'Guest';
$user_role = $_SESSION['role'] ?? null;

// Define navigation links based on role
$admin_links = [
    'dashboard_admin.php' => '📊 Dashboard',
    'manage_classes.php' => '🏫 Classes',
    'manage_subjects.php' => '📚 Subjects',
    'manage_teachers.php' => '👨‍🏫 Teachers',
    'manage_exams.php' => '📝 Exams',
     'manage_papers.php' => '📄 Manage Papers',
    'manage_assignments.php' => '🔗 Assignments',
    'manage_queries.php' => '❓ Teacher Queries',
    'manage_mcq.php' => 'Manage MCQ Answer Key',
    'mcq_progress.php' => '📈 Key Progress',
    'admitcardgenerator.php' => '🪪 Generate Admit Cards',
];

$teacher_links = [
    'dashboard_teacher.php' => '📊 Dashboard',
    'teacher_mcq.php' => '📄 MCQ Dashboard',
    'my_queries.php' => '❓ My Queries',
];

$links = [];
if ($user_role === 'admin') {
    $links = $admin_links;
} elseif ($user_role === 'teacher') {
    $links = $teacher_links;
}

// --- NOTIFICATION LOGIC ---
$notification_count = 0;
$notifications = [];
// Check if user is logged in AND $pdo was successfully created in config.php
if (isset($_SESSION['user_id']) && isset($pdo)) { 
    try {
        $user_id = $_SESSION['user_id'];
        
        // 1. Get unread count
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt_count->execute([$user_id]);
        $notification_count = $stmt_count->fetchColumn();

        // 2. Get recent 5 unread notifications
        $stmt_list = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
        $stmt_list->execute([$user_id]);
        $notifications = $stmt_list->fetchAll();

    } catch (PDOException $e) {
        // Handle error silently so it doesn't break the page
        error_log("Notification fetch error: " . $e->getMessage());
    }
}
// --- END: NOTIFICATION LOGIC ---

?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Paper Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/output.css?v=<?php echo filemtime(__DIR__ . '/assets/css/output.css'); ?>" rel="stylesheet">
    <!-- Load Alpine.js for interactivity -->
    <script src="//unpkg.com/alpinejs" defer></script>
    <style>
        /* Custom styles if needed */
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 selection:bg-indigo-100 selection:text-indigo-900">
<div class="min-h-full">
    <!-- 
      Alpine.js component to manage mobile menu state (open/close)
      This div wraps both the menu and the button that controls it.
    -->
    <div x-data="{ open: false }" @keydown.window.escape="open = false">
        <!-- Off-canvas menu for mobile, show/hide based on mobile menu state. -->
        <div x-show="open" class="fixed inset-0 flex z-40 md:hidden" 
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             role="dialog" aria-modal="true" x-cloak>
            
            <div @click="open = false" class="fixed inset-0 bg-gray-600 bg-opacity-75" aria-hidden="true"></div>

            <!-- Mobile menu content -->
            <div class="relative flex-1 flex flex-col max-w-xs w-full pt-5 pb-4 bg-slate-900">
                <!-- Close button -->
                <div class="absolute top-0 right-0 -mr-12 pt-2">
                    <button @click="open = false" type="button" class="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white">
                        <span class="sr-only">Close sidebar</span>
                        <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div class="flex-shrink-0 flex items-center px-4">
                    <svg class="h-8 w-auto text-indigo-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="text-white text-lg font-semibold ml-2">Exam System</span>
                </div>
                <div class="mt-5 flex-1 h-0 overflow-y-auto">
                    <nav class="space-y-1 mt-2">
                        <?php foreach ($links as $href => $label): ?>
                            <?php $isActive = ($current_page == $href); ?>
                            <a href="<?php echo $href; ?>" 
                               class="<?php echo $isActive ? 'bg-indigo-500/20 text-indigo-400 border-l-4 border-indigo-500 pl-3' : 'text-slate-300 hover:bg-slate-800 hover:text-white pl-4'; ?> transition-all duration-200 group flex items-center pr-2 py-3 text-base font-medium">
                                <?php echo $label; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </div>
            <div class="flex-shrink-0 w-14" aria-hidden="true">
                <!-- Dummy element to force sidebar to shrink to fit close icon -->
            </div>
        </div>

        <!-- Static sidebar for desktop -->
        <div class="hidden md:flex md:w-64 md:flex-col md:fixed md:inset-y-0 shadow-2xl z-20">
            <div class="flex flex-col flex-grow pt-5 bg-slate-900 overflow-y-auto border-r border-slate-800">
                <div class="flex items-center flex-shrink-0 px-4">
                     <svg class="h-8 w-auto text-indigo-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="text-white text-lg font-semibold ml-2">Exam Paper System</span>
                </div>
                <div class="mt-5 flex-1 flex flex-col">
                    <nav class="flex-1 mt-4 space-y-1">
                        <?php foreach ($links as $href => $label): ?>
                            <?php $isActive = ($current_page == $href); ?>
                            <a href="<?php echo $href; ?>" 
                               class="<?php echo $isActive ? 'bg-indigo-500/20 text-indigo-400 border-r-4 border-indigo-500' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?> transition-all duration-200 group flex items-center px-4 py-3 text-sm font-medium">
                                <?php echo $label; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </div>
        </div>
        
        <!-- Main content area -->
        <div class="md:pl-64 flex flex-col flex-1">
            <div class="sticky top-0 z-10 flex-shrink-0 flex h-16 bg-white/80 backdrop-blur-md shadow-sm border-b border-gray-200">
                
                <!-- Mobile menu button (Hamburger) -->
                <button @click.stop="open = !open" type="button" class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500 md:hidden">
                    <span class="sr-only">Open sidebar</span>
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                
                <div class="flex-1 px-4 flex justify-between">
                    <div class="flex-1 flex items-center">
                        <!-- NEW: Motivating Quote -->
                        <span id="motivating-quote" class="text-sm text-gray-600 hidden lg:block italic">Loading quote...</span>
                    </div>
                    <div class="ml-4 flex items-center md:ml-6">
                        
                        <!-- Real-time Clock -->
                        <div class="flex items-center">
                            <span id="realtime-clock" class="text-sm font-medium text-gray-700 hidden md:block">Loading time...</span>
                        </div>

                        <!-- Notification Bell -->
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <div x-data="{ open: false }" class="ml-3 relative">
                            <button @click="open = !open" type="button" class="p-1 rounded-full text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <span class="sr-only">View notifications</span>
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341A6.002 6.002 0 006 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                <?php if ($notification_count > 0): ?>
                                    <span class="absolute top-0 right-0 block h-2 w-2 rounded-full ring-2 ring-white bg-red-500"></span>
                                <?php endif; ?>
                            </button>
                            
                            <div x-show="open" 
                                 @click.outside="open = false" 
                                 x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="transform opacity-100 scale-100"
                                 x-transition:leave-end="transform opacity-0 scale-95"
                                 class="origin-top-right absolute right-0 mt-2 w-80 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" 
                                 role="menu" aria-orientation="vertical" aria-labelledby="notification-menu-button" tabindex="-1">
                                
                                <div class="px-4 py-2 text-sm font-medium text-gray-700">Notifications</div>
                                
                                <?php if (empty($notifications)): ?>
                                    <span class="block px-4 py-2 text-sm text-gray-500">No new notifications.</span>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notif): ?>
                                    <a href="mark_read.php?id=<?php echo $notif['notification_id']; ?>&redirect=<?php echo urlencode($notif['link']); ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem" tabindex="-1">
                                        <?php echo htmlspecialchars($notif['message']); ?>
                                        <span class="block text-xs text-gray-400"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($notif['created_at']))); ?></span>
                                    </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <div class="border-t border-gray-100">
                                    <a href="all_notifications.php" class="block px-4 py-2 text-sm font-medium text-center text-indigo-600 hover:bg-gray-100" role="menuitem" tabindex="-1">
                                        View All Notifications
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <!-- END: Notification Bell -->

                        <!-- Profile dropdown -->
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <div x-data="{ open: false }" class="ml-3 relative">
                            <div>
                                <button @click="open = !open" type="button" class="max-w-xs bg-white flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                                    <span class="sr-only">Open user menu</span>
                                    <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-indigo-500">
                                        <span class="text-sm font-medium leading-none text-white"><?php echo strtoupper(substr($user_name, 0, 1)); ?></span>
                                    </span>
                                    <span class="hidden ml-2 text-sm font-medium text-gray-700 lg:block"><?php echo htmlspecialchars($user_name); ?></span>
                                    <svg class="hidden ml-1 h-5 w-5 text-gray-400 lg:block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>

                            <div x-show="open" 
                                 @click.outside="open = false" 
                                 x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="transform opacity-100 scale-100"
                                 x-transition:leave-end="transform opacity-0 scale-95"
                                 class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" 
                                 role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
                                
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem" tabindex="-1" id="user-menu-item-0">My Profile</a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem" tabindex="-1" id="user-menu-item-1">Sign out</a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

<!-- Main content from other pages will be injected here -->
<!-- We don't close </div> or </body> here, footer.php does that -->

<!-- JavaScript for Clock and Quote -->
<script>
    // --- Clock Function ---
    function updateClock() {
        const clockElement = document.getElementById('realtime-clock');
        if (clockElement) {
            const now = new Date();
            const options = {
                day: 'numeric', 
                month: 'short', 
                year: 'numeric', 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true
            };
            clockElement.textContent = now.toLocaleString('en-US', options);
        }
    }
    
    // Update the clock immediately and then every second
    updateClock();
    setInterval(updateClock, 1000);

    // --- Motivating Quote Function ---
    const quotes = [
        "The future depends on what you do today.",
        "Believe you can and you're halfway there.",
        "Success is not final, failure is not fatal: it is the courage to continue that counts.",
        "It does not matter how slowly you go as long as you do not stop.",
        "The best way to predict the future is to create it.",
        "Education is the passport to the future, for tomorrow belongs to those who prepare for it today.",
        "Your limitation is only your imagination."
    ];

    function updateQuote() {
        const quoteElement = document.getElementById('motivating-quote');
        if (quoteElement) {
            const randomIndex = Math.floor(Math.random() * quotes.length);
            quoteElement.textContent = `"${quotes[randomIndex]}"`;
        }
    }

    // Update the quote immediately and then every 15 seconds
    updateQuote();
    setInterval(updateQuote, 15000); // 15000ms = 15 seconds
</script>