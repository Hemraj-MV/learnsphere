<?php
// student/course_player.php
require '../includes/db.php';

// 1. Security & Auth
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$course_id = $_GET['course_id'] ?? 0;
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'User';

// 2. Handle Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = $_POST['rating'] ?? 0;
    $review_text = trim($_POST['review_text']);
    if ($rating > 0 && !empty($review_text)) {
        $stmt = $pdo->prepare("INSERT INTO course_reviews (course_id, user_id, rating, review_text) VALUES (?, ?, ?, ?)");
        $stmt->execute([$course_id, $user_id, $rating, $review_text]);
        header("Location: course_player.php?course_id=$course_id&tab=reviews");
        exit;
    }
}

// 3. Fetch Data
$course_stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$course_stmt->execute([$course_id]);
$course = $course_stmt->fetch();
if (!$course) die("Course not found.");

$lessons_stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY id ASC");
$lessons_stmt->execute([$course_id]);
$lessons = $lessons_stmt->fetchAll();

$quizzes_stmt = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ?");
$quizzes_stmt->execute([$course_id]);
$quizzes = $quizzes_stmt->fetchAll();

$prog_stmt = $pdo->prepare("SELECT DISTINCT lp.lesson_id FROM lesson_progress lp JOIN lessons l ON lp.lesson_id = l.id WHERE lp.user_id = ? AND l.course_id = ?");
$prog_stmt->execute([$user_id, $course_id]);
$completed_lessons = $prog_stmt->fetchAll(PDO::FETCH_COLUMN);

// 4. Fetch Reviews
$rev_stmt = $pdo->prepare("SELECT r.*, u.name as user_name FROM course_reviews r JOIN users u ON r.user_id = u.id WHERE r.course_id = ? ORDER BY r.created_at DESC");
$rev_stmt->execute([$course_id]);
$reviews = $rev_stmt->fetchAll();

// Stats
$total_items = count($lessons);
$completed_count = count($completed_lessons);
$incomplete_count = $total_items - $completed_count;
$percentage = ($total_items > 0) ? round(($completed_count / $total_items) * 100) : 0;

$avg_rating = 0;
if (count($reviews) > 0) {
    $total_stars = 0;
    foreach($reviews as $r) $total_stars += $r['rating'];
    $avg_rating = round($total_stars / count($reviews), 1);
}

// --- VIEW & NAVIGATION LOGIC ---
$current_item = null;
$content_type = 'lesson';
$view_mode = 'overview'; 
$active_tab = $_GET['tab'] ?? 'overview';
$next_link = '';
$next_label = 'Next Content'; 

if (isset($_GET['quiz_id'])) {
    $view_mode = 'player';
    $content_type = 'quiz';
    foreach ($quizzes as $index => $q) {
        if ($q['id'] == $_GET['quiz_id']) {
            $current_item = $q;
            // Next Quiz Logic
            if (isset($quizzes[$index + 1])) {
                $next_link = "course_player.php?course_id=$course_id&quiz_id=" . $quizzes[$index + 1]['id'];
            } else {
                $next_link = "dashboard.php";
            }
            break;
        }
    }
} elseif (isset($_GET['lesson_id'])) {
    $view_mode = 'player';
    foreach ($lessons as $index => $l) {
        if ($l['id'] == $_GET['lesson_id']) {
            $current_item = $l;
            // Next Lesson Logic
            if (isset($lessons[$index + 1])) {
                $next_link = "course_player.php?course_id=$course_id&lesson_id=" . $lessons[$index + 1]['id'];
            } elseif (count($quizzes) > 0) {
                $next_link = "course_player.php?course_id=$course_id&quiz_id=" . $quizzes[0]['id'];
            } else {
                $next_link = "dashboard.php";
            }
            break;
        }
    }
} else {
    // Overview Mode Navigation Logic
    if (count($lessons) > 0) {
        $next_link = "course_player.php?course_id=$course_id&lesson_id=" . $lessons[0]['id'];
    } elseif (count($quizzes) > 0) {
        $next_link = "course_player.php?course_id=$course_id&quiz_id=" . $quizzes[0]['id'];
    }
}

