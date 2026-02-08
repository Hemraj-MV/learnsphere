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
    // Fallback if column differs
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
    // Mock tags if empty
    $course['tags'] = !empty($course['tags']) ? explode(',', $course['tags']) : ['General'];
    $display_courses[] = $course;
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - LearnSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    <style>
        /* INSTRUCTOR THEME (Light Mode) */
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: linear-gradient(135deg, #f0f4ff 0%, #eef2f6 100%); color: #1e293b; min-height: 100vh; }
        h1, h2, h3, .heading-font { font-family: 'Outfit', sans-serif; letter-spacing: -0.02em; }

        /* Premium Card Style (Replaces glass-panel) */
        .premium-card { 
            background: #ffffff; 
            border: 1px solid white; 
            border-radius: 1.5rem; 
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.08); 
            transition: all 0.3s ease-out; 
        }
        .premium-card:hover { transform: translateY(-4px); box-shadow: 0 20px 50px -10px rgba(37, 99, 235, 0.1); }

        /* Navbar Style */
        .premium-nav { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255, 255, 255, 0.5); box-shadow: 0 4px 20px -5px rgba(0, 0, 0, 0.05); }

        /* Ribbons */
        .ribbon { position: absolute; top: 1rem; right: -2rem; transform: rotate(45deg); padding: 0.25rem 2.5rem; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; box-shadow: 0 2px 4px rgba(0,0,0,0.1); z-index: 10; letter-spacing: 0.05em; }
        .ribbon-paid { background-color: #10b981; color: white; }
        .ribbon-draft { background-color: #94a3b8; color: white; }

        /* Search Input */
        .search-input { background: white; border: 2px solid #e2e8f0; color: #334155; }
        .search-input:focus { border-color: #6366f1; outline: none; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <nav class="premium-nav sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-black rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-lg">LS</div>
                <span class="heading-font font-bold text-2xl text-slate-900 tracking-tight">LearnSphere</span>
            </div>
            <div class="flex items-center gap-4 flex-1 justify-end">
                <form method="GET" class="relative w-full max-w-xs hidden md:block">
                    <i data-lucide="search" class="absolute left-3 top-3 w-4 h-4 text-slate-400"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search courses..." class="search-input input input-sm w-full pl-9 h-10 rounded-xl shadow-sm font-medium">
                </form>
                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar border-2 border-white shadow-sm">
                        <div class="bg-indigo-600 text-white rounded-full w-10 flex items-center justify-center font-bold text-xs"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
                    </div>
                    <ul tabindex="0" class="mt-3 z-[1] p-2 shadow-2xl menu menu-sm dropdown-content bg-white border border-white rounded-2xl w-52 text-slate-700">
                        <li class="px-4 py-2"><span class="font-bold text-slate-900"><?= htmlspecialchars($user_name) ?></span></li>
                        <div class="divider my-0 opacity-50"></div>
                        <li><a href="../logout.php" class="text-red-500 font-bold hover:bg-red-50"><i data-lucide="log-out" class="w-4 h-4"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-10 flex-1 w-full">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <div class="lg:col-span-8 xl:col-span-9 space-y-8">
                <div class="flex items-center justify-between">
                    <h1 class="text-3xl heading-font font-bold text-slate-900"><?= !empty($search) ? 'Search Results' : 'Explore Courses' ?></h1>
                    <?php if(!empty($search)): ?><a href="dashboard.php" class="btn btn-sm btn-ghost text-indigo-600 font-bold">Clear Filter</a><?php endif; ?>
                </div>

                <?php if (count($display_courses) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        <?php foreach ($display_courses as $course): ?>
                            <?php 
                                $is_paid = ($course['price'] > 0);
                                $is_draft = ($course['is_published'] == 0);
                            ?>
                            <div class="premium-card rounded-2xl overflow-hidden relative group">
                                <div class="relative h-48 overflow-hidden bg-slate-100">
                                    <img src="../<?= htmlspecialchars($course['image'] ?: 'assets/default_course.jpg') ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    
                                    <?php if ($is_draft): ?>
                                        <div class="ribbon ribbon-draft">Draft</div>
                                    <?php elseif ($is_paid && !$course['is_enrolled']): ?>
                                        <div class="ribbon ribbon-paid">Paid</div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="p-5 flex flex-col h-56">
                                    <div class="flex flex-wrap gap-2 mb-3">
                                        <?php foreach ($course['tags'] as $tag): $tag = trim($tag); if(empty($tag)) continue; ?>
                                            <span class="px-2 py-1 text-[10px] font-bold uppercase bg-indigo-50 text-indigo-600 rounded-lg border border-indigo-100"><?= htmlspecialchars($tag) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <h3 class="text-lg font-bold text-slate-800 mb-2 line-clamp-2 leading-tight"><?= htmlspecialchars($course['title']) ?></h3>
                                    
                                    <div class="mt-auto pt-4 border-t border-slate-50 flex items-center justify-between">
                                        <?php if ($course['is_enrolled']): ?>
                                            <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-lg border border-emerald-100 flex items-center gap-1"><i data-lucide="check-circle" class="w-3 h-3"></i> Enrolled</span>
                                            <a href="course_player.php?course_id=<?= $course['id'] ?>" class="btn btn-sm bg-indigo-600 hover:bg-indigo-700 text-white border-none rounded-lg shadow-md shadow-indigo-200">Continue</a>
                                        <?php else: ?>
                                            <span class="text-slate-900 font-bold"><?= $is_paid ? '$'.number_format($course['price']) : 'Free' ?></span>
                                            <a href="../course_details.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-outline border-slate-200 text-slate-600 hover:bg-slate-50 hover:text-indigo-600 rounded-lg">Join</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-24 premium-card">
                        <i data-lucide="search-x" class="w-12 h-12 text-slate-300 mx-auto mb-4"></i>
                        <h3 class="text-xl font-bold text-slate-900">No courses found</h3>
                        <p class="text-slate-500">Try adjusting your search terms.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="lg:col-span-4 xl:col-span-3 space-y-6">
                <div class="premium-card p-6 sticky top-28">
                    <h2 class="text-lg heading-font font-bold text-slate-900 mb-6 flex items-center gap-2">
                        <i data-lucide="trophy" class="w-5 h-5 text-yellow-500"></i> My Progress
                    </h2>
                    
                    <div class="flex flex-col items-center mb-8">
                        <div class="relative flex items-center justify-center">
                            <?php $progress_percentage = min(($user_points / 120) * 100, 100); ?>
                            <div class="radial-progress text-slate-100 absolute inset-0" style="--value:100; --size:9rem; --thickness: 8px;"></div>
                            <div class="radial-progress text-indigo-600" style="--value:<?= $progress_percentage ?>; --size:9rem; --thickness: 8px;">
                                <div class="flex flex-col items-center text-center z-10 bg-white rounded-full w-[7.5rem] h-[7.5rem] justify-center shadow-sm">
                                    <span class="text-slate-400 text-[10px] font-bold uppercase mb-0.5">Total Points</span>
                                    <span class="text-4xl font-black text-slate-900 mb-0.5"><?= $user_points ?></span>
                                    <span class="text-indigo-600 font-bold text-xs bg-indigo-50 px-2 py-0.5 rounded-full border border-indigo-100"><?= $user_badge ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4 border-b border-slate-50 pb-2">Badges</h3>
                        <ul class="space-y-3">
                            <?php $badges = ['Newbie' => 20, 'Explorer' => 40, 'Achiever' => 60, 'Specialist' => 80];
                            foreach ($badges as $badge_name => $points_req): $is_earned = $user_points >= $points_req; ?>
                            <li class="flex items-center justify-between p-3 rounded-xl transition-colors <?= $is_earned ? 'bg-indigo-50 border border-indigo-100' : 'bg-slate-50 opacity-60 grayscale' ?>">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center shadow-sm <?= $is_earned ? 'bg-white text-indigo-600' : 'bg-slate-200 text-slate-400' ?>">
                                        <i data-lucide="<?= $is_earned ? 'award' : 'lock' ?>" class="w-4 h-4"></i>
                                    </div>
                                    <span class="text-sm font-bold <?= $is_earned ? 'text-indigo-900' : 'text-slate-500' ?>"><?= $badge_name ?></span>
                                </div>
                                <span class="text-[10px] font-bold uppercase <?= $is_earned ? 'text-indigo-400' : 'text-slate-400' ?>"><?= $points_req ?> pts</span>
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