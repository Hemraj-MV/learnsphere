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

// 3. Logic: Is this the last lesson?
if ($next_id) {
    // A. Go to Next Lesson
    header("Location: course_player.php?course_id=$course_id&lesson_id=$next_id");
} else {
    // B. COURSE COMPLETED!
    
    // 1. Update Enrollment Status to 'completed'
    $complete_stmt = $pdo->prepare("UPDATE enrollments SET status = 'completed', completed_at = NOW() WHERE user_id = ? AND course_id = ?");
    $complete_stmt->execute([$user_id, $course_id]);

    // 2. Award Badge/Points (Gamification)
    $pdo->prepare("UPDATE student_profiles SET total_points = total_points + 100 WHERE user_id = ?")->execute([$user_id]);

    // 3. Redirect with Success Message
    header("Location: dashboard.php?msg=CourseCompleted");
}
exit;
?>