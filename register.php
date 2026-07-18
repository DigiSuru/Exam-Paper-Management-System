<?php
// We only need session_start() here to display flash messages
session_start();

$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Clear it after displaying
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-white">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Exam Paper System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/output.css" rel="stylesheet">
    <script src="//unpkg.com/alpinejs" defer></script>
    <style>
        .font-sans { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="font-sans h-screen flex overflow-hidden bg-white">

<!-- Left: Branding / Visual Panel (Hidden on small screens) -->
<div class="relative hidden lg:flex lg:w-1/2 bg-gradient-to-br from-teal-500 via-indigo-600 to-purple-700 items-center justify-center overflow-hidden">
    <!-- Decorative SVG background elements -->
    <div class="absolute inset-0 opacity-20">
        <svg class="absolute left-0 top-0 h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
            <polygon fill="white" points="0,0 100,0 0,100"/>
        </svg>
    </div>
    <div class="relative z-10 text-center px-12 text-white">
        <h1 class="text-5xl font-extrabold tracking-tight mb-6 drop-shadow-lg">Join the System</h1>
        <p class="text-lg text-indigo-100 font-medium">Create your teacher account and start managing exams effortlessly.</p>
    </div>
</div>

<!-- Right: Registration Form Panel -->
<div class="w-full lg:w-1/2 flex items-center justify-center p-8 sm:p-12 overflow-y-auto">
    <div class="max-w-md w-full space-y-6 animate-fade-in-up">
        <div class="text-center">
            <!-- Logo -->
            <svg class="mx-auto h-12 w-auto text-indigo-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18c-2.305 0-4.408.867-6 2.292m0-14.25v14.25" />
            </svg>
            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">Create an account</h2>
            <p class="mt-2 text-sm text-slate-500 font-medium">Register as a new teacher</p>
        </div>

        <!-- Display Error/Success Messages -->
        <?php if ($flash_message): ?>
            <div class="rounded-xl <?php echo $flash_message['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'; ?> border p-4 shadow-sm">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <?php if ($flash_message['type'] === 'success'): ?>
                            <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                            </svg>
                        <?php else: ?>
                            <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium">
                            <?php echo htmlspecialchars($flash_message['message']); ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form class="mt-8 space-y-5" action="register_action.php" method="POST">
            <div>
                <label for="name" class="block text-sm font-semibold text-slate-700 mb-1">Full name</label>
                <input id="name" name="name" type="text" autocomplete="name" required class="w-full" placeholder="John Doe">
            </div>

            <div>
                <label for="email" class="block text-sm font-semibold text-slate-700 mb-1">Email address</label>
                <input id="email" name="email" type="email" autocomplete="email" required class="w-full" placeholder="john@example.com">
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-slate-700 mb-1">Password</label>
                <input id="password" name="password" type="password" required class="w-full" placeholder="Create a password">
                <p class="mt-1 text-xs text-slate-400">Must be at least 8 characters long.</p>
            </div>
            
            <div>
                <label for="confirm_password" class="block text-sm font-semibold text-slate-700 mb-1">Confirm Password</label>
                <input id="confirm_password" name="confirm_password" type="password" required class="w-full" placeholder="Confirm your password">
            </div>

            <div class="pt-2">
                <button type="submit"
                        class="w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 hover:-translate-y-1 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg shadow-indigo-600/30">
                    Create account
                </button>
            </div>
        </form>

        <div class="text-sm text-center pt-2">
            <p class="text-slate-500">
                Already have an account? 
                <a href="index.php" class="font-bold text-indigo-600 hover:text-indigo-500 hover:underline transition-all">Sign in here</a>
            </p>
        </div>

        <!-- NEW FOOTER SECTION -->
        <footer class="pt-6 mt-6 border-t border-gray-100">
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
