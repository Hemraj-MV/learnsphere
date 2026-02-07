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

// 1. UPDATE VIEW COUNT
$pdo->prepare("UPDATE courses SET views = views + 1 WHERE id = ?")->execute([$course_id]);

// 2. FETCH COURSE DETAILS
$stmt = $pdo->prepare("SELECT c.*, u.name as instructor_name, u.email as instructor_email 
                       FROM courses c 
                       JOIN users u ON c.instructor_id = u.id 
                       WHERE c.id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) { die("Course not found."); }

// 3. FETCH LESSONS
$stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY position ASC");
$stmt->execute([$course_id]);
$lessons = $stmt->fetchAll();

// 4. CHECK ENROLLMENT
$is_enrolled = false;
if ($user_id) {
    $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE course_id = ? AND student_id = ?");
    $stmt->execute([$course_id, $user_id]); 
    $is_enrolled = $stmt->fetch() ? true : false;
}

// 5. HANDLE ENROLLMENT / PURCHASE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_enroll'])) {
    if (!$user_id) {
        header("Location: login.php?redirect=course_details.php?id=" . $course_id);
        exit;
    }

    // Logic: If 'payment' rule, this is where you'd integrate Stripe/PayPal.
    // For now, we assume success immediately.
    
    if (!$is_enrolled) {
        $stmt = $pdo->prepare("INSERT INTO enrollments (course_id, student_id, enrolled_at, status, progress_percent) VALUES (?, ?, NOW(), 'yet_to_start', 0)");
        $stmt->execute([$course_id, $user_id]);
        
        // Refresh page to show "Continue Learning"
        header("Location: course_details.php?id=" . $course_id . "&enrolled=1");
        exit;
    }
}

// --- ACCESS LOGIC HELPERS ---
$is_instructor = ($user_id == $course['instructor_id']);
$is_paid = ($course['access_rule'] === 'payment');
$price_display = $is_paid ? '$' . number_format($course['price'], 2) : 'Free';
$access_text = $course['access_rule'] === 'invitation' ? 'Invitation Only' : ($is_paid ? 'Lifetime Access' : 'Free Enrollment');

