<?php
// student/mark_complete.php
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$course_id = $_POST['course_id'];
$lesson_id = $_POST['lesson_id'];
$next_id = $_POST['next_id'] ?? null;

// 1. Check if record exists
$check = $pdo->prepare("SELECT id FROM lesson_progress WHERE user_id = ? AND lesson_id = ?");
$check->execute([$user_id, $lesson_id]);

if (!$check->fetch()) {
    // 2. Insert Progress
    $stmt = $pdo->prepare("INSERT INTO lesson_progress (user_id, course_id, lesson_id, status) VALUES (?, ?, ?, 'completed')");
    $stmt->execute([$user_id, $course_id, $lesson_id]);
    
    // Optional: Add Points for Gamification here!
}

// 3. Redirect
if ($next_id) {
    header("Location: course_player.php?course_id=$course_id&lesson_id=$next_id");
} else {
    // Course Finished!
    header("Location: dashboard.php?msg=CourseCompleted");
}
exit;
?>