<?php
// index.php
require 'includes/db.php';

// Check if session exists before starting
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch Published Courses for the catalog
$stmt = $pdo->query("SELECT * FROM courses WHERE is_published = 1 ORDER BY created_at DESC");
$courses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>LearnSphere - eLearning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
</head>
<body class="bg-base-200 min-h-screen flex flex-col font-sans">

    <div class="navbar bg-white shadow-md px-8 sticky top-0 z-50">
        <div class="flex-1">
            <a href="index.php" class="text-2xl font-bold text-primary flex items-center gap-2">
                🎓 LearnSphere
            </a>
        </div>
        <div class="flex-none gap-4">
            <?php if(isset($_SESSION['user_id'])): ?>
                <?php if($_SESSION['role'] == 'instructor'): ?>
                    <a href="instructor/dashboard.php" class="btn btn-primary">Instructor Dashboard</a>
                <?php else: ?>
                    <a href="student/dashboard.php" class="btn btn-primary">My Dashboard</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-outline">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-ghost">Log In</a>
                <a href="signup.php" class="btn btn-primary px-8">Join as Student</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="hero min-h-[500px] bg-base-100">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold mb-6">Learn Without Limits</h1>
                <p class="text-lg text-gray-600 mb-8">
                    Access high-quality courses, take interactive quizzes, and earn certificates. 
                    The best platform for students and professionals.
                </p>
                <div class="flex justify-center gap-4">
                    <a href="signup.php" class="btn btn-primary btn-lg">Start Learning</a>
                    <a href="#courses" class="btn btn-outline btn-lg">Browse Courses</a>
                </div>
            </div>
        </div>
    </div>

    <div id="courses" class="container mx-auto p-10">
        <h2 class="text-3xl font-bold mb-8 border-l-4 border-primary pl-4">Popular Courses</h2>
        
        <?php if(count($courses) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php foreach($courses as $course): ?>
                    <div class="card bg-base-100 shadow-xl hover:-translate-y-1 transition duration-300">
                        <figure class="h-48 bg-gray-200">
                            <?php if($course['thumbnail']): ?>
                                <img src="<?= htmlspecialchars($course['thumbnail']) ?>" class="w-full h-full object-cover" />
                            <?php else: ?>
                                <div class="flex items-center justify-center h-full w-full bg-gray-300 text-gray-500">No Image</div>
                            <?php endif; ?>
                        </figure>
                        <div class="card-body">
                            <h2 class="card-title text-lg"><?= htmlspecialchars($course['title']) ?></h2>
                            <p class="text-sm text-gray-500 line-clamp-2"><?= htmlspecialchars($course['description']) ?></p>
                            <div class="card-actions justify-end mt-4">
                                <a href="course_details.php?id=<?= $course['id'] ?>" class="btn btn-outline btn-sm w-full">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No courses published yet. Check back later!</div>
        <?php endif; ?>
    </div>

    <footer class="footer p-10 bg-neutral text-neutral-content mt-auto">
        <nav>
            <h6 class="footer-title">LearnSphere</h6> 
            <a class="link link-hover">About us</a>
            <a class="link link-hover">Contact</a>
        </nav> 
        <nav>
            <h6 class="footer-title">For Instructors</h6> 
            <a href="login.php" class="link link-hover text-warning">Faculty Login</a>
            <span class="text-xs text-gray-500">(Contact Admin for access)</span>
        </nav> 
        <nav>
            <h6 class="footer-title">Legal</h6> 
            <a class="link link-hover">Terms of use</a>
            <a class="link link-hover">Privacy policy</a>
        </nav>
    </footer>

</body>
</html>