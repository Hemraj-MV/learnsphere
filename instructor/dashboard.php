<?php
// instructor/dashboard.php
require '../includes/db.php';

// 1. Security: Only Admin/Instructor allowed
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'instructor')) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// 2. Fetch Courses for this Instructor
// PDF Requirement: Show Title, Views, Lesson Count [cite: 210]
$stmt = $pdo->prepare("
    SELECT c.*, 
    (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count 
    FROM courses c 
    WHERE instructor_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$courses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Instructor Dashboard - LearnSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-base-200 min-h-screen">

    <div class="navbar bg-base-100 shadow-md">
        <div class="flex-1">
            <a class="btn btn-ghost text-xl">LearnSphere Instructor</a>
        </div>
        <div class="flex-none gap-2">
            <span class="text-sm mr-2">Hello, <?= htmlspecialchars($user_name) ?></span>
            <a href="../logout.php" class="btn btn-sm btn-error">Logout</a>
        </div>
    </div>

    <div class="container mx-auto p-6">
        
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold">My Courses</h1>
            <a href="create_course.php" class="btn btn-primary">+ Create New Course</a>
        </div>

        <?php if (count($courses) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($courses as $course): ?>
                    <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition">
                        <?php if($course['thumbnail']): ?>
                            <figure><img src="../<?= htmlspecialchars($course['thumbnail']) ?>" alt="Thumbnail" class="h-48 w-full object-cover" /></figure>
                        <?php else: ?>
                            <figure class="h-48 bg-gray-200 flex items-center justify-center text-gray-400">No Image</figure>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <h2 class="card-title">
                                <?= htmlspecialchars($course['title']) ?>
                                <?php if($course['is_published']): ?>
                                    <div class="badge badge-success gap-2">Published</div>
                                <?php else: ?>
                                    <div class="badge badge-warning gap-2">Draft</div>
                                <?php endif; ?>
                            </h2>
                            <p class="text-sm text-gray-500 truncate"><?= htmlspecialchars($course['description']) ?></p>
                            
                            <div class="flex justify-between mt-4 text-xs font-bold text-gray-500">
                                <span>📚 <?= $course['lesson_count'] ?> Lessons</span>
                                <span>👁️ <?= $course['views'] ?> Views</span>
                            </div>

                            <div class="card-actions justify-end mt-4">
                                <a href="edit_course.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                                <a href="course_analytics.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-ghost">Report</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="hero bg-base-100 rounded-box p-10">
                <div class="hero-content text-center">
                    <div class="max-w-md">
                        <h1 class="text-2xl font-bold">No Courses Yet</h1>
                        <p class="py-6">Get started by creating your first course to share your knowledge.</p>
                        <a href="create_course.php" class="btn btn-primary">Create Course</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>