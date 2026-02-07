<?php
// instructor/dashboard.php
require '../includes/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'instructor')) {
    header("Location: ../login.php");
    exit;
}

$instructor_id = $_SESSION['user_id'];
$instructor_name = $_SESSION['name'];

// Fetch My Courses
$stmt = $pdo->prepare("SELECT * FROM courses WHERE instructor_id = ? ORDER BY created_at DESC");
$stmt->execute([$instructor_id]);
$courses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Instructor Dashboard - LearnSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-base-200 min-h-screen">

    <div class="navbar bg-base-100 shadow px-8">
        <div class="flex-1">
            <a class="text-xl font-bold text-primary">LearnSphere Instructor</a>
        </div>
        <div class="flex-none gap-2">
            <span>Welcome, <?= htmlspecialchars($instructor_name) ?></span>
            <a href="../logout.php" class="btn btn-sm btn-ghost">Logout</a>
        </div>
    </div>

    <div class="container mx-auto p-10">
        
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold">My Courses</h1>
            <a href="create_course.php" class="btn btn-primary">+ Create New Course</a>
        </div>

        <?php if(count($courses) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach($courses as $course): ?>
                    <div class="card bg-base-100 shadow-xl border">
                        <figure class="h-48 bg-gray-200 overflow-hidden">
                            <?php if($course['thumbnail']): ?>
                                <img src="../<?= htmlspecialchars($course['thumbnail']) ?>" alt="Course" class="object-cover w-full h-full" />
                            <?php else: ?>
                                <span class="text-gray-400">No Image</span>
                            <?php endif; ?>
                        </figure>
                        <div class="card-body">
                            <h2 class="card-title text-lg">
                                <?= htmlspecialchars($course['title']) ?>
                                <?php if($course['is_published']): ?>
                                    <div class="badge badge-success text-white text-xs">Live</div>
                                <?php else: ?>
                                    <div class="badge badge-warning text-xs">Draft</div>
                                <?php endif; ?>
                            </h2>
                            <p class="text-sm text-gray-500 line-clamp-2"><?= htmlspecialchars($course['description']) ?></p>
                            
                            <div class="card-actions justify-end mt-4">
                                <a href="manage_course.php?id=<?= $course['id'] ?>" class="btn btn-secondary btn-sm w-full">
                                    ⚙️ Manage Course
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-20">
                <h3 class="text-xl font-bold text-gray-400">You haven't created any courses yet.</h3>
                <a href="create_course.php" class="btn btn-primary mt-4">Create Your First Course</a>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>