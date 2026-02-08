<?php
// payment.php
require 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$course_id = $_GET['course_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$course_id) { header("Location: index.php"); exit; }

// 1. Fetch Course Details (Real Price)
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) die("Course not found.");

// 2. Handle "Process Payment" Click
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {
    // HERE is where you would call Stripe/PayPal API in a real app.
    // For the Hackathon, we simulate a successful transaction.
    
    // Check if already enrolled to prevent duplicates
    $check = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
    $check->execute([$user_id, $course_id]);
    
    if (!$check->fetch()) {
        // Enroll User
        $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, enrolled_at, status, progress) VALUES (?, ?, NOW(), 'yet_to_start', 0)");
        $stmt->execute([$user_id, $course_id]);
    }
    
    // Redirect to Player with Success Message
    header("Location: student/course_player.php?course_id=" . $course_id . "&payment_success=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secure Checkout - LearnSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6">

    <div class="bg-white max-w-4xl w-full rounded-3xl shadow-xl overflow-hidden flex flex-col md:flex-row border border-slate-200">
        
        <div class="w-full md:w-1/2 p-10 bg-slate-900 text-white flex flex-col justify-between relative overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-500 rounded-full blur-[100px] opacity-20 -mr-16 -mt-16"></div>
            
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-10">
                    <div class="w-8 h-8 bg-white text-slate-900 rounded-lg flex items-center justify-center font-bold">LS</div>
                    <span class="font-bold text-xl">LearnSphere Checkout</span>
                </div>
                
                <h2 class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-2">Order Summary</h2>
                <h1 class="text-3xl font-bold mb-4"><?= htmlspecialchars($course['title']) ?></h1>
                <p class="text-slate-400 text-sm mb-8 line-clamp-3"><?= htmlspecialchars($course['description']) ?></p>
                
                <div class="flex items-center gap-4 text-sm font-medium text-indigo-300">
                    <span class="flex items-center gap-2"><i data-lucide="shield-check" class="w-4 h-4"></i> Secure Transaction</span>
                    <span class="flex items-center gap-2"><i data-lucide="infinity" class="w-4 h-4"></i> Lifetime Access</span>
                </div>
            </div>

            <div class="relative z-10 mt-10 border-t border-white/10 pt-6">
                <div class="flex justify-between items-end">
                    <span class="text-sm text-slate-400">Total amount</span>
                    <span class="text-4xl font-bold">$<?= number_format($course['price'], 2) ?></span>
                </div>
            </div>
        </div>

        <div class="w-full md:w-1/2 p-10 bg-white">
            <h3 class="text-xl font-bold text-slate-900 mb-6">Payment Details</h3>
            
            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Cardholder Name</label>
                    <input type="text" placeholder="John Doe" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 outline-none focus:border-indigo-500 transition">
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Card Number</label>
                    <div class="relative">
                        <i data-lucide="credit-card" class="absolute left-4 top-3.5 w-5 h-5 text-slate-400"></i>
                        <input type="text" placeholder="0000 0000 0000 0000" class="w-full pl-12 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 outline-none focus:border-indigo-500 transition">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Expiry Date</label>
                        <input type="text" placeholder="MM / YY" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 outline-none focus:border-indigo-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">CVC</label>
                        <input type="text" placeholder="123" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 outline-none focus:border-indigo-500 transition">
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" name="pay_now" class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-lg shadow-lg shadow-indigo-200 transition-all flex items-center justify-center gap-2">
                        Pay $<?= number_format($course['price'], 2) ?> <i data-lucide="arrow-right" class="w-5 h-5"></i>
                    </button>
                    <p class="text-center text-xs text-slate-400 mt-4 flex items-center justify-center gap-1">
                        <i data-lucide="lock" class="w-3 h-3"></i> Payments are processed securely.
                    </p>
                </div>
            </form>
        </div>

    </div>
    <script>lucide.createIcons();</script>
</body>
</html>