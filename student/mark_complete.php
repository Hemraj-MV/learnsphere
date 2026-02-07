<?php
// student/mark_complete.php
require '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$lesson_id = $_POST['lesson_id'] ?? null;
$course_id = $_POST['course_id'] ?? null;

if (!$lesson_id || !$course_id) {
    header("Location: dashboard.php");
    exit;
}

// 1. Check if already marked complete
$check = $pdo->prepare("SELECT id FROM lesson_progress WHERE user_id = ? AND lesson_id = ?");
$check->execute([$user_id, $lesson_id]);

if (!$check->fetch()) {
    // 2. Mark as Complete
    try {
        // Assuming your table doesn't have 'course_id' or 'status' based on previous errors
        $stmt = $pdo->prepare("INSERT INTO lesson_progress (user_id, lesson_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $lesson_id]);
    } catch (PDOException $e) {
        // Silently ignore duplicates
    }
}

// 3. Navigation Logic: Find the Next Lesson
// We look for a lesson with an ID higher than the current one, in the same course
$next_stmt = $pdo->prepare("SELECT id FROM lessons WHERE course_id = ? AND id > ? ORDER BY id ASC LIMIT 1");
$next_stmt->execute([$course_id, $lesson_id]);
$next_lesson = $next_stmt->fetch();

if ($next_lesson) {
    // Case A: Go to next lesson
    header("Location: course_player.php?course_id=$course_id&lesson_id=" . $next_lesson['id']);
} else {
    // Case B: No more lessons (Course Completed) -> Go to Dashboard
    header("Location: dashboard.php?msg=course_completed");
}
exit;
?>