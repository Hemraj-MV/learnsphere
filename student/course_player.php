<?php
// student/course_player.php
require '../includes/db.php';

// 1. AUTHENTICATION
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$course_id = $_GET['course_id'] ?? 0;
// ... inside student/course_player.php ...

$user_id = $_SESSION['user_id']; // (Existing line)
$user_name = $_SESSION['name'] ?? 'User'; // (Existing line)

// --- ADD THIS BLOCK: UPDATE START DATE ---
// Check if this is the first time the user is accessing the course
$check_start = $pdo->prepare("SELECT started_at FROM enrollments WHERE student_id = ? AND course_id = ?");
$check_start->execute([$user_id, $course_id]);
$enrollment = $check_start->fetch();

if ($enrollment && $enrollment['started_at'] === null) {
    // It's their first time! Stamp the date and set status to in_progress
    $update_start = $pdo->prepare("UPDATE enrollments SET started_at = NOW(), status = 'in_progress' WHERE student_id = ? AND course_id = ?");
    $update_start->execute([$user_id, $course_id]);
}
// ----------------------------------------

// 2. HANDLE REVIEW SUBMISSION
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

// 3. FETCH DATA
$course_stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$course_stmt->execute([$course_id]);
$course = $course_stmt->fetch();
if (!$course) die("Course not found.");

$lessons_stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY position ASC");
$lessons_stmt->execute([$course_id]);
$lessons = $lessons_stmt->fetchAll();

$quizzes_stmt = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ?");
$quizzes_stmt->execute([$course_id]);
$quizzes = $quizzes_stmt->fetchAll();

// Get Progress
$prog_stmt = $pdo->prepare("SELECT DISTINCT lp.lesson_id FROM lesson_progress lp JOIN lessons l ON lp.lesson_id = l.id WHERE lp.user_id = ? AND l.course_id = ?");
$prog_stmt->execute([$user_id, $course_id]);
$completed_lessons = $prog_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get Reviews
$rev_stmt = $pdo->prepare("SELECT r.*, u.name as user_name FROM course_reviews r JOIN users u ON r.user_id = u.id WHERE r.course_id = ? ORDER BY r.created_at DESC");
$rev_stmt->execute([$course_id]);
$reviews = $rev_stmt->fetchAll();

// Calculations
$total_items = count($lessons);
$completed_count = count($completed_lessons);
$percentage = ($total_items > 0) ? round(($completed_count / $total_items) * 100) : 0;

$avg_rating = 0;
if (count($reviews) > 0) {
    $total_stars = array_sum(array_column($reviews, 'rating'));
    $avg_rating = round($total_stars / count($reviews), 1);
}

// --- NAVIGATION LOGIC ---
$current_item = null;
$content_type = 'lesson';
$view_mode = 'overview'; 
$next_link = "dashboard.php";
$next_label = "Finish Course"; 

// Determine View Mode
if (isset($_GET['quiz_id'])) {
    $view_mode = 'player';
    $content_type = 'quiz';
    foreach ($quizzes as $index => $q) {
        if ($q['id'] == $_GET['quiz_id']) {
            $current_item = $q;
            // Logic for next item could be added here if quizzes were intermixed with lessons
            break;
        }
    }
} elseif (isset($_GET['lesson_id'])) {
    $view_mode = 'player';
    foreach ($lessons as $index => $l) {
        if ($l['id'] == $_GET['lesson_id']) {
            $current_item = $l;
            // Next Item Logic
            if (isset($lessons[$index + 1])) {
                $next_link = "course_player.php?course_id=$course_id&lesson_id=" . $lessons[$index + 1]['id'];
                $next_label = "Next Lesson";
            } elseif (count($quizzes) > 0) {
                // If no more lessons, go to first quiz
                $next_link = "course_player.php?course_id=$course_id&quiz_id=" . $quizzes[0]['id'];
                $next_label = "Take Quiz";
            }
            break;
        }
    }
} else {
    // Default to first lesson if available
    if (count($lessons) > 0) {
        $next_link = "course_player.php?course_id=$course_id&lesson_id=" . $lessons[0]['id'];
        $next_label = "Start Learning";
    }
}

