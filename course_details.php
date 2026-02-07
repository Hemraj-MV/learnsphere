<?php
// course_details.php
require 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$course_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$course_id) {
    header("Location: index.php");
    exit;
}

// --- FETCH COURSE DETAILS ---
$pdo->prepare("UPDATE courses SET views = views + 1 WHERE id = ?")->execute([$course_id]);

// Get Course Info
$stmt = $pdo->prepare("SELECT c.*, u.name as instructor_name 
                       FROM courses c 
                       JOIN users u ON c.instructor_id = u.id 
                       WHERE c.id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) { die("Course not found."); }

// --- FETCH LESSONS (FIXED: changed lesson_order to position) ---
$stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY position ASC");
$stmt->execute([$course_id]);
$lessons = $stmt->fetchAll();

// --- CHECK ENROLLMENT ---
$is_enrolled = false;
if ($user_id) {
    $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE course_id = ? AND student_id = ?");
    $stmt->execute([$course_id, $user_id]); 
    $is_enrolled = $stmt->fetch() ? true : false;
}

// --- HANDLE ENROLLMENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll'])) {
    if (!$user_id) {
        header("Location: login.php");
        exit;
    }
    $stmt = $pdo->prepare("INSERT INTO enrollments (course_id, student_id, enrolled_at) VALUES (?, ?, NOW())");
    $stmt->execute([$course_id, $user_id]);
    header("Location: course_details.php?id=" . $course_id);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($course['title']) ?> - LearnSphere</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #f0f4ff 0%, #eef2f6 100%);
            color: #1e293b;
            min-height: 100vh;
        }
        h1, h2, h3, .heading-font { font-family: 'Outfit', sans-serif; letter-spacing: -0.02em; }

        .premium-header {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 20px -5px rgba(0, 0, 0, 0.05);
        }

        .premium-card {
            background: #ffffff;
            border: 1px solid white;
            border-radius: 1.5rem;
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.08); 
        }

        .lesson-row {
            border-bottom: 1px solid #f1f5f9;
            transition: 0.2s;
        }
        .lesson-row:last-child { border-bottom: none; }
        .lesson-row:hover { background: #f8fafc; }

        .btn-action-lg {
            background: #0f172a;
            color: white; font-weight: 700; border-radius: 1rem;
            text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.875rem;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.2);
            transition: all 0.2s;
        }
        .btn-action-lg:hover { background: #334155; transform: translateY(-2px); }
    </style>
</head>
<body class="flex flex-col">

    <header class="premium-header h-20 sticky top-0 z-40 px-8 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-black rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-lg">LS</div>
            <span class="heading-font font-bold text-2xl text-slate-900 tracking-tight">LearnSphere</span>
        </div>
        
        <div class="flex gap-4">
            <?php if($user_id): ?>
                <a href="student/dashboard.php" class="btn btn-sm btn-ghost">My Learning</a>
                <a href="logout.php" class="btn btn-sm btn-ghost text-red-500">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-sm btn-ghost font-bold">Login</a>
                <a href="register.php" class="btn btn-sm bg-black text-white hover:bg-gray-800">Sign Up</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="max-w-6xl mx-auto w-full p-8 pb-32">
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            
            <div class="lg:col-span-2 space-y-8">
                <div>
                    <div class="flex gap-2 mb-4">
                        <?php 
                            $tags = array_filter(explode(',', $course['tags']));
                            foreach($tags as $tag): 
                        ?>
                            <span class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-full text-[10px] font-bold uppercase tracking-widest"><?= trim($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <h1 class="text-5xl font-extrabold text-slate-900 heading-font mb-4 leading-tight"><?= htmlspecialchars($course['title']) ?></h1>
                    <p class="text-lg text-slate-500 font-medium">Created by <span class="text-slate-900 font-bold"><?= htmlspecialchars($course['instructor_name']) ?></span></p>
                </div>

                <div class="premium-card p-8">
                    <h3 class="heading-font font-bold text-xl mb-4">About this Course</h3>
                    <div class="prose prose-slate max-w-none text-gray-600">
                        <?= nl2br(htmlspecialchars($course['description'] ?? 'No description provided.')) ?>
                    </div>
                </div>

                <div class="premium-card p-8">
                    <h3 class="heading-font font-bold text-xl mb-6">Curriculum</h3>
                    <div class="border border-slate-100 rounded-2xl overflow-hidden">
                        <?php foreach($lessons as $index => $lesson): ?>
                            <div class="lesson-row p-5 flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <span class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center font-mono text-xs font-bold text-slate-400">
                                        <?= str_pad($index + 1, 2, '0', STR_PAD_LEFT) ?>
                                    </span>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-700"><?= htmlspecialchars($lesson['title']) ?></span>
                                        <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wide"><?= ucfirst($lesson['type']) ?></span>
                                    </div>
                                </div>
                                <?php if($is_enrolled || (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $course['instructor_id'])): ?>
                                    <a href="lesson_view.php?id=<?= $lesson['id'] ?>" class="btn btn-sm btn-circle btn-ghost text-blue-600">
                                        <i data-lucide="play-circle" class="w-6 h-6"></i>
                                    </a>
                                <?php else: ?>
                                    <i data-lucide="lock" class="w-4 h-4 text-gray-300"></i>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($lessons)): ?>
                            <div class="p-8 text-center text-gray-400 font-medium italic">Content coming soon.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-1">
                <div class="premium-card p-8 sticky top-28">
                    <div class="aspect-video bg-slate-100 rounded-xl mb-6 flex items-center justify-center text-slate-300">
                        <i data-lucide="image" class="w-12 h-12"></i>
                    </div>
                    
                    <div class="flex justify-between items-center mb-6 pb-6 border-b border-gray-100">
                        <div>
                            <span class="block text-3xl font-black text-slate-900">Free</span>
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wide">Full Access</span>
                        </div>
                        <div class="text-right">
                            <span class="block text-xl font-bold text-slate-900 flex items-center gap-1 justify-end">
                                <i data-lucide="eye" class="w-4 h-4 text-blue-500"></i> <?= number_format($course['views']) ?>
                            </span>
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wide">Views</span>
                        </div>
                    </div>

                    <?php if($is_enrolled): ?>
                        <div class="bg-green-50 text-green-700 p-4 rounded-xl font-bold text-center mb-4 flex items-center justify-center gap-2">
                            <i data-lucide="check-circle" class="w-5 h-5"></i> You are Enrolled
                        </div>
                        <a href="lesson_view.php?course_id=<?= $course_id ?>" class="btn-action-lg w-full py-4 flex items-center justify-center gap-2">
                            Continue Learning <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    <?php elseif(isset($_SESSION['user_id']) && $_SESSION['user_id'] == $course['instructor_id']): ?>
                        <a href="instructor/manage_course.php?id=<?= $course_id ?>" class="btn-action-lg w-full py-4 flex items-center justify-center gap-2 bg-slate-800 hover:bg-slate-900">
                            Edit in Studio <i data-lucide="edit-3" class="w-4 h-4"></i>
                        </a>
                    <?php else: ?>
                        <form method="POST">
                            <button type="submit" name="enroll" class="btn-action-lg w-full py-4 flex items-center justify-center gap-2">
                                Enroll Now
                            </button>
                        </form>
                    <?php endif; ?>

                    <div class="mt-6 space-y-3">
                        <div class="flex items-center gap-3 text-sm text-gray-600 font-medium">
                            <i data-lucide="bar-chart" class="w-4 h-4 text-slate-400"></i> Beginner Level
                        </div>
                        <div class="flex items-center gap-3 text-sm text-gray-600 font-medium">
                            <i data-lucide="clock" class="w-4 h-4 text-slate-400"></i> <?= htmlspecialchars($course['duration'] ?? 'Flexible') ?> Duration
                        </div>
                        <div class="flex items-center gap-3 text-sm text-gray-600 font-medium">
                            <i data-lucide="globe" class="w-4 h-4 text-slate-400"></i> English
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>