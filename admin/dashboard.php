<?php
// admin/dashboard.php
require '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- STRICT SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$user_name = $_SESSION['name'];
$current_admin_id = $_SESSION['user_id'];

// --- HANDLE ACTIONS ---

// 1. Delete Course
if (isset($_GET['delete_course'])) {
    $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
    $stmt->execute([$_GET['delete_course']]);
    header("Location: dashboard.php?msg=Course Deleted");
    exit;
}

// 2. Delete User
if (isset($_GET['delete_user'])) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$_GET['delete_user']]);
    header("Location: dashboard.php?msg=User Deleted");
    exit;
}

// 3. Change User Role (New Feature)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $target_user_id = $_POST['user_id'];
    $new_role = $_POST['new_role'];
    
    // Prevent changing own role
    if ($target_user_id != $current_admin_id) {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $target_user_id]);
    }
    header("Location: dashboard.php?msg=Role Updated");
    exit;
}

// --- FETCH STATS ---
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_courses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$total_enrollments = $pdo->query("SELECT COUNT(*) FROM enrollments")->fetchColumn();
$total_revenue = $pdo->query("SELECT SUM(c.price) FROM courses c JOIN enrollments e ON c.id = e.course_id WHERE c.price > 0")->fetchColumn() ?: 0;

// --- FETCH DATA LISTS ---
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 20")->fetchAll();
$courses = $pdo->query("SELECT c.*, u.name as instructor_name FROM courses c JOIN users u ON c.instructor_id = u.id ORDER BY c.created_at DESC LIMIT 10")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - LearnSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; color: #1e293b; }
        h1, h2, h3, .heading-font { font-family: 'Outfit', sans-serif; }
        .stat-card { background: white; border: 1px solid #e2e8f0; padding: 24px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .data-table th { background: #f1f5f9; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; }
        .data-table td { border-bottom: 1px solid #f8fafc; vertical-align: middle; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <nav class="bg-white border-b border-slate-200 h-16 sticky top-0 z-50 px-8 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-red-600 rounded-lg flex items-center justify-center text-white font-bold text-sm shadow-lg">LS</div>
            <span class="heading-font font-bold text-xl text-slate-900">Admin<span class="text-slate-400">Panel</span></span>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-sm font-bold text-slate-500">Welcome, <?= htmlspecialchars($user_name) ?></span>
            <a href="../logout.php" class="btn btn-sm btn-ghost text-red-500 font-bold hover:bg-red-50">Logout</a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto w-full p-8">
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
            <div class="stat-card">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center"><i data-lucide="users" class="w-6 h-6"></i></div>
                    <div>
                        <div class="text-3xl font-black text-slate-900"><?= $total_users ?></div>
                        <div class="text-xs font-bold text-slate-400 uppercase">Total Users</div>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center"><i data-lucide="book-open" class="w-6 h-6"></i></div>
                    <div>
                        <div class="text-3xl font-black text-slate-900"><?= $total_courses ?></div>
                        <div class="text-xs font-bold text-slate-400 uppercase">Total Courses</div>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center"><i data-lucide="check-circle" class="w-6 h-6"></i></div>
                    <div>
                        <div class="text-3xl font-black text-slate-900"><?= $total_enrollments ?></div>
                        <div class="text-xs font-bold text-slate-400 uppercase">Enrollments</div>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center"><i data-lucide="dollar-sign" class="w-6 h-6"></i></div>
                    <div>
                        <div class="text-3xl font-black text-slate-900">$<?= number_format($total_revenue) ?></div>
                        <div class="text-xs font-bold text-slate-400 uppercase">Platform Revenue</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <div class="stat-card p-0 overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-white">
                    <h3 class="font-bold text-lg text-slate-800">User Management</h3>
                    <span class="text-xs font-bold bg-slate-100 px-2 py-1 rounded text-slate-500"><?= count($users) ?> Shown</span>
                </div>
                <div class="overflow-visible h-[500px] overflow-y-auto">
                    <table class="table w-full data-table">
                        <thead class="sticky top-0 z-10 shadow-sm">
                            <tr>
                                <th>User Details</th>
                                <th>Role</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $u): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td>
                                    <div class="font-bold text-slate-700"><?= htmlspecialchars($u['name']) ?></div>
                                    <div class="text-xs text-slate-400"><?= htmlspecialchars($u['email']) ?></div>
                                </td>
                                <td>
                                    <?php if($u['id'] == $current_admin_id): ?>
                                        <span class="badge badge-error badge-sm font-bold text-white">Super Admin</span>
                                    <?php else: ?>
                                        <form method="POST" class="flex items-center">
                                            <input type="hidden" name="update_role" value="1">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <select name="new_role" onchange="this.form.submit()" class="select select-bordered select-xs w-28 font-bold <?= $u['role'] == 'instructor' ? 'text-indigo-600 bg-indigo-50' : ($u['role'] == 'admin' ? 'text-red-600 bg-red-50' : 'text-slate-600') ?>">
                                                <option value="learner" <?= $u['role'] == 'learner' ? 'selected' : '' ?>>Learner</option>
                                                <option value="instructor" <?= $u['role'] == 'instructor' ? 'selected' : '' ?>>Instructor</option>
                                                <option value="admin" <?= $u['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                            </select>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($u['id'] !== $current_admin_id): ?>
                                        <a href="?delete_user=<?= $u['id'] ?>" onclick="return confirm('Delete this user? This cannot be undone.')" class="btn btn-xs btn-square btn-ghost text-red-500 hover:bg-red-50">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="stat-card p-0 overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-white">
                    <h3 class="font-bold text-lg text-slate-800">Recent Courses</h3>
                    <span class="text-xs font-bold bg-slate-100 px-2 py-1 rounded text-slate-500"><?= count($courses) ?> Shown</span>
                </div>
                <div class="overflow-x-auto h-[500px]">
                    <table class="table w-full data-table">
                        <thead class="sticky top-0 z-10 shadow-sm">
                            <tr>
                                <th>Course Title</th>
                                <th>Instructor</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($courses as $c): ?>
                            <tr class="hover:bg-slate-50">
                                <td>
                                    <div class="font-bold text-slate-700 line-clamp-1"><?= htmlspecialchars($c['title']) ?></div>
                                    <div class="text-xs text-slate-400 font-bold">$<?= number_format($c['price']) ?></div>
                                </td>
                                <td class="text-xs font-bold text-slate-500"><?= htmlspecialchars($c['instructor_name']) ?></td>
                                <td>
                                    <?php if($c['is_published']): ?>
                                        <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded">Live</span>
                                    <?php else: ?>
                                        <span class="text-xs font-bold text-slate-400 bg-slate-100 px-2 py-1 rounded">Draft</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?delete_course=<?= $c['id'] ?>" onclick="return confirm('Delete this course? All lessons will be lost.')" class="btn btn-xs btn-square btn-ghost text-red-500 hover:bg-red-50">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>