// Helper: Render Stars
function renderStars($rating) {
    $html = '<div class="flex text-orange-400 gap-0.5">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) $html .= '<i data-lucide="star" class="w-4 h-4 fill-current"></i>';
        elseif ($rating >= $i - 0.5) $html .= '<i data-lucide="star-half" class="w-4 h-4 fill-current"></i>';
        else $html .= '<i data-lucide="star" class="w-4 h-4 text-slate-300"></i>';
    }
    $html .= '</div>';
    return $html;
}

// Fetch Quiz Questions if needed
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
    <title><?= htmlspecialchars($course['title']) ?> - Learning</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    
    <style>
        /* PREMIUM LIGHT THEME */
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; color: #1e293b; height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
        h1, h2, h3, .heading-font { font-family: 'Outfit', sans-serif; letter-spacing: -0.02em; }

        /* HEADER */
        .player-header { background: white; border-bottom: 1px solid #e2e8f0; height: 64px; flex-shrink: 0; z-index: 50; }
        
        /* SIDEBAR */
        .player-sidebar { width: 320px; background: white; border-right: 1px solid #e2e8f0; height: 100%; overflow-y: auto; display: flex; flex-direction: column; flex-shrink: 0; }
        
        .lesson-row { display: flex; align-items: center; padding: 12px 16px; border-bottom: 1px solid #f8fafc; cursor: pointer; transition: 0.2s; }
        .lesson-row:hover { background: #f1f5f9; }
        .lesson-row.active { background: #eef2ff; border-right: 3px solid #6366f1; }
        .lesson-row.active .lesson-title { color: #4f46e5; font-weight: 700; }
        
        /* MAIN CONTENT */
        .player-canvas { flex: 1; background: #f8fafc; overflow-y: auto; position: relative; }
        
        /* VIDEO WRAPPER */
        .video-wrapper { width: 100%; aspect-ratio: 16/9; background: black; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        
        /* CARDS */
        .content-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 32px; margin-bottom: 24px; }
        
        /* QUIZ UI */
        .quiz-option { border: 2px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 12px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 12px; }
        .quiz-option:hover { border-color: #6366f1; background: #f8fafc; }
        .quiz-option input:checked + span { color: #4f46e5; font-weight: 700; }
        .quiz-option:has(input:checked) { border-color: #6366f1; background: #eef2ff; }

        /* FULLSCREEN MODE */
        body.fullscreen-mode .player-header, body.fullscreen-mode .player-sidebar { display: none !important; }
        body.fullscreen-mode .player-canvas { padding: 0 !important; background: black; }
        body.fullscreen-mode .video-wrapper { border-radius: 0; height: 100vh; width: 100vw; }
        body.fullscreen-mode .content-card { display: none; }
        
        .exit-fs-btn { position: fixed; top: 20px; right: 20px; z-index: 100; display: none; }
        body.fullscreen-mode .exit-fs-btn { display: block; }
    </style>
</head>
<body>

    <header class="player-header px-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="dashboard.php" class="btn btn-sm btn-ghost text-slate-400 hover:text-slate-900">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div class="h-6 w-px bg-slate-200"></div>
            <div>
                <h1 class="text-sm font-bold text-slate-900 line-clamp-1"><?= htmlspecialchars($course['title']) ?></h1>
                <div class="flex items-center gap-2">
                    <progress class="progress progress-primary w-20 h-1.5" value="<?= $percentage ?>" max="100"></progress>
                    <span class="text-[10px] font-bold text-slate-400 uppercase"><?= $percentage ?>% Complete</span>
                </div>
            </div>
        </div>
        
        <div class="flex items-center gap-3">
            <?php if($view_mode === 'player' && $content_type === 'lesson'): ?>
            <button onclick="toggleFullscreen()" class="btn btn-sm btn-outline border-slate-200 text-slate-600 hover:bg-slate-50 hover:text-indigo-600 gap-2 rounded-lg">
                <i data-lucide="maximize" class="w-4 h-4"></i> <span class="hidden md:inline">Focus Mode</span>
            </button>
            <?php endif; ?>
            <div class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-xs">
                <?= strtoupper(substr($user_name, 0, 1)) ?>
            </div>
        </div>
    </header>

    <button onclick="toggleFullscreen()" class="exit-fs-btn btn btn-circle btn-neutral opacity-80 hover:opacity-100">
        <i data-lucide="minimize" class="w-5 h-5"></i>
    </button>

    <div class="flex flex-1 overflow-hidden">
        
        <aside class="player-sidebar hidden lg:flex">
            <div class="p-5 border-b border-slate-50 bg-slate-50/50">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest">Course Content</h3>
            </div>
            
            <div class="flex-1 overflow-y-auto">
                <?php foreach($lessons as $idx => $l): 
                    // FIX: Check if $current_item exists before accessing ['id']
                    $is_active = ($content_type == 'lesson' && !empty($current_item) && $current_item['id'] == $l['id']);
                    $is_complete = in_array($l['id'], $completed_lessons);
                ?>
                <a href="?course_id=<?= $course_id ?>&lesson_id=<?= $l['id'] ?>" class="lesson-row <?= $is_active ? 'active' : '' ?>">
                    <div class="mr-3 text-slate-400">
                        <?php if($is_complete): ?><i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500"></i>
                        <?php elseif($l['type'] == 'video'): ?><i data-lucide="play-circle" class="w-4 h-4"></i>
                        <?php else: ?><i data-lucide="file-text" class="w-4 h-4"></i><?php endif; ?>
                    </div>
                    <div class="flex-1">
                        <div class="text-sm font-medium text-slate-700 lesson-title line-clamp-1"><?= htmlspecialchars($l['title']) ?></div>
                        <div class="text-[10px] font-bold text-slate-400"><?= $l['duration'] ?></div>
                    </div>
                </a>
                <?php endforeach; ?>

                <?php if(count($quizzes) > 0): ?>
                    <div class="px-5 py-3 bg-slate-50/50 border-y border-slate-100 text-xs font-black text-slate-400 uppercase tracking-widest mt-2">Quizzes</div>
                    <?php foreach($quizzes as $q): 
                        // FIX: Check if $current_item exists here too
                        $is_active = ($content_type == 'quiz' && !empty($current_item) && $current_item['id'] == $q['id']);
                    ?>
                    <a href="?course_id=<?= $course_id ?>&quiz_id=<?= $q['id'] ?>" class="lesson-row <?= $is_active ? 'active' : '' ?>">
                        <div class="mr-3 text-orange-400"><i data-lucide="help-circle" class="w-4 h-4"></i></div>
                        <div class="text-sm font-medium text-slate-700 lesson-title"><?= htmlspecialchars($q['title']) ?></div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <main class="player-canvas p-6 md:p-10">
            <div class="max-w-4xl mx-auto h-full">
                
                <?php if($view_mode === 'overview'): ?>
                    <div class="content-card flex flex-col md:flex-row gap-8 items-center">
                        <div class="w-full md:w-1/3 aspect-video bg-slate-100 rounded-xl overflow-hidden relative">
                            <img src="../<?= htmlspecialchars($course['image'] ?: 'assets/default_course.jpg') ?>" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black/10"></div>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <a href="<?= $next_link ?>" class="btn btn-circle btn-lg bg-white/20 backdrop-blur-sm border-white/50 text-white hover:bg-white hover:text-indigo-600 hover:scale-110 transition-all">
                                    <i data-lucide="play" class="w-8 h-8 ml-1"></i>
                                </a>
                            </div>
                        </div>
                        <div class="flex-1">
                            <h2 class="text-3xl font-bold text-slate-900 mb-4"><?= htmlspecialchars($course['title']) ?></h2>
                            <p class="text-slate-500 mb-6 leading-relaxed line-clamp-3"><?= nl2br(htmlspecialchars($course['description'])) ?></p>
                            <a href="<?= $next_link ?>" class="btn btn-sm bg-indigo-600 hover:bg-indigo-700 border-none text-white rounded-lg px-6 h-10 shadow-lg shadow-indigo-200">
                                <?= $next_label ?> <i data-lucide="arrow-right" class="w-4 h-4 ml-1"></i>
                            </a>
                        </div>
                    </div>

                    <div class="content-card">
                        <div class="flex justify-between items-center mb-8 border-b border-slate-100 pb-6">
                            <div>
                                <h3 class="text-xl font-bold text-slate-900">Reviews</h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-2xl font-black text-indigo-600"><?= $avg_rating ?></span>
                                    <?= renderStars($avg_rating) ?>
                                    <span class="text-xs font-bold text-slate-400 uppercase ml-2"><?= count($reviews) ?> Ratings</span>
                                </div>
                            </div>
                            <button onclick="review_modal.showModal()" class="btn btn-sm btn-outline border-slate-200 text-slate-600 hover:text-indigo-600 rounded-lg">Write Review</button>
                        </div>

                        <div class="space-y-6">
                            <?php if(count($reviews) > 0): foreach($reviews as $r): ?>
                                <div class="bg-slate-50 rounded-xl p-5 border border-slate-100">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-[10px] font-bold"><?= strtoupper(substr($r['user_name'],0,1)) ?></div>
                                            <span class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($r['user_name']) ?></span>
                                        </div>
                                        <span class="text-[10px] font-bold text-slate-400"><?= date('M d, Y', strtotime($r['created_at'])) ?></span>
                                    </div>
                                    <div class="mb-2"><?= renderStars($r['rating']) ?></div>
                                    <p class="text-slate-600 text-sm"><?= nl2br(htmlspecialchars($r['review_text'])) ?></p>
                                </div>
                            <?php endforeach; else: ?>
                                <p class="text-center text-slate-400 text-sm italic">No reviews yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <dialog id="review_modal" class="modal">
                        <div class="modal-box bg-white p-8 rounded-2xl max-w-md">
                            <h3 class="font-bold text-lg text-slate-900 mb-4">Rate this Course</h3>
                            <form method="POST">
                                <div class="mb-4">
                                    <div class="rating rating-lg rating-half">
                                        <input type="radio" name="rating" class="rating-hidden" />
                                        <?php for($i=1; $i<=10; $i++): $val=$i/2; ?>
                                            <input type="radio" name="rating" value="<?= $val ?>" class="bg-orange-400 mask mask-star-2 <?= $i%2!=0?'mask-half-1':'mask-half-2' ?>" />
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <textarea name="review_text" class="textarea textarea-bordered w-full bg-slate-50 border-slate-200 h-24 rounded-xl mb-4" placeholder="Your experience..."></textarea>
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="btn btn-ghost rounded-lg" onclick="review_modal.close()">Cancel</button>
                                    <button type="submit" name="submit_review" class="btn bg-indigo-600 hover:bg-indigo-700 text-white border-none rounded-lg">Submit</button>
                                </div>
                            </form>
                        </div>
                        <form method="dialog" class="modal-backdrop bg-slate-900/20 backdrop-blur-sm"><button>close</button></form>
                    </dialog>

                <?php elseif($content_type === 'lesson'): ?>
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-slate-900 mb-4"><?= htmlspecialchars($current_item['title']) ?></h2>
                        
                        <div class="video-wrapper relative bg-black flex items-center justify-center">
                            <?php 
                                $show = false;
                                if(($current_item['type'] == 'video' || $current_item['type'] == 'document') && !empty($current_item['content_url'])) $show = true;
                                
                                if($show):
                                    if($current_item['type'] == 'video'):
                                        $vid = '';
                                        if(preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $current_item['content_url'], $m)) $vid = $m[1];
                            ?>
                                        <iframe class="w-full h-full absolute inset-0" src="https://www.youtube.com/embed/<?= $vid ?>" frameborder="0" allowfullscreen></iframe>
                                    <?php else: ?>
                                        <iframe src="../<?= htmlspecialchars($current_item['content_url']) ?>" class="w-full h-full bg-white"></iframe>
                                    <?php endif; 
                                else: ?>
                                    <div class="text-white text-center p-10">
                                        <p class="text-lg opacity-70">Read the content below.</p>
                                    </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if(!empty($current_item['description'])): ?>
                    <div class="content-card">
                        <h3 class="text-sm font-black text-slate-400 uppercase tracking-widest mb-4">Lesson Notes</h3>
                        <div class="prose prose-slate text-slate-600 max-w-none">
                            <?= nl2br(htmlspecialchars($current_item['description'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="flex justify-end pb-20">
                        <form method="POST" action="mark_complete.php">
                            <input type="hidden" name="course_id" value="<?= $course_id ?>">
                            <input type="hidden" name="lesson_id" value="<?= $current_item['id'] ?>">
                            <input type="hidden" name="redirect_url" value="<?= $next_link ?>">
                            <button type="submit" class="btn bg-indigo-600 hover:bg-indigo-700 text-white border-none rounded-xl px-8 h-12 shadow-lg shadow-indigo-200">
                                <?= $next_label ?> <i data-lucide="arrow-right" class="w-4 h-4 ml-1"></i>
                            </button>
                        </form>
                    </div>

                <?php elseif($content_type === 'quiz'): ?>
                    <?php $started = isset($_GET['started']); $score = isset($_GET['score']) ? $_GET['score'] : null; ?>
                    
                    <div class="flex flex-col items-center justify-center h-full pb-20">
                        <?php if($score !== null): ?>
                            <div class="content-card text-center max-w-md w-full">
                                <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-6">
                                    <i data-lucide="award" class="w-10 h-10"></i>
                                </div>
                                <h2 class="text-2xl font-bold text-slate-900 mb-2">Quiz Completed!</h2>
                                <div class="text-5xl font-black text-indigo-600 my-4"><?= round(($score / $_GET['total']) * 100) ?>%</div>
                                <p class="text-slate-500 font-bold mb-8">You scored <?= $score ?> out of <?= $_GET['total'] ?></p>
                                <a href="dashboard.php" class="btn btn-outline border-slate-200 text-slate-600 hover:bg-slate-50 hover:text-slate-900 w-full rounded-xl">Return to Dashboard</a>
                            </div>

                        <?php elseif(!$started): ?>
                            <div class="content-card text-center max-w-md w-full">
                                <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center mx-auto mb-6">
                                    <i data-lucide="help-circle" class="w-8 h-8"></i>
                                </div>
                                <h2 class="text-xl font-bold text-slate-900 mb-2"><?= htmlspecialchars($current_item['title']) ?></h2>
                                <p class="text-slate-500 text-sm mb-8">Test your knowledge. Good luck!</p>
                                <a href="course_player.php?course_id=<?= $course_id ?>&quiz_id=<?= $current_item['id'] ?>&started=1" class="btn bg-indigo-600 hover:bg-indigo-700 text-white border-none w-full rounded-xl h-12 shadow-lg shadow-indigo-200">Start Quiz</a>
                            </div>

                        <?php else: ?>
                            <div class="content-card w-full max-w-2xl">
                                <form method="POST" action="submit_quiz.php">
                                    <input type="hidden" name="course_id" value="<?= $course_id ?>">
                                    
                                    <?php foreach($quiz_questions as $idx => $q): ?>
                                        <div class="question-step <?= $idx === 0 ? '' : 'hidden' ?>" id="q-step-<?= $idx ?>">
                                            <div class="flex justify-between items-center mb-6">
                                                <span class="text-xs font-black text-slate-400 uppercase tracking-widest">Question <?= $idx + 1 ?> / <?= count($quiz_questions) ?></span>
                                            </div>
                                            <h3 class="text-xl font-bold text-slate-900 mb-8 leading-snug"><?= htmlspecialchars($q['question_text']) ?></h3>
                                            
                                            <div class="space-y-3 mb-10">
                                                <?php foreach(['A','B','C','D'] as $opt): $val = $q['option_'.strtolower($opt)]; if(empty($val)) continue; ?>
                                                    <label class="quiz-option">
                                                        <input type="radio" name="q<?= $q['id'] ?>" value="<?= $opt ?>" class="radio radio-sm radio-primary" required />
                                                        <span class="text-slate-600 font-medium text-sm"><?= htmlspecialchars($val) ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>

                                            <div class="flex justify-between pt-6 border-t border-slate-50">
                                                <?php if($idx > 0): ?>
                                                    <button type="button" class="btn btn-ghost text-slate-400 hover:text-slate-900" onclick="changeQuestion(<?= $idx - 1 ?>)">Back</button>
                                                <?php else: ?><div></div><?php endif; ?>
                                                
                                                <?php if($idx < count($quiz_questions)-1): ?>
                                                    <button type="button" class="btn btn-sm bg-slate-900 hover:bg-slate-700 text-white rounded-lg px-6" onclick="changeQuestion(<?= $idx + 1 ?>)">Next</button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn btn-sm bg-emerald-500 hover:bg-emerald-600 text-white border-none rounded-lg px-6 shadow-lg shadow-emerald-200">Submit</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
        function toggleFullscreen() { document.body.classList.toggle('fullscreen-mode'); }
        function changeQuestion(index) {
            document.querySelectorAll('.question-step').forEach(el => el.classList.add('hidden'));
            document.getElementById('q-step-' + index).classList.remove('hidden');
        }
    </script>
</body>
</html>