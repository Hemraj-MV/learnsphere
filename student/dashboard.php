<?php
// student/dashboard.php
require '../includes/db.php';

// 1. Initialize Session and Security
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'learner') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Learner';
$search = $_GET['search'] ?? '';

// --- PLACEHOLDER DATA FOR PROFILE SIDEBAR ---
$user_points = 20; 
$user_badge = 'Newbie'; 
// --------------------------------------------

// 2. Fetch ALL Courses (Removed 'is_published = 1' filter so you can see Drafts)
$sql = "SELECT * FROM courses WHERE 1=1"; // Changed to select all
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

// 3. Fetch Enrolled Courses
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

// 4. Process Courses
$display_courses = [];
foreach ($all_courses as $course) {
    $course_id = $course['id'];
    $is_enrolled = array_key_exists($course_id, $enrolled_courses_map);
    
    $course['is_enrolled'] = $is_enrolled;
    $course['enrollment_status'] = $is_enrolled ? $enrolled_courses_map[$course_id] : null;
    $course['tags'] = ['Web Dev', 'Beginner']; // Placeholder tags

    $display_courses[] = $course;
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - LearnSphere</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: #f3f4f6;
            color: #1f2937;
        }
        .nav-border { border-bottom: 1px solid #e5e7eb; }
        .card-shadow { 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); 
            transition: transform 0.2s ease-in-out;
        }
        .card-shadow:hover { transform: translateY(-4px); }
        .sidebar-box {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            padding: 1.5rem;
        }
        /* Ribbon Styles */
        .ribbon {
            position: absolute;
            top: 1rem;
            right: -2rem;
            transform: rotate(45deg);
            padding: 0.25rem 2.5rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 10;
        }
        .ribbon-paid { background-color: #10b981; color: white; }
        .ribbon-draft { background-color: #6b7280; color: white; } /* Gray for Drafts */
        .avatar-initial { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <nav class="bg-white nav-border sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center gap-2">
                        <div class="w-9 h-9 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold text-xl shadow-md">LS</div>
                        <span class="text-xl font-bold tracking-tight text-gray-900">LearnSphere</span>
                    </div>
                    <div class="hidden md:ml-10 md:flex md:space-x-8">
                        <a href="dashboard.php" class="inline-flex items-center px-1 pt-1 border-b-2 border-indigo-500 text-sm font-semibold text-gray-900">My Courses</a>
                    </div>
                </div>
                
                <div class="flex items-center gap-4 flex-1 justify-end">
                    <form method="GET" action="dashboard.php" class="max-w-md w-full hidden md:block relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i data-lucide="search" class="w-5 h-5 text-gray-400"></i>
                        </div>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search courses..." class="input input-bordered w-full pl-10 rounded-full bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all">
                    </form>

                    <div class="dropdown dropdown-end ml-2">
                        <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar border border-gray-200 hover:bg-gray-100">
                            <div class="w-10 rounded-full avatar-initial flex items-center justify-center">
                                <span class="text-sm font-bold"><?= strtoupper(substr($user_name, 0, 1)) ?></span>
                            </div>
                        </div>
                        <ul tabindex="0" class="mt-3 p-2 shadow-lg menu dropdown-content bg-base-100 rounded-box w-56 border border-gray-100 z-[100]">
                            <li class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Account</li>
                            <li class="px-4 py-1 text-sm font-medium text-gray-900"><?= htmlspecialchars($user_name) ?></li>
                            <div class="divider my-1"></div>
                            <li><a href="../logout.php" class="text-red-600 font-medium"><i data-lucide="log-out" class="w-4 h-4"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 flex-1 w-full">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <div class="lg:col-span-8 xl:col-span-9 space-y-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 tracking-tight">
                            <?= !empty($search) ? 'Search Results' : 'Explore Courses' ?>
                        </h1>
                        <p class="mt-1 text-sm text-gray-500">
                            <?= !empty($search) ? 'Showing results for "'.htmlspecialchars($search).'"' : 'Discover new skills.' ?>
                        </p>
                    </div>
                    <?php if(!empty($search)): ?>
                        <a href="dashboard.php" class="btn btn-sm btn-ghost text-indigo-600"><i data-lucide="x" class="w-4 h-4"></i> Clear</a>
                    <?php endif; ?>
                </div>

                <?php if (count($display_courses) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        <?php foreach ($display_courses as $course): ?>
                            <?php 
                                $is_paid = ($course['price'] > 0);
                                $price_display = $is_paid ? 'INR ' . number_format($course['price']) : 'Free';
                                $is_draft = ($course['is_published'] == 0);
                            ?>
                            <div class="bg-white rounded-2xl card-shadow flex flex-col overflow-hidden relative group h-full border border-gray-100">
                                <div class="relative h-48 overflow-hidden bg-gray-200">
                                    <img src="../<?= htmlspecialchars($course['thumbnail'] ?: 'assets/default.png') ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    
                                    <?php if ($is_draft): ?>
                                        <div class="ribbon ribbon-draft">Draft</div>
                                    <?php elseif ($is_paid && !$course['is_enrolled']): ?>
                                        <div class="ribbon ribbon-paid">Paid</div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="p-5 flex flex-col flex-1">
                                    <div class="flex flex-wrap gap-2 mb-3">
                                        <?php foreach ($course['tags'] as $tag): ?>
                                            <span class="px-2.5 py-0.5 text-[10px] font-bold uppercase bg-indigo-50 text-indigo-700 rounded-full border border-indigo-100">
                                                <?= htmlspecialchars($tag) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900 mb-2 line-clamp-1"><?= htmlspecialchars($course['title']) ?></h3>
                                    <p class="text-sm text-gray-500 line-clamp-2 mb-4 flex-1"><?= htmlspecialchars($course['description']) ?></p>
                                    
                                    <div class="pt-4 mt-auto border-t border-gray-100 flex items-center justify-between">
                                        <?php if ($course['is_enrolled']): ?>
                                             <span class="text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded-md">Enrolled</span>
                                            <a href="course_player.php?course_id=<?= $course['id'] ?>" class="btn btn-primary btn-sm bg-indigo-600 border-none">Continue</a>
                                        <?php elseif ($is_paid): ?>
                                            <span class="font-bold text-gray-900 text-lg"><?= $price_display ?></span>
                                            <a href="buy.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-outline text-indigo-600">Buy Now</a>
                                        <?php else: ?>
                                            <span class="font-bold text-emerald-600 text-lg">Free</span>
                                            <a href="enroll.php?id=<?= $course['id'] ?>" class="btn btn-primary btn-sm bg-indigo-600 border-none">Join Now</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center py-20 bg-white rounded-2xl border-2 border-dashed border-gray-200 text-center">
                        <i data-lucide="search-x" class="w-10 h-10 text-gray-400 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-900">No courses found</h3>
                        <p class="text-gray-500">Try adjusting your search terms.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="lg:col-span-4 xl:col-span-3 space-y-6">
                <div class="sidebar-box sticky top-24">
                    <h2 class="text-lg font-bold text-gray-900 mb-6">My Progress</h2>
                    <div class="flex flex-col items-center mb-8">
                        <div class="relative flex items-center justify-center">
                            <?php $progress_percentage = min(($user_points / 120) * 100, 100); ?>
                            <div class="radial-progress text-gray-200 absolute inset-0" style="--value:100; --size:9rem; --thickness: 8px;"></div>
                            <div class="radial-progress text-indigo-600" style="--value:<?= $progress_percentage ?>; --size:9rem; --thickness: 8px;">
                                <div class="flex flex-col items-center text-center z-10 bg-white rounded-full w-[7.5rem] h-[7.5rem] justify-center shadow-inner">
                                    <span class="text-gray-400 text-[10px] font-bold uppercase mb-0.5">Total Points</span>
                                    <span class="text-4xl font-black text-gray-900 mb-0.5"><?= $user_points ?></span>
                                    <span class="text-indigo-600 font-bold text-sm bg-indigo-50 px-2 py-0.5 rounded-full"><?= $user_badge ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-xs font-bold text-gray-500 uppercase mb-4 border-b border-gray-100 pb-2">Badges</h3>
                        <ul class="space-y-3">
                            <?php 
                            $badges = ['Newbie' => 20, 'Explorer' => 40, 'Achiever' => 60, 'Specialist' => 80];
                            foreach ($badges as $badge_name => $points_req): 
                                $is_earned = $user_points >= $points_req;
                            ?>
                            <li class="flex items-center justify-between p-2.5 rounded-xl transition-colors <?= $is_earned ? 'bg-indigo-50 border border-indigo-100' : 'opacity-50' ?>">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center <?= $is_earned ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400' ?>">
                                        <i data-lucide="<?= $is_earned ? 'award' : 'lock' ?>" class="w-4 h-4"></i>
                                    </div>
                                    <span class="text-sm font-bold text-gray-900"><?= $badge_name ?></span>
                                </div>
                                <span class="text-xs font-bold text-indigo-600"><?= $points_req ?> pts</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>