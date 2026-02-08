<?php
// course_details.php (COMPLETE VERSION)
require 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$course_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$course_id) { header("Location: index.php"); exit; }

// --- 1. HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(!$user_id) { header("Location: login.php"); exit; }

    // Enroll Action
    if (isset($_POST['join_now'])) {
        $check = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
        $check->execute([$user_id, $course_id]);
        if (!$check->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, enrolled_at, status, progress) VALUES (?, ?, NOW(), 'yet_to_start', 0)");
            $stmt->execute([$user_id, $course_id]);
        }
        header("Location: student/course_player.php?course_id=" . $course_id);
        exit;
    }

    // Add Review Action (PDF B4)
    if (isset($_POST['submit_review'])) {
        $rating = $_POST['rating'];
        $comment = trim($_POST['review_text']);
        
        $stmt = $pdo->prepare("INSERT INTO course_reviews (course_id, user_id, rating, review_text) VALUES (?, ?, ?, ?)");
        $stmt->execute([$course_id, $user_id, $rating, $comment]);
        header("Location: course_details.php?id=" . $course_id . "&review_posted=1");
        exit;
    }
}

// --- 2. FETCH COURSE DATA ---
$stmt = $pdo->prepare("SELECT c.*, u.name as instructor_name FROM courses c JOIN users u ON c.instructor_id = u.id WHERE c.id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();
if (!$course) die("Course not found.");

// Fetch Lessons
$stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY position ASC");
$stmt->execute([$course_id]);
$lessons = $stmt->fetchAll();

// Fetch Reviews
$stmt = $pdo->prepare("SELECT r.*, u.name as user_name FROM course_reviews r JOIN users u ON r.user_id = u.id WHERE r.course_id = ? ORDER BY r.created_at DESC");
$stmt->execute([$course_id]);
$reviews = $stmt->fetchAll();

// Calculate Average Rating
$avg_rating = 0;
if (count($reviews) > 0) {
    $sum = 0;
    foreach($reviews as $r) $sum += $r['rating'];
    $avg_rating = round($sum / count($reviews), 1);
}

// --- 3. USER STATUS & PROGRESS ---
$is_enrolled = false;
$user_progress = 0;
$completed_lesson_ids = [];

if($user_id) {
    // Check Enrollment
    $en_stmt = $pdo->prepare("SELECT progress FROM enrollments WHERE student_id = ? AND course_id = ?");
    $en_stmt->execute([$user_id, $course_id]);
    $enrollment = $en_stmt->fetch();
    
    if ($enrollment) {
        $is_enrolled = true;
        $user_progress = $enrollment['progress'];
        
        // Fetch Completed Lessons (for ticks)
        $lp_stmt = $pdo->prepare("SELECT lesson_id FROM lesson_progress WHERE user_id = ?");
        $lp_stmt->execute([$user_id]);
        $completed_lesson_ids = $lp_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Stats
$video_count = 0; $quiz_count = 0; $doc_count = 0;
foreach($lessons as $l) {
    if($l['type'] == 'video') $video_count++; elseif($l['type'] == 'quiz') $quiz_count++; else $doc_count++;
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($course['title']) ?> - Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; color: #1e293b; }
        h1, h2, h3, .heading-font { font-family: 'Outfit', sans-serif; }
    </style>
</head>
<body class="bg-slate-50">

    <nav class="bg-white border-b border-slate-200 h-20 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 h-full flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-black rounded-xl flex items-center justify-center text-white font-bold text-xl">LS</div>
                <span class="heading-font font-bold text-2xl">LearnSphere</span>
            </div>
            <div>
                <?php if($user_id): ?>
                    <a href="student/dashboard.php" class="btn btn-sm btn-ghost">Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-sm btn-primary">Log in</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-8 grid grid-cols-1 lg:grid-cols-3 gap-10">
        
        <div class="lg:col-span-2 space-y-8">
            
            <div class="bg-white rounded-3xl overflow-hidden border border-slate-200 shadow-sm">
                <div class="h-80 bg-slate-200 relative">
                    <img src="<?= htmlspecialchars($course['image'] ?: 'assets/default_course.jpg') ?>" class="w-full h-full object-cover">
                </div>
                <div class="p-8">
                    <h1 class="text-4xl font-bold text-slate-900 mb-4"><?= htmlspecialchars($course['title']) ?></h1>
                    
                    <div class="flex items-center gap-6 text-sm font-bold text-slate-500 mb-6">
                        <span class="flex items-center gap-2"><i data-lucide="user" class="w-4 h-4"></i> <?= htmlspecialchars($course['instructor_name']) ?></span>
                        <span class="flex items-center gap-2"><i data-lucide="clock" class="w-4 h-4"></i> <?= htmlspecialchars($course['duration']) ?></span>
                        <span class="flex items-center gap-2 text-orange-500"><i data-lucide="star" class="w-4 h-4 fill-current"></i> <?= $avg_rating ?> (<?= count($reviews) ?>)</span>
                    </div>

                    <?php if($is_enrolled): ?>
                        <div class="mb-6 p-4 bg-indigo-50 rounded-xl border border-indigo-100">
                            <div class="flex justify-between text-xs font-bold uppercase text-indigo-600 mb-2">
                                <span>Your Progress</span>
                                <span><?= $user_progress ?>%</span>
                            </div>
                            <progress class="progress progress-primary w-full" value="<?= $user_progress ?>" max="100"></progress>
                        </div>
                    <?php endif; ?>

                    <div class="prose max-w-none text-slate-600">
                        <?= nl2br(htmlspecialchars($course['description'] ?? '')) ?>
                    </div>
                </div>
            </div>

            <div role="tablist" class="tabs tabs-lifted tabs-lg">
                
                <input type="radio" name="my_tabs_2" role="tab" class="tab font-bold" aria-label="Course Content" checked />
                <div role="tabpanel" class="tab-content bg-white border-base-300 rounded-box p-8">
                    <div class="space-y-3">
                        <?php if(empty($lessons)): ?>
                            <p class="text-slate-400 italic">No lessons available yet.</p>
                        <?php else: foreach($lessons as $idx => $l): 
                            $is_done = in_array($l['id'], $completed_lesson_ids);
                        ?>
                        <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl group hover:bg-slate-100 transition">
                            <div class="flex items-center gap-4">
                                <span class="w-8 h-8 rounded-lg bg-white border border-slate-200 flex items-center justify-center font-bold text-xs text-slate-400">
                                    <?php if($is_done): ?>
                                        <i data-lucide="check" class="w-4 h-4 text-emerald-500"></i> <?php else: ?>
                                        <?= $idx+1 ?>
                                    <?php endif; ?>
                                </span>
                                <div>
                                    <div class="font-bold text-slate-700 group-hover:text-indigo-600"><?= htmlspecialchars($l['title']) ?></div>
                                    <div class="text-[10px] font-bold text-slate-400 uppercase"><?= $l['duration'] ?></div>
                                </div>
                            </div>
                            
                            <?php if($is_enrolled): ?>
                                <a href="student/course_player.php?course_id=<?= $course_id ?>&lesson_id=<?= $l['id'] ?>" class="btn btn-xs btn-ghost">Play</a>
                            <?php else: ?>
                                <i data-lucide="lock" class="w-4 h-4 text-slate-300"></i>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <input type="radio" name="my_tabs_2" role="tab" class="tab font-bold" aria-label="Reviews (<?= count($reviews) ?>)" />
                <div role="tabpanel" class="tab-content bg-white border-base-300 rounded-box p-8">
                    
                    <?php if($is_enrolled): ?>
                        <form method="POST" class="mb-8 p-6 bg-slate-50 rounded-2xl border border-slate-100">
                            <h4 class="font-bold text-slate-900 mb-4">Write a Review</h4>
                            <div class="rating rating-sm mb-4">
                                <input type="radio" name="rating" value="1" class="mask mask-star-2 bg-orange-400" />
                                <input type="radio" name="rating" value="2" class="mask mask-star-2 bg-orange-400" />
                                <input type="radio" name="rating" value="3" class="mask mask-star-2 bg-orange-400" />
                                <input type="radio" name="rating" value="4" class="mask mask-star-2 bg-orange-400" />
                                <input type="radio" name="rating" value="5" class="mask mask-star-2 bg-orange-400" checked />
                            </div>
                            <textarea name="review_text" class="textarea textarea-bordered w-full bg-white mb-4" placeholder="How was the course?"></textarea>
                            <button type="submit" name="submit_review" class="btn btn-sm bg-slate-900 text-white">Post Review</button>
                        </form>
                    <?php endif; ?>

                    <div class="space-y-6">
                        <?php if(empty($reviews)): ?>
                            <p class="text-slate-400 italic">No reviews yet.</p>
                        <?php else: foreach($reviews as $r): ?>
                            <div class="border-b border-slate-50 pb-6 last:border-0">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="font-bold text-slate-900 flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs">
                                            <?= strtoupper(substr($r['user_name'],0,1)) ?>
                                        </div>
                                        <?= htmlspecialchars($r['user_name']) ?>
                                    </div>
                                    <span class="text-xs text-slate-400"><?= date('M d, Y', strtotime($r['created_at'])) ?></span>
                                </div>
                                <div class="rating rating-xs disabled mb-2 pointer-events-none">
                                    <?php for($i=0; $i<5; $i++): ?>
                                        <input type="radio" class="mask mask-star-2 bg-orange-400" <?= ($i < $r['rating']) ? 'checked' : '' ?> />
                                    <?php endfor; ?>
                                </div>
                                <p class="text-slate-600 text-sm"><?= nl2br(htmlspecialchars($r['review_text'])) ?></p>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <div class="lg:col-span-1">
            <div class="bg-white rounded-3xl border border-slate-200 shadow-lg p-8 sticky top-28">
                <div class="text-4xl font-black text-slate-900 mb-2">
                    <?= ($course['price'] > 0) ? '$'.number_format($course['price'], 2) : 'Free' ?>
                </div>
                <p class="text-slate-400 font-bold text-sm mb-8">Lifetime access</p>

                <?php if($is_enrolled): ?>
                    <a href="student/course_player.php?course_id=<?= $course_id ?>" class="btn btn-block bg-slate-900 text-white hover:bg-slate-800 h-12 rounded-xl shadow-lg">
                        Continue Learning
                    </a>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="join_now" value="1">
                        <button type="submit" class="btn btn-block bg-indigo-600 text-white hover:bg-indigo-700 h-12 rounded-xl shadow-xl shadow-indigo-200">
                            <?= ($course['price'] > 0) ? 'Buy Now' : 'Enroll Now' ?>
                        </button>
                    </form>
                    <p class="text-center text-xs text-slate-400 mt-4">30-Day Money-Back Guarantee</p>
                <?php endif; ?>
                
                <div class="mt-8 pt-8 border-t border-slate-100 space-y-4">
                    <div class="flex items-center gap-3 text-sm font-bold text-slate-600"><i data-lucide="play-circle" class="w-5 h-5 text-indigo-500"></i> <?= $video_count ?> Videos</div>
                    <div class="flex items-center gap-3 text-sm font-bold text-slate-600"><i data-lucide="file-text" class="w-5 h-5 text-indigo-500"></i> <?= $doc_count ?> Articles</div>
                    <div class="flex items-center gap-3 text-sm font-bold text-slate-600"><i data-lucide="help-circle" class="w-5 h-5 text-indigo-500"></i> <?= $quiz_count ?> Quizzes</div>
                </div>
            </div>
        </div>

    </main>
    <script> lucide.createIcons(); </script>
</body>
</html>