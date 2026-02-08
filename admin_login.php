<?php
// admin_login.php
require 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Redirect if already logged in as Admin
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header("Location: admin/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // 1. Check User
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // 2. Verify Password AND Admin Role
    if ($user && password_verify($password, $user['password'])) {
        if ($user['role'] === 'admin') {
            // Success: Login as Admin
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            
            header("Location: admin/dashboard.php");
            exit;
        } else {
            // Found user, but NOT an admin
            $error = "Access Denied. You do not have administrative privileges.";
        }
    } else {
        $error = "Invalid Admin Credentials.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Portal - LearnSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #0f172a; color: white; }
        .admin-glow { box-shadow: 0 0 40px -10px rgba(220, 38, 38, 0.3); } /* Red Glow */
    </style>
</head>
<body class="h-screen flex items-center justify-center relative overflow-hidden">

    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-red-600 to-transparent opacity-50"></div>
    <div class="absolute bottom-0 right-0 w-96 h-96 bg-red-900/20 rounded-full blur-[120px]"></div>
    <div class="absolute top-20 left-20 w-72 h-72 bg-blue-900/10 rounded-full blur-[100px]"></div>

    <div class="w-full max-w-md p-8 relative z-10">
        
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-800 border border-slate-700 shadow-2xl mb-6">
                <i data-lucide="shield-alert" class="w-8 h-8 text-red-500"></i>
            </div>
            <h1 class="text-3xl font-bold tracking-tight text-white">Admin Access</h1>
            <p class="text-slate-400 mt-2 text-sm">Restricted Area. Authorized Personnel Only.</p>
        </div>

        <div class="bg-slate-900/50 backdrop-blur-xl border border-slate-800 p-8 rounded-3xl admin-glow">
            
            <?php if($error): ?>
                <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 flex items-start gap-3 text-red-400">
                    <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 mt-0.5"></i>
                    <span class="text-sm font-medium leading-relaxed"><?= $error ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Admin Email</label>
                    <div class="relative">
                        <i data-lucide="mail" class="absolute left-4 top-3.5 w-5 h-5 text-slate-500"></i>
                        <input type="email" name="email" required placeholder="admin@learnsphere.com" 
                            class="w-full pl-12 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-all outline-none text-white placeholder-slate-600">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Security Key (Password)</label>
                    <div class="relative">
                        <i data-lucide="lock" class="absolute left-4 top-3.5 w-5 h-5 text-slate-500"></i>
                        <input type="password" name="password" required placeholder="••••••••" 
                            class="w-full pl-12 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-all outline-none text-white placeholder-slate-600">
                    </div>
                </div>

                <button type="submit" class="w-full py-3.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold text-sm uppercase tracking-wide shadow-lg shadow-red-900/20 transition-all duration-300 mt-4 flex items-center justify-center gap-2">
                    <i data-lucide="log-in" class="w-4 h-4"></i> Authenticate
                </button>
            </form>
        </div>

        <div class="text-center mt-8">
            <a href="index.php" class="text-slate-500 hover:text-white text-sm transition flex items-center justify-center gap-2">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Return to Site
            </a>
        </div>

    </div>

    <script> lucide.createIcons(); </script>
</body>
</html>