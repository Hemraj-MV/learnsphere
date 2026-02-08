<?php
// student/enroll.php
require '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. AUTHENTICATION
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$course_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Learner';

if (!$course_id) { header("Location: dashboard.php"); exit; }

// 2. HANDLE ENROLLMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_now'])) {
    $check = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
    $check->execute([$user_id, $course_id]);
    
    if (!$check->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, enrolled_at, status, progress) VALUES (?, ?, NOW(), 'yet_to_start', 0)");
        $stmt->execute([$user_id, $course_id]);
    }
    header("Location: course_player.php?course_id=" . $course_id);
    exit;
}

// 3. FETCH DATA
$stmt = $pdo->prepare("SELECT c.*, u.name as instructor_name FROM courses c JOIN users u ON c.instructor_id = u.id WHERE c.id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) die("Course not found.");

$stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY position ASC");
$stmt->execute([$course_id]);
$lessons = $stmt->fetchAll();

// 4. CHECK STATUS
$is_enrolled = false;
$check = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
$check->execute([$user_id, $course_id]);
if ($check->fetch()) $is_enrolled = true;

// Stats for UI
$video_count = 0; $quiz_count = 0; $doc_count = 0;
foreach($lessons as $l) {
    if($l['type'] == 'video') $video_count++;
    elseif($l['type'] == 'quiz') $quiz_count++;
    else $doc_count++;
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
        /* THEME DNA */
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: linear-gradient(135deg, #f0f4ff 0%, #eef2f6 100%); color: #1e293b; min-height: 100vh; }
        h1, h2, h3, .heading-font { font-family: 'Outfit', sans-serif; letter-spacing: -0.02em; }

        /* HEADER & CARDS */
        .premium-nav { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255, 255, 255, 0.5); box-shadow: 0 4px 20px -5px rgba(0, 0, 0, 0.05); }
        .premium-card { background: #ffffff; border: 1px solid white; border-radius: 1.5rem; box-shadow: 0 10px 40px -10px rgba(0,0,0,0.08); overflow: hidden; transition: all 0.3s ease-out; }
        
        /* SYLLABUS LIST */
        .syllabus-item { padding: 16px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; transition: 0.2s; }
        .syllabus-item:hover { background: #f8fafc; }
        .syllabus-item:last-child { border-bottom: none; }
        
        .icon-box { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; }
        .icon-video { background: #e0e7ff; color: #4f46e5; }
        .icon-quiz { background: #ffedd5; color: #f97316; }
        .icon-doc { background: #f1f5f9; color: #64748b; }

        .sticky-sidebar { position: sticky; top: 120px; }
    </style>
</head>
<body class="flex flex-col">

    <nav class="premium-nav h-20 sticky top-0 z-50 px-8 flex items-center justify-between">
        <div class="max-w-7xl mx-auto w-full flex justify-between items-center">
            <div class="flex items-center gap-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-black rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-lg">LS</div>
                    <span class="heading-font font-bold text-2xl text-slate-900 tracking-tight">LearnSphere</span>
                </div>
                <a href="dashboard.php" class="hidden md:flex items-center gap-2 text-slate-500 font-bold hover:text-indigo-600 transition">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Dashboard
                </a>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar border-2 border-white shadow-sm">
                        <div class="bg-indigo-600 text-white rounded-full w-10 flex items-center justify-center font-bold text-xs"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
                    </div>
                    <ul tabindex="0" class="mt-3 p-2 shadow-2xl menu menu-sm dropdown-content bg-white border border-white rounded-2xl w-52 text-slate-700">
                        <li class="px-4 py-2"><span class="font-bold text-slate-900"><?= htmlspecialchars($user_name) ?></span></li>
                        <div class="divider my-0 opacity-50"></div>
                        <li><a href="../logout.php" class="text-red-500 font-bold hover:bg-red-50"><i data-lucide="log-out" class="w-4 h-4"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto w-full p-8 pb-32">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            
            <div class="lg:col-span-2 space-y-8">
                
                <div class="premium-card relative h-80 group">
                    <img src="../<?= htmlspecialchars($course['image'] ?: 'assets/default_course.jpg') ?>" class="w-full h-full object-cover transition duration-700 group-hover:scale-105">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/20 to-transparent"></div>
                    <div class="absolute bottom-0 left-0 p-8 w-full">
                        <span class="bg-indigo-600 text-white px-3 py-1 rounded-lg text-[10px] font-bold uppercase tracking-widest shadow-lg mb-3 inline-block">Course</span>
                        <h1 class="text-4xl heading-font font-bold text-white mb-2 leading-tight"><?= htmlspecialchars($course['title']) ?></h1>
                        <div class="flex items-center gap-4 text-slate-200 text-sm font-medium">
                            <span class="flex items-center gap-1"><i data-lucide="user" class="w-4 h-4"></i> <?= htmlspecialchars($course['instructor_name']) ?></span>
                            <span class="flex items-center gap-1"><i data-lucide="clock" class="w-4 h-4"></i> <?= htmlspecialchars($course['duration']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="premium-card p-8">
                    <h3 class="heading-font text-xl font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <i data-lucide="book-open" class="w-5 h-5 text-indigo-500"></i> About this Course
                    </h3>
                    <div class="prose prose-slate max-w-none text-slate-600">
                        <?= nl2br(htmlspecialchars($course['description'] ?? 'No description provided.')) ?>
                    </div>
                </div>

                <div class="premium-card">
                    <div class="p-6 border-b border-slate-50 bg-slate-50/50 flex justify-between items-center">
                        <h3 class="heading-font text-lg font-bold text-slate-900">Curriculum</h3>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider"><?= count($lessons) ?> Modules</span>
                    </div>
                    
                    <div class="bg-white">
                        <?php if(count($lessons) > 0): ?>
                            <?php foreach($lessons as $index => $l): 
                                $iconClass = 'icon-doc'; $iconName = 'file-text';
                                if($l['type'] == 'video') { $iconClass = 'icon-video'; $iconName = 'play-circle'; }
                                if($l['type'] == 'quiz')  { $iconClass = 'icon-quiz';  $iconName = 'help-circle'; }
                            ?>
                                <div class="syllabus-item group">
                                    <div class="flex items-center gap-4">
                                        <div class="icon-box <?= $iconClass ?>">
                                            <?= $index + 1 ?>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-slate-700 text-sm group-hover:text-indigo-600 transition-colors">
                                                <?= htmlspecialchars($l['title']) ?>
                                            </h4>
                                            <span class="text-[10px] font-bold uppercase text-slate-400 flex items-center gap-1 mt-1">
                                                <i data-lucide="<?= $iconName ?>" class="w-3 h-3"></i> <?= ucfirst($l['type']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <span class="text-xs font-mono font-bold text-slate-400 bg-slate-50 px-2 py-1 rounded">
                                        <?= $l['duration'] ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-8 text-center text-slate-400 italic text-sm">No lessons yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-1">
                <div class="premium-card p-8 sticky-sidebar">
                    <div class="mb-8">
                        <span class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Price</span>
                        <div class="text-4xl font-black text-slate-900 tracking-tight flex items-baseline gap-2">
                            <?= ($course['price'] > 0) ? '$'.number_format($course['price'], 2) : 'Free' ?>
                            <?php if($course['price'] > 0): ?><span class="text-sm text-slate-400 line-through decoration-red-400">$<?= number_format($course['price']*1.2, 2) ?></span><?php endif; ?>
                        </div>
                    </div>

                    <?php if($is_enrolled): ?>
                        <div class="alert bg-emerald-50 text-emerald-700 border-emerald-100 rounded-xl mb-4 text-sm font-bold p-4 flex gap-2">
                            <i data-lucide="check-circle" class="w-5 h-5"></i> You are enrolled
                        </div>
                        <a href="course_player.php?course_id=<?= $course_id ?>" class="btn w-full bg-slate-900 hover:bg-slate-700 text-white border-none rounded-xl h-12 shadow-lg">
                            Go to Course <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                        </a>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="join_now" value="1">
                            <button type="submit" class="btn w-full bg-indigo-600 hover:bg-indigo-700 text-white border-none rounded-xl h-12 shadow-lg shadow-indigo-200">
                                <?= ($course['price'] > 0) ? 'Buy Now' : 'Enroll Now' ?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <div class="mt-8 pt-6 border-t border-slate-100 space-y-4">
                        <h4 class="text-[10px] font-black text-slate-900 uppercase tracking-widest">Included:</h4>
                        <div class="flex items-center gap-3 text-sm font-medium text-slate-600"><i data-lucide="monitor-play" class="w-4 h-4 text-indigo-500"></i> <?= $video_count ?> Video Lessons</div>
                        <div class="flex items-center gap-3 text-sm font-medium text-slate-600"><i data-lucide="file-text" class="w-4 h-4 text-indigo-500"></i> <?= $doc_count ?> Readings</div>
                        <div class="flex items-center gap-3 text-sm font-medium text-slate-600"><i data-lucide="trophy" class="w-4 h-4 text-indigo-500"></i> <?= $quiz_count ?> Quizzes</div>
                        <div class="flex items-center gap-3 text-sm font-medium text-slate-600"><i data-lucide="infinity" class="w-4 h-4 text-indigo-500"></i> Lifetime Access</div>
                        <div class="flex items-center gap-3 text-sm font-medium text-slate-600"><i data-lucide="award" class="w-4 h-4 text-indigo-500"></i> Certificate</div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script> lucide.createIcons(); </script>
</body>
</html>