// Use uploaded image or a default fallback
$course_image = !empty($course['image']) ? $course['image'] : 'assets/default_course.jpg';
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
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
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

        .course-card {
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid #f1f5f9;
        }

        .lesson-row {
            border-bottom: 1px solid #f8fafc;
            transition: all 0.2s;
        }
        .lesson-row:hover { background: #f8fafc; padding-left: 1.5rem; }
        .lesson-row:last-child { border-bottom: none; }

        .btn-action-lg {
            background: #0f172a;
            color: white; font-weight: 700; border-radius: 1rem;
            text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.875rem;
            box-shadow: 0 10px 20px -5px rgba(15, 23, 42, 0.3);
            transition: all 0.2s;
            border: none;
        }
        .btn-action-lg:hover { background: #334155; transform: translateY(-2px); box-shadow: 0 15px 30px -5px rgba(15, 23, 42, 0.4); }
        
        .tag-badge {
            background: #eef2ff; color: #6366f1; border-radius: 99px; 
            padding: 4px 12px; font-size: 11px; font-weight: 700; 
            text-transform: uppercase; letter-spacing: 0.05em;
        }
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
                <a href="student/dashboard.php" class="btn btn-sm btn-ghost font-bold text-slate-600">My Dashboard</a>
                <div class="w-px h-8 bg-slate-200"></div>
                <a href="logout.php" class="btn btn-sm btn-ghost text-red-500 font-bold">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-sm btn-ghost font-bold">Login</a>
                <a href="register.php" class="btn btn-sm bg-black text-white hover:bg-slate-800 border-none rounded-lg px-6">Join Free</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="max-w-7xl mx-auto w-full p-8 pb-32">
        
        <?php if(isset($_GET['enrolled'])): ?>
            <div class="alert bg-emerald-500 text-white rounded-2xl shadow-lg mb-8 flex items-center gap-4">
                <i data-lucide="party-popper" class="w-6 h-6"></i>
                <div>
                    <h3 class="font-bold text-lg">Welcome Aboard!</h3>
                    <div class="text-xs opacity-90">You have successfully enrolled in this course.</div>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
            
            <div class="lg:col-span-2 space-y-10">
                
                <div>
                    <div class="flex gap-2 mb-4 flex-wrap">
                        <?php 
                            $tags = array_filter(explode(',', $course['tags']));
                            foreach($tags as $tag): 
                        ?>
                            <span class="tag-badge"><?= trim($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <h1 class="text-5xl font-extrabold text-slate-900 heading-font mb-4 leading-tight"><?= htmlspecialchars($course['title']) ?></h1>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold">
                            <?= strtoupper(substr($course['instructor_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500 font-bold uppercase tracking-wide">Instructor</p>
                            <p class="text-base font-bold text-slate-900"><?= htmlspecialchars($course['instructor_name']) ?></p>
                        </div>
                    </div>
                </div>

                <?php if(!empty($course['image'])): ?>
                    <div class="w-full aspect-video rounded-2xl overflow-hidden shadow-lg border border-slate-100">
                        <img src="<?= htmlspecialchars($course['image']) ?>" class="w-full h-full object-cover" alt="Course Thumbnail">
                    </div>
                <?php endif; ?>

                <div class="course-card p-8">
                    <h3 class="heading-font font-bold text-2xl mb-6 flex items-center gap-2">
                        <i data-lucide="book-open" class="w-6 h-6 text-indigo-500"></i> Course Overview
                    </h3>
                    <div class="prose prose-slate max-w-none text-slate-600 leading-relaxed">
                        <?= nl2br(htmlspecialchars($course['description'] ?? 'No description provided by the instructor.')) ?>
                    </div>
                </div>

                <div class="course-card p-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="heading-font font-bold text-2xl flex items-center gap-2">
                            <i data-lucide="layers" class="w-6 h-6 text-indigo-500"></i> Curriculum
                        </h3>
                        <span class="text-xs font-bold bg-slate-100 px-3 py-1 rounded-lg text-slate-500"><?= count($lessons) ?> Modules</span>
                    </div>

                    <div class="border border-slate-100 rounded-2xl overflow-hidden">
                        <?php foreach($lessons as $index => $lesson): ?>
                            <div class="lesson-row p-5 flex items-center justify-between group">
                                <div class="flex items-center gap-5">
                                    <span class="w-8 h-8 rounded-lg bg-slate-50 text-slate-400 flex items-center justify-center font-mono text-xs font-bold group-hover:bg-indigo-50 group-hover:text-indigo-600 transition">
                                        <?= str_pad($index + 1, 2, '0', STR_PAD_LEFT) ?>
                                    </span>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-700 text-lg"><?= htmlspecialchars($lesson['title']) ?></span>
                                        <div class="flex items-center gap-3 text-xs font-bold text-slate-400 uppercase tracking-wider mt-1">
                                            <span class="flex items-center gap-1"><i data-lucide="<?= $lesson['type'] == 'video' ? 'play-circle' : 'file-text' ?>" class="w-3 h-3"></i> <?= ucfirst($lesson['type']) ?></span>
                                            <span>•</span>
                                            <span><?= htmlspecialchars($lesson['duration']) ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if($is_enrolled || $is_instructor): ?>
                                    <a href="lesson_view.php?id=<?= $lesson['id'] ?>" class="btn btn-sm btn-circle btn-ghost text-indigo-600 hover:bg-indigo-50">
                                        <i data-lucide="play" class="w-5 h-5 fill-current"></i>
                                    </a>
                                <?php else: ?>
                                    <div class="w-8 h-8 flex items-center justify-center text-slate-300">
                                        <i data-lucide="lock" class="w-4 h-4"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($lessons)): ?>
                            <div class="p-10 text-center text-slate-400 font-medium italic bg-slate-50/50">
                                No lessons have been published yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-1">
                <div class="course-card p-8 sticky top-28 border-t-4 border-t-indigo-600">
                    
                    <div class="flex justify-between items-end mb-6 pb-6 border-b border-slate-100">
                        <div>
                            <span class="block text-4xl font-black text-slate-900 heading-font"><?= $price_display ?></span>
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wide"><?= $access_text ?></span>
                        </div>
                    </div>

                    <?php if($is_instructor): ?>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 mb-4 text-center">
                            <span class="text-xs font-bold uppercase text-slate-400 block mb-2">Instructor Controls</span>
                            <a href="instructor/manage_course.php?id=<?= $course_id ?>" class="btn-action-lg w-full py-4 flex items-center justify-center gap-2 bg-slate-800 hover:bg-slate-900">
                                Edit Course <i data-lucide="edit-3" class="w-4 h-4"></i>
                            </a>
                        </div>

                    <?php elseif($is_enrolled): ?>
                        <div class="bg-emerald-50 text-emerald-700 p-4 rounded-xl font-bold text-center mb-6 flex flex-col items-center justify-center gap-2 border border-emerald-100">
                            <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center mb-1">
                                <i data-lucide="check" class="w-6 h-6"></i>
                            </div>
                            <span>You are Enrolled</span>
                        </div>
                        <a href="lesson_view.php?course_id=<?= $course_id ?>" class="btn-action-lg w-full py-4 flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 shadow-indigo-200">
                            Continue Learning <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>

                    <?php elseif($course['access_rule'] === 'invitation'): ?>
                        <button disabled class="btn btn-disabled w-full font-bold uppercase tracking-wider">Invitation Only</button>

                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="action_enroll" value="1">
                            <button type="submit" class="btn-action-lg w-full py-4 flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 shadow-xl shadow-indigo-200/50">
                                <?= $is_paid ? 'Buy Now' : 'Enroll Now' ?> <i data-lucide="arrow-right" class="w-4 h-4"></i>
                            </button>
                        </form>
                        <?php if($is_paid): ?>
                            <p class="text-xs text-center text-slate-400 mt-3 font-medium">30-Day Money-Back Guarantee</p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="mt-8 space-y-4 pt-6 border-t border-slate-100">
                        <div class="flex items-center gap-3 text-sm text-slate-600 font-bold">
                            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-500"><i data-lucide="clock" class="w-4 h-4"></i></div>
                            <?= htmlspecialchars($course['duration'] ?? 'Flexible') ?> Duration
                        </div>
                        <div class="flex items-center gap-3 text-sm text-slate-600 font-bold">
                            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-500"><i data-lucide="bar-chart" class="w-4 h-4"></i></div>
                            Beginner Level
                        </div>
                        <div class="flex items-center gap-3 text-sm text-slate-600 font-bold">
                            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-500"><i data-lucide="globe" class="w-4 h-4"></i></div>
                            English
                        </div>
                        <div class="flex items-center gap-3 text-sm text-slate-600 font-bold">
                            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-500"><i data-lucide="users" class="w-4 h-4"></i></div>
                            <?= number_format($course['views']) ?> Views
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>