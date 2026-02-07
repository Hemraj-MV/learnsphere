<?php
// student/dashboard.php
require '../includes/db.php';

// Security: Kick out if not Learner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'learner') {
    // Optional: If they are instructor, let them view it too for testing
    // header("Location: ../login.php"); 
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// 1. Fetch All Published Courses
$stmt = $pdo->query("SELECT * FROM courses WHERE is_published = 1 ORDER BY created_at DESC");
$courses = $stmt->fetchAll();

// 2. Fetch My Enrolled Courses (To show progress)
$my_courses_stmt = $pdo->prepare("
    SELECT c.*, e.status as enrollment_status 
    FROM courses c 
    JOIN enrollments e ON c.id = e.course_id 
    WHERE e.user_id = ?
");
$my_courses_stmt->execute([$user_id]);
$my_courses = $my_courses_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Learning - LearnSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-base-200 min-h-screen">

    <div class="navbar bg-base-100 shadow-sm px-6">
        <div class="flex-1">
            <a class="btn btn-ghost text-xl font-bold text-primary">LearnSphere</a>
        </div>
        <div class="flex-none gap-4">
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar placeholder">
                    <div class="bg-neutral text-neutral-content rounded-full w-10">
                        <span><?= strtoupper(substr($user_name, 0, 1)) ?></span>
                    </div>
                </div>
                <ul tabindex="0" class="mt-3 z-[1] p-2 shadow menu menu-sm dropdown-content bg-base-100 rounded-box w-52">
                    <li><a>Profile</a></li>
                    <li><a href="../logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="container mx-auto p-6">
        
        <div class="hero bg-base-100 rounded-box p-8 mb-10 shadow-lg">
            <div class="hero-content flex-col lg:flex-row-reverse">
               

[Image of web development illustration]

                <div>
                    <h1 class="text-4xl font-bold">Welcome back, <?= htmlspecialchars($user_name) ?>!</h1>
                    <p class="py-6">Ready to learn something new today? Your AI tutor is waiting.</p>
                    <a href="#catalog" class="btn btn-primary">Browse Courses</a>
                </div>
            </div>
        </div>
        <h2 class="text-2xl font-bold mb-6 border-b pb-2">My Learning</h2>
        
        <?php if (count($my_courses) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
                <?php foreach ($my_courses as $my_course): ?>
                    <div class="card card-side bg-base-100 shadow-xl border">
                        <figure class="w-1/3">
                            <img src="../<?= htmlspecialchars($my_course['thumbnail']) ?>" alt="Course" class="h-full object-cover" />
                        </figure>
                        <div class="card-body w-2/3">
                            <h2 class="card-title text-lg"><?= htmlspecialchars($my_course['title']) ?></h2>
                            
                            <?php if($my_course['enrollment_status'] === 'completed'): ?>
                                <div class="badge badge-success text-white">Completed</div>
                                <p class="text-sm text-gray-500">You have mastered this course!</p>
                                <div class="card-actions justify-end">
                                    <a href="certificate.php?course_id=<?= $my_course['id'] ?>" target="_blank" class="btn btn-warning btn-sm">
                                        🏆 Download Certificate
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="badge badge-info text-white">In Progress</div>
                                <progress class="progress progress-primary w-56" value="40" max="100"></progress>
                                <div class="card-actions justify-end">
                                    <a href="course_player.php?course_id=<?= $my_course['id'] ?>" class="btn btn-primary btn-sm">Continue</a>
                                </div>
                            <?php endif; ?>
                            
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-ghost mb-8">You haven't enrolled in any courses yet.</div>
        <?php endif; ?>
        <h2 id="catalog" class="text-2xl font-bold mb-6 border-b pb-2">Available Courses</h2>
        
        <?php if (count($courses) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach ($courses as $course): ?>
                    <div class="card bg-base-100 shadow-xl hover:scale-105 transition duration-300">
                        <figure>
                            <?php if($course['thumbnail']): ?>
                                <img src="../<?= htmlspecialchars($course['thumbnail']) ?>" alt="Course" class="h-48 w-full object-cover" />
                            <?php else: ?>
                                <div class="h-48 w-full bg-gray-200 flex items-center justify-center text-gray-400">No Image</div>
                            <?php endif; ?>
                        </figure>
                        <div class="card-body">
                            <h2 class="card-title">
                                <?= htmlspecialchars($course['title']) ?>
                                <?php if($course['price'] > 0): ?>
                                    <div class="badge badge-secondary">$<?= $course['price'] ?></div>
                                <?php else: ?>
                                    <div class="badge badge-accent">Free</div>
                                <?php endif; ?>
                            </h2>
                            <p class="text-sm text-gray-500 line-clamp-2"><?= htmlspecialchars($course['description']) ?></p>
                            
                            <div class="card-actions justify-end mt-4">
                                <a href="enroll.php?id=<?= $course['id'] ?>" class="btn btn-primary w-full">Start Learning</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No courses published yet. Check back later!</div>
        <?php endif; ?>

    </div>

</body>
</html>