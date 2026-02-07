<?php
// login.php
require 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    // if ($_SESSION['role'] === 'admin') header("Location: admin/dashboard.php");
    // elseif ($_SESSION['role'] === 'instructor') header("Location: instructor/dashboard.php");
    // else header("Location: student/dashboard.php");
    header("Location: {$_SESSION['role']}/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];

        if ($user['role'] === 'admin') header("Location: admin/dashboard.php");
        elseif ($user['role'] === 'instructor') header("Location: instructor/dashboard.php");
        else header("Location: student/dashboard.php");
        exit;
    } else {
        $error = "Invalid Email or Password"; 
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Login - LearnSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Urbanist', sans-serif; }
        /* Smooth fade-in animation */
        .fade-in { animation: fadeIn 0.5s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-white h-screen overflow-hidden flex">

    <div class="hidden lg:flex flex-col justify-between w-[45%] bg-slate-900 text-white p-12 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-96 h-96 bg-blue-500 rounded-full blur-[120px] opacity-20 translate-x-1/2 -translate-y-1/2"></div>
        <div class="absolute bottom-0 left-0 w-96 h-96 bg-purple-500 rounded-full blur-[120px] opacity-20 -translate-x-1/2 translate-y-1/2"></div>

        <div class="relative z-10">
            <div class="flex items-center gap-3 text-2xl font-bold tracking-tight">
                <span class="bg-blue-600 p-2 rounded-lg">🎓</span> LearnSphere
            </div>
        </div>

        <div class="relative z-10 mb-20">
            <h1 class="text-5xl font-bold leading-tight mb-6">Master new skills <br>today.</h1>
            <p class="text-gray-400 text-lg max-w-sm">Join thousands of learners from around the world mastering the latest technologies.</p>
        </div>

        <div class="relative z-10 text-sm text-gray-500">
            © 2026 LearnSphere Inc.
        </div>
    </div>

    <div class="w-full lg:w-[55%] flex items-center justify-center p-8 bg-gray-50">
        <div class="w-full max-w-md fade-in bg-white p-10 rounded-3xl shadow-xl border border-gray-100">
            
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-gray-900">Welcome back</h2>
                <p class="text-gray-500 mt-2">Please enter your details to sign in.</p>
            </div>

            <?php if($error): ?>
                <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-100 flex items-center gap-3 text-red-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                    <span class="text-sm font-medium"><?= $error ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                    <input type="email" name="email" required placeholder="Enter your email" 
                        class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-blue-50 focus:border-blue-500 transition-all outline-none font-medium text-gray-900">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                    <input type="password" name="password" required placeholder="••••••••" 
                        class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-blue-50 focus:border-blue-500 transition-all outline-none font-medium text-gray-900">
                </div>

                <button type="submit" class="w-full py-4 bg-gray-900 hover:bg-black text-white rounded-xl font-bold text-lg shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                    Sign In
                </button>

                <div class="flex items-center justify-between mt-6 pt-6 border-t border-gray-100">
                    <a href="#" class="text-sm font-medium text-gray-500 hover:text-gray-900">Forgot password?</a>
                    <a href="signup.php" class="text-sm font-bold text-blue-600 hover:text-blue-700">Create an account</a>
                </div>
            </form>
        </div>
    </div>

</body>
</html>