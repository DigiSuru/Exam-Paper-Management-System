<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$error_message = '';
$flash_message = get_flash_message();

// Login Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? null;
    $password = $_POST['password'] ?? null;

    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT user_id, name, email, role, password_hash FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Use password_verify() to check the hashed password
            if ($user && password_verify($password, $user['password_hash'])) {
                // Password is correct, now check role and time
                
                // --- NEW LOGIN RESTRICTION LOGIC FOR TEACHERS ---
                if ($user['role'] === 'teacher') {
                    // Set the timezone to India Standard Time (IST)
                    $timezone = new DateTimeZone('Asia/Kolkata');
                    
                    // Get the current time in IST
                    $currentTime = new DateTime('now', $timezone);
                    
                    // Define the cutoff time in IST (2025-01-30 22:00 )
                    $cutoffTime = new DateTime('2026-01-02 20:00:00', $timezone);

                    // Check if the current time is after the cutoff time
                    if ($currentTime > $cutoffTime) {
                        // Teacher login is restricted, set custom error message
                        $error_message = 'Login disabled after 20:00 PM, January, 2026. Please contact the administrator.';
                        // Do not create a session or redirect
                    } else {
                        // Teacher login is allowed. Start the session and redirect.
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['name'] = $user['name'];
                        $_SESSION['role'] = $user['role'];
                        header('Location: dashboard_teacher.php');
                        exit;
                    }
                } else if ($user['role'] === 'admin') {
                    // User is an admin, login is always allowed
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['role'] = $user['role'];
                    header('Location: dashboard_admin.php');
                    exit;
                } else {
                    // Other roles (if any) are treated as teachers for this logic
                    // Or you can define specific logic here.
                    // For now, only 'admin' and 'teacher' are handled.
                    // Assuming default teacher logic for any other role.
                    $error_message = 'Your user role is not configured for login.';
                }
                // --- END NEW LOGIN RESTRICTION LOGIC ---

            } else {
                // Invalid credentials
                $error_message = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Exam Paper System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/output.css" rel="stylesheet">
    <script src="//unpkg.com/alpinejs" defer></script>
</head>
<body class="font-sans h-screen flex overflow-hidden bg-white">

<!-- Left: Branding / Visual Panel (Hidden on small screens) -->
<div class="relative hidden lg:flex lg:w-1/2 bg-gradient-to-br from-indigo-600 via-purple-700 to-sky-500 items-center justify-center overflow-hidden">
    <!-- Decorative SVG background elements -->
    <div class="absolute inset-0 opacity-20">
        <svg class="absolute left-0 top-0 h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
            <polygon fill="white" points="0,100 100,0 100,100"/>
        </svg>
    </div>
    <div class="relative z-10 text-center px-12 text-white">
        <h1 class="text-5xl font-extrabold tracking-tight mb-6 drop-shadow-lg">Exam Paper<br>Management</h1>
        <p class="text-lg text-indigo-100 font-medium">A unified platform for teachers and administrators to streamline exam operations.</p>
    </div>
</div>

<!-- Right: Login Form Panel -->
<div class="w-full lg:w-1/2 flex items-center justify-center p-8 sm:p-12 overflow-y-auto">
    <div class="max-w-md w-full space-y-8 animate-fade-in-up">
        <div class="text-center">
            <!-- ADDED: School image -->
            <img class="mx-auto h-20 w-auto mb-6 rounded-full shadow-md" src="uploads/322554729_889228362504294_5639580102782801357_n.jpg" alt="School image" onerror="this.style.display='none'">
            <!-- END ADDED -->
            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">Welcome Back</h2>
            <p class="mt-2 text-sm text-slate-500 font-medium">Sign in to your account</p>
        </div>

        <!-- Flash Message Display -->
        <?php if ($flash_message): ?>
            <div class="rounded-xl <?php echo $flash_message['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'; ?> border p-4 shadow-sm">
                <p class="text-sm font-medium"><?php echo htmlspecialchars($flash_message['message']); ?></p>
            </div>
        <?php endif; ?>

        <!-- Error Message Display -->
        <?php if ($error_message): ?>
            <div class="rounded-xl bg-red-50 border border-red-200 text-red-700 p-4 shadow-sm">
                <p class="text-sm font-medium"><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>

        <form class="mt-8 space-y-6" action="index.php" method="POST">
            <input type="hidden" name="remember" value="true">
            <div class="space-y-4">
                <div>
                    <label for="email-address" class="block text-sm font-semibold text-slate-700 mb-1">Email address</label>
                    <input id="email-address" name="email" type="email" autocomplete="email" required
                           class="w-full"
                           placeholder="Enter your email">
                </div>
                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-700 mb-1">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="w-full"
                           placeholder="Enter your password">
                </div>
            </div>

            <div class="flex items-center justify-between mt-4">
                <div class="flex items-center">
                    <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="remember-me" class="ml-2 block text-sm text-slate-600">Remember me</label>
                </div>
                <div class="text-sm">
                    <a href="#" class="font-medium text-indigo-600 hover:text-indigo-500">Forgot password?</a>
                </div>
            </div>

            <div>
                <button type="submit"
                        class="w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 hover:-translate-y-1 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg shadow-indigo-600/30">
                    Sign in
                </button>
            </div>
        </form>

        <div class="text-sm text-center pt-4">
            <p class="text-slate-500">
                Don't have an account? 
                <a href="register.php" class="font-bold text-indigo-600 hover:text-indigo-500 hover:underline transition-all">Register here</a>
            </p>
        </div>

        <!-- NEW FOOTER SECTION -->
        <footer class="pt-8 mt-8 border-t border-gray-100">
            <p class="text-center text-xs text-slate-400 font-medium tracking-wide">
                Made with &hearts; by
                <a href="https://skmeghwal.in" target="_blank" rel="noopener noreferrer" class="text-indigo-500 hover:text-indigo-400">
                    SK Meghwal
                </a>
            </p>
        </footer>
        <!-- END FOOTER SECTION -->
    </div>
</div>

</body>
</html>