<?php
// student/dashboard.php
require '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Learner';
$search = $_GET['search'] ?? '';

// Points Logic
$pt_stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress WHERE user_id = ?");
$pt_stmt->execute([$user_id]);
$completed_count = $pt_stmt->fetchColumn();
$user_points = $completed_count * 10;

$user_badge = 'Newbie';
if ($user_points >= 120) $user_badge = 'Master';
elseif ($user_points >= 100) $user_badge = 'Expert';
elseif ($user_points >= 80) $user_badge = 'Specialist';
elseif ($user_points >= 60) $user_badge = 'Achiever';
elseif ($user_points >= 40) $user_badge = 'Explorer';

// Fetch Courses
$sql = "SELECT * FROM courses WHERE 1=1"; 
$params = [];
if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check Enrollment
$enrolled_courses_map = [];
try {
    $enroll_stmt = $pdo->prepare("SELECT course_id, status FROM enrollments WHERE student_id = ?");
    $enroll_stmt->execute([$user_id]);
} catch (PDOException $e) {
    $enroll_stmt = $pdo->prepare("SELECT course_id, status FROM enrollments WHERE user_id = ?");
    $enroll_stmt->execute([$user_id]);
}
while ($row = $enroll_stmt->fetch(PDO::FETCH_ASSOC)) {
    $enrolled_courses_map[$row['course_id']] = $row['status'];
}

$display_courses = [];
foreach ($all_courses as $course) {
    $course_id = $course['id'];
    $is_enrolled = array_key_exists($course_id, $enrolled_courses_map);
    $course['is_enrolled'] = $is_enrolled;
    $course['tags'] = ['Web Dev', 'Beginner'];
    $display_courses[] = $course;
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - LearnSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0f172a; color: white; }
        .glass-panel { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); }
        .ribbon { position: absolute; top: 1rem; right: -2rem; transform: rotate(45deg); padding: 0.25rem 2.5rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; box-shadow: 0 2px 4px rgba(0,0,0,0.1); z-index: 10; }
        .ribbon-paid { background-color: #10b981; color: white; }
        .ribbon-draft { background-color: #64748b; color: white; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <nav class="border-b border-white/10 bg-slate-900/80 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold text-xl">LS</div>
                <span class="text-xl font-bold text-white">LearnSphere</span>
            </div>
            <div class="flex items-center gap-4 flex-1 justify-end">
                <form method="GET" class="relative w-full max-w-xs hidden md:block">
                    <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search courses..." class="input input-sm w-full pl-9 bg-slate-800 border-white/10 text-white rounded-full focus:border-indigo-500">
                </form>
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

    <main class="max-w-7xl mx-auto px-6 py-10 flex-1 w-full">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <div class="lg:col-span-8 xl:col-span-9 space-y-8">
                <div class="flex items-center justify-between">
                    <h1 class="text-3xl font-bold text-white"><?= !empty($search) ? 'Search Results' : 'Explore Courses' ?></h1>
                    <?php if(!empty($search)): ?><a href="dashboard.php" class="btn btn-sm btn-ghost text-indigo-400">Clear</a><?php endif; ?>
                </div>

                <?php if (count($display_courses) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        <?php foreach ($display_courses as $course): ?>
                            <?php 
                                $is_paid = ($course['price'] > 0);
                                $is_draft = ($course['is_published'] == 0);
                            ?>
                            <div class="glass-panel rounded-2xl overflow-hidden relative group hover:border-indigo-500/50 transition-colors">
                                <div class="relative h-48 overflow-hidden">
                                    <img src="../<?= htmlspecialchars($course['thumbnail'] ?: 'assets/default.png') ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    <?php if ($is_draft): ?><div class="ribbon ribbon-draft">Draft</div><?php elseif ($is_paid && !$course['is_enrolled']): ?><div class="ribbon ribbon-paid">Paid</div><?php endif; ?>
                                </div>
                                <div class="p-5 flex flex-col h-48">
                                    <div class="flex flex-wrap gap-2 mb-3">
                                        <?php foreach ($course['tags'] as $tag): ?>
                                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase bg-indigo-500/10 text-indigo-300 rounded-full border border-indigo-500/20"><?= htmlspecialchars($tag) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <h3 class="text-lg font-bold text-white mb-2 line-clamp-1"><?= htmlspecialchars($course['title']) ?></h3>
                                    <div class="mt-auto pt-4 border-t border-white/5 flex items-center justify-between">
                                        <?php if ($course['is_enrolled']): ?>
                                            <span class="text-xs font-bold text-emerald-400 bg-emerald-400/10 px-2 py-1 rounded">Enrolled</span>
                                            <a href="course_player.php?course_id=<?= $course['id'] ?>" class="btn btn-sm btn-primary bg-indigo-600 border-none hover:bg-indigo-700">Continue</a>
                                        <?php else: ?>
                                            <span class="text-white font-bold"><?= $is_paid ? 'INR '.number_format($course['price']) : 'Free' ?></span>
                                            <a href="enroll.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-outline text-indigo-300 border-indigo-500/50 hover:bg-indigo-600 hover:border-indigo-600 hover:text-white">Join</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-20 glass-panel rounded-2xl"><i data-lucide="search-x" class="w-12 h-12 text-slate-600 mx-auto mb-4"></i><h3 class="text-xl font-bold text-slate-300">No courses found</h3></div>
                <?php endif; ?>
            </div>
            
            <div class="lg:col-span-4 xl:col-span-3 space-y-6">
                <div class="glass-panel rounded-2xl p-6 sticky top-24">
                    <h2 class="text-lg font-bold text-white mb-6">My Progress</h2>
                    <div class="flex flex-col items-center mb-8">
                        <div class="relative flex items-center justify-center">
                            <?php $progress_percentage = min(($user_points / 120) * 100, 100); ?>
                            <div class="radial-progress text-slate-700 absolute inset-0" style="--value:100; --size:9rem; --thickness: 8px;"></div>
                            <div class="radial-progress text-indigo-500" style="--value:<?= $progress_percentage ?>; --size:9rem; --thickness: 8px;">
                                <div class="flex flex-col items-center text-center z-10 bg-slate-900 rounded-full w-[7.5rem] h-[7.5rem] justify-center border border-white/5">
                                    <span class="text-slate-400 text-[10px] font-bold uppercase mb-0.5">Total Points</span>
                                    <span class="text-4xl font-black text-white mb-0.5"><?= $user_points ?></span>
                                    <span class="text-indigo-300 font-bold text-sm bg-indigo-500/10 px-2 py-0.5 rounded-full border border-indigo-500/20"><?= $user_badge ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-500 uppercase mb-4 border-b border-white/5 pb-2">Badges</h3>
                        <ul class="space-y-3">
                            <?php $badges = ['Newbie' => 20, 'Explorer' => 40, 'Achiever' => 60, 'Specialist' => 80];
                            foreach ($badges as $badge_name => $points_req): $is_earned = $user_points >= $points_req; ?>
                            <li class="flex items-center justify-between p-2.5 rounded-xl transition-colors <?= $is_earned ? 'bg-indigo-500/10 border border-indigo-500/20' : 'opacity-30' ?>">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center <?= $is_earned ? 'bg-indigo-500 text-white' : 'bg-slate-700 text-slate-400' ?>"><i data-lucide="<?= $is_earned ? 'award' : 'lock' ?>" class="w-4 h-4"></i></div>
                                    <span class="text-sm font-bold text-slate-200"><?= $badge_name ?></span>
                                </div>
                                <span class="text-xs font-bold text-indigo-400"><?= $points_req ?> pts</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script> lucide.createIcons(); </script>
</body>
</html>