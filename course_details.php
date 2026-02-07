<?php
// course_details.php
require 'includes/db.php';
session_start();

$course_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;

// 1. Fetch Course Info
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();
if(!$course) die("Course not found");

// 2. Fetch Lessons (Syllabus)
$l_stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ?");
$l_stmt->execute([$course_id]);
$lessons = $l_stmt->fetchAll();

// 3. Fetch Reviews (Updated to use 'comment')
$r_stmt = $pdo->prepare("SELECT r.*, u.name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.course_id = ? ORDER BY r.created_at DESC");
$r_stmt->execute([$course_id]);
$reviews = $r_stmt->fetchAll();

// 4. Handle Review Submission (Updated to use 'comment')
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating'])) {
    if(!$user_id) header("Location: login.php");
    
    $rating = $_POST['rating'];
    $comment = $_POST['comment']; // Changed from review_text to comment
    
    $sql = "INSERT INTO reviews (user_id, course_id, rating, comment) VALUES (?, ?, ?, ?)";
    $pdo->prepare($sql)->execute([$user_id, $course_id, $rating, $comment]);
    
    header("Location: course_details.php?id=$course_id");
    exit;
}

// 5. Check Enrollment Status
$is_enrolled = false;
if($user_id) {
    $e_stmt = $pdo->prepare("SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?");
    $e_stmt->execute([$user_id, $course_id]);
    if($e_stmt->fetch()) $is_enrolled = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= htmlspecialchars($course['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
</head>
<body class="bg-base-200 min-h-screen">

    <div class="navbar bg-base-100 shadow px-8">
        <div class="flex-1"><a href="index.php" class="btn btn-ghost text-xl">LearnSphere</a></div>
        <div class="flex-none">
            <?php if($user_id): ?>
                <a href="student/dashboard.php" class="btn btn-primary btn-sm">My Dashboard</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary btn-sm">Login</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container mx-auto p-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <div class="md:col-span-2">
                <div class="card bg-base-100 shadow-xl">
                    <figure class="h-64 bg-gray-200">
                        <?php if($course['thumbnail']): ?>
                            <img src="<?= htmlspecialchars($course['thumbnail']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="text-gray-400">No Image</span>
                        <?php endif; ?>
                    </figure>
                    <div class="card-body">
                        <h1 class="text-4xl font-bold"><?= htmlspecialchars($course['title']) ?></h1>
                        <p class="py-4 text-gray-600"><?= htmlspecialchars($course['description']) ?></p>
                        
                        <h3 class="text-xl font-bold mt-6 mb-2">Course Content</h3>
                        <ul class="menu bg-base-200 rounded-box">
                            <?php foreach($lessons as $l): ?>
                                <li><a>
                                    <?= $l['type'] == 'video' ? '🎥' : '📄' ?> 
                                    <?= htmlspecialchars($l['title']) ?>
                                </a></li>
                            <?php endforeach; ?>
                            <?php if(count($lessons) == 0): ?>
                                <li><a class="text-gray-400">No lessons added yet.</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <div class="mt-8">
                    <h3 class="text-2xl font-bold mb-4">Ratings & Reviews</h3>
                    
                    <?php if($user_id && $is_enrolled): ?>
                        <form method="POST" class="card bg-base-100 shadow p-6 mb-6">
                            <h4 class="font-bold">Leave a Review</h4>
                            <select name="rating" class="select select-bordered w-full max-w-xs my-2">
                                <option value="5">⭐⭐⭐⭐⭐ (Excellent)</option>
                                <option value="4">⭐⭐⭐⭐ (Good)</option>
                                <option value="3">⭐⭐⭐ (Average)</option>
                                <option value="2">⭐⭐ (Poor)</option>
                                <option value="1">⭐ (Terrible)</option>
                            </select>
                            <textarea name="comment" class="textarea textarea-bordered w-full" placeholder="Write your feedback..." required></textarea>
                            <button class="btn btn-primary mt-2">Submit Review</button>
                        </form>
                    <?php endif; ?>

                    <?php if(count($reviews) > 0): ?>
                        <?php foreach($reviews as $r): ?>
                            <div class="chat chat-start mb-4">
                                <div class="chat-header">
                                    <?= htmlspecialchars($r['name']) ?>
                                    <time class="text-xs opacity-50"><?= substr($r['created_at'],0,10) ?></time>
                                </div>
                                <div class="chat-bubble bg-white text-black shadow">
                                    <div class="text-yellow-500 mb-1"><?= str_repeat('★', $r['rating']) ?></div>
                                    <?= htmlspecialchars($r['comment']) ?> </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-ghost">No reviews yet. Be the first to review!</div>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="card bg-base-100 shadow-xl sticky top-10">
                    <div class="card-body">
                        <div class="text-3xl font-bold text-primary mb-4">
                            <?= $course['price'] > 0 ? '$'.$course['price'] : 'Free' ?>
                        </div>
                        
                        <?php if($is_enrolled): ?>
                            <a href="student/course_player.php?course_id=<?= $course['id'] ?>" class="btn btn-success w-full text-white">
                                Continue Learning
                            </a>
                        <?php else: ?>
                            <a href="student/enroll.php?id=<?= $course['id'] ?>" class="btn btn-primary w-full">
                                Join Course
                            </a>
                        <?php endif; ?>
                        
                        <div class="divider"></div>
                        <ul class="text-sm text-gray-500 space-y-2">
                            <li>✅ Full Lifetime Access</li>
                            <li>✅ Access on Mobile</li>
                            <li>✅ Certificate of Completion</li>
                            <li>✅ AI Tutor Support</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </div>
</body>
</html>