<?php
// lesson_view.php
require 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$lesson_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$lesson_id) {
    header("Location: index.php");
    exit;
}

// 1. FETCH LESSON & COURSE DETAILS
$stmt = $pdo->prepare("SELECT l.*, c.title as course_title, c.instructor_id, c.id as course_id 
                       FROM lessons l 
                       JOIN courses c ON l.course_id = c.id 
                       WHERE l.id = ?");
$stmt->execute([$lesson_id]);
$lesson = $stmt->fetch();

if (!$lesson) { die("Lesson not found."); }

$course_id = $lesson['course_id'];

// 2. CHECK PERMISSIONS
$can_access = false;
if ($user_id) {
    if ($_SESSION['role'] === 'admin' || $user_id == $lesson['instructor_id']) {
        $can_access = true;
    } else {
        $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE course_id = ? AND student_id = ?");
        $stmt->execute([$course_id, $user_id]);
        if ($stmt->fetch()) $can_access = true;
    }
}

if (!$can_access) {
    header("Location: course_details.php?id=" . $course_id);
    exit;
}

// 3. FETCH SIDEBAR LESSONS
$stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY position ASC");
$stmt->execute([$course_id]);
$all_lessons = $stmt->fetchAll();

// Find next lesson for navigation
$next_lesson_id = null;
$found = false;
foreach ($all_lessons as $l) {
    if ($found) { $next_lesson_id = $l['id']; break; }
    if ($l['id'] == $lesson_id) $found = true;
}

// 4. QUIZ LOGIC
$quiz_data = null;
$questions = [];
$quiz_submitted = false;
$score = 0;
$total_questions = 0;
$results = [];

if ($lesson['type'] === 'quiz') {
    // Fetch Quiz ID
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE lesson_id = ?");
    $stmt->execute([$lesson_id]);
    $quiz_data = $stmt->fetch();

    if ($quiz_data) {
        // Fetch Questions
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ?");
        $stmt->execute([$quiz_data['id']]);
        $questions = $stmt->fetchAll();
        $total_questions = count($questions);
    }

    // Handle Quiz Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
        $quiz_submitted = true;
        foreach ($questions as $q) {
            $user_answer = $_POST['q_' . $q['id']] ?? '';
            $is_correct = ($user_answer === $q['correct_option']);
            if ($is_correct) $score++;
            
            $results[$q['id']] = [
                'user_answer' => $user_answer,
                'correct_answer' => $q['correct_option'],
                'is_correct' => $is_correct
            ];
        }
        
        // Save Progress (Mark as complete if score > 0 or logic dictates)
        if($user_id) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO lesson_progress (user_id, lesson_id, completed_at) VALUES (?, ?, NOW())");
            $stmt->execute([$user_id, $lesson_id]);
        }
    }
} elseif ($user_id) {
    // Auto-mark non-quiz lessons as visited
    $stmt = $pdo->prepare("INSERT IGNORE INTO lesson_progress (user_id, lesson_id, completed_at) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $lesson_id]);
}

// Helper: YouTube Embed
function getYoutubeEmbedUrl($url) {
    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
    return isset($matches[1]) ? "https://www.youtube.com/embed/" . $matches[1] : $url;
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($lesson['title']) ?> - Classroom</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; color: #1e293b; height: 100vh; display: flex; overflow: hidden; }
        .sidebar { width: 350px; background: white; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; height: 100%; z-index: 20; }
        .lesson-item { padding: 1rem 1.5rem; border-bottom: 1px solid #f8fafc; transition: all 0.2s; cursor: pointer; display: flex; align-items: flex-start; gap: 12px; }
        .lesson-item:hover { background: #f1f5f9; }
        .lesson-item.active { background: #eef2ff; border-right: 3px solid #6366f1; }
        .lesson-item.active .lesson-title { color: #4f46e5; font-weight: 700; }
        .main-content { flex: 1; height: 100%; overflow-y: auto; position: relative; }
    </style>
</head>
<body>

    <aside class="sidebar hidden md:flex">
        <div class="p-6 border-b border-gray-100 bg-white sticky top-0 z-10">
            <a href="course_details.php?id=<?= $course_id ?>" class="text-xs font-bold text-slate-400 hover:text-indigo-600 flex items-center gap-2 mb-3 uppercase tracking-wider">
                <i data-lucide="arrow-left" class="w-3 h-3"></i> Back to Course
            </a>
            <h2 class="font-bold text-lg text-slate-900 leading-tight"><?= htmlspecialchars($lesson['course_title']) ?></h2>
        </div>
        <div class="flex-1 overflow-y-auto">
            <?php foreach($all_lessons as $l): ?>
                <a href="?id=<?= $l['id'] ?>" class="lesson-item <?= $l['id'] == $lesson_id ? 'active' : '' ?>">
                    <div class="mt-1">
                        <i data-lucide="<?= $l['type'] == 'video' ? 'play-circle' : ($l['type'] == 'quiz' ? 'help-circle' : 'file-text') ?>" class="w-4 h-4 <?= $l['id'] == $lesson_id ? 'text-indigo-500' : 'text-slate-400' ?>"></i>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-slate-700 lesson-title"><?= htmlspecialchars($l['title']) ?></div>
                        <div class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($l['duration']) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </aside>

    <main class="main-content bg-slate-50">
        
        <div class="md:hidden p-4 bg-white border-b border-gray-200 flex justify-between items-center sticky top-0 z-50">
            <a href="course_details.php?id=<?= $course_id ?>" class="btn btn-sm btn-ghost"><i data-lucide="arrow-left"></i></a>
            <span class="font-bold truncate"><?= htmlspecialchars($lesson['title']) ?></span>
        </div>

        <div class="max-w-5xl mx-auto p-6 lg:p-10">
            
            <h1 class="text-3xl font-bold text-slate-900 mb-6"><?= htmlspecialchars($lesson['title']) ?></h1>

            <?php if ($lesson['type'] == 'quiz'): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    
                    <?php if (!$quiz_data): ?>
                        <div class="p-10 text-center text-slate-500">Quiz data unavailable.</div>
                    
                    <?php elseif ($quiz_submitted): ?>
                        <div class="p-10 text-center">
                            <div class="w-20 h-20 bg-<?= $score == $total_questions ? 'green' : 'orange' ?>-100 rounded-full flex items-center justify-center text-<?= $score == $total_questions ? 'green' : 'orange' ?>-600 mx-auto mb-6">
                                <i data-lucide="<?= $score == $total_questions ? 'trophy' : 'check-circle' ?>" class="w-10 h-10"></i>
                            </div>
                            <h2 class="text-3xl font-bold text-slate-900 mb-2">Quiz Completed!</h2>
                            <p class="text-lg text-slate-600 mb-8">You scored <span class="font-bold text-indigo-600"><?= $score ?> / <?= $total_questions ?></span></p>
                            
                            <div class="text-left max-w-2xl mx-auto space-y-4">
                                <?php foreach($questions as $index => $q): 
                                    $res = $results[$q['id']];
                                    $is_correct = $res['is_correct'];
                                ?>
                                    <div class="p-4 rounded-xl border <?= $is_correct ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' ?>">
                                        <p class="font-bold text-slate-800 mb-2"><?= ($index+1) . ". " . htmlspecialchars($q['question_text']) ?></p>
                                        <p class="text-sm">
                                            Your Answer: <span class="font-bold"><?= $res['user_answer'] ?></span> 
                                            <?php if(!$is_correct): ?>
                                                <span class="text-red-500 ml-2">(Correct: <?= $q['correct_option'] ?>)</span>
                                            <?php else: ?>
                                                <span class="text-green-600 ml-2">✔ Correct</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-10">
                                <?php if($next_lesson_id): ?>
                                    <a href="?id=<?= $next_lesson_id ?>" class="btn btn-primary px-8 rounded-full">Continue to Next Lesson</a>
                                <?php else: ?>
                                    <a href="course_details.php?id=<?= $course_id ?>" class="btn btn-success text-white px-8 rounded-full">Finish Course</a>
                                <?php endif; ?>
                                <a href="?id=<?= $lesson_id ?>" class="btn btn-ghost ml-2">Retake Quiz</a>
                            </div>
                        </div>

                    <?php else: ?>
                        <form method="POST" class="p-8 lg:p-12">
                            <div class="mb-8 pb-6 border-b border-slate-100">
                                <h2 class="text-xl font-bold text-slate-800">Answer all questions</h2>
                                <p class="text-slate-500 text-sm">Select the best answer for each question below.</p>
                            </div>

                            <div class="space-y-8">
                                <?php foreach($questions as $index => $q): ?>
                                    <div class="bg-slate-50 p-6 rounded-xl border border-slate-200">
                                        <h3 class="font-bold text-lg text-slate-800 mb-4 flex gap-2">
                                            <span class="text-indigo-500"><?= $index + 1 ?>.</span> 
                                            <?= htmlspecialchars($q['question_text']) ?>
                                        </h3>
                                        <div class="space-y-3">
                                            <label class="flex items-center gap-3 p-3 bg-white border border-slate-200 rounded-lg cursor-pointer hover:border-indigo-300 transition">
                                                <input type="radio" name="q_<?= $q['id'] ?>" value="A" class="radio radio-xs radio-primary" required>
                                                <span class="text-sm text-slate-700">A) <?= htmlspecialchars($q['option_a']) ?></span>
                                            </label>
                                            <label class="flex items-center gap-3 p-3 bg-white border border-slate-200 rounded-lg cursor-pointer hover:border-indigo-300 transition">
                                                <input type="radio" name="q_<?= $q['id'] ?>" value="B" class="radio radio-xs radio-primary">
                                                <span class="text-sm text-slate-700">B) <?= htmlspecialchars($q['option_b']) ?></span>
                                            </label>
                                            <label class="flex items-center gap-3 p-3 bg-white border border-slate-200 rounded-lg cursor-pointer hover:border-indigo-300 transition">
                                                <input type="radio" name="q_<?= $q['id'] ?>" value="C" class="radio radio-xs radio-primary">
                                                <span class="text-sm text-slate-700">C) <?= htmlspecialchars($q['option_c']) ?></span>
                                            </label>
                                            <label class="flex items-center gap-3 p-3 bg-white border border-slate-200 rounded-lg cursor-pointer hover:border-indigo-300 transition">
                                                <input type="radio" name="q_<?= $q['id'] ?>" value="D" class="radio radio-xs radio-primary">
                                                <span class="text-sm text-slate-700">D) <?= htmlspecialchars($q['option_d']) ?></span>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-10 pt-6 border-t border-slate-100 flex justify-end">
                                <button type="submit" name="submit_quiz" class="btn btn-primary px-10 rounded-full shadow-lg shadow-indigo-200">Submit Quiz</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

            <?php elseif ($lesson['type'] == 'video'): ?>
                <div class="bg-black rounded-2xl overflow-hidden shadow-lg aspect-video mb-8">
                    <?php if (strpos($lesson['content_url'], 'uploads/') !== false): ?>
                        <video controls class="w-full h-full">
                            <source src="<?= htmlspecialchars($lesson['content_url']) ?>" type="video/mp4">
                        </video>
                    <?php else: ?>
                        <iframe src="<?= getYoutubeEmbedUrl($lesson['content_url']) ?>" class="w-full h-full" frameborder="0" allowfullscreen></iframe>
                    <?php endif; ?>
                </div>

            <?php elseif ($lesson['type'] == 'document'): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden h-[800px] mb-8">
                    <iframe src="<?= htmlspecialchars($lesson['content_url']) ?>" class="w-full h-full"></iframe>
                </div>

            <?php elseif ($lesson['type'] == 'image'): ?>
                <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 mb-8 flex justify-center">
                    <img src="<?= htmlspecialchars($lesson['content_url']) ?>" class="max-h-[600px] rounded-lg">
                </div>
            <?php endif; ?>

            <?php if($lesson['type'] !== 'quiz'): ?>
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-200">
                    <div class="flex justify-between items-start mb-6">
                        <h3 class="text-xl font-bold text-slate-800">About this lesson</h3>
                        <?php if($lesson['is_downloadable']): ?>
                            <a href="<?= htmlspecialchars($lesson['content_url']) ?>" download class="btn btn-sm btn-outline gap-2">
                                <i data-lucide="download" class="w-4 h-4"></i> Download
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="prose prose-slate text-slate-600">
                        <?= nl2br(htmlspecialchars($lesson['description'] ?? '')) ?>
                    </div>
                    
                    <div class="mt-8 pt-6 border-t border-slate-100 flex justify-end">
                        <?php if($next_lesson_id): ?>
                            <a href="?id=<?= $next_lesson_id ?>" class="btn btn-primary rounded-full px-8">Next Lesson <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i></a>
                        <?php else: ?>
                            <a href="course_details.php?id=<?= $course_id ?>" class="btn btn-success text-white rounded-full px-8">Complete Course</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>