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

// 2. Handle Review Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = $_POST['rating'] ?? 0;
    $review_text = trim($_POST['review_text']);
    if ($rating > 0 && !empty($review_text)) {
        $stmt = $pdo->prepare("INSERT INTO course_reviews (course_id, user_id, rating, review_text) VALUES (?, ?, ?, ?)");
        $stmt->execute([$course_id, $user_id, $rating, $review_text]);
        header("Location: ?course_id=$course_id&tab=reviews");
        exit;
    }
}

// 3. Fetch Course Data
$course_stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$course_stmt->execute([$course_id]);
$course = $course_stmt->fetch();
if (!$course) die("Course not found.");

// 4. Fetch Lessons & Quizzes
$lessons_stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY id ASC");
$lessons_stmt->execute([$course_id]);
$lessons = $lessons_stmt->fetchAll();

$quizzes_stmt = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ?");
$quizzes_stmt->execute([$course_id]);
$quizzes = $quizzes_stmt->fetchAll();

// 5. Fetch User Progress
$prog_sql = "SELECT DISTINCT lp.lesson_id FROM lesson_progress lp JOIN lessons l ON lp.lesson_id = l.id WHERE lp.user_id = ? AND l.course_id = ?";
$prog_stmt = $pdo->prepare($prog_sql);
$prog_stmt->execute([$user_id, $course_id]);
$completed_lessons = $prog_stmt->fetchAll(PDO::FETCH_COLUMN);

// 6. Fetch Reviews
$rev_stmt = $pdo->prepare("SELECT r.*, u.name as user_name FROM course_reviews r JOIN users u ON r.user_id = u.id WHERE r.course_id = ? ORDER BY r.created_at DESC");
$rev_stmt->execute([$course_id]);
$reviews = $rev_stmt->fetchAll();

$avg_rating = 0;
if (count($reviews) > 0) {
    $total_stars = 0;
    foreach($reviews as $r) $total_stars += $r['rating'];
    $avg_rating = round($total_stars / count($reviews), 1);
}

// --- VIEW LOGIC ---
$current_item = null;
$content_type = 'lesson';
$view_mode = 'overview'; // Default
$active_tab = $_GET['tab'] ?? 'overview';

// Logic to switch to Player Mode
if (isset($_GET['quiz_id'])) {
    $view_mode = 'player';
    $content_type = 'quiz';
    foreach ($quizzes as $q) { if ($q['id'] == $_GET['quiz_id']) { $current_item = $q; break; } }
} elseif (isset($_GET['lesson_id'])) {
    $view_mode = 'player';
    foreach ($lessons as $index => $l) {
        if ($l['id'] == $_GET['lesson_id']) {
            $current_item = $l;
            $is_last_item = !isset($lessons[$index + 1]);
            break;
        }
    }
}

// Stats
$total_items = count($lessons);
$completed_count = count($completed_lessons);
$incomplete_count = $total_items - $completed_count;
$percentage = ($total_items > 0) ? round(($completed_count / $total_items) * 100) : 0;

// Helper: Render Stars
function renderStars($rating) {
    $output = '<div class="rating rating-sm rating-half">';
    for ($i = 1; $i <= 5; $i++) {
        $output .= '<input type="radio" name="rating-readonly-'.$i.'" class="bg-orange-400 mask mask-star-2 mask-half-1" disabled ' . ($rating >= ($i - 0.5) ? 'checked' : '') . ' />';
        $output .= '<input type="radio" name="rating-readonly-'.$i.'" class="bg-orange-400 mask mask-star-2 mask-half-2" disabled ' . ($rating >= $i ? 'checked' : '') . ' />';
    }
    $output .= '</div>';
    return $output;
}