// Helper: Stars
function renderStars($rating) {
    $output = '<div class="rating rating-sm rating-half">';
    for ($i = 1; $i <= 5; $i++) {
        $output .= '<input type="radio" name="rating-readonly-'.$i.'" class="bg-orange-400 mask mask-star-2 mask-half-1" disabled ' . ($rating >= ($i - 0.5) ? 'checked' : '') . ' />';
        $output .= '<input type="radio" name="rating-readonly-'.$i.'" class="bg-orange-400 mask mask-star-2 mask-half-2" disabled ' . ($rating >= $i ? 'checked' : '') . ' />';
    }
    $output .= '</div>';
    return $output;
}

// Fetch Quiz Questions
$quiz_questions = [];
if ($view_mode === 'player' && $content_type === 'quiz' && $current_item) {
    $q_stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ?");
    $q_stmt->execute([$current_item['id']]);
    $quiz_questions = $q_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($course['title']) ?> - LearnSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0f172a; color: white; }
        
        /* Glass UI Components */
        .glass-panel { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); }
        .glass-sidebar { background: rgba(15, 23, 42, 0.98); border-right: 1px solid rgba(255, 255, 255, 0.1); display: flex; flex-direction: column; }
        .sidebar-header-box { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; padding: 20px; margin: 20px; }
        .stat-box { background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); }
        
        /* Tab Styling */
        .tabs-boxed { background: rgba(30, 41, 59, 0.5); border: 1px solid rgba(255, 255, 255, 0.1); }
        .tab { color: #94a3b8; transition: all 0.3s ease; }
        .tab:hover { color: white; }
        .tab-active-custom { background-color: #6366f1 !important; color: white !important; }
        
        /* Review Styling */
        .review-card { background: rgba(30, 41, 59, 0.4); border: 1px solid rgba(255, 255, 255, 0.05); transition: transform 0.2s; }
        .review-card:hover { border-color: rgba(255, 255, 255, 0.1); transform: translateY(-2px); }
        
        /* Content List Styling */
        .content-row { transition: background-color 0.2s; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .content-row:hover { background-color: rgba(255, 255, 255, 0.05); }
        .content-row:last-child { border-bottom: none; }

        /* Quiz & Fullscreen */
        .quiz-start-box { border: 2px solid rgba(255, 255, 255, 0.2); border-radius: 20px; padding: 40px; max-width: 500px; margin: 0 auto; text-align: left; background: transparent; }
        .question-step { display: none; }
        .question-step.active { display: block; animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        body.fullscreen-active nav { display: none !important; }
        body.fullscreen-active #sidebar { display: none !important; }
        body.fullscreen-active .player-content { padding: 0 !important; width: 100vw !important; height: 100vh !important; position: fixed; top: 0; left: 0; z-index: 100; background-color: #0f172a; }
        body.fullscreen-active .max-w-4xl { max-width: 100% !important; padding: 40px; margin: 0 auto; }
        #exitFullscreenBtn { display: none; position: fixed; top: 20px; right: 20px; z-index: 200; }
        body.fullscreen-active #exitFullscreenBtn { display: flex; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <nav class="border-b border-white/10 bg-slate-900/80 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-full px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="dashboard.php" class="btn btn-sm btn-ghost text-slate-400 hover:text-white gap-2">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Dashboard
                </a>
                <span class="text-slate-600">|</span>
                <span class="font-bold text-slate-200 truncate max-w-xs"><?= htmlspecialchars($course['title']) ?></span>
            </div>
            <div class="flex items-center gap-3">
                <?php if ($view_mode === 'player'): ?>
                    <button onclick="toggleFullscreen()" class="btn btn-sm btn-outline text-indigo-400 border-indigo-400 hover:bg-indigo-400 hover:text-white gap-2">
                        <i data-lucide="maximize" class="w-4 h-4"></i> Full Screen
                    </button>
                <?php endif; ?>
                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar placeholder border border-white/10">
                        <div class="bg-indigo-600 text-white rounded-full w-10"><span class="text-xs font-bold"><?= strtoupper(substr($user_name, 0, 1)) ?></span></div>
                    </div>
                    <ul tabindex="0" class="mt-3 z-[1] p-2 shadow menu menu-sm dropdown-content bg-slate-800 rounded-box w-52 border border-white/10">
                        <li class="menu-title text-slate-400">Signed in as <br><span class="text-white"><?= htmlspecialchars($user_name) ?></span></li>
                        <div class="divider my-0 border-white/10"></div>
                        <li><a href="../logout.php" class="text-red-400 hover:text-red-300">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <button id="exitFullscreenBtn" onclick="toggleFullscreen()" class="btn btn-circle btn-neutral shadow-lg border border-white/20">
        <i data-lucide="minimize" class="w-5 h-5"></i>
    </button>

    <div class="flex-1 flex flex-col md:flex-row overflow-hidden relative">

        <?php if ($view_mode === 'overview'): ?>
            <main class="w-full max-w-5xl mx-auto p-6 overflow-y-auto pb-20">
                <div class="glass-panel rounded-3xl p-8 mb-8">
                    <div class="flex flex-col lg:flex-row gap-8">
                        <div class="lg:w-1/3">
                            <div class="aspect-video rounded-xl overflow-hidden border border-white/10 shadow-2xl relative">
                                <img src="../<?= htmlspecialchars($course['thumbnail'] ?: 'assets/default.png') ?>" class="w-full h-full object-cover">
                                <div class="absolute top-3 left-3 px-3 py-1 bg-indigo-600/90 text-white text-xs font-bold rounded-full uppercase tracking-wider">Course</div>
                            </div>
                        </div>
                        <div class="lg:w-2/3 flex flex-col justify-between">
                            <div>
                                <h1 class="text-3xl font-bold mb-3 text-white"><?= htmlspecialchars($course['title']) ?></h1>
                                <p class="text-slate-400 text-sm line-clamp-2"><?= htmlspecialchars($course['description']) ?></p>
                            </div>
                            <div class="mt-6 bg-slate-800/50 rounded-xl p-5 border border-white/10">
                                <div class="flex justify-between items-end mb-2">
                                    <span class="text-lg font-bold text-white"><?= $percentage ?>% Completed</span>
                                    <span class="text-xs text-emerald-400 font-bold uppercase tracking-wider">In Progress</span>
                                </div>
                                <progress class="progress progress-success w-full h-3 bg-slate-700 mb-6" value="<?= $percentage ?>" max="100"></progress>
                                <div class="grid grid-cols-3 gap-4 text-center">
                                    <div class="stat-box rounded-lg p-3"><div class="text-2xl font-bold text-white"><?= $total_items ?></div><div class="text-[10px] text-slate-400 uppercase font-bold">Content</div></div>
                                    <div class="stat-box rounded-lg p-3"><div class="text-2xl font-bold text-emerald-400"><?= $completed_count ?></div><div class="text-[10px] text-slate-400 uppercase font-bold">Completed</div></div>
                                    <div class="stat-box rounded-lg p-3"><div class="text-2xl font-bold text-amber-400"><?= $incomplete_count ?></div><div class="text-[10px] text-slate-400 uppercase font-bold">Incomplete</div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                    <div class="tabs tabs-boxed p-1 rounded-lg">
                        <a href="?course_id=<?= $course_id ?>&tab=overview" class="tab <?= $active_tab === 'overview' ? 'tab-active-custom' : '' ?> rounded-md text-sm font-medium">Course Overview</a>
                        <a href="?course_id=<?= $course_id ?>&tab=reviews" class="tab <?= $active_tab === 'reviews' ? 'tab-active-custom' : '' ?> rounded-md text-sm font-medium">Ratings & Reviews</a>
                    </div>
                </div>

                <?php if ($active_tab === 'overview'): ?>
                    <div class="glass-panel rounded-2xl overflow-hidden border border-white/10">
                        <div class="p-5 border-b border-white/10 bg-slate-800/30">
                            <h3 class="font-bold text-lg text-white">Course Content</h3>
                        </div>
                        <div class="flex flex-col">
                            <?php foreach ($lessons as $index => $lesson): ?>
                                <?php $is_done = in_array($lesson['id'], $completed_lessons); ?>
                                <a href="course_player.php?course_id=<?= $course_id ?>&lesson_id=<?= $lesson['id'] ?>" class="content-row flex items-center justify-between p-5 group">
                                    <div class="flex items-center gap-5">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold bg-slate-800 border border-slate-700 text-slate-400 group-hover:bg-indigo-600 group-hover:text-white group-hover:border-indigo-500 transition-colors">
                                            <?= $index + 1 ?>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-slate-200 text-base group-hover:text-white transition-colors"><?= htmlspecialchars($lesson['title']) ?></h4>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="text-xs text-slate-500 flex items-center gap-1">
                                                    <?php if($lesson['type'] == 'video'): ?><i data-lucide="play-circle" class="w-3 h-3"></i> Video
                                                    <?php elseif($lesson['type'] == 'document'): ?><i data-lucide="file-text" class="w-3 h-3"></i> Document<?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <?php if($is_done): ?>
                                            <i data-lucide="check-circle-2" class="w-6 h-6 text-emerald-500 fill-emerald-500/20"></i>
                                        <?php else: ?>
                                            <i data-lucide="circle" class="w-6 h-6 text-slate-600 group-hover:text-slate-400 transition-colors"></i>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                            
                            <?php if (count($quizzes) > 0): ?>
                                <div class="p-4 bg-slate-800/50 border-y border-white/5 text-xs font-bold text-slate-400 uppercase tracking-wider pl-6">Assessments</div>
                                <?php foreach ($quizzes as $quiz): ?>
                                    <a href="course_player.php?course_id=<?= $course_id ?>&quiz_id=<?= $quiz['id'] ?>" class="content-row flex items-center justify-between p-5 group">
                                        <div class="flex items-center gap-5">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center bg-orange-500/10 text-orange-400 border border-orange-500/20 group-hover:bg-orange-500 group-hover:text-white transition-colors">
                                                <i data-lucide="trophy" class="w-5 h-5"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-semibold text-slate-200 text-base group-hover:text-white transition-colors"><?= htmlspecialchars($quiz['title']) ?></h4>
                                                <span class="text-xs text-slate-500">Quiz</span>
                                            </div>
                                        </div>
                                        <i data-lucide="chevron-right" class="w-5 h-5 text-slate-600 group-hover:text-white transition-colors"></i>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="glass-panel rounded-2xl p-8 border border-white/10">
                        <div class="flex flex-col md:flex-row justify-between items-center gap-6 mb-8 border-b border-white/10 pb-8">
                            <div class="flex items-center gap-6">
                                <div class="text-center">
                                    <span class="text-6xl font-black text-white"><?= $avg_rating ?></span>
                                    <p class="text-sm text-slate-400 mt-1">out of 5</p>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <div><?= renderStars($avg_rating) ?></div>
                                    <p class="text-sm text-slate-400 font-medium"><?= count($reviews) ?> Student Ratings</p>
                                </div>
                            </div>
                            <button onclick="review_modal.showModal()" class="btn btn-primary bg-indigo-600 border-none hover:bg-indigo-700 gap-2 shadow-lg shadow-indigo-500/20">
                                <i data-lucide="pen-tool" class="w-4 h-4"></i> Write a Review
                            </button>
                        </div>

                        <div class="space-y-4">
                            <?php if (count($reviews) > 0): ?>
                                <?php foreach($reviews as $r): ?>
                                    <div class="review-card rounded-xl p-6">
                                        <div class="flex gap-4">
                                            <div class="avatar placeholder">
                                                <div class="bg-indigo-600/20 text-indigo-300 rounded-full w-12 h-12 border border-indigo-500/30">
                                                    <span class="text-lg font-bold"><?= strtoupper(substr($r['user_name'], 0, 1)) ?></span>
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex justify-between items-start mb-2">
                                                    <div>
                                                        <h4 class="font-bold text-white text-base"><?= htmlspecialchars($r['user_name']) ?></h4>
                                                        <div class="flex items-center gap-2 mt-1">
                                                            <?= renderStars($r['rating']) ?>
                                                            <span class="text-xs text-slate-500">• <?= date('M d, Y', strtotime($r['created_at'])) ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <p class="text-slate-300 text-sm leading-relaxed mt-3"><?= nl2br(htmlspecialchars($r['review_text'])) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-16 bg-slate-800/30 rounded-2xl border border-dashed border-slate-700">
                                    <i data-lucide="message-square" class="w-12 h-12 text-slate-600 mx-auto mb-3"></i>
                                    <h3 class="text-lg font-medium text-slate-300">No reviews yet</h3>
                                    <p class="text-slate-500 text-sm">Be the first to share your thoughts on this course.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <dialog id="review_modal" class="modal">
                        <div class="modal-box bg-slate-900 border border-white/10 shadow-2xl">
                            <h3 class="font-bold text-xl text-white mb-6">Write a Review</h3>
                            <form method="POST">
                                <div class="mb-6">
                                    <label class="block text-sm font-medium text-slate-400 mb-3">Your Rating</label>
                                    <div class="flex items-center justify-between bg-slate-800/50 p-4 rounded-xl border border-white/5">
                                        <div class="rating rating-lg rating-half">
                                            <input type="radio" name="rating" class="rating-hidden" />
                                            <input type="radio" name="rating" value="0.5" class="bg-orange-400 mask mask-star-2 mask-half-1" />
                                            <input type="radio" name="rating" value="1.0" class="bg-orange-400 mask mask-star-2 mask-half-2" />
                                            <input type="radio" name="rating" value="1.5" class="bg-orange-400 mask mask-star-2 mask-half-1" />
                                            <input type="radio" name="rating" value="2.0" class="bg-orange-400 mask mask-star-2 mask-half-2" />
                                            <input type="radio" name="rating" value="2.5" class="bg-orange-400 mask mask-star-2 mask-half-1" />
                                            <input type="radio" name="rating" value="3.0" class="bg-orange-400 mask mask-star-2 mask-half-2" />
                                            <input type="radio" name="rating" value="3.5" class="bg-orange-400 mask mask-star-2 mask-half-1" />
                                            <input type="radio" name="rating" value="4.0" class="bg-orange-400 mask mask-star-2 mask-half-2" />
                                            <input type="radio" name="rating" value="4.5" class="bg-orange-400 mask mask-star-2 mask-half-1" />
                                            <input type="radio" name="rating" value="5.0" class="bg-orange-400 mask mask-star-2 mask-half-2" checked />
                                        </div>
                                        <span class="text-2xl font-bold text-white"><span id="rating-val">5.0</span><span class="text-sm text-slate-500">/5</span></span>
                                    </div>
                                </div>
                                <div class="mb-6">
                                    <label class="block text-sm font-medium text-slate-400 mb-2">Review</label>
                                    <textarea name="review_text" class="textarea textarea-bordered w-full bg-slate-800 text-white border-white/10 focus:border-indigo-500 h-32" required placeholder="What did you like or dislike?"></textarea>
                                </div>
                                <div class="modal-action">
                                    <button type="button" class="btn btn-ghost text-slate-400 hover:text-white" onclick="review_modal.close()">Cancel</button>
                                    <button type="submit" name="submit_review" class="btn btn-primary bg-indigo-600 border-none hover:bg-indigo-700 px-8">Submit Review</button>
                                </div>
                            </form>
                        </div>
                        <form method="dialog" class="modal-backdrop"><button>close</button></form>
                    </dialog>
                <?php endif; ?>

                <div class="absolute bottom-8 right-8">
                    <a href="<?= $next_link ?>" class="btn btn-lg bg-indigo-600 hover:bg-indigo-700 border-none text-white shadow-2xl gap-2 px-8 rounded-full shadow-indigo-500/20 transition-transform hover:scale-105">
                        <?= $next_label ?> <i data-lucide="arrow-right" class="w-5 h-5"></i>
                    </a>
                </div>
            </main>

        <?php else: ?>
            <div id="sidebar" class="w-80 glass-sidebar overflow-y-auto hidden md:flex flex-shrink-0 h-full">
                <div class="sidebar-header-box">
                    <a href="course_player.php?course_id=<?= $course_id ?>" class="btn btn-xs btn-outline text-indigo-300 border-indigo-500/50 hover:bg-indigo-600 hover:border-indigo-600 hover:text-white gap-1 mb-4"><i data-lucide="arrow-left" class="w-3 h-3"></i> Back</a>
                    <h2 class="font-bold text-white text-lg leading-tight mb-4"><?= htmlspecialchars($course['title']) ?></h2>
                    <div class="w-full"><div class="flex justify-between text-xs text-slate-400 mb-1"><span>Progress</span><span><?= $percentage ?>%</span></div><progress class="progress progress-success w-full h-2 bg-slate-700" value="<?= $percentage ?>" max="100"></progress></div>
                </div>
                <div class="p-4 pt-0">
                    <ul class="space-y-4">
                        <?php foreach ($lessons as $index => $lesson): $is_done = in_array($lesson['id'], $completed_lessons); $is_active = ($content_type === 'lesson' && isset($current_item['id']) && $current_item['id'] == $lesson['id']); $text_color = $is_active ? 'text-indigo-400' : 'text-white'; ?>
                            <li class="relative pl-4 border-l-2 <?= $is_active ? 'border-indigo-500' : 'border-slate-700' ?>">
                                <a href="course_player.php?course_id=<?= $course_id ?>&lesson_id=<?= $lesson['id'] ?>" class="block group">
                                    <div class="flex justify-between items-start"><div><span class="text-sm font-medium <?= $text_color ?> group-hover:text-indigo-300 transition-colors"><?= htmlspecialchars($lesson['title']) ?></span><div class="flex items-center gap-1 mt-1 text-xs text-slate-500"><?php if($lesson['type'] == 'video'): ?><i data-lucide="video" class="w-3 h-3 text-orange-400"></i> Video<?php elseif($lesson['type'] == 'document'): ?><i data-lucide="file-text" class="w-3 h-3 text-blue-400"></i> Document<?php endif; ?></div></div><?php if($is_done): ?><div class="w-4 h-4 bg-emerald-500 rounded-full flex items-center justify-center mt-1"><i data-lucide="check" class="w-3 h-3 text-white"></i></div><?php else: ?><div class="w-4 h-4 rounded-full border border-slate-600 mt-1"></div><?php endif; ?></div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <?php foreach ($quizzes as $quiz): $is_active = ($content_type === 'quiz' && isset($current_item['id']) && $current_item['id'] == $quiz['id']); $text_color = $is_active ? 'text-indigo-400' : 'text-white'; ?>
                            <li class="relative pl-4 border-l-2 <?= $is_active ? 'border-indigo-500' : 'border-slate-700' ?>">
                                <a href="course_player.php?course_id=<?= $course_id ?>&quiz_id=<?= $quiz['id'] ?>" class="block group">
                                    <div class="flex justify-between items-start"><div><span class="text-sm font-medium <?= $text_color ?> group-hover:text-indigo-300 transition-colors"><?= htmlspecialchars($quiz['title']) ?></span><div class="flex items-center gap-1 mt-1 text-xs text-slate-500"><i data-lucide="help-circle" class="w-3 h-3 text-purple-400"></i> Quiz</div></div><div class="w-4 h-4 rounded-full border border-slate-600 mt-1"></div></div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="flex-1 flex flex-col h-full overflow-y-auto p-6 md:p-8 player-content relative">
                <?php if ($content_type === 'lesson'): ?>
                    <div class="max-w-4xl mx-auto w-full pb-20">
                        <div class="border border-white/20 rounded-xl p-4 mb-6 bg-slate-800/50"><h2 class="text-xl font-bold text-white mb-2"><?= htmlspecialchars($current_item['title']) ?></h2><p class="text-sm text-slate-400">Watch the video/read the content below to complete this module.</p></div>
                        <div class="border border-white/20 rounded-2xl p-1 min-h-[400px] flex items-center justify-center relative bg-black/40">
                            <?php $show_media = false; if (($current_item['type'] == 'video' || $current_item['type'] == 'document') && !empty($current_item['content_url'])) $show_media = true; if ($show_media): if ($current_item['type'] == 'video'): $video_id = ''; if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $current_item['content_url'], $match)) $video_id = $match[1]; ?><iframe class="w-full h-full absolute inset-0 rounded-xl" src="https://www.youtube.com/embed/<?= $video_id ?>" frameborder="0" allowfullscreen></iframe><?php elseif ($current_item['type'] == 'document'): ?><iframe src="../<?= htmlspecialchars($current_item['content_url']) ?>" class="w-full h-full absolute inset-0 rounded-xl bg-white"></iframe><?php endif; else: ?><div class="text-center p-8"><div class="text-white text-lg font-medium mb-4">Lesson Text Content</div><div class="prose max-w-none text-slate-300 text-left"><?= nl2br(htmlspecialchars($current_item['text_content'] ?? 'No content available.')) ?></div></div><?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($content_type === 'quiz'): ?>
                    <?php $quiz_started = isset($_GET['started']); $quiz_score = isset($_GET['score']) ? $_GET['score'] : null; ?>
                    <div class="max-w-4xl mx-auto w-full h-full flex flex-col justify-center items-center pb-20">
                        <?php if ($quiz_score !== null): ?>
                            <div class="quiz-start-box text-center"><h3 class="text-3xl font-bold text-white mb-4">Quiz Result</h3><p class="text-5xl font-bold text-emerald-400 mb-2"><?= $quiz_score ?> / <?= $_GET['total'] ?></p><a href="dashboard.php" class="btn btn-outline text-white border-white/20 mt-6">Return to Dashboard</a></div>
                        <?php elseif (!$quiz_started): ?>
                            <div class="quiz-start-box"><div class="space-y-6 mb-10"><div class="flex items-center gap-4"><div class="w-2 h-2 bg-orange-400 rounded-full"></div><span class="text-xl text-slate-200">Total Questions: <span class="font-bold text-white"><?= count($quiz_questions) ?></span></span></div><div class="flex items-center gap-4"><div class="w-2 h-2 bg-blue-400 rounded-full"></div><span class="text-xl text-slate-200">Multiple Attempts Allowed</span></div></div><div class="text-center"><a href="course_player.php?course_id=<?= $course_id ?>&quiz_id=<?= $current_item['id'] ?>&started=1" class="btn btn-wide bg-indigo-600 border-none hover:bg-indigo-700 text-white shadow-lg shadow-indigo-500/20">Start Quiz</a></div></div>
                        <?php else: ?>
                            <div class="glass-panel rounded-2xl p-8 border border-white/10 w-full max-w-2xl"><form method="POST" action="submit_quiz.php" id="quizForm"><?php foreach($quiz_questions as $idx => $q): ?><div class="question-step <?= $idx === 0 ? 'active' : '' ?>" id="q-step-<?= $idx ?>"><div class="mb-6 flex justify-between"><span class="text-sm font-mono text-slate-400">Question <?= $idx + 1 ?> / <?= count($quiz_questions) ?></span></div><p class="font-bold text-white mb-8 text-xl"><?= ($idx+1) . ". " . htmlspecialchars($q['question_text']) ?></p><div class="space-y-4"><?php foreach(['A','B','C','D'] as $opt): ?><label class="flex items-center gap-4 p-4 rounded-xl border border-white/10 cursor-pointer hover:bg-white/5 hover:border-indigo-500 transition-all bg-slate-800/30"><input type="radio" name="q<?= $q['id'] ?>" value="<?= $opt ?>" class="radio radio-sm radio-primary" required /><span class="text-slate-200"><?= htmlspecialchars($q['option_'.strtolower($opt)]) ?></span></label><?php endforeach; ?></div><div class="flex justify-between mt-10"><?php if ($idx > 0): ?><button type="button" class="btn btn-ghost text-slate-400" onclick="changeQuestion(<?= $idx - 1 ?>)">Previous</button><?php else: ?><div></div><?php endif; ?><?php if ($idx < count($quiz_questions) - 1): ?><button type="button" class="btn btn-primary" onclick="changeQuestion(<?= $idx + 1 ?>)">Next</button><?php else: ?><button type="submit" class="btn btn-success text-white">Submit Quiz</button><?php endif; ?></div></div><?php endforeach; ?></form></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($content_type === 'lesson' || ($content_type === 'quiz' && !$quiz_started && $quiz_score === null)): ?>
                    <div class="absolute bottom-8 right-8">
                        <form method="POST" action="mark_complete.php">
                            <input type="hidden" name="course_id" value="<?= $course_id ?>">
                            <input type="hidden" name="lesson_id" value="<?= $current_item['id'] ?>">
                            <input type="hidden" name="redirect_url" value="<?= $next_link ?>">
                            <button type="submit" class="btn btn-lg bg-indigo-600 hover:bg-indigo-700 border-none text-white shadow-2xl gap-2 px-8 rounded-full"><?= $next_label ?> <i data-lucide="arrow-right" class="w-5 h-5"></i></button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        lucide.createIcons();
        function toggleFullscreen() { document.body.classList.toggle('fullscreen-active'); }
        function changeQuestion(index) {
            const steps = document.querySelectorAll('.question-step');
            steps.forEach(s => s.classList.remove('active'));
            steps[index].classList.add('active');
        }
        const ratingInputs = document.querySelectorAll('input[name="rating"]');
        const ratingLabel = document.getElementById('rating-val');
        if(ratingLabel) { ratingInputs.forEach(input => { input.addEventListener('change', () => { ratingLabel.textContent = input.value; }); }); }
    </script>
</body>
</html>