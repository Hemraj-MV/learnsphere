<?php
// index.php
require 'includes/db.php';
session_start();

// Fetch Published Courses
$stmt = $pdo->query("SELECT * FROM courses WHERE is_published = 1 ORDER BY created_at DESC");
$courses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>LearnSphere - Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
</head>
<body class="bg-base-200 min-h-screen flex flex-col">

    <div class="navbar bg-base-100 shadow-lg px-8">
        <div class="flex-1">
            <a href="index.php" class="text-2xl font-bold text-primary">LearnSphere</a>
        </div>
        <div class="flex-none gap-4">
            <a href="index.php" class="btn btn-ghost">Courses</a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <?php if($_SESSION['role'] == 'instructor'): ?>
                    <a href="instructor/dashboard.php" class="btn btn-primary">Instructor Dashboard</a>
                <?php else: ?>
                    <a href="student/dashboard.php" class="btn btn-primary">My Learning</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-outline">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-ghost">Login</a>
                <a href="signup.php" class="btn btn-primary">Get Started</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="hero bg-base-100 py-20">
        <div class="hero-content text-center">
            <div class="max-w-md">
                <h1 class="text-5xl font-bold">Master New Skills</h1>
                <p class="py-6">Join thousands of learners on LearnSphere. Start your journey today.</p>
                <a href="#courses" class="btn btn-primary">Browse Courses</a>
            </div>
        </div>
    </div>

    <div id="courses" class="container mx-auto p-10">
        <h2 class="text-3xl font-bold mb-6">Popular Courses</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php foreach($courses as $course): ?>
                <div class="card bg-base-100 shadow-xl hover:scale-105 transition">
                    <figure class="h-48 bg-gray-200">
                        <?php if($course['thumbnail']): ?>
                            <img src="<?= htmlspecialchars($course['thumbnail']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="text-gray-400">No Image</span>
                        <?php endif; ?>
                    </figure>
                    <div class="card-body">
                        <h2 class="card-title"><?= htmlspecialchars($course['title']) ?></h2>
                        <p class="text-sm text-gray-500 line-clamp-2"><?= htmlspecialchars($course['description']) ?></p>
                        <div class="card-actions justify-end mt-4">
                            <a href="course_details.php?id=<?= $course['id'] ?>" class="btn btn-outline btn-sm w-full">View Details</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</body>
</html>