// Quiz Questions
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
        .glass-panel { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); }
        .glass-sidebar { background: rgba(15, 23, 42, 0.98); border-right: 1px solid rgba(255, 255, 255, 0.1); }
        .stat-box { background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); }
        .tab-active-custom { background-color: #6366f1 !important; color: white !important; }
        .review-box { border: 1px solid rgba(255, 165, 0, 0.3); }
        
        /* Full Screen Styles */
        body.fullscreen-active nav { display: none !important; }
        body.fullscreen-active #sidebar { display: none !important; }
        body.fullscreen-active .player-content { 
            padding: 0 !important; 
            width: 100vw !important; 
            height: 100vh !important; 
            position: fixed; top: 0; left: 0; z-index: 100;
            background-color: #0f172a; 
        }
        body.fullscreen-active .max-w-4xl { max-width: 100% !important; padding: 40px; margin: 0 auto; }
        #exitFullscreenBtn { display: none; position: fixed; top: 20px; right: 20px; z-index: 200; }
        body.fullscreen-active #exitFullscreenBtn { display: flex; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <nav class="border-b border-white/10 bg-slate-900/80 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
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
                        <div class="bg-indigo-600 text-white rounded-full w-10">
                            <span class="text-xs font-bold"><?= strtoupper(substr($user_name, 0, 1)) ?></span>
                        </div>
                    </div>
                    <ul tabindex="0" class="mt-3 z-[1] p-2 shadow menu menu-sm dropdown-content bg-slate-800 rounded-box w-52 border border-white/10">
                        <li class="menu-title text-slate-400">Signed in as <br><span class="text-white"><?= htmlspecialchars($user_name) ?></span></li>
                        <div class="divider my-0 border-white/10"></div>
                        <li><a href="profile.php" class="text-slate-300 hover:text-white">My Profile</a></li>
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
                    <div class="tabs tabs-boxed bg-slate-800 p-1 rounded-lg border border-white/10">
                        <a href="?course_id=<?= $course_id ?>&tab=overview" class="tab <?= $active_tab === 'overview' ? 'tab-active-custom' : 'text-slate-400 hover:text-white' ?> rounded-md transition-all">Course Overview</a>
                        <a href="?course_id=<?= $course_id ?>&tab=reviews" class="tab <?= $active_tab === 'reviews' ? 'tab-active-custom' : 'text-slate-400 hover:text-white' ?> rounded-md transition-all">Ratings & Reviews</a>
                    </div>
                </div>

                <?php if ($active_tab === 'overview'): ?>
                    <div class="glass-panel rounded-2xl overflow-hidden">
                        <div class="p-4 border-b border-white/10 bg-slate-800/30"><h3 class="font-bold text-slate-200">Course Content</h3></div>
                        <div class="divide-y divide-white/5">
                            <?php foreach ($lessons as $index => $lesson): ?>
                                <?php $is_done = in_array($lesson['id'], $completed_lessons); ?>
                                <a href="?course_id=<?= $course_id ?>&lesson_id=<?= $lesson['id'] ?>" class="flex items-center justify-between p-5 transition-colors hover:bg-white/5 group">
                                    <div class="flex items-center gap-4">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold bg-slate-800 text-slate-400 border border-slate-700"><?= $index + 1 ?></div>
                                        <div><h4 class="font-medium text-slate-200 group-hover:text-indigo-400 transition-colors"><?= htmlspecialchars($lesson['title']) ?></h4><span class="text-xs text-slate-500">Video Lesson</span></div>
                                    </div>
                                    <?php if($is_done): ?><i data-lucide="check-circle-2" class="w-6 h-6 text-blue-500 fill-blue-500/20"></i><?php else: ?><i data-lucide="circle" class="w-6 h-6 text-slate-600 group-hover:text-slate-400"></i><?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                            <?php if (count($quizzes) > 0): ?>
                                <div class="p-4 bg-slate-800/50 border-y border-white/5 text-xs font-bold text-slate-400 uppercase tracking-wider">Assessments</div>
                                <?php foreach ($quizzes as $quiz): ?>
                                    <a href="?course_id=<?= $course_id ?>&quiz_id=<?= $quiz['id'] ?>" class="flex items-center justify-between p-5 hover:bg-white/5 transition-colors group">
                                        <div class="flex items-center gap-4">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center bg-orange-900/20 text-orange-400 border border-orange-500/20"><i data-lucide="file-question" class="w-4 h-4"></i></div>
                                            <h4 class="font-medium text-slate-200 group-hover:text-orange-400 transition-colors"><?= htmlspecialchars($quiz['title']) ?></h4>
                                        </div>
                                        <i data-lucide="chevron-right" class="w-5 h-5 text-slate-600"></i>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="glass-panel rounded-2xl p-8">
                        <div class="flex flex-col md:flex-row justify-between items-center gap-6 mb-8 border-b border-white/10 pb-8">
                            <div class="flex items-center gap-4">
                                <span class="text-6xl font-bold text-white"><?= $avg_rating ?></span>
                                <div><?= renderStars($avg_rating) ?><p class="text-sm text-slate-400 mt-1"><?= count($reviews) ?> Ratings</p></div>
                            </div>
                            <button onclick="review_modal.showModal()" class="btn btn-primary bg-indigo-600 border-none hover:bg-indigo-700 gap-2"><i data-lucide="pen-tool" class="w-4 h-4"></i> Add Review</button>
                        </div>
                        <div class="space-y-6">
                            <?php if (count($reviews) > 0): foreach($reviews as $r): ?>
                                <div class="review-box rounded-xl p-6 bg-slate-800/30">
                                    <div class="flex gap-4">
                                        <div class="avatar placeholder"><div class="bg-slate-700 text-slate-200 rounded-full w-12 h-12"><span class="text-lg font-bold"><?= strtoupper(substr($r['user_name'], 0, 1)) ?></span></div></div>
                                        <div class="flex-1">
                                            <div class="flex justify-between items-start mb-2"><h4 class="font-bold text-white"><?= htmlspecialchars($r['user_name']) ?></h4><span class="text-xs text-slate-500"><?= date('M d, Y', strtotime($r['created_at'])) ?></span></div>
                                            <div class="mb-2"><?= renderStars($r['rating']) ?></div>
                                            <div class="bg-slate-900/50 p-3 rounded-lg border border-orange-500/20 text-slate-300 text-sm"><?= htmlspecialchars($r['review_text']) ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; else: ?><div class="text-center py-10 text-slate-500"><p>No reviews yet.</p></div><?php endif; ?>
                        </div>
                    </div>
                    <dialog id="review_modal" class="modal">
                        <div class="modal-box bg-slate-800 border border-white/10">
                            <h3 class="font-bold text-lg text-white mb-4">Write a Review</h3>
                            <form method="POST">
                                <div class="mb-6">
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="block text-sm font-medium text-slate-400">Your Rating</label>
                                        <span class="text-xl font-bold text-white"><span id="rating-val">5.0</span><span class="text-sm text-slate-500">/5</span></span>
                                    </div>
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
                                </div>
                                <div class="mb-6"><label class="block text-sm font-medium text-slate-400 mb-2">Review</label><textarea name="review_text" class="textarea textarea-bordered w-full bg-slate-900 text-white border-white/20 focus:border-indigo-500" rows="4" required></textarea></div>
                                <div class="modal-action"><button type="button" class="btn btn-ghost text-slate-400" onclick="review_modal.close()">Cancel</button><button type="submit" name="submit_review" class="btn btn-primary bg-indigo-600 border-none">Submit Review</button></div>
                            </form>
                        </div>
                        <form method="dialog" class="modal-backdrop"><button>close</button></form>
                    </dialog>
                <?php endif; ?>
            </main>

        <?php else: ?>
            <div id="sidebar" class="w-80 glass-sidebar overflow-y-auto hidden md:block flex-shrink-0 h-full">
                <div class="p-4 border-b border-white/10">
                    <a href="?course_id=<?= $course_id ?>&tab=overview" class="flex items-center gap-2 text-sm font-bold text-slate-400 hover:text-white transition-colors">
                        <i data-lucide="arrow-left-circle" class="w-4 h-4"></i> Back to Overview
                    </a>
                </div>
                <div class="p-2">
                    <ul class="space-y-1">
                        <?php foreach ($lessons as $index => $lesson): ?>
                            <?php 
                                $is_done = in_array($lesson['id'], $completed_lessons); 
                                $is_active = ($content_type === 'lesson' && isset($current_item['id']) && $current_item['id'] == $lesson['id']);
                                $active_class = $is_active ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'border-transparent hover:bg-white/5 text-slate-400';
                            ?>
                            <li>
                                <a href="?course_id=<?= $course_id ?>&lesson_id=<?= $lesson['id'] ?>" class="flex items-center justify-between p-3 rounded-lg border <?= $active_class ?> transition-all">
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs font-bold opacity-50"><?= $index + 1 ?></span>
                                        <span class="text-sm font-medium line-clamp-1"><?= htmlspecialchars($lesson['title']) ?></span>
                                    </div>
                                    <?php if($is_done): ?><i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500"></i><?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        
                        <?php if(count($quizzes) > 0): ?>
                            <li class="mt-4 px-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Assessments</li>
                            <?php foreach ($quizzes as $quiz): ?>
                                <?php 
                                    $is_active = ($content_type === 'quiz' && isset($current_item['id']) && $current_item['id'] == $quiz['id']);
                                    $active_class = $is_active ? 'bg-orange-900/20 text-orange-400 border-orange-500/30' : 'border-transparent hover:bg-white/5 text-slate-400';
                                ?>
                                <li>
                                    <a href="?course_id=<?= $course_id ?>&quiz_id=<?= $quiz['id'] ?>" class="flex items-center gap-3 p-3 rounded-lg border <?= $active_class ?>">
                                        <i data-lucide="file-question" class="w-4 h-4 text-orange-400"></i>
                                        <span class="text-sm font-medium"><?= htmlspecialchars($quiz['title']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="flex-1 flex flex-col h-full overflow-y-auto p-6 md:p-8 player-content">
                <?php if ($current_item): ?>
                    <div class="max-w-4xl mx-auto w-full">
                        <h2 class="text-2xl font-bold text-white mb-6"><?= htmlspecialchars($current_item['title']) ?></h2>

                        <?php if ($content_type === 'lesson'): ?>
                            <?php 
                                $show_media = false;
                                if (($current_item['type'] == 'video' || $current_item['type'] == 'document') && !empty($current_item['content_url'])) $show_media = true;
                            ?>
                            <?php if ($show_media): ?>
                                <div class="bg-black rounded-2xl overflow-hidden shadow-2xl border border-white/10 aspect-video w-full mb-8 relative">
                                    <?php if ($current_item['type'] == 'video'): ?>
                                        <?php 
                                            $video_id = '';
                                            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $current_item['content_url'], $match)) $video_id = $match[1];
                                        ?>
                                        <?php if($video_id): ?>
                                            <iframe class="w-full h-full" src="https://www.youtube.com/embed/<?= $video_id ?>" frameborder="0" allowfullscreen></iframe>
                                        <?php else: ?>
                                            <div class="flex items-center justify-center h-full text-white">Invalid Video URL</div>
                                        <?php endif; ?>
                                    <?php elseif ($current_item['type'] == 'document'): ?>
                                        <iframe src="../<?= htmlspecialchars($current_item['content_url']) ?>" class="w-full h-full bg-white"></iframe>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="glass-panel rounded-2xl p-8 mb-8">
                                <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2"><i data-lucide="book-open" class="w-5 h-5 text-indigo-400"></i> Lesson Notes</h3>
                                <div class="prose max-w-none text-slate-300 leading-relaxed">
                                    <?= nl2br(htmlspecialchars($current_item['text_content'] ?? 'No text notes available.')) ?>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <form method="POST" action="mark_complete.php">
                                    <input type="hidden" name="course_id" value="<?= $course_id ?>">
                                    <input type="hidden" name="lesson_id" value="<?= $current_item['id'] ?>">
                                    <button type="submit" class="btn btn-primary bg-indigo-600 hover:bg-indigo-700 border-none shadow-lg shadow-indigo-900/50 gap-2 px-8 rounded-xl h-12">
                                        <?php if (isset($is_last_item) && $is_last_item): ?>
                                            Complete Course <i data-lucide="check-check" class="w-4 h-4"></i>
                                        <?php else: ?>
                                            Next Lesson <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                        <?php endif; ?>
                                    </button>
                                </form>
                            </div>

                        <?php elseif ($content_type === 'quiz'): ?>
                            <div class="glass-panel rounded-2xl p-8 border border-white/10">
                                <div class="card-body p-0">
                                    <h3 class="text-2xl font-bold text-white mb-2">Quiz Time!</h3>
                                    
                                    <?php if (isset($_GET['score'])): ?>
                                        <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-2xl p-6 text-center mb-6">
                                            <div class="w-12 h-12 bg-emerald-500 rounded-full flex items-center justify-center mx-auto mb-3">
                                                <i data-lucide="trophy" class="w-6 h-6 text-white"></i>
                                            </div>
                                            <h3 class="text-xl font-bold text-white mb-1">Quiz Completed!</h3>
                                            <p class="text-slate-300">You scored <span class="text-emerald-400 font-bold text-lg"><?= $_GET['score'] ?></span> / <?= $_GET['total'] ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <p class="text-slate-400 mb-6">Test your knowledge of the module.</p>
                                    <div class="divider border-white/10"></div>
                                    <form method="POST" action="submit_quiz.php"> 
                                        <?php foreach($quiz_questions as $idx => $q): ?>
                                            <div class="mb-8">
                                                <p class="font-bold text-slate-200 mb-4 text-lg"><?= ($idx+1) . ". " . htmlspecialchars($q['question_text']) ?></p>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                    <?php foreach(['A','B','C','D'] as $opt): ?>
                                                        <label class="flex items-center gap-3 p-4 rounded-xl border border-white/10 cursor-pointer hover:bg-white/5 hover:border-indigo-500/50 transition-all bg-slate-800/50">
                                                            <input type="radio" name="q<?= $q['id'] ?>" value="<?= $opt ?>" class="radio radio-sm radio-primary" />
                                                            <span class="text-slate-300"><?= htmlspecialchars($q['option_'.strtolower($opt)]) ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="card-actions justify-end mt-6"><button type="submit" class="btn btn-primary bg-indigo-600 border-none hover:bg-indigo-700 px-8">Submit Answers</button></div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <script>
        lucide.createIcons();
        function toggleFullscreen() { document.body.classList.toggle('fullscreen-active'); }
        
        // JS for Dynamic Rating Label
        const ratingInputs = document.querySelectorAll('input[name="rating"]');
        const ratingLabel = document.getElementById('rating-val');
        if(ratingLabel) {
            ratingInputs.forEach(input => {
                input.addEventListener('change', () => { ratingLabel.textContent = input.value; });
            });
        }
    </script>
</body>
</html>