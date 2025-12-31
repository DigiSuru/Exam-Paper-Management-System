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
                // Password is correct
                
                // Login successful for all roles (Teacher restrictions are now handled at upload time)
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header('Location: dashboard_admin.php');
                } else {
                    // Default to teacher dashboard
                    header('Location: dashboard_teacher.php');
                }
                exit;

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
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="//unpkg.com/alpinejs" defer></script>
    <style>
        .font-sans { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="font-sans h-full">
<div class="min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <!-- ADDED: School image -->
            <img class="mx-auto h-24 w-auto" src="uploads/322554729_889228362504294_5639580102782801357_n.jpg" alt="School image" onerror="this.style.display='none'">
            <!-- END ADDED -->

            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Exam Paper Management System
                
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Sign in to your account
            </p>
        </div>

        <!-- Flash Message Display -->
        <?php if ($flash_message): ?>
            <div class="rounded-md <?php echo $flash_message['type'] === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?> border p-4">
                <p><?php echo htmlspecialchars($flash_message['message']); ?></p>
            </div>
        <?php endif; ?>

        <!-- Error Message Display -->
        <?php if ($error_message): ?>
            <div class="rounded-md bg-red-100 border-red-400 text-red-700 border p-4">
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>


        <form class="mt-8 space-y-6" action="index.php" method="POST">
            <input type="hidden" name="remember" value="true">
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="email-address" class="sr-only">Email address</label>
                    <input id="email-address" name="email" type="email" autocomplete="email" required
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-sky-500 focus:border-sky-500 focus:z-10 sm:text-sm"
                           placeholder="Email address">
                </div>
                <div>
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-sky-500 focus:border-sky-500 focus:z-10 sm:text-sm"
                           placeholder="Password">
                </div>
            </div>

            <div>
                <button type="submit"
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                    Sign in
                </button>
            </div>
        </form>

        <div class="text-sm text-center">
            <a href="register.php" class="font-medium text-sky-600 hover:text-sky-500">
                Don't have an account? Register here
            </a>
        </div>

        <!-- NEW FOOTER SECTION -->
        <footer class="pt-6 border-t border-gray-200">
            <p class="text-center text-sm text-gray-500">
                Made with &hearts; by
                <a href="https://instagram.com/digital_suru" target="_blank" rel="noopener noreferrer" class="font-medium text-sky-600 hover:text-sky-500">
                    Suru
                </a>
            </p>
        </footer>
        <!-- END FOOTER SECTION -->

    </div>
</div>
</body>
</html>